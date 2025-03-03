<?php declare(strict_types=1);

namespace XoopsModules\News;

use WideImage\WideImage;
use Xmf\Request;

/**
 * Class Utility
 */
class Utility extends Common\SysUtility
{
    //--------------- Custom module methods -----------------------------
    /**
     * @param string $option
     * @param string $repmodule
     * @return mixed
     */
    public static function getModuleOption(string $option, string $repmodule = 'news')
    {
        global $xoopsModuleConfig, $xoopsModule;
        static $tbloptions = [];
        if (\is_array($tbloptions) && \array_key_exists($option, $tbloptions)) {
            return $tbloptions[$option];
        }

        $retval = false;
        if (isset($xoopsModuleConfig)
            && (\is_object($xoopsModule) && $xoopsModule->getVar('dirname') == $repmodule
                && $xoopsModule->getVar('isactive'))) {
            if (isset($xoopsModuleConfig[$option])) {
                $retval = $xoopsModuleConfig[$option];
            }
        } else {
            /** @var \XoopsModuleHandler $moduleHandler */
            $moduleHandler = \xoops_getHandler('module');
            $module        = $moduleHandler->getByDirname($repmodule);
            /** @var XoopsConfigHandler $configHandler */
            $configHandler = \xoops_getHandler('config');
            if ($module) {
                $moduleConfig = $configHandler->getConfigsByCat(0, $module->getVar('mid'));
                if (isset($moduleConfig[$option])) {
                    $retval = $moduleConfig[$option];
                }
            }
        }
        $tbloptions[$option] = $retval;

        return $retval;
    }

    /**
     * Updates rating data in item table for a given item
     *
     * @param int $storyid
     * @author        Hervé Thouzard (https://www.herve-thouzard.com)
     * @copyright (c) Hervé Thouzard
     */
    public static function updateRating(int $storyid): void
    {
        global $xoopsDB;
        $sql         = 'SELECT rating FROM ' . $xoopsDB->prefix('news_stories_votedata') . ' WHERE storyid = ' . $storyid;
        $result      = self::queryAndCheck($xoopsDB, $sql);
        $votesDB     = $xoopsDB->getRowsNum($result);
        $totalrating = 0;
        while ([$rating] = $xoopsDB->fetchRow($result)) {
            $totalrating += $rating;
        }
        $finalrating = $totalrating / $votesDB;
        $finalrating = \number_format((float)$finalrating, 4);
        $sql         = \sprintf('UPDATE `%s` SET rating = %u, votes = %u WHERE storyid = %u', $xoopsDB->prefix('news_stories'), $finalrating, $votesDB, $storyid);
        $xoopsDB->queryF($sql);
    }

    /**
     * Internal function for permissions
     *
     * Returns a list of all the permitted topics Ids for the current user
     *
     * @param string $permtype
     *
     * @return array    Permitted topics Ids
     *
     * @author        Hervé Thouzard (https://www.herve-thouzard.com)
     * @copyright (c) Hervé Thouzard
     */
    public static function getMyItemIds(string $permtype = 'news_view'): array
    {
        global $xoopsUser;
        static $tblperms = [];
        if (\is_array($tblperms) && \array_key_exists($permtype, $tblperms)) {
            return $tblperms[$permtype];
        }

        /** @var \XoopsModuleHandler $moduleHandler */
        $moduleHandler       = \xoops_getHandler('module');
        $newsModule          = $moduleHandler->getByDirname('news');
        $groups              = \is_object($xoopsUser) ? $xoopsUser->getGroups() : XOOPS_GROUP_ANONYMOUS;
        $grouppermHandler    = \xoops_getHandler('groupperm');
        $topics              = $grouppermHandler->getItemIds($permtype, $groups, $newsModule->getVar('mid'));
        $tblperms[$permtype] = $topics;

        return $topics;
    }

