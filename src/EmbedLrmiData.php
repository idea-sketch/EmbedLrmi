<?php

/**
 * EmbedLrmi
 * Embed LRMI metadata for Articles
 *
 * PHP version 8.0
 *
 * @category Extension
 * @package  EmbedLRMI
 * @author   Jan Böhme <jan@idea-sketch.com>, Uwe Schützenmeister <uwe@idea-sketch.com>
 * @license  GPL http://www.gnu.org/licenses/gpl.html
 * @link     https://idea-sketch.com
 */

namespace MediaWiki\Extension\EmbedLrmi;

use OutputPage;
use Title;
use ErrorPageError;
use MediaWiki\MediaWikiServices;

if (!defined('MEDIAWIKI')) {
  echo ("This is a Mediawiki extension and doesn't provide standalone functionality\n");
  die(1);
}

class EmbedLrmiData
{
  private static $instance;
  private $title;
  private $endpoint;
  private $pageUrl;
  private $lrmidata;
  private $cache;
  private $cacheExpiry;

  public static function getInstance()
  {
    if (!isset(self::$instance)) {
      self::$instance = new EmbedLrmiData();
    }
    return self::$instance;
  }

  public function __construct()
  {
    global $wgServer, $wgSitename, $wgTitle, $wgEmbedLrmiEndpoint, $wgEmbedLrmiUrlReplacements, $wgEmbedLrmiCacheExpiry;

    $this->title = $wgTitle;
    $this->sitename = $wgSitename;
    $this->server = $wgServer;

    // Check if $wgEmbedLrmiEndpoint is set
    if (!isset($wgEmbedLrmiEndpoint)) {
      throw new ErrorPageError('embedlrmi-missing-config', 'EmbedLRMI configuration error: $wgEmbedLrmiEndpoint is not set in LocalSettings.php');
    }
    $this->endpoint = $wgEmbedLrmiEndpoint;

    // Apply URL replacements only if $wgEmbedLrmiUrlReplacements is set
    $originalUrl = $this->title->getFullURL();
    if (isset($wgEmbedLrmiUrlReplacements)) {
      $from = $wgEmbedLrmiUrlReplacements['from'] ?? [];
      $to = $wgEmbedLrmiUrlReplacements['to'] ?? [];
      $this->pageUrl = str_replace($from, $to, $originalUrl);
    } else {
      $this->pageUrl = $originalUrl;
    }

    // Initialize cache
    $this->cache = MediaWikiServices::getInstance()->getMainObjectStash();
    $this->cacheExpiry = $wgEmbedLrmiCacheExpiry ?? 2592000; // Default: 30 days in seconds

    // Debug: Log the final URL
    error_log("FINAL PAGE URL: " . $this->pageUrl);
  }

  public function getCTime()  {
    $ctime = \DateTime::createFromFormat('YmdHis', $this->title->getEarliestRevTime());
    if ($ctime) {
      return $ctime->format('c');
    }
    return 0;
  }

  public function getMTime()  {
    $mtime = \DateTime::createFromFormat('YmdHis', $this->title->getTouched());
    if ($mtime) {
      return $mtime->format('c');
    }
    return 0;
  }

  private function getMetaData()  {
    // Generate a unique cache key based on the page URL
    $cacheKey = $this->cache->makeKey('embedlrmi', md5($this->pageUrl));

    // Try to get data from cache
    $cachedData = $this->cache->get($cacheKey);
    if ($cachedData !== false) {
      $this->lrmidata = $cachedData;
      error_log("LRMI DATA LOADED FROM CACHE");
      return;
    }

    // Fetch data from API if not in cache
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);

    $postfields = "{\n \"criteria\": [\n   {\n     \"property\": \"ccm:wwwurl\",\n     \"values\": [\n       \"{$this->pageUrl}\"\n     ]\n   }\n ]\n}";
    error_log("SENDING POSTFIELDS: " . $postfields);

    $headers = [
      'Content-Type: application/json',
      'Accept: application/json'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);

    $result = curl_exec($ch);

    // Debug: Log the raw API response
    error_log("RAW API RESPONSE: " . $result);

    if ($result === false) {
      error_log("cURL Error: " . curl_error($ch));
    }

    if ($result) {
      $this->lrmidata = json_decode($result, true);
      error_log("DECODED LRMI DATA: " . print_r($this->lrmidata, true));

      // Store data in cache
      $this->cache->set($cacheKey, $this->lrmidata, $this->cacheExpiry);
      error_log("LRMI DATA STORED IN CACHE");
    }
  }

  function generateTree($data)  {
    if (!is_array($data)) {
      $data = json_decode($data, true);
    }

    $html = '<div><ul>';
    foreach ($data as $key => $value) {
      $html .= '<li><b>' . htmlspecialchars($key) . '</b>: ';
      if (is_array($value)) {
        $html .= $this->generateTree($value);
      } else {
        $html .= htmlspecialchars($value);
      }
      $html .= '</li>';
    }
    $html .= '</ul></div>';
    return $html;
  }

  function render(OutputPage &$out)  {
    if ($this->title instanceof Title && $this->title->isContentPage()) {
      $this->getMetaData();

      if ($this->lrmidata && isset($this->lrmidata['nodes'][0])) {
        $out->addHeadItem(
          'EmbedLrmiData',
          '<script type="application/ld+json">' . json_encode($this->lrmidata['nodes'][0]) . '</script>'
        );
      }
    }
  }


  /**
 * Check if the 'lrmi' action is requested and display LRMI data
 */
/**
 * Check if the 'lrmi' action is requested and display LRMI data
 */
public static function onMediaWikiPerformAction($outputPage, $article, $title, $user, $request, $mediaWiki) {
  $action = $request->getVal('action');

  if ($action === 'lrmi') {
      $instance = self::getInstance();
      $instance->getMetaData();

      $outputPage->setArticleRelated(true);
      $outputPage->setRobotPolicy('noindex,nofollow');
      $outputPage->setPageTitle($outputPage->msg('embedlrmi-show-lrmi-data'));

      if ($instance->lrmidata && isset($instance->lrmidata['nodes'][0])) {
          $outputPage->addHTML('<div class="lrmi-data-container">');
          $outputPage->addHTML($instance->generateTree($instance->lrmidata['nodes'][0]));
          $outputPage->addHTML('</div>');
      } else {
          $outputPage->addHTML('<div class="lrmi-data-container">');
          $outputPage->addHTML('<p>No LRMI metadata available for this page.</p>');
          $outputPage->addHTML('</div>');
      }
      return false;
  }
  return true;
}



  /**
   * Clear the cache for a page when it is saved
   */
  public static function onPageSaveComplete($wikiPage, $user, $summary, $flags, $revision, $editResult) {
    $title = $wikiPage->getTitle();
    $cache = MediaWikiServices::getInstance()->getMainObjectStash();

    // Generate the cache key for the saved page
    $originalUrl = $title->getFullURL();
    if (isset($GLOBALS['wgEmbedLrmiUrlReplacements'])) {
      $from = $GLOBALS['wgEmbedLrmiUrlReplacements']['from'] ?? [];
      $to = $GLOBALS['wgEmbedLrmiUrlReplacements']['to'] ?? [];
      $pageUrl = str_replace($from, $to, $originalUrl);
    } else {
      $pageUrl = $originalUrl;
    }

    $cacheKey = $cache->makeKey('embedlrmi', md5($pageUrl));
    $cache->delete($cacheKey);
    error_log("LRMI CACHE CLEARED FOR PAGE: " . $pageUrl);
  }
}
