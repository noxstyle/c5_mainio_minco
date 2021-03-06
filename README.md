# Mainio MinCo for concrete5 CMS #
Mainio MinCo is a concrete5 add-on built by Mainio Tech Ltd. <www.mainiotech.fi>

The original author of this add-on is:
Antti Hukkanen <antti.hukkanen@mainiotech.fi>

The add-on's name stands for Minify and Combine static resources on your site.
This add-on is meant for developers/theme developers for easy minification 
and combination of CSS files in concrete5 themes. Minifying and combining
the static assets usually improves the site performance and can be used
to optimize the concrete5 site you're working on.

Note: If you don't have any cache library (e.g. apc/memcached) installed and
in use with your concrete5 installation, this add-on will probably lower the
performance of your site rather than giving you any extra performance boost.

For minifying the source files this add-on uses the PHP-based Minify library:
https://github.com/mrclay/minify

# Usage #
To install this package to concrete5, please download the desired branch as zip file
and extract that to your site's /packages folder. After that, remember to rename
the folder to "mainio_minco" after which it can be installed through your dashboard.

After installing this, you can apply the css/js combining and minifying pretty easily
in your themes. The only thing you need to do is to wrap the lines for which you want 
to apply the combining and minification into two helper functions as follows:

```php
<?php MincoBlock::start('layout_resources', 1) ?>
<script type="text/javascript" src="<?php echo View::getInstance()->getThemePath() ?>/js/cufon-yui.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo $this->getStyleSheet('style/reset.css') ?>" />
<link rel="stylesheet" type="text/css" href="<?php echo $this->getStyleSheet('style/mystyles.css') ?>" />
<link rel="stylesheet" type="text/css" href="<?php echo $this->getStyleSheet('style/block_overrides.css') ?>" />
<script type="text/javascript" src="<?php echo View::getInstance()->getThemePath() ?>/js/my_awesome_unminified_script.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo $this->getStyleSheet('typography.css') ?>" />
<?php MincoBlock::end() ?>
```

This would result for example in the following (or similar) output in your html:

```php
<script type="text/javascript" src="/index.php/tools/packages/mainio_minco/min?k=0caf9ce48d3ad9bcac4026d7a4d4b7d7"></script>
<link rel="stylesheet" type="text/css" href="/index.php/tools/packages/mainio_minco/min?k=613d6d8d13122913c2c73d89778511c1" />
```

And these addresses provide your site users a combined and minified version of all the css/js
files specified between the minco block.

The two arguments passed to the minco block starting function are optional but suggested to be used.
The arguments are meant for cache-related functionality and refer to the following items:

* First argument (in the example 'layout_resources'): Unique cache ID for this block
  * Used to save/get this minified block contents from server-side cache
* Second argument (in the example 1): The file version
  * Should be changed if you want to clear out browser cache for combined files in this block
  * This is also appended to the server-side cache ID
  
## Save combined assets to static files ##
If you want to save the combined and minified files into static assets you can do so with 
the Mainio MinCo add-on. However, this is not suggested because e.g. if you want to combine
the header_required assets, they might differ on each page. In these situations, you should
let the Mainio MinCo produce your assets by the default tools url call.

For example in the example presented above, there is no risk of having different files on
different pages loaded inside the Mainio MinCo block, so in these kinds of situations you
might want to save the combined and minified file into static assets files. Before doing
this, you should make sure:

1. That your server process is allowed to write into the template files where you've
   included the Minco::start() and Minco::end() functions
2. You should also have both of these function calls in their own lines without anything
   else on those lines as shown in the example above

After you've made sure both of these requirements are fulfilled, you can let Mainio MinCo
to combine, minify and save the files into static assets files during the next request.
To do this, you need to modify the block ending function to this:

```php
<?php MincoBlock::end(true) ?>
```

With this applied into your theme you will end up with your original Mainio MinCo block
wrapped in a PHP statement that will never be true and above that you will have the
minified and combined content. For instance, the example above might look something
like this after requesting the page for the first time with MincoBlock::end(true)
in place:

