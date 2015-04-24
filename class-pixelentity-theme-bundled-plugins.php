<?php
if (!class_exists("PixelentityThemeBundledPlugins")) {
	class PixelentityThemeBundledPlugins {
			
		static $instance;
		protected $plugins;
		protected $redirect = true;
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
			
			$this->messages = (object)
				array(
					  "not-installed" => __("Install","pe-theme-bundled-plugins"),
					  "installed" => __("Activate","pe-theme-bundled-plugins"),
					  "active" => __('<i class="dashicons dashicons-yes"></i>',"pe-theme-bundled-plugins"),
					  "update" => __("Update","pe-theme-bundled-plugins"),
					  "installing" => __("Installing","pe-theme-bundled-plugins"),
					  "updating" => __("Updating","pe-theme-bundled-plugins"),
					  "activating" => __("Activating","pe-theme-bundled-plugins"),
					  );

			
			add_action('admin_init',array(&$this,'admin_init'));

		}

		public function ajax_pe_theme_bundled_plugins_table() {
			$this->output();
			die();
		}
		
		public function pre_current_active_plugins() {
			$this->output();
		}

		public function admin_init() {

			$this->redirect = !isset($_REQUEST['pe-theme-bundle-no-redirect']);
			
			add_filter('plugins_api',array(&$this,"plugins_api_filter"),10,3);
			add_filter('install_plugin_complete_actions',array(&$this,"install_plugin_complete_actions_filter"),10,3);
			add_filter('update_plugin_complete_actions',array(&$this,"update_plugin_complete_actions_filter"),10,2);
			add_action('activate_plugin',array(&$this,'activate_plugin'));
			add_action('wp_ajax_pe_theme_bundled_plugins_table',array(&$this,'ajax_pe_theme_bundled_plugins_table'));
			add_filter("pre_set_site_transient_update_plugins", array(&$this,"check"));
			
			if (isset($_REQUEST['pe-theme-bundle-list'])) {
				add_action('pre_current_active_plugins',array(&$this,'pre_current_active_plugins'));
			}

			$status = $this->status();

			if (!function_exists('_maybe_update_plugins')) {
				return;
			}

			$update = false;

			foreach ($status as $key => $plugin) {
				if ($plugin->status === 'update') {
					$update[$plugin->file] = $plugin->version;
				}
			}

			if ($update) {
				_maybe_update_plugins();
				$current = get_site_transient( 'update_plugins' );
				foreach ($update as $name=>$version) {
					$current->checked[$name] = $version;
				}
				// we force our filter here
				set_site_transient( 'update_plugins', $current );
			}
		}

		public function activate_plugin($plugin) {
			if (isset($_REQUEST["pe-theme-bundle-redirect"]) && isset($this->installed[$plugin])) {
				add_filter("wp_redirect",array(&$this,'wp_redirect_filter'));
			}
		}

		public function wp_redirect_filter($redirect) {
			if ($this->redirect) {
				return $_REQUEST["pe-theme-bundle-redirect"] == "1" ? $_SERVER["HTTP_REFERER"] : urldecode($_REQUEST["pe-theme-bundle-redirect"]);
			} else {
				return $redirect."&pe-theme-bundle-list";
			}
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

		public function back_action_link() {
			$url = $_SERVER["HTTP_REFERER"];
			return
				sprintf(
					'<a id="pe-theme-bundled-plugins-redirect" href="%s" target="_parent">%s</a>',
					$url,
					__('Return to Theme Options',"pe-theme-bundled-plugins")
					);
		}

		public function activate_action_link($file) {
			$ref = urlencode($_SERVER["HTTP_REFERER"]);
			$ufile = urlencode($file);
			$url = wp_nonce_url(self_admin_url("plugins.php?action=activate&pe-theme-bundle-redirect=$ref&plugin=$ufile"), "activate-plugin_$file");
			return
				sprintf(
						'<a id="pe-theme-bundled-plugins-redirect" href="%s" target="_parent">%s</a>',
						$url,
						__('Activate Plugin')
						);
		}

		public function auto_redirect_link($delay = 0) {
			$script = <<<EOT
<script type="text/javascript">
	setTimeout(
		function () {
			window.location.href=jQuery("#pe-theme-bundled-plugins-redirect").attr("href");
		}
	,$delay*1000)
</script>
EOT;
			return $script;
		}


		public function update_plugin_complete_actions_filter($actions,$file) {
			if (isset($_REQUEST["pe-theme-bundle-redirect"]) && isset($this->installed[$file])) {
				if (!$this->redirect) {
					$this->output();
					return $actions;
				}
				if (isset($actions['activate_plugin'])) {
					// plugin is updated but not active
					$custom['pe_activate'] = $this->activate_action_link($file);
				} else {
					// plugin is updated and active
					$custom['pe_back'] = $this->back_action_link();
				}
				$actions = $custom;
				$actions['auto'] = $this->auto_redirect_link(2);
			}
			return $actions;
		}

		public function install_plugin_complete_actions_filter($actions,$api,$file) {
			if (isset($_REQUEST["pe-theme-bundle-redirect"]) && isset($this->plugins[$api->slug])) {
				if (!$this->redirect) {
					$this->output();
					return $actions;
				}
				if (is_plugin_active($file)) {
					$custom['pe_back'] = $this->back_action_link();
				} else {
					$custom['pe_activate'] = $this->activate_action_link($file);
				}
				$actions = $custom;
				$actions['auto'] = $this->auto_redirect_link();
			}
			return $actions;
		}

		public function status($invalidate = false) {
			if ($invalidate) {
				wp_cache_delete('plugins','plugins');
			} else if ($this->_status) {
				return $this->_status;
			}
			
			$info = get_plugins();

			$installed = array_flip(wp_list_pluck($info, 'PluginURI'));
			
			foreach ($this->plugins as $plugin) {
				$slug = $plugin->slug;
				$stat = new StdClass();
				$stat->link = false;
				$stat->name = $plugin->name;
				$stat->version = $plugin->version;
				if (isset($installed[$slug])) {
					$file = $installed[$slug];
					$this->installed[$file] = true;
					$stat->file = $file;
					$stat->version = $info[$file]["Version"];

					if (version_compare($info[$file]["Version"], $plugin->version, '<')) {
						// update
						$stat->status = "update";
						$stat->action = $this->messages->updating;
						$stat->link = wp_nonce_url(self_admin_url("update.php?action=upgrade-plugin&pe-theme-bundle-redirect=1&plugin=$file"), "upgrade-plugin_$file");
					} else if (is_plugin_active($file)) {
						// active
						$stat->status = "active";
						$stat->action = '';
					} else {
						// installed
						$stat->status = "installed";
						$stat->action = $this->messages->activating;
						$stat->link = wp_nonce_url(self_admin_url("plugins.php?action=activate&pe-theme-bundle-redirect=1&plugin=$file"), "activate-plugin_$file");
					}
				} else {
					// not installed
					$stat->status = "not-installed";
					$stat->action = $this->messages->installing;
					$stat->link = wp_nonce_url(self_admin_url("update.php?action=install-plugin&pe-theme-bundle-redirect=1&plugin=$slug"), "install-plugin_$slug");
				}
				$res[$slug] = $stat;
			}

			// cache result
			$this->_status = $res;
						
			return $res;
		}

		public function options($invalidate = false) {
			$plugins = $this->status($invalidate);
			$html = '<table class="pe-theme-bundled-plugins" id="pe-theme-bundled-plugins">';
			foreach ($plugins as $slug => $stat) {
				$status = $stat->status;
				$message = $this->messages->$status;
				$html .= sprintf(
								 '<tr class="pe-theme-plugin-%s" data-status="%s" data-action="%s"><td>%s</td><td class="pe-theme-plugin-column-action">%s</td></tr>',
								 $status,
								 $status,
								 $stat->action,
								 $this->plugins[$slug]->name,
								 $stat->link ? sprintf(
													   '<a data-name="%s" data-version="%s" data-action="%s" href="%s">%s</a>',
													   $stat->name,
													   $stat->version,
													   $stat->action,
													   $stat->link,
													   $message
													   ) : $message 
								 );
			}
			$html .= '</table>';
			return $html;
		}

		public static function init($plugins) {
			self::$instance = new PixelentityThemeBundledPlugins($plugins);
			return self::$instance;
		}

		public function output() {
			printf('<div style="display:none">%s</div>',$this->options(true));
		}


	}
}
?>
