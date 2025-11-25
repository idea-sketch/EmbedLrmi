<?php

/**
 * EmbedLrmi
 * Embed LRMI metadata for Articles
 *
 * PHP version 8.0
 *
 * @category Extension
 * @package  EmbedLrmi
 * @author   Jan Böhme <jan@idea-sketch.com>, Uwe Schützenmeister <uwe@idea-sketch.com>
 * @license  GPL http://www.gnu.org/licenses/gpl.html
 * @link     https://idea-sketch.com
 */

namespace MediaWiki\Extension\EmbedLrmi;

use OutputPage;
use Skin;
use SkinTemplate;

class EmbedLrmiHooks {
    public static function onBeforePageDisplay(OutputPage &$out, Skin &$skin) {
        $lrmidata = EmbedLrmiData::getInstance();
        $lrmidata->render($out);
        return true;
    }

    public static function onSkinTemplateNavigation__Universal(SkinTemplate $skin, array &$links) {
        $title = $skin->getTitle();
        if ($title && $title->isContentPage()) {
            $links['toolbox']['lrmi'] = [
                'text' => $skin->msg('embedlrmi-show-lrmi-data')->text(),
                'href' => $title->getLocalURL(['action' => 'lrmi']),
                'id' => 't-lrmi'
            ];
        }
    }

    public static function onSidebarBeforeOutput(Skin $skin, array &$sidebar) {
        $title = $skin->getTitle();
        if ($title && $title->isContentPage()) {
            if (!isset($sidebar['TOOLBOX'])) {
                $sidebar['TOOLBOX'] = [];
            }
            $sidebar['TOOLBOX']['lrmi'] = [
                'text' => $skin->msg('embedlrmi-show-lrmi-link')->text(),
                'href' => $title->getLocalURL(['action' => 'lrmi']),
                'id' => 't-lrmi'
            ];
        }
    }
}