```php
<script type="text/javascript" src="/files/tmp/mainio_minco_combined_assets/0caf9ce48d3ad9bcac4026d7a4d4b7d7.js"></script>
<link rel="stylesheet" type="text/css" href="/files/tmp/mainio_minco_combined_assets/613d6d8d13122913c2c73d89778511c1.css" />
<?php if(false): MincoBlock::start('layout_resources', 1) ?>
<script type="text/javascript" src="<?php echo View::getInstance()->getThemePath() ?>/js/cufon-yui.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo $this->getStyleSheet('style/reset.css') ?>" />
<link rel="stylesheet" type="text/css" href="<?php echo $this->getStyleSheet('style/mystyles.css') ?>" />
<link rel="stylesheet" type="text/css" href="<?php echo $this->getStyleSheet('style/block_overrides.css') ?>" />
<script type="text/javascript" src="<?php echo View::getInstance()->getThemePath() ?>/js/my_awesome_unminified_script.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo $this->getStyleSheet('typography.css') ?>" />
<?php MincoBlock::end(true); endif; ?>
```

When you want to re-compile the assets, just remove these parts before and after the minco block:

```php
// From the start of the Mainio MinCo block, remove this:
if(false):
// From the end of the Mainio MinCo block, remove this:
endif;
```

When recompiling, also please remember to increment the file version number to avoid loading the
combined parts from cache.

## Applying the Mainio MinCo block ##
You can apply the block starting and ending functions to any place in your theme. However,
it is not suggested to wrap the whole contents of your theme into a single minco block 
because this will probably break your side especially if it originally has many css/js 
files.

A single minco block inside the start() and end() functions can contain any number of 
javascript or css files that are located on this specific site or alternatively on 
some external location. The files in external locations are not included in the 
combined css/js files and are left un-touched.

The order of the files resources from the original HTML are the following:

1. Everything that wasn't found to be a css/js file loaded from this site, excluding IE conditional statements
2. Inline JavaScript that contains concrete-specific global variables starting with var CCM_...
3. CDN JS resources (if any found)
4. All the JS files loaded from your site combined into a single file
5. Inline JavaScript that doesn't contain concrete-specific global variables, e.g. $(document).ready();
6. Inline CSS specified inside the block
7. CDN CSS resources (if any found)
8. All the CSS files loaded from your site combined into a single file
9. IE conditional statements

## Passing Resources from Controllers ##
You can minify and combine your resources directly from your controller by calling
Minco::combineResources() routine. It takes in an array of js/css files loaded
via Concrete5_Helper_Html (or _V2) and returns a style or a script tag similarly to
MincoBlock functionality.

Options:
* $resources Array - an array holding your css/js resources
* $cacheID String - same as the first parameter on MincoBlock::start(), Unique cache ID for the resource set
* $fileVersion Int - same as the second parameter on MinicoBlock::start(), file version number
* $writeFiles Bool - a trigger whether files should be saved or not, same as on MinicoBlock::end()

Usage:

```php
$html = Loader::helper('html');
$m = Minco::getInstance();

$cr = $m->combineResources(array(
	$html->javascript('myscript1.js', 'pkghandle'),
    $html->javascript('myscript2.js', 'pkghandle'),
), 'my_page_specific_scripts');

$this->addFooterItem($cr);

$cr = $m->combineResources(array(
    $html->css('mycss1.css', 'pkghandle'),
    $html->css('mycss2.css', 'pkghandle'),
    $html->css('mycss3.min.css'),
), 'my_page_specific_css', 1);

$this->addHeaderItem($cr);
```

## CDN resources ##
By default, if the Mainio MinCo finds any occurences of js/css files that are defined
to be found from an external CDN location, they are replaced with their specified CDN
location to speed up the site loading for the users.


## Options ##
The add-on is designed to be highly configurable and currently these configurations can be changed
in your config/site.php by defining them to PHP constants:

```php
define('CONF_NAME', 'conf_value');
```

The boolean configurations are the following (true/false):

* MINCO_USE_CDN_RESOURCES: determines whether to use CDN resources, defaults to true
* MINCO_BYPASS_CACHE: Bypasses all server-side cache for all minco related cachable resources, defaults to false
* MINCO_CLIENT_CACHE: Determines whether to tell the client browser to cache the resources, defaults to true
* MINCO_MINIFY_HTML: Determines whether the output HTML is wanted to be minified, defaults to false
* MINCO_MINIFY_INLINE: Determines whether the inline js/css in minco blocks is wanted to be minified, defaults to true
* MINICO_REPLACE_CSS_IMG_PATHS: Changes all css url() paths to relative paths to your C5 root, enable this if you're having problems with images

