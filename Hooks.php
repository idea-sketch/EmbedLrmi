<?php
/**
 * EmbedLRMI
 * Embed LRMI metadata for Articles
 *
 * PHP version 5.4
 *
 * @category Extension
 * @package  EmbedLRMI
 * @author   Jan Böhme <jan@idea-sketch.com>
 * @license  GPL http://www.gnu.org/licenses/gpl.html
 * @link     https://idea-sketch.com
 */

namespace MediaWiki\Extension\EmbedLRMI;

use OutputPage;
use Skin;

class Hooks {
  /**
	 * Handle meta elements and page title modification.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * @param OutputPage &$out The output page.
	 * @param Skin &$skin The current skin.
	 * @return bool
	 */
  public static function onBeforePageDisplay(OutputPage &$out, Skin &$skin) {

    $lrmidata = LRMIData::getInstance();
    $lrmidata->render($out);
    return true;

  }

}

?>
