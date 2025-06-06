<?php
/*------------------------------------------------------------------------
# plg_extravote - ExtraVote Plugin
# ------------------------------------------------------------------------
# author    Conseilgouz
# from joomlahill Plugin
# Copyright (C) 2025 www.conseilgouz.com. All Rights Reserved.
# @license - https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
-------------------------------------------------------------------------*/

namespace ConseilGouz\Plugin\Content\Extravote\Extension;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

define("EXTRAVOTE_OPTION_AFTER_TITLE", 0);
define("EXTRAVOTE_OPTION_AFTER_CONTENT", 1);
define("EXTRAVOTE_OPTION_HIDE", 2);


class Extravote extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    protected $article_id;
    protected $article_title;
    protected $view;
    public $myname = 'Extravote';
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentBeforeDisplay'   => 'checkExtra',
            'onSchemaBeforeCompileHead' => 'beforeCompileHead',
            'onAjaxExtravote'   => 'goAjax',
        ];
    }
    public function checkExtra($event)
    {
        if (strpos($event->getContext(), 'com_content') !== false) {
            $input               = Factory::getApplication()->input;
            $this->view          = $input->getCmd('view');
            $this->article_id    = $event->getItem()->id;
            $this->article_title = $event->getItem()->title;

            $this->ExtraVotePrepare($event->getItem(), $event->getParams());

            if ($this->params->get('display') == EXTRAVOTE_OPTION_AFTER_TITLE) {
                $hide  = $this->params->get('hide', 1);
                $hidecat  = $this->params->get('hidecat', 0); // default = show
                if (($hide == 1 && $this->view != 'article') ||
                    ($hide == 0 && $hidecat && strpos(get_class($event->getItem()), 'Category'))) {
                    return;
                }
                if ($hide != 1 || $this->view == 'article') {
                    $event->getItem()->xid = 0;
                    $event->addResult($this->ContentExtraVote($event->getItem(), $event->getParams()));
                }
            }
        }
    }
    protected function ContentExtraVote($article, $params)
    {
        $table = ($this->params->get('table', 1) == 1 ? '#__content_extravote' : '#__content_rating');
        $rating_count = $rating_sum = 0;
        $html = $ip = '';

        if ($params->get('show_vote')) {
            $db	= $this->getDatabase();
            $query = 'SELECT * FROM ' . $table . ' WHERE content_id='.$this->article_id . ($table == '#__content_extravote' ? ' AND extra_id = 0' : '');
            $db->setQuery($query);
            $vote = $db->loadObject();
            if($vote) {
                $rating_sum   = $vote->rating_sum;
                $rating_count = intval($vote->rating_count);
                $ip = $vote->lastip;
            }
            $html .= $this->plgContentExtraVoteStars($this->article_id, $rating_sum, $rating_count, $article->xid, $ip);
        }
        return $html;
    }
    protected function plgContentExtraVoteStars($id, $rating_sum, $rating_count, $xid, $ip)
    {
        $plg = 'media/plg_content_extravote/';

        /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        if ($this->params->get('css', 1)) :
            $wa->registerAndUseStyle('extravotecontent', $plg.'extravote.css');
        endif;
        $wa->registerAndUseScript('extravotecontent', $plg.'extravote.js');
        if ($this->params->get('customcss')) {
            $wa->addInlineStyle($this->params->get('customcss'));
        }

        global $plgContentExtraVoteAddScript;

        $show_counter = $this->params->get('show_counter', 1);
        $show_rating  = $this->params->get('show_rating', 1);
        $rating_mode  = $this->params->get('rating_mode', 1);
        $show_unrated = $this->params->get('show_unrated', 1);
        $initial_hide = $this->params->get('initial_hide', 0);
        $currip = $_SERVER['REMOTE_ADDR'];
        $add_snippets = 0;
        if (PluginHelper::isEnabled('system', 'schemaorg')) {
            $add_snippets = 0; // will be added by onSchemaBeforeCompileHead event
        }
        $rating  = 0;

        if(!$plgContentExtraVoteAddScript) {
            $wa->addInlineScript("
                var ev_basefolder = '".URI::base(true)."';
                var extravote_text=Array('".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_NO_AJAX')."','".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_LOADING')."','".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_THANKS')."','".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_LOGIN')."','".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_RATED')."','".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_LABEL_VOTES')."','".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_LABEL_VOTE')."','".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_LABEL_RATING').
               "');
            ");
            $plgContentExtraVoteAddScript = 1;
        }

        if($rating_count != 0) {
            $rating  = ($rating_sum / intval($rating_count));
            $add_snippets = $this->params->get('snippets', 0);
        } elseif($show_unrated == 0) {
            $show_counter = -1;
            $show_rating  = -1;
        }

        $container = 'div';
        $class     = 'size-'.$this->params->get('size', 1);
        if((int)$xid) {
            if ($show_counter == 2) {
                $show_counter = 0;
            }
            if ($show_rating == 2) {
                $show_rating = 0;
            }
            $container = 'span';
            $class    .= ' extravote-small';
            $add_snippets = 0;
        } else {
            if ($show_counter == 3) {
                $show_counter = 0;
            }
            if ($show_rating == 3) {
                $show_rating = 0;
            }
            $class    .= ' extravote';
        }
        if ($show_counter && $show_rating && $this->params->get('one_line', 0)) { // display all on 1 or 2 lines
            $class .= ' d-flex'; // one line
        }
        $stars = (($this->params->get('table', 1) != 1 && !(int)$xid) ? 5 : $this->params->get('stars', 10));
        $spans = '';
        for ($i = 0,$j = 5 / $stars; $i < $stars; $i++,$j += 5 / $stars) :
            $spans .= "
      <span class=\"extravote-star\"><a href=\"javascript:void(null)\" onclick=\"javascript:JVXVote(".$id.",".$j.",".$rating_sum.",".$rating_count.",'".$xid."',".$show_counter.",".$show_rating.",".$rating_mode.");\" title=\"".TEXT::_('PLG_CONTENT_EXTRAVOTE_RATING_'.($j * 10).'_OUT_OF_5')."\" class=\"ev-".($j * 10)."-stars\">1</a></span>";
        endfor;
        $html = "<".$container." class=\"".$class."\">";
        if ($this->params->get('description',"")) {
            $html .= "<div class=\"extravote-desc\"".">" . $this->params->get('description') . "</div>";
        }
        $html .= 
  "<div class=\"extravote-stars\"".">"."<span id=\"rating_".$id."_".$xid."\" class=\"current-rating\"".((!$initial_hide || $currip == $ip) ? " style=\"width:".round($rating * 20)."%;\"" : "")."></span>"
    .$spans."
  </div>
  <div class=\"extravote-info".(($initial_hide && $currip != $ip) ? " ihide\"" : "")."\" id=\"extravote_".$id."_".$xid."\">";

        if ($show_rating > 0) {
            if ($rating_mode == 0) {
                $rating = round($rating * 20) . '%';
            } else {
                $rating = number_format($rating, 2);
            }
            $html .= TEXT::sprintf('PLG_CONTENT_EXTRAVOTE_LABEL_RATING', $rating);
        }
        if ($show_counter > 0) {
            if($rating_count != 1) {
                $html .= TEXT::sprintf('PLG_CONTENT_EXTRAVOTE_LABEL_VOTES', $rating_count);
            } else {
                $html .= TEXT::sprintf('PLG_CONTENT_EXTRAVOTE_LABEL_VOTE', $rating_count);
            }
        }
        $html .= "</div>";
        $html .= "
