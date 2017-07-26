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

    /**
     * Return a list of subscribed events.
     *
     * @return array    The list of events of the plugin of the form
     *                      'name' => ['method_name', priority].
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => [['setup', 100000],['onPluginsInitialized', 0]],
            'onTwigExtensions'           => ['onTwigExtensions', 1000],
            'onTwigTemplatePaths'   => ['onTwigTemplatePaths', 0],
            'onGetPageBlueprints'   => ['onGetPageBlueprints', 0],
            'onGetPageTemplates'   => ['onGetPageTemplates', 0],
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
        $page_dir = $locator->findResource('user://pages',true);
        $widget_root = Grav::instance()['config']->get('plugins.widget.parent');
        $wig_dir = $page_dir.'/'.$widget_root;
        if(!is_dir($wig_dir))
            mkdir($wig_dir);

        /**
         * Check for the Modal and dynamically add it to Admin
         */
        if($this->config->get('plugins.widget.enable_modal')){
            // get other user defined modals and
            $admin_modals = $this->config->get('plugins.admin.add_modals',[]);
            $modal = $this->config()['add_modals'];
            $this->config->set('plugins.admin.add_modals',array_merge($admin_modals,$modal));
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
        ]);
    }

    public function onGetPageBlueprints(Event $event)
    {
        /* @var Types $types */
        $types  = $event->types;
        /* @var UniformResourceLocator $locator*/
        $locator = Grav::instance()['locator'];
        $types->scanBlueprints($locator->findResource('plugin://' . $this->name . '/blueprints'));
    }

    public function onGetPageTemplates(Event $event)
    {
        /* @var Types $types */
        $types  = $event->types;
//        $types->scanTemplates('plugin://widgets/templates/');
        /* @var UniformResourceLocator $locator*/
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
        if(!$list){
            $config = Grav::instance()['config'];
            $widget_root = $config->get('plugins.widget.parent');

            //TODO: fetch display style from blueprint
            $display  = 'title_path';

            /**
             * Get pages
             * e.g Pages::parentsRawRoutes(),Pages::parents(),Pages::types(),
             * Pages::getHomeRoute(),Pages::pageTypes(),Pages::getTypes()
            */
            $types = Pages::parentsRawRoutes();
            foreach ($types as $name => $title) {
                if (strpos($name, "/{$widget_root}/") !== 0) {
                    continue;
                }

                if($display == 'title')
                    $list[$name] = $title;
                elseif ($display == 'path')
                    $list[$name] = trim(ucfirst(strtr(basename($name), '_', ' ')));
                else
                    $list[$name] = $title.' ('.basename($name).')';

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

    static function definedWidgetAreas(){
        if(!self::$definedAreas){
            /* @var Config $config */
            $config = Grav::instance()['config'];

            if($da = $config->get('theme.widget.areas'))
                foreach ($da as $item) {
                    self::$definedAreas[$item] = ucwords(strtr($item,'-',' '))." ($item)";

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
        if($config_field == 'widget_tab'){
            $wiw = $config->get('plugins.widget.widgets_in_widget');

            if($wiw)
                return $type;

            /* @var Uri $uri */
            $uri = Grav::instance()['uri'];
            $widget_root = $config->get('plugins.widget.parent');

            /**
             * Is current page a widget?
             * e.g /admin/pages/widget_root/_widget_name
             *
            */
            $is_widget = ($uri->paths(2) == $widget_root);
            return $is_widget?'ignore':$type;

        }
        else
            $ret = ($enabled = $config->get("plugins.widget.{$config_field}")) ? $type : 'hidden';
        return $ret;
    }

    static function locationHelp(){
        if(self::$definedAreas)
            return 'Select a widget area';
        else
            return 'Please define widget areas in your theme and they  will show up';
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
        static $rc_level=0;

        /* @var Page $page */
        $page = $context['page'];

        /* @var Config $config */
        $config = $context['config'];
        $global_widgets  = $config->get('site.widget',false);

        $headers = $page->header();
        $has_widget = array_key_exists(self::KEY,$headers);
        $content = '';
        $has_widget = $merge_global ? ($global_widgets || $has_widget): $has_widget;

        if($has_widget & $rc_level++ <= self::MAX_RECURSE){
            $wigs = (array) $headers->{self::KEY};
            if($merge_global && $global_widgets)
                $wigs = $global_widgets ? array_merge_recursive($global_widgets,$wigs) : $wigs;

            if($wigs){
                $locs =[];

                /* Fix empty call with widget('') instead of widget() */
                $area = empty($area)?'default':$area;
                if(isset($wigs['areas'])) { //prevent invalid argument for foreach
                    foreach ($wigs['areas'] as $idx => $node) {
                        $key = $node['location'];

                        /* Merge multiple area definition into one */
                        if (isset($node['widgets']))
                            $locs[$key] = isset($locs[$key]) ? array_merge($locs[$key], $node['widgets']) : $node['widgets'];
                    }
                }

                if(array_key_exists($area,$locs)){

                    foreach ($locs[$area] as $idx => $widget) {
                        $page_path = $widget['load'];
                        $enabled = $widget['enabled'];

                        if($enabled){
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
        }
        $rc_level--;

        return $content;
    }

}
