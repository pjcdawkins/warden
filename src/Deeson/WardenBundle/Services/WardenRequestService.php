<?php

namespace Deeson\WardenBundle\Services;

use Deeson\WardenBundle\Document\ModuleDocument;
use Deeson\WardenBundle\Exception\WardenRequestException;

class WardenRequestService extends BaseRequestService {

  /**
   * Drupal core version.
   *
   * @var float
   */
  protected $coreVersion = 0;

  /**
   * List of contrib modules.
   *
   * @var array
   */
  protected $moduleData = array();

  /**
   * @var \Deeson\WardenBundle\Document\SiteDocument $site
   */
  protected $site = NULL;

  /**
   * List of any additional errors that have come through from the site.
   *
   * @var array
   */
  protected $additionalIssues = array();

  /**
   * The site name from the site request.
   *
   * @var string
   */
  protected $siteName = '';

  /**
   * @var SSLEncryptionService
   */
  protected $sslEncryptionService;

  public function __construct(SSLEncryptionService $sslEncryptionService, $buzz) {
    parent::__construct($buzz);
    $this->sslEncryptionService = $sslEncryptionService;
  }

  /**
   * @param \Deeson\WardenBundle\Document\SiteDocument $site
   */
  public function setSite($site) {
    $this->site = $site;
  }

  /**
   * Get the core version for the site.
   *
   * @return float
   */
  public function getCoreVersion() {
    return $this->coreVersion;
  }

  /**
   * Get the modules data for the site.
   *
   * @return array
   */
  public function getModuleData() {
    return $this->moduleData;
  }

  /**
   * Get the site name for this site.
   *
   * @return string
   */
  public function getSiteName() {
    return $this->siteName;
  }

  /**
   * Get the site status URL.
   *
   * @return mixed
   */
  protected function getRequestUrl() {
    return $this->site->getUrl() . '/admin/reports/warden/' . $this->site->getWardenToken();
  }

  /**
   * @return array
   */
  public function getAdditionalIssues() {
    return $this->additionalIssues;
  }

  /**
   * Processes the data that has come back from the request.
   *
   * @param $requestData
   *   Data that has come back from the request.
   */
  protected function processRequestData($requestData) {
    $requestDataObject = json_decode($requestData);

    // @todo add logging of response to a file.
    if (!isset($requestDataObject->data)) {
      throw new WardenRequestException("Invalid return response - possibly access denied");
    }

    $wardenDataObject = $this->sslEncryptionService->decrypt($requestDataObject->data);
    // @TODO check signature.

    // Get the core version from the site.
    if (isset($wardenDataObject->core->drupal)) {
      $this->coreVersion = $wardenDataObject->core->drupal->version;
    }
    else {
      foreach ($wardenDataObject->contrib as $module) {
        $coreVersion = ModuleDocument::getMajorVersion((string) $module->version);
        break;
      }
      $this->coreVersion = $coreVersion . '.x';
    }

    // Get the site name.
    $this->siteName = $wardenDataObject->site_name;

    //$this->coreVersion = isset($wardenDataObject->warden->core->drupal) ? $wardenDataObject->warden->core->drupal->version : '0';
    $this->moduleData = json_decode(json_encode($wardenDataObject->contrib), TRUE);
  }
}