<?php

namespace Drupal\shops_users;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\User;

/**
 * Service to manage the shops users imports.
 */
class ShopsUsersManager
{
  use StringTranslationTrait;

  /**
   * The module's configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * The module's logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   */
  public function __construct(
    ConfigFactoryInterface        $configFactory,
    LoggerChannelFactoryInterface $loggerFactory,
    EntityTypeManagerInterface    $entityTypeManager
  )
  {
    $this->config = $configFactory->get('shops_users.settings');
    $this->logger = $loggerFactory->get('shops_users');
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Parse the Shops Users data XML.
   */
  public function processXml(): void
  {
    $uri = $this->config->get('xml_uri');
    $xml = simplexml_load_file($uri);

    if ($xml !== FALSE && isset($xml->groepchefs->groepchef)) {
      foreach ($xml->groepchefs->groepchef as $chgr) {
        if ($groupLeaderId = $this->upsertGroupLeader($chgr->attributes())) {
          $firstFiliaal = TRUE;

          foreach ($chgr->filialen->filiaal as $shop) {
            $shop = $shop->attributes();
            $this->upsertShop($shop, $groupLeaderId);

            if ($firstFiliaal) {
              $this->addInfoGroupLeader($groupLeaderId, $shop);
              $firstFiliaal = FALSE;
            }
          }
        }
      }
    } else {
      $this->logger->error($this->t('Empty or missing XML at @uri', ['@uri' => $uri]));
    }
  }

  /**
   * Disable all users from the XML.
   */
  public function blockAllUsers(): void
  {
    $users = $this->entityTypeManager->getStorage('user')->loadByProperties([
      'status' => 1,
      'field_come_from_xml' => 1,
    ]);

    /** @var \Drupal\user\Entity\User $user */
    foreach ($users as $user) {
      $user->block();
      $user->save();
    }
  }

  /**
   * Retrieve users using the group leader id.
   *
   * @param int $groupLeaderId
   *   The group leader identifier.
   *
   * @return int|array
   *   An array of user ids.
   */
  public function getUserByGroupLeader(int $groupLeaderId)
  {
    return $this->entityTypeManager->getStorage('user')->getQuery()
      ->condition('status', 1)
      ->condition('field_group_leader', $groupLeaderId)
      ->accessCheck(TRUE)
      ->execute();
  }

  /**
   * Check if the module is properly configured.
   *
   * @return bool
   *   True if the module is configured, false otherwise.
   */
  public function isConfigured(): bool
  {
    if ($this->config->get('xml_uri') && $this->config->get('email_domain')) {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  /**
   * Upsert the group leader data.
   *
   * @param \SimpleXMLElement $data
   *   Group leader data from the XML.
   *
   * @return int
   *   The group leader identifier.
   */
  private function upsertGroupLeader(\SimpleXMLElement $data): int
  {
    $email = $this->formatValue($data['email_grpchf']);
    if (!$user = user_load_by_mail($email)) {
      $user = User::create();
      $user->enforceIsNew();
    }

    $user->setEmail($email);
    $user->setUsername($this->formatValue($data['naam_grpchf']));
    $user->setPassword($this->formatValue($data['web_iorder_logon']));
    $user->set('preferred_langcode', $this->formatLang($this->formatValue($data['taal_grpchf'])));
    $user->set('field_lang', $this->formatLang($this->formatValue($data['taal_grpchf'])));
    $user->set('field_come_from_xml', 1);
    $user->addRole('group_leader');
    $user->activate();

    try {
      $user->save();
    } catch (\Exception $e) {
      $this->logger->error($this->t('Error while upserting group leader @grpchf : @error', [
        '@grpchf' => $data['grpchf'],
        '@error' => $e->getMessage(),
      ]));
    }

    return $user->id() ?? 0;
  }

  /**
   * Upsert the shop data.
   *
   * @param \SimpleXMLElement $data
   *   Shop data from the XML.
   * @param int $groupLeaderId
   *   The related group leader identifier.
   */
  private function upsertShop(\SimpleXMLElement $data, int $groupLeaderId): void
  {
    $storeUnit = $this->formatValue($data['storeunit']);
    $email = '5-' . $storeUnit . '@' . $this->config->get('email_domain');

    if (!$user = user_load_by_mail($email)) {
      $user = User::create();
      $user->enforceIsNew();
    }

    $user->setEmail($email);

    $username = $this->formatValue($data['verkort']);
    $request = \Drupal::request();
    $current_path = $request->getPathInfo();
    if (strpos($current_path, 'intrastore') !== FALSE) {
      $username .= ' ' . $storeUnit;
    }
    $user->setUsername($username);

    $user->setPassword($storeUnit);
    $user->set('field_storeunit', $storeUnit);
    $user->set('ip_login', [
      'ip_start' => inet_pton($data['ip']),
      'ip_end' => inet_pton($data['ip']),
    ]);
    $user->set('field_postnr', $this->formatValue($data['postnr']));
    $user->set('preferred_langcode', $this->formatLang($this->formatValue($data['taal'])));
    $user->set('field_lang', $this->formatLang($this->formatValue($data['taal'])));
    $user->set('field_country', strtolower($this->formatValue($data['land'])));
    $user->set('field_region', $this->getRegion($this->formatValue($data['land']), $this->formatValue($data['dc'])));
    $user->set('field_city', $this->formatValue($data['woonplaats']));
    $user->set('field_street', $this->formatValue($data['adres']));
    $user->set('field_phone', $this->formatValue($data['tel']));
    $user->set('field_fax', $this->formatValue($data['fax']));
    $user->set('field_group_leader', $groupLeaderId);
    $user->set('field_come_from_xml', 1);
    $user->addRole('shop');
    $user->activate();

    try {
      $user->save();
    } catch (\Exception $e) {
      $this->logger->error($this->t('Error while upserting shop @shop : @error', [
        '@shop' => $storeUnit,
        '@error' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Add extra info to the group leader from the shop data.
   *
   * @param int $groupLeaderId
   *   The group leader identifier.
   * @param \SimpleXMLElement $data
   *   Shop data from the XML.
   */
  private function addInfoGroupLeader(int $groupLeaderId, \SimpleXMLElement $data): void
  {
    $user = User::load($groupLeaderId);
    $user->set('field_storeunit', $this->formatValue($data['storeunit']));
    $user->set('field_postnr', $this->formatValue($data['postnr']));
    $user->set('field_country', strtolower($this->formatValue($data['land'])));
    $user->set('field_region', $this->getRegion($this->formatValue($data['land']), $this->formatValue($data['dc'])));
    $user->set('field_city', $this->formatValue($data['woonplaats']));
    try {
      $user->save();
    } catch (\Exception $e) {
      $this->logger->error($this->t('Error while updating group leader @grpchf info : @error', [
        '@grpchf' => $groupLeaderId,
        '@error' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Format input value into trimmed string.
   *
   * @return string
   *   The formatted value.
   */
  private function formatValue($value): string
  {
    return trim((string)$value);
  }

  /**
   * Format the language attribute into ISO 639-1.
   *
   * @return string
   *   The formatted language id.
   */
  private function formatLang($taal): string
  {
    return $taal == 'N' ? 'nl' : 'fr';
  }

  /**
   * Generate the region code based on the country code and distribution center.
   *
   * @param string $countryCode
   *   The uppercase country code (ISO 3166-1 alpha-2).
   * @param string $distributionCenter
   *   The Distribution Center identifier (3 chars).
   *
   * @return string
   *   The lowercase region or country code (2 chars).
   */
  private function getRegion($countryCode, $distributionCenter): string
  {
    if ($countryCode == 'LU') {
      return 'lu';
    }
    if ($countryCode == 'FR') {
      return 'fr';
    }
    if ($countryCode == 'BE') {
      if ($distributionCenter == 'TRM') {
        return 'vl';
      }
      if ($distributionCenter == 'MSD') {
        return 'wa';
      }
    }

    return '';
  }

}