</".$container.">";
        if ($add_snippets) {
            $html .= "<div class=\"visually-hidden\" itemscope=\"itemscope\" itemtype=\"http://schema.org/Product\">";
            $html .= "<span itemprop=\"name\">".$this->article_title."</span>";
            $html .= "<div class=\"visually-hidden\" itemprop=\"aggregateRating\" itemscope itemtype=\"http://schema.org/AggregateRating\">";
            $html .= "<meta itemprop=\"ratingCount\" content=\"".$rating_count."\" />";
            $html .= "<meta itemprop=\"ratingValue\" content=\"".$rating."\" />";
            $html .= "<meta itemprop=\"bestRating\" content=\"5\" />";
            $html .= "<meta itemprop=\"worstRating\" content=\"1\" />";
            $html .= "</div></div>";
        }

        return $html;
    }
    protected function ExtraVotePrepare($article, $params)
    {
        if (isset($this->article_id)) {
            $extra = $this->params->get('extra', 1);
            $main  = $this->params->get('main', 1);
            if ($extra != 0) {
                $regex = "/{extravote\s*([0-9]+)}/i";
                if ($this->view != 'article' && isset($article->introtext) && stripos($article->introtext, 'extravote')) {
                    if ($extra == 2) {
                        $article->introtext = preg_replace($regex, '', $article->introtext);
                    } else {
                        $article->introtext = preg_replace_callback($regex, array($this,'plgContentExtraVoteReplacer'), $article->introtext);
                    }
                } elseif (stripos($article->text, 'extravote')) {
                    $article->text = preg_replace_callback($regex, array($this,'plgContentExtraVoteReplacer'), $article->text);
                }
            }
            if ($main != 0) {
                $strposIntro = isset($article->introtext) ? stripos($article->introtext, 'mainvote') : false;
                $strposText  = stripos($article->text, 'mainvote');
                $regex = "/{mainvote\s*([0-9]*)}/i";
                if ($main == 2 && $this->view != 'article' && $strposIntro) {
                    $article->introtext = preg_replace($regex, '', $article->introtext);
                } else {
                    $this->article_id = $article->id;
                    if ($this->view == 'article' && $strposText) {
                        $article->text = preg_replace_callback($regex, array($this,'plgContentExtraVoteReplacer'), $article->text);
                    } elseif($strposIntro) {
                        $article->introtext = preg_replace_callback($regex, array($this,'plgContentExtraVoteReplacer'), $article->introtext);
                    }
                }
            }
            if ($this->params->get('display') == EXTRAVOTE_OPTION_AFTER_CONTENT) {
                $article->xid = 0;
                if ($this->view == 'article') {
                    $article->text .= $this->ContentExtraVote($article, $params);
                } elseif ($this->params->get('hide') == 0) {
                    $article->introtext .= $this->ContentExtraVote($article, $params);
                }
            }
        }
        return $article;
    }

    protected function plgContentExtraVoteReplacer(&$matches)
    {
        $db	 = $this->getDatabase();
        $cid = 0;
        $xid = 0;
        if (isset($matches[1])) {
            if(stripos($matches[0], 'extravote')) {
                $xid = (int)$matches[1];
            } else {
                $cid = (int)$matches[1];
            }
        }
        if ($cid == 0 && ($this->params->get('article_id') || $xid == 0)) {
            $cid = $this->article_id;
        }
        $rating_sum = 0;
        $rating_count = 0;
        if ($xid == 0) :
            global $extravote_mainvote;
            $extravote_mainvote .= 'x';
            $xid = $extravote_mainvote;
            $table = ($this->params->get('table', 1) == 1 ? '#__content_extravote' : '#__content_rating');
            $db->setQuery('SELECT * FROM ' . $table . ' WHERE content_id='.(int)$cid);
        else :
            $db->setQuery('SELECT * FROM #__content_extravote WHERE content_id='.(int)$cid.' AND extra_id='.(int)$xid);
        endif;
        $vote = $db->loadObject();
        if($vote) {
            if($vote->rating_count != 0) {
                $rating_sum = $vote->rating_sum;
            }
            $rating_count = intval($vote->rating_count);
        }
        return $this->plgContentExtraVoteStars($cid, $rating_sum, $rating_count, $xid, ($vote ? $vote->lastip : ''));
    }

    /**
     * Create SchemaOrg AggregateRating
     *
     * @param   object   $schema  The schema of the content being passed to the plugin
     * @param   string   $context The context of the content being passed to the plugin
     *
     * @return  void
     *
     * @since   5.0
     */
    public function beforeCompileHead($event): void
    {
        $add_snippets = $this->params->get('snippets', 0);
        if (!$add_snippets) {
            return;
        } // don't add snippet
        $schema = $event->getSchema();
        $context = $event->getContext();
        $graph    = $schema->get('@graph');
        $baseId   = Uri::root() . '#/schema/';
        $schemaId = $baseId . str_replace('.', '/', $context);

        foreach ($graph as &$entry) {
            if (!isset($entry['@type']) || !isset($entry['@id'])) {
                continue;
            }
            if ($entry['@id'] !== $schemaId) {
                continue;
            }
            if (isset($entry['aggregateRating'])) {
                return;
            } // already done

            switch ($entry['@type']) {
                case 'Book':
                case 'Brand':
                case 'CreativeWork':
                case 'Event':
                case 'Offer':
                case 'Organization':
                case 'Place':
                case 'Product':
                case 'Recipe':
                case 'Service':
                case 'alors':
                    $rating = $this->prepareAggregateRating($context);
                    break;
                case 'Article':
                case 'BlogPosting':
                    $rating = $this->prepareProductAggregateRating($context);
                    break;
            }
        }

        if (isset($rating) && $rating) {
            $graph[] = $rating;
            $schema->set('@graph', $graph);
        }
    }

    /**
     * Prepare AggregateRating
     *
     * @param   string $context
     *
     * @return  ?string
     *
     * @since  5.0
     */
    protected function prepareAggregateRating($context)
    {
        [$extension, $view, $id] = explode('.', $context);

        if ($view === 'article') {
            $baseId   = Uri::root() . '#/schema/';
            $schemaId = $baseId . str_replace('.', '/', $context);

            $component = $this->getApplication()->bootComponent('com_content')->getMVCFactory();
            $model     = $component->createModel('Article', 'Site');
            $article   = $model->getItem($id);
            $count     = $article->rating_count;
            $rating    = $article->rating;
            if ($this->params->get('table', 1)) { // use extravote table ?
                $this->getExtraVoteInfos($id, $count, $rating);
            }
            if ($count > 0) {
                return ['@isPartOf' => ['@id' => $schemaId, 'aggregateRating' => ['@type' => 'AggregateRating','ratingCount' => (string) $count,'ratingValue' => (string) $rating]]];
            }
        }

        return false;
    }

    /**
     * Prepare Product AggregateRating
     *
     * @param   string $context
     *
     * @return  ?string
     *
     * @since  5.0
     */
    protected function prepareProductAggregateRating($context)
    {
        [$extension, $view, $id] = explode('.', $context);

        if ($view === 'article') {
            $baseId   = Uri::root() . '#/schema/';
            $schemaId = $baseId . str_replace('.', '/', $context);

            $component = $this->getApplication()->bootComponent('com_content')->getMVCFactory();
            $model     = $component->createModel('Article', 'Site');
            $article   = $model->getItem($id);
            $count     = $article->rating_count;
            $rating    = $article->rating;
            if ($this->params->get('table', 1)) { // use extravote table ?
                $this->getExtraVoteInfos($id, $count, $rating);
            }
            if ($count > 0) {
                return ['@isPartOf' => ['@id' => $schemaId, '@type' => 'Product', 'name' => $article->title, 'aggregateRating' => ['@type' => 'AggregateRating', 'ratingCount' => (string) $count, 'ratingValue' => (string) $rating]]];
            }
        }

        return false;
    }
    protected function getExtraVoteInfos($id, &$count, &$rating)
    {
        $db	 = $this->getDatabase();
        $db->setQuery('SELECT * FROM #__content_extravote WHERE content_id='.(int)$id.' AND extra_id = 0');
        $vote = $db->loadObject();
        if($vote) {
            if($vote->rating_count != 0) {
                $rating = $vote->rating_sum;
            }
            $count = intval($vote->rating_count);
        }

    }
    public function goAjax($event)
    {
        $input	= Factory::getApplication()->input;
        $user = Factory::getApplication()->getIdentity();
        $action = $input->getString('action');
        if ($action == 'sync') {
            $event->addResult($this->goSync());
            return;
        }

        $plugin	= PluginHelper::getPlugin('content', 'extravote');
        $params = new Registry();
        $params->loadString($plugin->params);

        if ($params->get('access') == 2 && !$user->id) {
            return $event->addResult('login');
        }
        $user_rating = $input->getFloat('user_rating');
        $xid         = $input->getInt('xid');
        $table       = (($params->get('table', 1) != 1 && !(int)$xid) ? '#__content_rating' : '#__content_extravote');
        $cid = 0;
        if ($params->get('article_id') || $xid == 0) {
            $cid = $input->getInt('cid');
        }
        $db  = $this->getDatabase();
        $query	= $db->getQuery(true);
        if ($user_rating < 0.5 || $user_rating > 5) {
            return;
        }
        $currip = $_SERVER['REMOTE_ADDR'];

        $query->select('*')
            ->from($db->quoteName($table))
            ->where('content_id = '.$db->quote($cid).($table == '#__content_extravote' ? ' AND extra_id = '.$db->quote($xid) : ''));
        $db->setQuery($query);
        try {
            $votesdb = $db->loadObject();
        } catch (\RuntimeException $e) {
            return  $event->addResult('error');
        }
        $query	= $db->getQuery(true);
        if (!$votesdb) { // No vote for this article
            $columns = array('content_id', 'rating_sum', 'rating_count', 'lastip');
            $values = array(':content_id', ':rating_sum', ':rating_count', ':lastip');
            // $values = array($cid, $user_rating, 1, $db->quote($currip));
            if($table == '#__content_extravote') :
                $columns[] = 'extra_id';
                $values[] = ':extra_id';
            endif;
            $one = 1;
            $query
                ->insert($db->quoteName($table))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));
            $query->bind(':content_id', $cid, \Joomla\Database\ParameterType::INTEGER)
            ->bind(':rating_sum', $user_rating, \Joomla\Database\ParameterType::INTEGER)
            ->bind(':rating_count', $one, \Joomla\Database\ParameterType::INTEGER)
            ->bind(':lastip', $currip, \Joomla\Database\ParameterType::STRING);
            if($table == '#__content_extravote') {
                $query->bind(':extra_id', $xid, \Joomla\Database\ParameterType::INTEGER);
            }

            $db->setQuery($query);
            try {
                $result = $db->execute();
            } catch (\RuntimeException $e) {
                return $event->addResult('error');
            }
            if ($params->get('access') == 2 &&  $params->get('onevoteuser') == 1) { // one vote per user/article
                return $event->addResult($this->checkuservote($cid, $user->id, $user_rating, $xid, $table, true));
            }
        } else { // vote exists in table
            if ($params->get('access') == 2 &&  $params->get('onevoteuser') == 1) { // one vote per user/article
                return $event->addResult($this->checkuservote($cid, $user->id, $user_rating, $xid, $table, false));
            } elseif ($currip != ($votesdb->lastip)) {
                $query
                    ->update($db->quoteName($table))
                    ->set('rating_sum = rating_sum + :user_rating')
                    ->set('rating_count = rating_count +'. 1)
                    ->set('lastip = :lastip')
                    ->where('content_id = :content_id'.($table == '#__content_extravote' ? ' AND extra_id = '.$xid : ''));
                $query->bind(':content_id', $cid, \Joomla\Database\ParameterType::INTEGER)
                      ->bind(':user_rating', $user_rating, \Joomla\Database\ParameterType::INTEGER)
                      ->bind(':lastip', $currip, \Joomla\Database\ParameterType::STRING);
                $db->setQuery($query);
                try {
                    $result = $db->execute();
                } catch (\RuntimeException $e) {
                    return $event->addResult('error');
                }
            } else { // last IP
                return $event->addResult('voted');
            }
        }
        if ($params->get('sync', 0) &&  ($table == '#__content_extravote')) {// synchronize Vote and ExtraVote table
            return $event->addResult($this->sync_vote($cid, $xid, $currip));
        }
        $event->addResult('thanks');
    }
    /* Extravote : 1 vote per user/article
    */
    protected function checkuservote($cid, $user_id, $user_rating, $xid, $table, $create)
    {
        $db  = $this->getDatabase();
        $query	= $db->getQuery(true);
        $query->select('*')
            ->from($db->quoteName('#__content_extravote_user'))
            ->where('content_id = '.$db->quote($cid).' AND user_id = '.$db->quote($user_id).' AND extra_id = '.$db->quote($xid));
        $db->setQuery($query);
        try {
            $voteuser = $db->loadObject();
        } catch (\RuntimeException $e) {
            return   'error';
        }
        if (!$voteuser) { // No vote for this user/article
            $columns = array('content_id', 'rating', 'user_id', 'created');
            $values = array($cid, $user_rating, $user_id,  $db->quote(Factory::getDate()->toSql()));
            $columns[] = 'extra_id';
            $values[] = $xid;
            $query	= $db->getQuery(true);
            $query
                ->insert($db->quoteName('#__content_extravote_user'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));
            $db->setQuery($query);
            try {
                $result = $db->execute();
            } catch (\RuntimeException $e) {
                return 'error';
            }
            if (!$create) {// update vote count
                $currip = $_SERVER['REMOTE_ADDR'];
                $query	= $db->getQuery(true);
                $query
                    ->update($db->quoteName($table))
                    ->set('rating_sum = rating_sum + ' . $user_rating)
                    ->set('rating_count = rating_count +'. 1)
                    ->set('lastip = '. $db->quote($currip))
                    ->where('content_id = '.$cid.($table == '#__content_extravote' ? ' AND extra_id = '.$xid : ''));
                $db->setQuery($query);
                try {
                    $result = $db->execute();
                } catch (\RuntimeException $e) {
                    return 'error';
                }
            }
        } else {
            return 'voted';
        }
        return 'thanks';
    }
    /*
    Synchronize Vote table with ExtraVote infos
    */
    protected function sync_vote($cid, $xid, $currip)
    {
        // get extra_vote infos
        $db  = $this->getDatabase();
        $query	= $db->getQuery(true);
        $query->select('*')
            ->from($db->quoteName('#__content_extravote'))
            ->where('content_id = '.$db->quote($cid).' AND extra_id = '.$db->quote($xid));
        $db->setQuery($query);
        try {
            $extravote = $db->loadObject();
        } catch (\RuntimeException $e) {
            return 'error';
        }
        // store extravoteinfo in vote table
        $query	= $db->getQuery(true);
        $query->select('*')
            ->from($db->quoteName('#__content_rating'))
            ->where('content_id = '.$db->quote($cid));
        $db->setQuery($query);
        try {
            $vote = $db->loadObject();
        } catch (\RuntimeException $e) {
            return  'error';
        }
        $query	= $db->getQuery(true);
        if (!$vote) { // No vote for this article
            $columns = array('content_id', 'rating_sum', 'rating_count', 'lastip');
            $values = array(':content_id', ':rating_sum', ':rating_count', ':lastip');
            $query
                ->insert($db->quoteName('#__content_rating'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));
            $query->bind(':content_id', $cid, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':rating_sum', $extravote->rating_sum, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':rating_count', $extravote->rating_count, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':lastip', $currip, \Joomla\Database\ParameterType::STRING);
            $db->setQuery($query);
            try {
                $result = $db->execute();
            } catch (\RuntimeException $e) {
                return 'error';
            }
        } else { // vote exists in table
            $query
                ->update($db->quoteName('#__content_rating'))
                ->set('rating_sum = :rating_sum')
                ->set('rating_count = :rating_count')
                ->set('lastip = :lastip')
                ->where('content_id = :content_id ');
            $query->bind(':content_id', $cid, \Joomla\Database\ParameterType::INTEGER)
            ->bind(':rating_sum', $extravote->rating_sum, \Joomla\Database\ParameterType::INTEGER)
            ->bind(':rating_count', $extravote->rating_count, \Joomla\Database\ParameterType::INTEGER)
            ->bind(':lastip', $currip, \Joomla\Database\ParameterType::STRING);

            $db->setQuery($query);
            try {
                $result = $db->execute();
            } catch (\RuntimeException $e) {
                return 'error';
            }
        }
        return	'thanks';
    }
    protected function goSync()
    {
        $db  = $this->getDatabase();
        $query	= $db->getQuery(true);
        $q2  	= $db->getQuery(true);
        // in extravote and not in rating
        $q2->select('rating.content_id,rating.rating_sum,rating.rating_count,rating.lastip,extra.content_id as extraid, extra.rating_sum as extrasum ,extra.rating_count as extracount,extra.lastip as extralastip')
            ->from($db->quoteName('#__content_rating').' as rating')
            ->join('RIGHT', $db->quoteName('#__content_extravote').' as extra on rating.content_id = extra.content_id');
        // in rating but not in extravote
        $query->select('rating.content_id,rating.rating_sum,rating.rating_count,rating.lastip,extra.content_id as extraid,extra.rating_sum as extrasum ,extra.rating_count as extracount,extra.lastip as extralastip')
            ->from($db->quoteName('#__content_rating').' as rating')
            ->join('LEFT', $db->quoteName('#__content_extravote').' as extra on rating.content_id = extra.content_id')
            ->union($q2);
        $db->setQuery($query);
        try {
            $tosync = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            return 'error';
        }
        if (!sizeof($tosync)) {
            return 'empty';
        }
        foreach ($tosync as $one) {
            if (!$one->rating_sum) {// does not exist in extravote : create it
                $query	= $db->getQuery(true);
                $columns = array('content_id', 'rating_sum', 'rating_count', 'lastip');
                $values = array(':content_id', ':rating_sum', ':rating_count', ':lastip');
                $query
                    ->insert($db->quoteName('#__content_rating'))
                    ->columns($db->quoteName($columns))
                    ->values(implode(',', $values));
                $query->bind(':content_id', $one->extraid, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':rating_sum', $one->extrasum, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':rating_count', $one->extracount, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':lastip', $one->extralastip, \Joomla\Database\ParameterType::STRING);
                $db->setQuery($query);
                try {
                    $result = $db->execute();
                } catch (\RuntimeException $e) {
                    return 'error';
                }
                continue;
            }
            if (!$one->extrasum) {// does not exist in extravote : create it
                $query	= $db->getQuery(true);
                $columns = array('content_id', 'rating_sum', 'rating_count', 'lastip','extra_id');
                $values = array(':content_id', ':rating_sum', ':rating_count', ':lastip',0);
                $query
                    ->insert($db->quoteName('#__content_extravote'))
                    ->columns($db->quoteName($columns))
                    ->values(implode(',', $values));
                $query->bind(':content_id', $one->content_id, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':rating_sum', $one->rating_sum, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':rating_count', $one->rating_count, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':lastip', $one->lastip, \Joomla\Database\ParameterType::STRING);
                $db->setQuery($query);
                try {
                    $result = $db->execute();
                } catch (\RuntimeException $e) {
                    return 'error';
                }
                continue;
            }
            if ($one->rating_sum > $one->extrasum) {
                $query	= $db->getQuery(true);
                $query
                    ->update($db->quoteName('#__content_extravote'))
                    ->set('rating_sum = :rating_sum ')
                    ->set('rating_count = :rating_count')
                    ->set('lastip = :lastip'. $db->quote($one->lastip))
                    ->where('content_id = :content_id AND extra_id = 0');
                $query->bind(':content_id', $one->content_id, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':rating_sum', $one->rating_sum, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':rating_count', $one->rating_count, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':lastip', $one->lastip, \Joomla\Database\ParameterType::STRING);
                $db->setQuery($query);
                try {
                    $result = $db->execute();
                } catch (\RuntimeException $e) {
                    return 'error';
                }
                continue;
            }
            if ($one->rating_sum < $one->extrasum) {
                $query	= $db->getQuery(true);
                $query
                    ->update($db->quoteName('#__content_rating'))
                    ->set('rating_sum = :rating_sum')
                    ->set('rating_count = :rating_count')
                    ->set('lastip = :lastip')
                    ->where('content_id = :content_id');
                $query->bind(':content_id', $one->content_id, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':rating_sum', $one->extrasum, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':rating_count', $one->extracount, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':lastip', $one->extralastip, \Joomla\Database\ParameterType::STRING);
                $db->setQuery($query);
                try {
                    $result = $db->execute();
                } catch (\RuntimeException $e) {
                    return 'error';
                }
            }
        }
        return 'ok';
    }
}
