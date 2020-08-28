<?php
/**
 * Widget
 *
 * This plugin uses your modular templates as reusable widget
 *
 * Licensed under MIT, see LICENSE.
 */

namespace Grav\Plugin;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Common\Page\Types;
use Grav\Common\Plugin;
use Grav\Common\Page\Page;
use Grav\Common\Twig\Twig;
use Grav\Common\Uri;
use Grav\Common\Utils;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class WidgetsPlugin
 * Implement reusable widget for pages
 *
 * @package Grav\Plugin
 */
class WidgetPlugin extends Plugin
{
    public $features = [
        'blueprints' => 1000,
    ];
    protected $version;
    private $_widget;
    const KEY = 'widget';
    const MAX_RECURSE = 2;
    static $definedAreas;
    static $parent;

    const EXTRA_ENABLED_WIDGET_ONLY = 1;
    const EXTRA_ENABLED_ALL_PAGES = 2;

    /**
     * Return a list of subscribed events.
     *
     * @return array    The list of events of the plugin of the form
     *                      'name' => ['method_name', priority].
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => [['setup', 100000], ['onPluginsInitialized', 0]],
            'onTwigExtensions' => ['onTwigExtensions', 1000],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onGetPageBlueprints' => ['onGetPageBlueprints', 0],
            'onGetPageTemplates' => ['onGetPageTemplates', 0],
        ];
    }

    /**
     * Initialize the Widget plugin configuration and set the admin
     * configs .
     */
    public function setup()
    {
        /**
         * Check if the widgets dir is available
         */

        /* @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        $page_dir = $locator->findResource('user://pages', true);
        $wig_dir = $page_dir . self::rootDirectory();
        if (!is_dir($wig_dir))
            mkdir($wig_dir);

        /**
         * Check for the Modal and dynamically add it to Admin
         */
        if ($this->config->get('plugins.widget.enable_modal')) {
            // get other user defined modals and
            $admin_modals = $this->config->get('plugins.admin.add_modals', []);
            $modal = $this->config()['add_modals'];
            $this->config->set('plugins.admin.add_modals', array_merge($admin_modals, $modal));
        }

        // Autoloader
//        spl_autoload_register(function ($class) {
//            if (Utils::startsWith($class, 'Grav\Plugin\Widgets')) {
//                require_once __DIR__ .'/classes/' . strtolower(basename(str_replace("\\", "/", $class))) . '.php';
//            }
//        });
    }

    /**
     * Initialize configuration.
     */
    public function onPluginsInitialized()
    {

        $this->enable([
//            'onBlueprintCreated'   => ['onBlueprintCreated', 0],
            'onPageContentRaw' => ['onPageContentRaw', 0],
        ]);
    }

