Copy the library in your theme folder, then in functions.php

require_once("pixelentity-theme-bundled-plugins/class-pixelentity-theme-bundled-plugins.php");
PixelentityThemeBundledPlugins::init(
									 array(
										   array(
												 "slug" => "pe-theme-framework",
												 "name" => __pe("Pixelentity Theme Framework Plugin"),
												 "version" => "1.1.0",
												 "download_link" => get_template_directory_uri()."/plugins/pe-theme-framework.zip"
												 ),
										   array(
												 "slug" => "pe-theme-framework2",
												 "name" => __pe("Pixelentity Theme Framework Plugin 2"),
												 "version" => "1.1.0",
												 "download_link" => get_template_directory_uri()."/plugins/pe-theme-framework2.zip"
												 ),
										   array(
												 "slug" => "pe-theme-framework3",
												 "name" => __pe("Pixelentity Theme Framework Plugin 3"),
												 "version" => "1.1.0",
												 "download_link" => get_template_directory_uri()."/plugins/pe-theme-framework3.zip"
												 )
										   )
									 );

To display table of plugins, Somewhere in your theme options page, use:

echo PixelentityThemeBundledPlugins::$instance->options();

Since plugin zips files are hosted inside theme folder, "PluginURI" in plugin header must match the "slug" used upon inited, example:

<?php
/*
  Plugin Name: Pixelentity Framework Theme Plugin
  Plugin URI: pe-theme-framework
  Description: Provides advanced features to theme based on the Pixelentity Theme Framework
  Version: 1.1.0
  Author: pixelentity
  Author URI: http://pixelentity.com
  License: GPL2
*/
?>