    /**
     * @param $document
     *
     * @return array|string|string[]|null
     */
    public static function html2text($document)
    {
        // PHP Manual:: function preg_replace
        // $document should contain an HTML document.
        // This will remove HTML tags, javascript sections
        // and white space. It will also convert some
        // common HTML entities to their text equivalent.

        $search = [
            "'<script[^>]*?>.*?</script>'si", // Strip out javascript
            "'<img.*?>'si", // Strip out img tags
            "'<[\/\!]*?[^<>]*?>'i", // Strip out HTML tags
            "'([\r\n])[\s]+'", // Strip out white space
            "'&(quot|#34);'i", // Replace HTML entities
            "'&(amp|#38);'i",
            "'&(lt|#60);'i",
            "'&(gt|#62);'i",
            "'&(nbsp|#160);'i",
            "'&(iexcl|#161);'i",
            "'&(cent|#162);'i",
            "'&(pound|#163);'i",
            "'&(copy|#169);'i",
        ]; // evaluate as php

        $replace = [
            '',
            '',
            '',
            '\\1',
            '"',
            '&',
            '<',
            '>',
            ' ',
            \chr(161),
            \chr(162),
            \chr(163),
            \chr(169),
        ];

        $text = \preg_replace($search, $replace, $document);

        \preg_replace_callback(
            '/&#(\d+);/',
            static function ($matches) {
                return \chr($matches[1]);
            },
            $document
        );

        return $text;
    }

    /**
     * Is Xoops 2.3.x ?
     *
     * @return bool need to say it ?
     */
    public static function isX23(): bool
    {
        $x23 = false;
        $xv  = \str_replace('XOOPS ', '', \XOOPS_VERSION);
        if (mb_substr($xv, 2, 1) >= '3') {
            $x23 = true;
        }

        return $x23;
    }

    /**
     * Retrieve an editor according to the module's option "form_options"
     *
     * @param string $caption
     * @param string $name
     * @param string $value
     * @param int $width
     * @param int $height
     * @param string $supplemental
     * @return bool|\XoopsFormEditor|\XoopsFormTextArea
     * @author        Hervé Thouzard (https://www.herve-thouzard.com)
     * @copyright (c) Hervé Thouzard
     */
    public static function getWysiwygForm(string $caption, string $name, string $value = '', int $width = 15, int $height = 60, string $supplemental = '')
    {
        $editor_option            = \mb_strtolower(static::getModuleOption('form_options'));
        $editor                   = false;
        $editor_configs           = [];
        $editor_configs['name']   = $name;
        $editor_configs['value']  = $value;
        $editor_configs['rows']   = 35;
        $editor_configs['cols']   = 60;
        $editor_configs['width']  = (string)$width;
        $editor_configs['height'] = (string)$height;
        $editor_configs['editor'] = $editor_option;

        if (static::isX23()) {
            $editor = new \XoopsFormEditor($caption, $name, $editor_configs);

            return $editor;
        }

        // Only for Xoops 2.0.x
        switch ($editor_option) {
            case 'dhtmltextarea':
            case 'dhtml':
                $editor = new \XoopsFormDhtmlTextArea($caption, $name, $value, 10, 50, $supplemental);
                break;
            case 'textarea':
                $editor = new \XoopsFormTextArea($caption, $name, $value);
                break;
            case 'tinyeditor':
            case 'tinymce':
                if (\is_readable(XOOPS_ROOT_PATH . '/class/xoopseditor/tinyeditor/formtinyeditortextarea.php')) {
                    require_once XOOPS_ROOT_PATH . '/class/xoopseditor/tinyeditor/formtinyeditortextarea.php';
                    $editor = new \XoopsFormTinyeditorTextArea(
                        [
                            'caption' => $caption,
                            'name'    => $name,
                            'value'   => $value,
                            'width'   => '100%',
                            'height'  => '400px',
                        ]
                    );
                }
                break;
        }

        return $editor;
    }

//    /**
//     * @param \Xmf\Module\Helper|null $helper
//     * @param array|null              $options
//     * @return \XoopsFormDhtmlTextArea|\XoopsFormEditor
//     */
//    public static function getEditor(?\Xmf\Module\Helper $helper = null, ?array $options = null)
//    {
//        /** @var Helper $helper */
//        if (null === $options) {
//            $options           = [];
//            $options['name']   = 'Editor';
//            $options['value']  = 'Editor';
//            $options['rows']   = 10;
//            $options['cols']   = '100%';
//            $options['width']  = '100%';
//            $options['height'] = '400px';
//        }
//
//        if (null === $helper) {
//            $helper = Helper::getInstance();
//        }
//
//        $isAdmin = $helper->isUserAdmin();
//
//        if (\class_exists('XoopsFormEditor')) {
//            if ($isAdmin) {
//                $descEditor = new \XoopsFormEditor(\ucfirst((string) $options['name']), $helper->getConfig('editorAdmin'), $options, $nohtml = false, $onfailure = 'textarea');
//            } else {
//                $descEditor = new \XoopsFormEditor(\ucfirst((string) $options['name']), $helper->getConfig('editorUser'), $options, $nohtml = false, $onfailure = 'textarea');
//            }
//        } else {
//            $descEditor = new \XoopsFormDhtmlTextArea(\ucfirst((string) $options['name']), $options['name'], $options['value']);
//        }
//
//        //        $form->addElement($descEditor);
//
//        return $descEditor;
//    }

