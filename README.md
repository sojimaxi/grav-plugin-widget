# Grav Widget Plugin

A `Widget` plugin for the [Grav][grav] flat-file CMS. It was made out of love for widgets and their flexibilities to reuse web components for your project. The widget works by using your modular templates as a widget.

# Installation

Installing the Widget plugin can be done in one of two ways.  GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

## GPM Installation (Preferred but not currently available, pending integration)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm) through your system's Terminal (also called the command line).  From the root of your Grav install type:

    bin/gpm install widget

This will install the Widget plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/widget`.


## Manual Installation 

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `widget`. You can find these files on [GitHub](https://github.com/sojimaxi/grav-plugin-widget).

You should now have all the plugin files under

    /your/site/grav/user/plugins/widget

# Config Defaults
This are the default configurations.

```yaml
enabled: true
parent: widgets
enable_twig: true
enable_modal: true
add_modals:
  -
    label: 'Create Widget'
    blueprint: admin/pages/widget_new
    show_in: bar
extras: true
bg_image_source: 'theme@:/images'
widgets_in_widget: false

```

If you need to change any value, the best process is to use the admin dashboard. Go to your `Dashboard -> Plugins -> Widget` to configure your desired settings. This will override the default settings.

### Theme Configs

For widget area location lookup, your `theme_name.yaml` should contain widget areas field. You can create as many or as little fields as you like and they will be populated in the Location select field from the widget tab:

```yaml
widget: 
  areas: [top,bottom,content-top,content-bottom] #example fields
```

All modular templates are used as widget template since they are initially sub-modules of an entire page.

To create a new template just create them in your theme's ` templates/modular ` directory

    /your/site/grav/user/theme/theme_name/templates/modular/template_name.html.twig

Widget show up by default in standard pages but to enable it show in your custom page create a new ` template_name.yaml`
in your theme's `blueprints` directory that extends the `default` blueprint. Read more on how to create blueprints in Grav's docs

```yaml
# Example Blueprint

# file: template_name.yaml


title: TemplateName
'@extends': default

#... other configs
```

### Create a widget from dashboard
Simply click on the ` Create Widget ` button on the top-bar on `Pages` filling the `Widget Title` , 
select a `Widget Template`, click `continue` make changes and finally click `Save` button to create and save to disk.

![](https://i.imgur.com/WXkrEF2.jpg)
![](https://i.imgur.com/lL3wlxr.jpg)

All new widgets are created in the default widgets folder configurable from the widgets plugin.

### Adding Widgets to Page 
Here is a screeshot of how to add widgets to a page

![](http://i.imgur.com/d5athK0.gif)

### Frontmatter Configs

No frontmatter configuration is necessary as all frontmatter will be auto-generated by the admin plugin

```yaml
# example generated page header 
widget:
    areas:
        -
            location: default
            widgets:
                - { load: /widgets/_counter, enabled: true }
                - { load: /widgets/_feature-testimonial, enabled: false }
        -
            location: top
            widgets:
                - { load: /widgets/_feature-slider, enabled: true } 
        -
            location: footer
            widgets:
                - { load: /widgets/_email-subscription, enabled: true }
```

# Example Usage

**Twig**

To use widget in your page template, call the ` widget() ` twig function to load widgets defined in the `default` location

```twig
{{ widget() }}
```
    
or use ` widget(location) ` to use a named widget area.

```twig
{{ widget('top') }}
```

### Grav Fixes in Development

Your changes in your modular twig templates may be auto re-cached if working from the admin dashboard.
If they don't get re-cached when reloading the browser simply clear the cache to force re-caching all templates and all is good.

```bash
$ bin/grav clear-cache

```
# Credit
Thanks goes to Team Grav for creating such an awesome system and also got to learn more from their [Page-Inject Plugin](https://github.com/getgrav/grav-plugin-page-inject) on how content injection works.

[grav]: http://github.com/getgrav/grav

