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

use OutputPage;
use Skin;
use SkinTemplate;

/**
 * Class for handling hooks related to EmbedLRMI extension
 */
class EmbedLrmiHooks {
	/**
	 * Hook handler for BeforePageDisplay
	 *
	 * Adds LRMI metadata to the page output.
	 *
	 * @param OutputPage &$out The output page object
	 * @param Skin &$skin The skin object
	 * @return bool Always returns true
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$lrmidata = EmbedLrmiData::getInstance();
		$lrmidata->render( $out );
		return true;
	}

	/**
	 * Hook handler for SkinTemplateNavigation::Universal
	 *
	 * Adds a link to view LRMI data to the toolbox in modern MediaWiki skins.
	 *
	 * @param SkinTemplate $skin The skin template object
	 * @param array &$links The navigation links array
	 */
	public static function onSkinTemplateNavigation__Universal( SkinTemplate $skin, array &$links ) {
		$title = $skin->getTitle();
		if ( $title && $title->isContentPage() ) {
			$links['toolbox']['lrmi'] = [
				'text' => $skin->msg( 'embedlrmi-show-lrmi-data' )->text(),
				'href' => $title->getLocalURL( [ 'action' => 'lrmi' ] ),
				'id' => 't-lrmi'
			];
		}
	}

	/**
	 * Hook handler for SidebarBeforeOutput
	 *
	 * Adds a link to view LRMI data to the sidebar in older MediaWiki skins.
	 *
	 * @param Skin $skin The skin object
	 * @param array &$sidebar The sidebar array
	 */
	public static function onSidebarBeforeOutput( Skin $skin, array &$sidebar ) {
		$title = $skin->getTitle();
		if ( $title && $title->isContentPage() ) {
			if ( !isset( $sidebar['TOOLBOX'] ) ) {
				$sidebar['TOOLBOX'] = [];
			}
			$sidebar['TOOLBOX']['lrmi'] = [
				'text' => $skin->msg( 'embedlrmi-show-lrmi-link' )->text(),
				'href' => $title->getLocalURL( [ 'action' => 'lrmi' ] ),
				'id' => 't-lrmi'
			];
		}
	}
}