    /**
     * Internal function
     *
     * @param string $text
     * @return array|string|string[]
     * @copyright (c) Hervé Thouzard
     * @author        Hervé Thouzard (https://www.herve-thouzard.com)
     */
    public static function getDublinQuotes(string $text)
    {
        return \str_replace('"', ' ', $text);
    }

    /**
     * Creates all the metadatas :
     * - For Mozilla/Netscape and Opera the site navigation's bar
     * - The Dublin's Core Metadata
     * - The link for Firefox 2 micro summaries
     * - The meta keywords
     * - The meta description
     *
     * @param object|null $story
     * @author        Hervé Thouzard (https://www.herve-thouzard.com)
     * @copyright (c) Hervé Thouzard
     */
    public static function createMetaDatas($story = null): void
    {
        global $xoopsConfig, $xoTheme, $xoopsTpl;
        $content = '';
        $myts    = \MyTextSanitizer::getInstance();
        //        require_once XOOPS_ROOT_PATH . '/modules/news/class/class.newstopic.php';

        /**
         * Firefox and Opera Navigation's Bar
         */
        if (static::getModuleOption('sitenavbar')) {
            $content .= \sprintf("<link rel=\"Home\" title=\"%s\" href=\"%s/\">\n", $xoopsConfig['sitename'], XOOPS_URL);
            $content .= \sprintf("<link rel=\"Contents\" href=\"%s\">\n", XOOPS_URL . '/modules/news/index.php');
            $content .= \sprintf("<link rel=\"Search\" href=\"%s\">\n", XOOPS_URL . '/search.php');
            $content .= \sprintf("<link rel=\"Glossary\" href=\"%s\">\n", XOOPS_URL . '/modules/news/archive.php');
            $content .= \sprintf("<link rel=\"%s\" href=\"%s\">\n", \htmlspecialchars(_NW_SUBMITNEWS, \ENT_QUOTES | \ENT_HTML5), XOOPS_URL . '/modules/news/submit.php');
            $content .= \sprintf("<link rel=\"alternate\" type=\"application/rss+xml\" title=\"%s\" href=\"%s/\">\n", $xoopsConfig['sitename'], XOOPS_URL . '/backend.php');

            // Create chapters
            require_once XOOPS_ROOT_PATH . '/class/tree.php';
            //            require_once XOOPS_ROOT_PATH . '/modules/news/class/class.newstopic.php';
            $xt         = new NewsTopic();
            $allTopics  = $xt->getAllTopics(static::getModuleOption('restrictindex'));
            $topic_tree = new \XoopsObjectTree($allTopics, 'topic_id', 'topic_pid');
            $topics_arr = $topic_tree->getAllChild(0);
            foreach ($topics_arr as $onetopic) {
                $content .= \sprintf("<link rel=\"Chapter\" title=\"%s\" href=\"%s\">\n", $onetopic->topic_title(), XOOPS_URL . '/modules/news/index.php?storytopic=' . $onetopic->topic_id());
            }
        }

        /**
         * Meta Keywords and Description
         * If you have set this module's option to 'yes' and if the information was entered, then they are rendered in the page else they are computed
         */
        $meta_keywords = '';
        if (isset($story) && \is_object($story)) {
            if ('' !== \xoops_trim($story->keywords())) {
                $meta_keywords = $story->keywords();
            } else {
                $meta_keywords = static::createMetaKeywords($story->hometext() . ' ' . $story->bodytext());
            }
            if ('' !== \xoops_trim($story->description())) {
                $meta_description = \strip_tags($story->description);
            } else {
                $meta_description = \strip_tags($story->title);
            }
            if (isset($xoTheme) && \is_object($xoTheme)) {
                $xoTheme->addMeta('meta', 'keywords', $meta_keywords);
                $xoTheme->addMeta('meta', 'description', $meta_description);
            } elseif (isset($xoopsTpl) && \is_object($xoopsTpl)) { // Compatibility for old Xoops versions
                $xoopsTpl->assign('xoops_meta_keywords', $meta_keywords);
                $xoopsTpl->assign('xoops_meta_description', $meta_description);
            }
        }

        /**
         * Dublin Core's metadatas
         */
        if (static::getModuleOption('dublincore') && isset($story) && \is_object($story)) {
            /** @var XoopsConfigHandler $configHandler */
            $configHandler         = \xoops_getHandler('config');
            $xoopsConfigMetaFooter = $configHandler->getConfigsByCat(\XOOPS_CONF_METAFOOTER);
            $content               .= '<meta name="DC.Title" content="' . static::getDublinQuotes($story->title()) . "\">\n";
            $content               .= '<meta name="DC.Creator" content="' . static::getDublinQuotes($story->uname()) . "\">\n";
            $content               .= '<meta name="DC.Subject" content="' . static::getDublinQuotes($meta_keywords) . "\">\n";
            $content               .= '<meta name="DC.Description" content="' . static::getDublinQuotes($story->title()) . "\">\n";
            $content               .= '<meta name="DC.Publisher" content="' . static::getDublinQuotes($xoopsConfig['sitename']) . "\">\n";
            $content               .= '<meta name="DC.Date.created" scheme="W3CDTF" content="' . \date('Y-m-d', $story->created) . "\">\n";
            $content               .= '<meta name="DC.Date.issued" scheme="W3CDTF" content="' . \date('Y-m-d', $story->published) . "\">\n";
            $content               .= '<meta name="DC.Identifier" content="' . XOOPS_URL . '/modules/news/article.php?storyid=' . $story->storyid() . "\">\n";
            $content               .= '<meta name="DC.Source" content="' . XOOPS_URL . "\">\n";
            $content               .= '<meta name="DC.Language" content="' . \_LANGCODE . "\">\n";
            $content               .= '<meta name="DC.Relation.isReferencedBy" content="' . XOOPS_URL . '/modules/news/index.php?storytopic=' . $story->topicid() . "\">\n";
            if (isset($xoopsConfigMetaFooter['meta_copyright'])) {
                $content .= '<meta name="DC.Rights" content="' . static::getDublinQuotes($xoopsConfigMetaFooter['meta_copyright']) . "\">\n";
            }
        }

        /**
         * Firefox 2 micro summaries
         */
        if (static::getModuleOption('firefox_microsummaries')) {
            $content .= \sprintf("<link rel=\"microsummary\" href=\"%s\">\n", XOOPS_URL . '/modules/news/micro_summary.php');
        }

        if (isset($xoopsTpl) && \is_object($xoopsTpl)) {
            $xoopsTpl->assign('xoops_module_header', $content);
        }
    }

