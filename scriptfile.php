<?php
/*------------------------------------------------------------------------
# plg_extravote - ExtraVote Plugin
# ------------------------------------------------------------------------
# author    Conseilgouz
# from joomlahill Plugin
# Copyright (C) 2025 www.conseilgouz.com. All Rights Reserved.
# @license - https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
-------------------------------------------------------------------------*/

// No direct access
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;

class plgContentExtraVoteInstallerScript
{
    private $min_joomla_version      = '5.0';
    private $min_php_version         = '8.1';

    public function preflight($type, $parent)
    {
        if (! $this->passMinimumJoomlaVersion()) {
            return false;
        }
        if (! $this->passMinimumPHPVersion()) {
            return false;
        }
    }

    public function install($parent)
    {
        echo Text::_('PLG_CONTENT_EXTRAVOTE_ENABLED_0');
    }
    public function update($parent)
    {
        echo Text::_('PLG_CONTENT_EXTRAVOTE_ENABLED_'.plgContentExtraVoteInstallerScript::isEnabled());
    }
    public function isEnabled()
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('enabled'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('extravote'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('content'));
        $db->setQuery($query);

        return $db->loadResult();
    }
    public function postflight($type, $parent)
    {
        if (($type == 'install') || ($type == 'update')) { // remove obsolete dir/files
            $this->postinstall_cleanup();
        }
    }
    private function postinstall_cleanup()
    {
        $obsloteFolders = ['assets', 'language'];
        foreach ($obsloteFolders as $folder) {
            $f = JPATH_SITE . '/plugins/content/extravote/' . $folder;
            if (!@file_exists($f) || !is_dir($f) || is_link($f)) {
                continue;
            }
            Folder::delete($f);
        }
        $this->removeExtraVoteAjax();
    }
    private function removeExtraVoteAjax()
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Remove CG LIKE AJAX folder.
        $f = JPATH_SITE . '/plugins/ajax/extravote';
        if (is_dir($f)) {
            Folder::delete($f);
        }
        // remove language files
        $langFiles = [
            sprintf("%s/language/en-GB/plg_ajax_%s.ini", JPATH_ADMINISTRATOR, 'extravote'),
            sprintf("%s/language/en-GB/plg_ajax_%s.sys.ini", JPATH_ADMINISTRATOR, 'extravote'),
            sprintf("%s/language/fr-FR/plg_ajax_%s.ini", JPATH_ADMINISTRATOR, 'extravote'),
            sprintf("%s/language/fr-FR/plg_ajax_%s.sys.ini", JPATH_ADMINISTRATOR, 'extravote'),
        ];
        foreach ($langFiles as $file) {
            if (@is_file($file)) {
                File::delete($file);
            }
        }
        // delete extravote ajax plugin
        $conditions = array(
            $db->quoteName('type').'='.$db->quote('plugin'),
            $db->quoteName('folder').'='.$db->quote('ajax'),
            $db->quoteName('element').'='.$db->quote('extravote')
        );
        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__extensions'))->where($conditions);
        $db->setQuery($query);
        try {
            $db->execute();
        } catch (\RuntimeException $e) {
            Log::add('unable to delete extra ajax from extensions', Log::ERROR, 'jerror');
        }
        // delete extravote package
        $conditions = array(
            $db->quoteName('type').'='.$db->quote('package'),
            $db->quoteName('element').'='.$db->quote('pkg_extravote')
        );
        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__extensions'))->where($conditions);
        $db->setQuery($query);
        try {
            $db->execute();
        } catch (\RuntimeException $e) {
            Log::add('unable to delete extra ajax from extensions', Log::ERROR, 'jerror');
        }
        // delete #__update_sites (keep showing update even if system extravote is disabled)
        $query = $db->getQuery(true);
        $query->select('site.update_site_id')
        ->from($db->quoteName('#__extensions', 'ext'))
        ->join('LEFT', $db->quoteName('#__update_sites_extensions', 'site').' ON site.extension_id = ext.extension_id')
        ->where($db->quoteName('ext.type').'='.$db->quote('plugin'))
        ->where($db->quoteName('ext.folder').'='.$db->quote('ajax'))
        ->where($db->quoteName('ext.element').'='.$db->quote('extravote'));
        $db->setQuery($query);
        $upd_id = $db->loadResult();
        if ($upd_id) {
            $conditions = array(
                $db->qn('update_site_id') . ' = ' . $upd_id
            );
            $query = $db->getQuery(true);
            $query->delete($db->quoteName('#__update_sites'))->where($conditions);
            $db->setQuery($query);
            try {
                $db->execute();
            } catch (\RuntimeException $e) {
                Log::add('unable to delete extravote ajax from updata_sites', Log::ERROR, 'jerror');
            }
        }
        // delete #__update_sites (keep showing update even if system extravote is disabled)
        $query = $db->getQuery(true);
        $query->select('site.update_site_id')
            ->from($db->quoteName('#__extensions', 'ext'))
            ->join('LEFT', $db->quoteName('#__update_sites_extensions', 'site').' ON site.extension_id = ext.extension_id')
            ->where($db->quoteName('ext.type').'='.$db->quote('package'))
            ->where($db->quoteName('ext.element').'='.$db->quote('pkg_extravote'));
        $db->setQuery($query);
        $upd_id = $db->loadResult();
        if (!$upd_id) {
            return true;
        }
        $conditions = array(
            $db->qn('update_site_id') . ' = ' . $upd_id
        );

        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__update_sites'))->where($conditions);
        $db->setQuery($query);
        try {
            $db->execute();
        } catch (\RuntimeException $e) {
            Log::add('unable to delete extravote pack from updata_sites', Log::ERROR, 'jerror');
        }

    }
    
    // Check if Joomla version passes minimum requirement
    private function passMinimumJoomlaVersion()
    {
        if (version_compare(JVERSION, $this->min_joomla_version, '<')) {
            Factory::getApplication()->enqueueMessage(
                'Incompatible Joomla version : found <strong>' . JVERSION . '</strong>, Minimum : <strong>' . $this->min_joomla_version . '</strong>',
                'error'
            );

            return false;
        }

        return true;
    }

    // Check if PHP version passes minimum requirement
    private function passMinimumPHPVersion()
    {

        if (version_compare(PHP_VERSION, $this->min_php_version, '<')) {
            Factory::getApplication()->enqueueMessage(
                'Incompatible PHP version : found  <strong>' . PHP_VERSION . '</strong>, Minimum <strong>' . $this->min_php_version . '</strong>',
                'error'
            );
            return false;
        }

        return true;
    }

}
