<?php
if (!class_exists("PixelentityThemeBundledPlugins")) {
	class PixelentityThemeBundledPlugins {
			
		static $instance;
		protected $plugins;
		protected $installed = array();
		protected $_status;
		public $messages;

		public function __construct($plugins) {
			// to debug
			//set_site_transient('update_plugins',null);

			foreach ($plugins as $plugin) {
				$slug = empty($plugin["slug"]) ? md5($plugin["name"].$plugin["download_link"]) : $plugin["slug"];
				$this->plugins[$slug] = (object) $plugin;
			}
			
			$this->messages = 
				array(
					  "not-installed" => __("Install","pe-theme-bundled-plugins"),
					  "installed" => __("Activate","pe-theme-bundled-plugins"),
					  "active" => __("Active","pe-theme-bundled-plugins")
					  );

			add_action('admin_init',array(&$this,'admin_init'));
			add_filter('plugins_api',array(&$this,"plugins_api_filter"),10,3);
			add_filter('install_plugin_complete_actions',array(&$this,"install_plugin_complete_actions_filter"),10,3);
			add_action('activate_plugin',array(&$this,'activate_plugin'));
			add_filter("pre_set_site_transient_update_plugins", array(&$this,"check"));

		}

		public function admin_init() {
			$this->status();
		}

		public function activate_plugin($plugin) {
			if (isset($_REQUEST["peredirect"]) && isset($this->installed[$plugin])) {
				add_filter("wp_redirect",array(&$this,'wp_redirect_filter'));
			}
		}

		public function wp_redirect_filter($redirect) {
			return $_REQUEST["peredirect"] == "1" ? $_SERVER["HTTP_REFERER"] : urldecode($_REQUEST["peredirect"]);
		}


		public function plugins_api_filter($value,$action,$args) {
			if ($action === "plugin_information" && isset($this->plugins[$args->slug])) {
				$value = $this->plugins[$args->slug];
			}
			return $value;
		}

		public function check($updates) {
			
			if (!isset($updates->checked)) return $updates;

			$plugins = $this->status();

			foreach ($plugins as $slug => $current) {
				$updated = $this->plugins[$slug];
				if ($current->status === "not-installed") continue;
				if (version_compare($current->version, $updated->version, '<')) {
					// bingo!! found update.
					$update = array(
									"url" => home_url(),
									"slug" => $slug,
									"new_version" => $updated->version,
									"package" => $updated->download_link
									);

					$updates->response[$current->file] = (object) $update;

				}
			}
			return $updates;
		}


		public function install_plugin_complete_actions_filter($actions,$api,$plugin_file) {
			if (isset($_REQUEST["peredirect"]) && isset($this->plugins[$api->slug])) {
				$ref=urlencode($_SERVER["HTTP_REFERER"]);
				$actions["activate_plugin"] = str_replace("plugins.php?","plugins.php?peredirect=$ref&",$actions["activate_plugin"]);
				unset($actions["plugins_page"]);
			}
			return $actions;
		}

		public function status() {
			if ($this->_status) {
				return $this->_status;
			}
			$info = get_plugins();

			$installed = array_flip(wp_list_pluck($info, 'PluginURI'));
			foreach ($this->plugins as $plugin) {
				$slug = $plugin->slug;
				$stat["link"] = false;
				if (isset($installed[$slug])) {
					$file = $installed[$slug];
					$this->installed[$file] = true;
					$stat["file"] = $file;
					$stat["version"] = $info[$file]["Version"];
					if (is_plugin_active($file)) {
						// active
						$stat["status"] = "active";
					} else {
						// installed
						$stat["status"] = "installed";
						$stat["link"] = wp_nonce_url(self_admin_url("plugins.php?action=activate&peredirect=1&plugin=$file"), "activate-plugin_$file");
					}
				} else {
					// not installed
					$stat["status"] = "not-installed";
					$stat["link"] = wp_nonce_url(self_admin_url("update.php?action=install-plugin&peredirect=1&plugin=$slug"), "install-plugin_$slug");
				}
				$res[$slug] = (object) $stat;
			}
			// cache result
			$this->_status = $res;
			return $res;
		}

		public function options() {
			$plugins = $this->status();
			$html = '<table class="pe-theme-bundled-plugins">';
			foreach ($plugins as $slug => $stat) {
				$status = $stat->status;
				$message = $this->messages[$status];
				$html .= sprintf(
								 '<tr class="pe-theme-plugin-%s"><td>%s</td><td>%s</td></tr>',
								 $status,
								 $this->plugins[$slug]->name,
								 $stat->link ? sprintf('<a href="%s">%s</a>',$stat->link,$message) : $message 
								 );
			}
			$html .= '</table>';
			return $html;
		}

		
		public static function init($plugins) {
			self::$instance = new PixelentityThemeBundledPlugins($plugins);
			return self::$instance;
		}

	}
}
?>