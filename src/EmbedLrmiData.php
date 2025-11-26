<?php
/**
 * EmbedLrmi
 * Embed LRMI metadata for Articles
 *
 * PHP version 8.0
 *
 * @category Extension
 * @package  EmbedLRMI
 * @author   Jan Böhme <jan@idea-sketch.com>
 * @author   Uwe Schützenmeister <uwe@idea-sketch.com>
 * @license  GPL-2.0-or-later
 * @link     https://idea-sketch.com
 */

namespace MediaWiki\Extension\EmbedLrmi;

use ErrorPageError;
use MediaWiki\MediaWikiServices;
use OutputPage;
use RequestContext;
use Title;

if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This is a MediaWiki extension and doesn't provide standalone functionality\n" );
}

/**
 * Class for embedding LRMI metadata in MediaWiki pages
 */
class EmbedLrmiData {
	/**
	 * @var static EmbedLrmiData instance to use for Singleton pattern
	 */
	private static $instance;

	/**
	 * @var Title current instance of Title received from global $wgTitle
	 */
	private $title;

	/**
	 * @var string Site name received from global $wgSitename
	 */
	private $sitename;

	/**
	 * @var string Server URL received from global $wgServer
	 */
	private $server;

	/**
	 * @var string Repository endpoint for fetching LRMI metadata
	 */
	private $endpoint;

	/**
	 * @var string current page/article URL
	 */
	private $pageUrl;

	/**
	 * @var array LRMI data for current page/article
	 */
	private $lrmidata;

	/**
	 * @var ObjectStash Cache instance for storing LRMI data
	 */
	private $cache;

	/**
	 * @var int Cache expiry time in seconds
	 */
	private $cacheExpiry;

	/**
	 * Singleton pattern getter
	 *
	 * @return EmbedLrmiData The singleton instance of EmbedLrmiData
	 */
	public static function getInstance() {
		if ( !isset( self::$instance ) ) {
			self::$instance = new EmbedLrmiData();
		}
		return self::$instance;
	}

	/**
	 * Class constructor
	 *
	 * Initializes the EmbedLrmiData instance with configuration values from global variables.
	 * Sets up the API endpoint, URL replacements, and cache configuration.
	 *
	 * @throws ErrorPageError If $wgEmbedLrmiEndpoint is not set in LocalSettings.php
	 */
	public function __construct() {
		$services = MediaWikiServices::getInstance();
		$config = $services->getMainConfig();

		$this->title = RequestContext::getMain()->getTitle();
		$this->sitename = $config->get( 'Sitename' );
		$this->server = $config->get( 'Server' );

	// Get configuration values
		$this->endpoint = $config->get( 'EmbedLrmiEndpoint' );
		if ( empty( $this->endpoint ) ) {
			throw new ErrorPageError(
		'embedlrmi-missing-config',
		'EmbedLRMI configuration error: $wgEmbedLrmiEndpoint is not set in LocalSettings.php'
			);
		}

	// Apply URL replacements if configured
		$urlReplacements = null;
		if ( $config->has( 'EmbedLrmiUrlReplacements' ) ) {
			$urlReplacements = $config->get( 'EmbedLrmiUrlReplacements' );
		}
		$originalUrl = $this->title->getFullURL();
		if ( $urlReplacements ) {
			$from = $urlReplacements['from'] ?? [];
			$to = $urlReplacements['to'] ?? [];
			$this->pageUrl = str_replace( $from, $to, $originalUrl );
		} else {
			$this->pageUrl = $originalUrl;
		}

	// Initialize cache
		$this->cache = $services->getMainObjectStash();
		if ( $config->has( 'EmbedLrmiCacheExpiry' ) ) {
			$this->cacheExpiry = $config->get( 'EmbedLrmiCacheExpiry' );
		} else {
	  // Default: 30 days in seconds
			$this->cacheExpiry = 2592000;
		}

	// Debug: Log the final URL
		error_log( "FINAL PAGE URL: " . $this->pageUrl );
	}

	/**
	 * Get the creation time of the current article
	 *
	 * @return string Creation time in ISO 8601 format or 0 if not available
	 */
	public function getCTime() {
		$ctime = \DateTime::createFromFormat( 'YmdHis', $this->title->getEarliestRevTime() );
		if ( $ctime ) {
			return $ctime->format( 'c' );
		}
		return 0;
	}

	/**
	 * Get the modification time of the current article
	 *
	 * @return string Modification time in ISO 8601 format or 0 if not available
	 */
	public function getMTime() {
		$mtime = \DateTime::createFromFormat( 'YmdHis', $this->title->getTouched() );
		if ( $mtime ) {
			return $mtime->format( 'c' );
		}
		return 0;
	}