    /**
     * Create the meta keywords based on the content
     *
     * @param string $content
     * @return string
     * @copyright (c) Hervé Thouzard
     * @author        Hervé Thouzard (https://www.herve-thouzard.com)
     */
    public static function createMetaKeywords(string $content): string
    {
        global $cfg;
        require_once XOOPS_ROOT_PATH . '/modules/news/config.php';
        // require_once XOOPS_ROOT_PATH . '/modules/news/class/blacklist.php';
        // require_once XOOPS_ROOT_PATH . '/modules/news/class/registryfile.php';

        if (!$cfg['meta_keywords_auto_generate']) {
            return '';
        }
        $registry = new Registryfile('news_metagen_options.txt');
        //    $tcontent = '';
        $tcontent = $registry->getfile();
        if ('' !== \xoops_trim($tcontent)) {
            [$keywordscount, $keywordsorder] = \explode(',', $tcontent);
        } else {
            $keywordscount = $cfg['meta_keywords_count'];
            $keywordsorder = $cfg['meta_keywords_order'];
        }

        $tmp = [];
        // Search for the "Minimum keyword length"
        if (Request::hasVar('news_keywords_limit', 'SESSION')) {
            $limit = $_SESSION['news_keywords_limit'];
        } else {
            /** @var XoopsConfigHandler $configHandler */
            $configHandler                   = \xoops_getHandler('config');
            $xoopsConfigSearch               = $configHandler->getConfigsByCat(\XOOPS_CONF_SEARCH);
            $limit                           = $xoopsConfigSearch['keyword_min'];
            $_SESSION['news_keywords_limit'] = $limit;
        }
        $myts            = \MyTextSanitizer::getInstance();
        $content         = \str_replace('<br>', ' ', $content);
        $content         = $myts->undoHtmlSpecialChars($content);
        $content         = \strip_tags($content);
        $content         = \mb_strtolower($content);
        $search_pattern  = [
            '&nbsp;',
            "\t",
            "\r\n",
            "\r",
            "\n",
            ',',
            '.',
            "'",
            ';',
            ':',
            ')',
            '(',
            '"',
            '?',
            '!',
            '{',
            '}',
            '[',
            ']',
            '<',
            '>',
            '/',
            '+',
            '-',
            '_',
            '\\',
            '*',
        ];
        $replace_pattern = [
            ' ',
            ' ',
            ' ',
            ' ',
            ' ',
            ' ',
            ' ',
            ' ',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ];
        $content         = \str_replace($search_pattern, $replace_pattern, $content);
        $keywords        = \explode(' ', $content);
        switch ($keywordsorder) {
            case 0: // Ordre d'apparition dans le texte
                $keywords = \array_unique($keywords);
                break;
            case 1: // Ordre de fréquence des mots
                $keywords = \array_count_values($keywords);
                \asort($keywords);
                $keywords = \array_keys($keywords);
                break;
            case 2: // Ordre inverse de la fréquence des mots
                $keywords = \array_count_values($keywords);
                \arsort($keywords);
                $keywords = \array_keys($keywords);
                break;
        }
        // Remove black listed words
        $metablack = new Blacklist();
        $words     = $metablack->getAllKeywords();
        $keywords  = $metablack->remove_blacklisted($keywords);

        foreach ($keywords as $keyword) {
            if (mb_strlen($keyword) >= $limit && !\is_numeric($keyword)) {
                $tmp[] = $keyword;
            }
        }
        $tmp = \array_slice($tmp, 0, $keywordscount);
        if (\count($tmp) > 0) {
            return \implode(',', $tmp);
        }
        if (!isset($configHandler) || !\is_object($configHandler)) {
            /** @var XoopsConfigHandler $configHandler */
            $configHandler = \xoops_getHandler('config');
        }
        $xoopsConfigMetaFooter = $configHandler->getConfigsByCat(\XOOPS_CONF_METAFOOTER);
        return $xoopsConfigMetaFooter['meta_keywords'] ?? '';
    }

