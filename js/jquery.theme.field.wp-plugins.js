(function ($) {
	/*jshint undef: false, browser: true, devel: false, eqeqeq: false, bitwise: false, white: false, plusplus: false, regexp: false, nomen: false */ 
	/*global jQuery,setTimeout,location,setInterval,YT,clearInterval,clearTimeout,pixelentity,tb_show,tb_remove,ajaxurl */
	
	$.pixelentity = $.pixelentity || {version: '1.0.0'};
	
	$.pixelentity.peFieldWPPlugins = {	
		conf: {
			api: false
		},
		ready: false,
		callback: false,
		install: function () {
		}
	};
	
	function PeFieldWPPlugins(target, conf) {
		
		var id = target.attr("id");
		var button = $("#%0".format(id));
		var messages = $("#%0_messages".format(id));
		var nonce = button.attr("data-nonce");
		var try_ajax = true;
		var table = false;
		var action = false;
		
		function log(which,message) {
			which = messages.show().find("> div").hide().filter("#%0_%1".format(id,which));
			which.show();
			if (message) {
				which.find(".pe-log").html(message);
			}
		}
		
		function noop(e) {
			e.preventDefault();
			e.stopImmediatePropagation();
			return false;
		}
		
		function get_table() {
			try_ajax = false;
			jQuery.post(
				ajaxurl,
				{ action: 'pe_theme_bundled_plugins_table' },
				success
			);
		}
		
		function get_next_action() {
			var result = table.find("a[data-action]:eq(0)");
			if (result.length === 0) {
				return false;
			}
			return {
				url: result.attr("href"),
				text: "<em>%0</em>%1 %2".format(result.attr('data-action'),result.attr('data-name'),result.attr('data-version'))
			};
		}
		
		function error() {
			table.off('click','a',noop);
			log("warning",action.text);
		}
		
		function next_action() {
			var next = get_next_action();
			if (next) {
				if (next.url != action.url) {
					action = next;
					perform(action);
				} else {
					error();
				}
			} else {
				alldone();
			}
		}
		
		function set_table_from(data) {
			var new_table = $(data).find("#pe-theme-bundled-plugins");
			if (new_table.length === 0) {
				return false;
			}
			table.replaceWith(new_table);
			table = new_table;
			disablelinks();
			try_ajax = true;
			return true;
		}

		
		function success(data) {
			if (data) {
				if (!set_table_from(data)) {
					// page didn't include table markup, try to fetch via ajax call
					if (try_ajax) {
						get_table();
					} else {
						error();
					}
					return;
				}
				next_action();
			} else {
				error();
			}
		}
		
		function alldone() {
			$.pixelentity.peFieldWPPlugins.ready = true;
			button.hide();
			log('done');
			if ($.pixelentity.peFieldWPPlugins.callback) {
				$.pixelentity.peFieldWPPlugins.callback();
			}
		}
		
		function perform(action) {
			disablelinks();
			log("saving",action.text);
			$.ajax({
				url: action.url+"&pe-theme-bundle-no-redirect",
				success: success
			});
		}

		function install() {
			button.hide();
			perform(action);
			return false;
		}
		
		function disablelinks() {
			table.on('click','a',noop);
		}

		// init function
		function start() {
			table = $("#pe-theme-bundled-plugins");
			action = get_next_action();
			if (action) {
				button.click(install);
			} else {
				alldone();
			}
			$.pixelentity.peFieldWPPlugins.install = install;
		}
		
		$.extend(this, {
			// plublic API
			destroy: function() {
				target.data("peFieldWPPlugins", null);
				target = undefined;
			}
		});
		
		// initialize
		$(start);
	}
	
	// jQuery plugin implementation
	$.fn.peFieldWPPlugins = function(conf) {
		
		// return existing instance	
		var api = this.data("peFieldWPPlugins");
		
		if (api) { 
			return api; 
		}
		
		conf = $.extend(true, {}, $.pixelentity.peFieldWPPlugins.conf, conf);
		
		// install the plugin for each entry in jQuery object
		this.each(function() {
			var el = $(this);
			api = new PeFieldWPPlugins(el, conf);
			el.data("peFieldWPPlugins", api); 
		});
		
		return conf.api ? api: this;		 
	};
	
}(jQuery));