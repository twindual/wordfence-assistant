<?php
namespace Wordfence;

if (!defined('WFWAF_LOG_PATH')) {
	define('WFWAF_LOG_PATH', content_url() . '/wflogs/');
}

require_once 'WfaWAFAutoPrependUninstaller.php';

use Wordfence\WfaWAFAutoPrependUninstaller;

class WordfenceAssistant
{	
	public static function admin_init()
	{
		if (!self::isAdmin()) { return; }
		add_action('wp_ajax_wordfenceAssistant_do', 'Wordfence\WordfenceAssistant::ajax_do_callback');
		wp_enqueue_script('wordfenceAstjs', self::getBaseURL() . 'js/admin.js', array('jquery'), WORDFENCE_VERSION);
		wp_enqueue_style('wordfenceast-main-style', self::getBaseURL() . 'css/main.css', '', WORDFENCE_VERSION);
		wp_localize_script('wordfenceAstjs', 'WordfenceAstVars', array(
			'ajaxURL' => admin_url('admin-ajax.php'),
			'firstNonce' => wp_create_nonce('wp-ajax')
			));
	}

	public static function admin_menus()
	{
		if(!self::isAdmin()) { return; }
		$icon = plugins_url() . '/wordfence-assistant/images/wordfence-logo-16x16.png';
		add_submenu_page("WFAssistant", "WF Assistant", "WF Assistant", "activate_plugins", "WFAssistant", 'Wordfence\WordfenceAssistant::mainMenu');
		add_menu_page('WF Assistant', 'WF Assistant', 'activate_plugins', 'WFAssistant', 'Wordfence\WordfenceAssistant::mainMenu', self::getBaseURL() . 'images/wordfence-logo-16x16.png'); 
	}

	public static function ajax_do_callback()
	{
		$response = '';
		if (!self::isAdmin()) {
			$response = json_encode(array('errorMsg' => "You appear to have logged out or you are not an admin. Please sign-out and sign-in again."));
		} else {
			$func = $_POST['func'];
			$nonce = $_POST['nonce'];
			if (!wp_verify_nonce($nonce, 'wp-ajax')) { 
				$response = json_encode(array('errorMsg' => "Your browser sent an invalid security token to Wordfence. Please try reloading this page or signing out and in again."));
			} else {
				if ($func == 'deleteAll') {
					$response = self::deleteAll();
				} elseif ($func == 'clearLocks') {
					$response = self::clearLocks();
				} elseif ($func == 'disableFirewall') {
					$response = self::disableFirewall();
				} elseif ($func == 'clearLiveTraffic') {
					$response = self::clearLiveTraffic();
				} else {
					$response = json_encode(array('errorMsg' => "An invalid operation was requested."));
				}
			}
		}

		exit($response);
	}

	public static function clearLiveTraffic()
	{
		global $wpdb;
		$wpdb->query("truncate table " . $wpdb->base_prefix . "wfHits");
		$wpdb->query("delete from " . $wpdb->base_prefix . "wfHits");
		return json_encode(array('msg' => "All Wordfence live traffic data deleted."));
	}

	public static function clearLocks()
	{
		global $wpdb;
		$tables = array('wfBlocks', 'wfBlocksAdv', 'wfLockedOut', 'wfScanners', 'wfLeechers');
		foreach ($tables as $t) {
			$wpdb->query("truncate table " . $wpdb->base_prefix . "$t"); //Some users don't have truncate permission but if they do the next query will return immediatelly. 
			$wpdb->query("delete from " . $wpdb->base_prefix . "$t");
		}
		return json_encode(array('msg' => "All locked IPs, locked out users and advanced blocks cleared."));
	}

	public static function disableFirewall()
	{
		self::_disableFirewall();
		return json_encode(array('msg' => "Wordfence firewall has been disabled."));
	}

	private static function _disableFirewall()
	{
		global $wpdb;

		//Old Firewall
		$wpdb->query("update " . $wpdb->base_prefix . "wfConfig set val=0 where name='firewallEnabled'");

		//WAF
		$filesToRemove = array(WFWAF_LOG_PATH . 'attack-data.php', WFWAF_LOG_PATH . 'ips.php', WFWAF_LOG_PATH . 'config.php', WFWAF_LOG_PATH . 'wafRules.rules', WFWAF_LOG_PATH . 'rules.php', WFWAF_LOG_PATH . '.htaccess');
		foreach($filesToRemove as $path) {
			@unlink($path);
		}
		@rmdir(WFWAF_LOG_PATH);

		$wafUninstaller = new WfaWAFAutoPrependUninstaller();
		$wafUninstaller->uninstall();
	}

	public static function deleteAll()
	{
		$response = '';
		if (defined('WORDFENCE_VERSION')) {
			$response = json_encode(array('errorMsg' => "Please deactivate the Wordfence plugin before you delete all its data."));
		} else {
			global $wpdb;
			self::_disableFirewall();
			$tables = array('wfBadLeechers', 'wfBlocks', 'wfBlocksAdv', 'wfConfig', 'wfCrawlers', 'wfFileMods', 'wfHits', 'wfHoover', 'wfIssues', 'wfLeechers', 'wfLockedOut', 'wfLocs', 'wfLogins', 'wfNet404s', 'wfReverseCache', 'wfScanners', 'wfStatus', 'wfThrottleLog', 'wfVulnScanners');
			foreach ($tables as $t) {
				$wpdb->query("drop table " . $wpdb->base_prefix . "$t");
			}
			update_option('wordfenceActivated', 0);
			wp_clear_scheduled_hook('wordfence_daily_cron');
			wp_clear_scheduled_hook('wordfence_hourly_cron');
			//Remove old legacy cron job if it exists
			wp_clear_scheduled_hook('wordfence_scheduled_scan');
			wp_clear_scheduled_hook('wordfence_start_scheduled_scan'); //Unschedule legacy scans without args
			//Any additional scans will fail and won't be rescheduled. 
			foreach (array('wordfence_version', 'wordfenceActivated') as $opt) {
				delete_option($opt);
			}

			$response = json_encode(array('msg' => "All Wordfence tables and data removed."));
		}

		return $response;
	}

	public static function getBaseURL()
	{
		return plugins_url() . '/wordfence-assistant/';
	}

	public static function install_actions()
	{
		if (is_admin()) {
			add_action('admin_init', 'Wordfence\WordfenceAssistant::admin_init');
			if (is_multisite()) {
				if (self::isAdminPageMU()) {
					add_action('network_admin_menu', 'Wordfence\WordfenceAssistant::admin_menus');
				} //else don't show menu
			} else {
				add_action('admin_menu', 'Wordfence\WordfenceAssistant::admin_menus');
			}

		}
	}

	public static function installPlugin() { }

	public static function isAdmin()
	{
		if (is_multisite()) {
			if(current_user_can('manage_network')) {
				return true;
			}
		} else {
			if (current_user_can('manage_options')) {
				return true;
			}
		}
		return false;
	}

	public static function isAdminPageMU()
	{
		if (preg_match('/^[\/a-zA-Z0-9\-\_\s\+\~\!\^\.]*\/wp-admin\/network\//', $_SERVER['REQUEST_URI'])) { 
			return true; 
		}
		return false;
	}

	public static function mainMenu()
	{
		require self::getBaseUrl() . 'lib/mainMenu.php';
	}

	public static function uninstallPlugin() { }
}