    /**
     * Remove module's cache
     *
     * @author        Hervé Thouzard (https://www.herve-thouzard.com)
     * @copyright (c) Hervé Thouzard
     */
    public static function updateCache(): void
    {
        global $xoopsModule;
        $folder  = $xoopsModule->getVar('dirname');
        $tpllist = [];
        require_once XOOPS_ROOT_PATH . '/class/xoopsblock.php';
        require_once XOOPS_ROOT_PATH . '/class/template.php';
        $tplfileHandler = \xoops_getHandler('tplfile');
        $tpllist        = $tplfileHandler->find(null, null, null, $folder);
        $xoopsTpl       = new \XoopsTpl();
        \xoops_template_clear_module_cache($xoopsModule->getVar('mid')); // Clear module's blocks cache

        // Remove cache for each page.
        foreach ($tpllist as $onetemplate) {
            if ('module' === $onetemplate->getVar('tpl_type')) {
                // Note, I've been testing all the other methods (like the one of Smarty) and none of them run, that's why I have used this code
                $files_del = [];
                $files_del = \glob(XOOPS_CACHE_PATH . '/*' . $onetemplate->getVar('tpl_file') . '*', \GLOB_NOSORT);
                if (\count($files_del) > 0) {
                    foreach ($files_del as $one_file) {
                        \unlink($one_file);
                    }
                }
            }
        }
    }

