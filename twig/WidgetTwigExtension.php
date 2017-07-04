<?php
namespace Grav\Plugin;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Page\Page;
use Grav\Common\Uri;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Parser;

class WidgetTwigExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface
{

    /**
     * Returns extension name.
     *
     * @return string
     */
    public function getName()
    {
        return 'WidgetsTwigExtension';
    }

    public function getFunctions()
    {
        return [
            /** Add the widgets function to call custom widgets from twig */
            new \Twig_SimpleFunction('widget', [$this, 'loadWidget'], ['needs_context' => true]),
        ];
    }

    /**
     * Load the called widget
     * @param $context
     * @param string $area
     * @param bool $merge_global combine with site-wide widgets
     * @return string
     */
    public function loadWidget($context, $area='default', $merge_global = true)
    {
        $widgets = WidgetPlugin::process($context,$area,$merge_global);
        return !empty($widgets)?$widgets:'';
    }

}