	/**
	 * Get metadata from EduSharing API
	 *
	 * Fetches LRMI metadata from the EduSharing API and caches the result.
	 * If metadata is already cached, it will be retrieved from cache instead.
	 */
	private function getMetaData() {
		// Generate a unique cache key based on the page URL
		$cacheKey = $this->cache->makeKey( 'embedlrmi', md5( $this->pageUrl ) );

		// Try to get data from cache
		$cachedData = $this->cache->get( $cacheKey );
		if ( $cachedData !== false ) {
			$this->lrmidata = $cachedData;
			error_log( "LRMI DATA LOADED FROM CACHE" );
			return;
		}

		// Fetch data from API if not in cache
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $this->endpoint );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_POST, 1 );

		$postData = [
	  'criteria' => [
		  [
			  'property' => 'ccm:wwwurl',
			  'values' => [ $this->pageUrl ]
		  ]
	  ]
		];

		$postfields = json_encode( $postData, JSON_PRETTY_PRINT );
		error_log( "SENDING POSTFIELDS: " . $postfields );

		$headers = [
			'Content-Type: application/json',
			'Accept: application/json'
		];
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $postfields );

		$result = curl_exec( $ch );

		// Debug: Log the raw API response
		error_log( "RAW API RESPONSE: " . $result );

		if ( $result === false ) {
			error_log( "cURL Error: " . curl_error( $ch ) );
		}

		if ( $result ) {
			$this->lrmidata = json_decode( $result, true );
			error_log( "DECODED LRMI DATA: " . print_r( $this->lrmidata, true ) );

			// Store data in cache
			$this->cache->set( $cacheKey, $this->lrmidata, $this->cacheExpiry );
			error_log( "LRMI DATA STORED IN CACHE" );
		}
	}

	/**
	 * Generate a HTML tree from LRMI data
	 *
	 * Recursively generates an HTML unordered list from an associative array.
	 *
	 * @param array|string $data The LRMI data to generate HTML from
	 * @return string HTML representation of the LRMI data
	 */
	private function generateTree( $data ) {
		if ( !is_array( $data ) ) {
			$data = json_decode( $data, true );
		}

		$html = '<div><ul>';
		foreach ( $data as $key => $value ) {
			$html .= '<li><b>' . htmlspecialchars( $key ) . '</b>: ';
			if ( is_array( $value ) ) {
				$html .= $this->generateTree( $value );
			} else {
				$html .= htmlspecialchars( $value );
			}
			$html .= '</li>';
		}
		$html .= '</ul></div>';
		return $html;
	}

	/**
	 * Render head item with LRMI metadata
	 *
	 * Adds LRMI metadata as JSON-LD script to the page head.
	 *
	 * @param OutputPage &$out The output page object
	 */
	public function render( OutputPage &$out ) {
		if ( $this->title instanceof Title && $this->title->isContentPage() ) {
			$this->getMetaData();
			if ( $this->lrmidata && isset( $this->lrmidata['nodes'][0] ) ) {
				$out->addHeadItem(
					'EmbedLrmiData',
					'<script type="application/ld+json">' . json_encode( $this->lrmidata['nodes'][0] ) . '</script>'
				);
			}
		}
	}

	/**
	 * Handle the 'lrmi' action to display LRMI data
	 *
	 * This method is called when the 'lrmi' action is requested.
	 * It displays the LRMI metadata for the current page.
	 *
	 * @param OutputPage $outputPage The output page object
	 * @param Article $article The article object
	 * @param Title $title The title object
	 * @param User $user The user object
	 * @param WebRequest $request The web request object
	 * @param MediaWiki $mediaWiki The MediaWiki object
	 * @return bool Returns false if the action was handled, true otherwise
	 */
	public static function onMediaWikiPerformAction( $outputPage, $article, $title, $user, $request, $mediaWiki ) {
		$action = $request->getVal( 'action' );
		if ( $action === 'lrmi' ) {
			$instance = self::getInstance();
			$instance->getMetaData();
			$outputPage->setArticleRelated( true );
			$outputPage->setRobotPolicy( 'noindex,nofollow' );
			$outputPage->setPageTitle( $outputPage->msg( 'embedlrmi-show-lrmi-data' ) );
			if ( $instance->lrmidata && isset( $instance->lrmidata['nodes'][0] ) ) {
				$outputPage->addHTML( '<div class="lrmi-data-container">' );
				$outputPage->addHTML( $instance->generateTree( $instance->lrmidata['nodes'][0] ) );
				$outputPage->addHTML( '</div>' );
			} else {
				$outputPage->addHTML( '<div class="lrmi-data-container">' );
				$outputPage->addHTML( '<p>No LRMI metadata available for this page.</p>' );
				$outputPage->addHTML( '</div>' );
			}
			return false;
		}
		return true;
	}

	/**
	 * Clear the cache for a page when it is saved
	 *
	 * This method is called when a page is saved to invalidate the LRMI cache
	 * for that page, ensuring fresh data is fetched on the next request.
	 *
	 * @param WikiPage $wikiPage The wiki page that was saved
	 * @param User $user The user who saved the page
	 * @param string $summary The edit summary
	 * @param int $flags The edit flags
	 * @param Revision $revision The new revision
	 * @param Status $editResult The edit result status
	 */
	public static function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revision, $editResult ) {
		$title = $wikiPage->getTitle();
		$cache = MediaWikiServices::getInstance()->getMainObjectStash();

		// Generate the cache key for the saved page
		$originalUrl = $title->getFullURL();
		if ( isset( $GLOBALS['wgEmbedLrmiUrlReplacements'] ) ) {
			$from = $GLOBALS['wgEmbedLrmiUrlReplacements']['from'] ?? [];
			$to = $GLOBALS['wgEmbedLrmiUrlReplacements']['to'] ?? [];
			$pageUrl = str_replace( $from, $to, $originalUrl );
		} else {
			$pageUrl = $originalUrl;
		}

		$cacheKey = $cache->makeKey( 'embedlrmi', md5( $pageUrl ) );
		$cache->delete( $cacheKey );
		error_log( "LRMI CACHE CLEARED FOR PAGE: " . $pageUrl );
	}
}