    /**
     * Verify that a mysql table exists
     *
     * @param string $tablename
     * @return bool
     * @copyright (c) Hervé Thouzard
     * @author        Hervé Thouzard (https://www.herve-thouzard.com)
     */
    public static function existTable(string $tablename): bool
    {
        global $xoopsDB;
        $sql = "SHOW TABLES LIKE '$tablename'";
        $result = Utility::queryFAndCheck($xoopsDB, $sql);

        return ($xoopsDB->getRowsNum($result) > 0);
    }

    /**
     * Verify that a field exists inside a mysql table
     *
     * @param string $fieldname
     * @param string $table
     * @return bool
     * @author        Hervé Thouzard (https://www.herve-thouzard.com)
     * @copyright (c) Hervé Thouzard
     */
    public static function existField(string $fieldname, string $table): bool
    {
        global $xoopsDB;
        $sql = "SHOW COLUMNS FROM   $table LIKE '$fieldname'";
        $result = Utility::queryFAndCheck($xoopsDB, $sql);

        return ($xoopsDB->getRowsNum($result) > 0);
    }

    /**
     * Add a field to a mysql table
     *
     * @param string $field
     * @param string $table
     * @return bool|\mysqli_result
     * @author        Hervé Thouzard (https://www.herve-thouzard.com)
     * @copyright (c) Hervé Thouzard
     */
    public static function addField(string $field, string $table)
    {
        global $xoopsDB;
        $result = $xoopsDB->queryF('ALTER TABLE ' . $table . " ADD $field;");

        return $result;
    }

    /**
     * Verify that the current user is a member of the Admin group
     */
    public static function isAdminGroup(): bool
    {
        global $xoopsUser, $xoopsModule;
        if (\is_object($xoopsUser)) {
            if (\in_array('1', $xoopsUser->getGroups(), true)) {
                return true;
            }
            if ($xoopsUser->isAdmin($xoopsModule->mid())) {
                return true;
            }

            return false;
        }

        return false;
    }

    /**
     * Verify if the current "user" is a bot or not
     *
     * If you have a problem with this function, insert the folowing code just before the line if (\Xmf\Request::hasVar('news_cache_bot', 'SESSION'))) { :
     * return false;
     *
     * @author        Hervé Thouzard (https://www.herve-thouzard.com)
     * @copyright (c) Hervé Thouzard
     */
    public static function isBot()
    {
        if (Request::hasVar('news_cache_bot', 'SESSION')) {
            return $_SESSION['news_cache_bot'];
        }
        // Add here every bot you know separated by a pipe | (not matter with the upper or lower cases)
        // If you want to see the result for yourself, add your navigator's user agent at the end (mozilla for example)
        $botlist      = 'AbachoBOT|Arachnoidea|ASPSeek|Atomz|cosmos|crawl25-public.alexa.com|CrawlerBoy Pinpoint.com|Crawler|DeepIndex|EchO!|exabot|Excalibur Internet Spider|FAST-WebCrawler|Fluffy the spider|GAIS Robot/1.0B2|GaisLab data gatherer|Google|Googlebot-Image|googlebot|Gulliver|ia_archiver|Infoseek|Links2Go|Lycos_Spider_(modspider)|Lycos_Spider_(T-Rex)|MantraAgent|Mata Hari|Mercator|MicrosoftPrototypeCrawler|Mozilla@somewhere.com|MSNBOT|NEC Research Agent|NetMechanic|Nokia-WAPToolkit|nttdirectory_robot|Openfind|Oracle Ultra Search|PicoSearch|Pompos|Scooter|Slider_Search_v1-de|Slurp|Slurp.so|SlySearch|Spider|Spinne|SurferF3|Surfnomore Spider|suzuran|teomaagent1|TurnitinBot|Ultraseek|VoilaBot|vspider|W3C_Validator|Web Link Validator|WebTrends|WebZIP|whatUseek_winona|WISEbot|Xenu Link Sleuth|ZyBorg';
        $botlist      = \mb_strtoupper($botlist);
        $currentagent = \mb_strtoupper(\xoops_getenv('HTTP_USER_AGENT'));
        $retval       = false;
        $botarray     = \explode('|', $botlist);
        foreach ($botarray as $onebot) {
            if (false !== mb_strpos($currentagent, $onebot)) {
                $retval = true;
                break;
            }
        }

        $_SESSION['news_cache_bot'] = $retval;

        return $retval;
    }

