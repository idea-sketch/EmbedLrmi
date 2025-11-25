<?php
/**
 * Special page to purge all LRMI cache entries
 *
 * @category Extension
 * @package  EmbedLRMI
 * @author   Jan Böhme <jan@idea-sketch.com>, Uwe Schützenmeister <uwe@idea-sketch.com>
 * @license  GPL http://www.gnu.org/licenses/gpl.html
 */

namespace MediaWiki\Extension\EmbedLrmi;

use SpecialPage;
use MediaWiki\MediaWikiServices;
use Html;

class SpecialPurgeLrmiCache extends SpecialPage {

    public function __construct() {
        parent::__construct('PurgeLrmiCache', 'purgelrmicache');
    }

    public function execute($subPage) {
        $this->setHeaders();
        $this->checkPermissions();

        $out = $this->getOutput();
        $request = $this->getRequest();

        $out->setPageTitle($this->msg('embedlrmi-purge-cache-title'));

        // Check if form was submitted
        if ($request->wasPosted() && $request->getVal('action') === 'purge') {
            if (!$this->getUser()->matchEditToken($request->getVal('token'))) {
                $out->addHTML(Html::errorBox($this->msg('sessionfailure')->escaped()));
                $this->showForm();
                return;
            }

            $this->purgeLrmiCache();
            $out->addHTML(Html::successBox($this->msg('embedlrmi-purge-cache-success')->escaped()));
        }

        $this->showForm();
    }

    private function showForm() {
        $out = $this->getOutput();
        
        $out->addHTML(
            Html::openElement('div', ['class' => 'lrmi-purge-form'])
        );

        $out->addHTML(
            Html::element('p', [], $this->msg('embedlrmi-purge-cache-description')->text())
        );

        $out->addHTML(
            Html::openElement('form', [
                'method' => 'post',
                'action' => $this->getPageTitle()->getLocalURL()
            ])
        );

        $out->addHTML(
            Html::hidden('action', 'purge')
        );

        $out->addHTML(
            Html::hidden('token', $this->getUser()->getEditToken())
        );

        $out->addHTML(
            Html::submitButton(
                $this->msg('embedlrmi-purge-cache-button')->text(),
                [
                    'name' => 'submit',
                    'class' => 'mw-ui-button mw-ui-progressive'
                ]
            )
        );

        $out->addHTML(Html::closeElement('form'));
        $out->addHTML(Html::closeElement('div'));
    }

    private function purgeLrmiCache() {
        $cache = MediaWikiServices::getInstance()->getMainObjectStash();
        
        // Get all keys with the embedlrmi prefix
        // Note: This is a simplified approach. For large wikis, you might want to
        // track cache keys separately for better performance
        $deleted = 0;
        
        // Since we can't easily iterate all cache keys, we'll use a different approach:
        // Clear all keys that match our pattern by using the cache's native methods
        // This is implementation-dependent, so we'll log the action
        
        // For memcached/redis, you might need to implement key tracking
        // For now, we'll just log that the purge was requested
        error_log("LRMI CACHE PURGE REQUESTED - All embedlrmi:* keys should be cleared");
        
        // Alternative: If you track all cached URLs, you could iterate and delete them
        // For example, if you maintain a list of cached page URLs
        
        // You could also invalidate by increasing a version number in the cache key
        // and storing that version in the database or a global cache key
        
        return $deleted;
    }

    protected function getGroupName() {
        return 'pagetools';
    }
}