    public function onGetPageBlueprints(Event $event)
    {
        /* @var Types $types */
        $types = $event->types;
        /* @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        $types->scanBlueprints($locator->findResource('plugin://' . $this->name . '/blueprints'));
    }

    public function onGetPageTemplates(Event $event)
    {
        /* @var Types $types */
        $types = $event->types;
//        $types->scanTemplates('plugin://widgets/templates/');
        /* @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        $types->scanTemplates($locator->findResource('plugin://' . $this->name . '/templates'));
    }

    /**
     * Add the Widgets Twig Extensions
     */
    public function onTwigExtensions()
    {
        require_once(__DIR__ . '/twig/WidgetTwigExtension.php');

        /* @var Twig $twig */
        $twig = $this->grav['twig'];
        $twig->twig->addExtension(new WidgetTwigExtension());

    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Set needed variables for twig.
     */
    public function onTwigSiteVariables()
    {

    }

    /**
     * Get available page types.
     *
     * @return array
     */
    public static function widgetTypes()
    {
        static $list = [];

        /* skip if the list has been generated before */
        if (!$list) {
            $widget_root = self::rootDirectory();

            //TODO: fetch display style from blueprint
            $display = 'title_path';

            /**
             * Get pages
             * e.g Pages::parentsRawRoutes(),Pages::parents(),Pages::types(),
             * Pages::getHomeRoute(),Pages::pageTypes(),Pages::getTypes()
             */
            $types = Pages::parentsRawRoutes();
            foreach ($types as $name => $title) {
                if (strpos($name, "{$widget_root}/") !== 0) {
                    continue;
                }

                if ($display == 'title')
                    $list[$name] = $title;
                elseif ($display == 'path')
                    $list[$name] = trim(ucfirst(strtr(basename($name), '_', ' ')));
                else
                    $list[$name] = $title . ' (' . basename($name) . ')';

            }
            ksort($list);
        }

        return $list;
    }


    /**
     * Get available page types.
     *
     * @return array
     */
    public static function widgetLocations()
    {
        $types = Pages::getTypes();

        return $types->modularSelect();
    }

    static function definedWidgetAreas()
    {
        if (!self::$definedAreas) {
            /* @var Config $config */
            $config = Grav::instance()['config'];
            $appendAreaName = $config->get('plugins.widget.append_area_name');

            if ($da = $config->get('theme.widget.areas'))
                foreach ($da as $item) {
                    $location = ucwords(strtr($item, '-', ' '));
                    self::$definedAreas[$item] = $appendAreaName ? $location . " ($item)" : $location;

                }
        }
        return self::$definedAreas;
    }

    static function featureEnabled($config_field, $type)
    {
        /* @var Config $config */
        $config = Grav::instance()['config'];

        /**
         * Disable showing widgets within widget modular pages
         */
        if ($config_field == 'widget_tab') {
            $wiw = $config->get('plugins.widget.widgets_in_widget');

            if ($wiw)
                return $type;

            return self::isWidget() ? 'ignore' : $type;

        } elseif ($config_field == 'extras') {
            $enabled = $config->get("plugins.widget.extras");
            $ret = 'ignore';

            if ($enabled == self::EXTRA_ENABLED_ALL_PAGES)
                $ret = $type;
            elseif ($enabled == self::EXTRA_ENABLED_WIDGET_ONLY && self::isWidget()) {
                $ret = $type;
            }
            return $ret;
        } else
            $ret = ($enabled = $config->get("plugins.widget.{$config_field}")) && !self::isWidget() ? $type : 'ignore';
        return $ret;
    }

    static function folderPattern()
    {
        return self::isWidget() ? '[_][a-zA-Zа-яA-Я0-9_\-]+' : '[a-zA-Zа-яA-Я0-9_\-]+';
    }

    static function isWidget()
    {
        /* @var Uri $uri */
        $uri = Grav::instance()['uri'];
        $widget_root = Grav::instance()['config']->get('plugins.widget.parent');

        /**
         * Is current page a widget?
         * e.g /admin/pages/widget_root/_widget_name
         *
         */
        return isset($uri->paths()[2]) && ($uri->paths(2) == $widget_root);
    }

    static function rootDirectory()
    {
        if (!self::$parent) {
            $widget_root = Grav::instance()['config']->get('plugins.widget.parent');
            // e.g /widgets
            self::$parent = '/' . $widget_root;
        }
        return self::$parent;
    }

    static function locationHelp()
    {
        if (self::$definedAreas)
            return 'Select a widget area';
        else
            return 'Please define widget areas in your theme and they will show up';
    }

    /**
     * Process widget for page
     * @param $context
     * @param $area
     * @param $merge_global bool
     * @return string
     */
    public static function process($context, $area, $merge_global)
    {
        /**
         * This variable is meant to prevent indefinite recursive widgets;
         * in case if widgets in widget is enabled
         *  maximum allow nest 2;
         */
        static $rc_level = 0;

        /* @var Page $page */
        $page = $context['page'];

        static $processed_widgets = null;
        $content = '';
        $locations = [];
        $sortWidgetLocations = function ($widgets) {
            $sorted_locations = [];
            if ($widgets) {

                if (isset($widgets['areas'])) { //prevent invalid argument for foreach
                    foreach ($widgets['areas'] as $idx => $node) {
                        $key = $node['location'];

                        /* Merge multiple area definition into one */
                        if (isset($node['widgets']))
                            $sorted_locations[$key] = isset($sorted_locations[$key]) ? array_merge($sorted_locations[$key], $node['widgets']) : $node['widgets'];
                    }
                }


            }

            return $sorted_locations;
        };

        if (null === $processed_widgets) {

            /* @var Config $config */
            $config = $context['config'];
            $global_widgets = $config->get('site.widget', false);
            $subpage_inherit = $config->get('plugins.widget.subpage_inherit', false);

            /* Get the page widgets with subpage inheritance is enabled */
            $widgets = self::getPageWidgets($page, $subpage_inherit);

            if ($merge_global && $global_widgets)
                $widgets = array_merge_recursive($widgets, (array)$global_widgets);

            $processed_widgets = $sortWidgetLocations($widgets);
        }

        /* Load widgets for the current widget since we are in a recursion */
        if($rc_level > 0)
            $locations = $sortWidgetLocations(self::getPageWidgets($page,false));

        /* Load the full page widgets for the current page */
        else
            $locations = $processed_widgets;

        if ($locations && $rc_level++ <= self::MAX_RECURSE) {

            /* Fix empty call with widget('') instead of widget() */
            $area = empty($area) ? 'default' : $area;

            if (array_key_exists($area, $locations)) {

                foreach ($locations[$area] as $idx => $widget) {
                    $page_path = $widget['load'];
                    $enabled = (boolean) $widget['enabled'];

                    if ($enabled) {
                        $inject = $page->find($page_path);
                        if ($inject) {

                            /**
                             * Sometimes twig changes don't get picked up immediately you can force this
                             * by temporarily disabling cache for the page
                             */
//                                $inject->modifyHeader('cache_enable',false);
//                                $inject->modifyHeader('never_cache_twig',true);
                            $content .= $inject->content();

                        }
                    }
                }
            }
        }

        $rc_level--;

        return $content;
    }

    /**
     * Grab the page widgets from current page and it's parents if available
     * @param Page $page
     * @param bool $inherit
     * @return array
     */
    public static function getPageWidgets(Page $page, bool $inherit = false)
    {

        $widgetFromHeader = function (Page $page) {
            $headers = $page->header();
            return property_exists($headers, self::KEY) ? (array)$headers->{self::KEY} : [];
        };

        $widgets = $widgetFromHeader($page);

        if (!$inherit)
            return $widgets;

        /* @var Page $parentPage */
        $parentPage = $page->parent();

        while (true) {
            $currentParent = $parentPage;

            if ($currentParent == null || $currentParent->header() == null)
                break;

            $widgets = array_merge_recursive($widgets, $widgetFromHeader($currentParent));

            if ($currentParent->parent() !== null) {
                $parentPage = $currentParent->parent();
            } else {
                break;
            }
        }

        return $widgets;
    }

    /**
     * Grab the raw page content to process defined widget shortcodes
     */
    public function onPageContentRaw(Event $event)
    {
        /** @var Page $page */
        $page = $event['page'];

        $config = $this->mergeConfig($page);

        $twig = $this->grav['twig'];

        if ($config->get('enabled') && $config->get('enable_shortcode')) {
            // Get raw content and substitute all formulas by a unique token
            $raw = $page->getRawContent();
            $function = function ($matches) use (&$page) {
                $search = $matches[0];
                $params = $this->parseShortCodeParams($matches[1]);
                $page_path = $params['load'];
                $inject = $page->find($page_path);
                if ($inject)
                    $content = $inject->content();
                else
                    $content = $search;  # replace with orignal

                return $content;
            };

            // set the parsed content back into as raw content for further processing
            $page->setRawContent($this->processWidgetInContent($raw, $function));
        }
    }

    /**
     * This enables us to par
     */
    protected function processWidgetInContent($content, $function)
    {
        $regex = '/\[widget (.+?)\/?]/i';
        return preg_replace_callback($regex, $function, $content);
    }

    /**
     * Parse shortcode params
     * Available params are
     * 1. load
     */
    public function parseShortCodeParams($attrs)
    {
        $paramRegex = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)/';
        // parse out the arguments
        preg_match_all($paramRegex, $attrs, $matches, PREG_SET_ORDER);
        $params = array();
        foreach ($matches as $v) {
            if (!empty($v[1]))
                $params[strtolower($v[1])] = trim($v[2]);
            elseif (!empty($v[3]))
                $params[strtolower($v[3])] = trim($v[4]);
        }
        return $params;
    }

}