    /**
     * Create an infotip
     *
     * @param string $text
     * @return string|null
     * @copyright (c) Hervé Thouzard
     * @author        Hervé Thouzard (https://www.herve-thouzard.com)
     */
    public static function makeInfotips(string $text): ?string
    {
        $infotips = static::getModuleOption('infotips');
        if ($infotips > 0) {
            $myts = \MyTextSanitizer::getInstance();

            return \htmlspecialchars(\xoops_substr(\strip_tags($text), 0, $infotips), \ENT_QUOTES | \ENT_HTML5);
        }

        return null;
    }

    /**
     * @param string $string
     * @return string
     * @author   Monte Ohrt <monte at ohrt dot com>, modified by Amos Robinson
     *           <amos dot robinson at gmail dot com>
     */
    public static function closeTags(string $string): string
    {
        // match opened tags
        if (\preg_match_all('/<([a-z\:\-]+)[^\/]>/', $string, $start_tags)) {
            $start_tags = $start_tags[1];
            // match closed tags
            if (\preg_match_all('/<\/([a-z]+)>/', $string, $end_tags)) {
                $complete_tags = [];
                $end_tags      = $end_tags[1];

                foreach ($start_tags as $key => $val) {
                    $posb = \array_search($val, $end_tags, true);
                    if (\is_int($posb)) {
                        unset($end_tags[$posb]);
                    } else {
                        $complete_tags[] = $val;
                    }
                }
            } else {
                $complete_tags = $start_tags;
            }

            $complete_tags = \array_reverse($complete_tags);
            foreach ($complete_tags as $iValue) {
                $string .= '</' . $iValue . '>';
            }
        }

        return $string;
    }

    /**
     * Smarty truncate_tagsafe modifier plugin
     *
     * Type:     modifier<br>
     * Name:     truncate_tagsafe<br>
     * Purpose:  Truncate a string to a certain length if necessary,
     *           optionally splitting in the middle of a word, and
     *           appending the $etc string or inserting $etc into the middle.
     *           Makes sure no tags are left half-open or half-closed
     *           (e.g. "Banana in a <a...")
     *
     * @param string $string
     * @param int    $length
     * @param string $etc
     * @param bool   $break_words
     *
     * @return string
     * @author   Monte Ohrt <monte at ohrt dot com>, modified by Amos Robinson
     *           <amos dot robinson at gmail dot com>
     */
    public static function truncateTagSafe(string $string, int $length = 80, string $etc = '...', bool $break_words = false): string
    {
        if (0 == $length) {
            return '';
        }
        if (mb_strlen($string) > $length) {
            $length -= \mb_strlen($etc);
            if (!$break_words) {
                $string = \preg_replace('/\s+?(\S+)?$/', '', mb_substr($string, 0, $length + 1));
                $string = \preg_replace('/<[^>]*$/', '', $string);
                $string = static::closeTags($string);
            }

            return $string . $etc;
        }

        return $string;
    }

    /**
     * Resize a Picture to some given dimensions (using the wideImage library)
     *
     * @param string $src_path      Picture's source
     * @param string $dst_path      Picture's destination
     * @param int    $param_width   Maximum picture's width
     * @param int    $param_height  Maximum picture's height
     * @param bool   $keep_original Do we have to keep the original picture ?
     * @param string $fit           Resize mode (see the wideImage library for more information)
     *
     * @return bool
     */
    public static function resizePicture(
        string $src_path,
        string $dst_path,
        int    $param_width,
        int    $param_height,
        bool   $keep_original = false,
        string $fit = 'inside'
    ): bool {
        //    require_once XOOPS_PATH . '/vendor/wideimage/WideImage.php';
        $resize            = true;
        $pictureDimensions = \getimagesize($src_path);
        if (\is_array($pictureDimensions)) {
            $pictureWidth  = $pictureDimensions[0];
            $pictureHeight = $pictureDimensions[1];
            if ($pictureWidth < $param_width && $pictureHeight < $param_height) {
                $resize = false;
            }
        }

        $img = WideImage::load($src_path);
        if ($resize) {
            $result = $img->resize($param_width, $param_height, $fit);
            $result->saveToFile($dst_path);
        } else {
            @\copy($src_path, $dst_path);
        }
        if (!$keep_original) {
            @\unlink($src_path);
        }

        return true;
    }
}
