<?php
namespace Wordfence;
//
// Requires WordPress v4.3 to use return code from (wp_register_script(), wp_enqueue_script())
//
if (!defined('WFWAF_LOG_PATH')) {
//	define('WFWAF_LOG_PATH', content_url() . '/wflogs/');
	define('WFWAF_LOG_PATH', WP_CONTENT_DIR . '/wflogs/');
}

require_once 'WfaWAFAutoPrependUninstaller.php';

use Wordfence\WfaWAFAutoPrependUninstaller;

class WordfenceAssistant
{
	// AJAX setttings.
	const ACTION = 'wordfenceAssistant_do';	// Action hook used by the class.
	const ADMIN_AJAX_URL = 'admin-ajax.php';
	const HANDLE_AJAX = 'wp-ajax-wfa';		// 
	const NONCE = 'wp-nonce-wfa';			// Action argument used by the nonce validating the AJAX request.

	// ADMIN MENU settings
	const ADMIN_PAGE_TITLE = 'Wordfence Assistant Rebooted';
	const ADMIN_MENU_CAPABILITY = 'activate_plugins';
	const ADMIN_MENU_IMAGE = 'wordfence-logo-16x16.png';
	const ADMIN_MENU_SLUG = 'menuWfaRebooted';
	const ADMIN_MENU_TITLE = 'WFA Rebooted';
	const ADMIN_SUBMENU_CLEAR_LIVE_TRAFFIC_SLUG = 'menuWfaRebootedDeleteLiveTraffic';
	const ADMIN_SUBMENU_CLEAR_LIVE_TRAFFIC_TITLE = 'Clear Live Traffic Data';
	const ADMIN_SUBMENU_CLEAR_LOCKS_SLUG = 'menuWfaRebootedClearLocks';
	const ADMIN_SUBMENU_CLEAR_LOCKS_TITLE = 'Clear Locks';
	const ADMIN_SUBMENU_DELETE_ALL_SLUG = 'menuWfaRebootedDeleteAll';
	const ADMIN_SUBMENU_DELETE_ALL_TITLE = 'Delete All Data and Tables';
	const ADMIN_SUBMENU_DISABLE_FIREWALL_SLUG = 'menuWfaRebootedDisableFirewall';
	const ADMIN_SUBMENU_DISABLE_FIREWALL_TITLE = 'Disable Firewall';

	// Status messages that should be translated.
	const MSG_CLEAR_LIVE_TRAFFIC = "All Wordfence live traffic data has been deleted.";
	const MSG_CLEAR_LOCKS = "All locked IPs, locked out users and advanced blocks have been cleared.";
	const MSG_DEACTIVATE_WORDFENCE_FIRST = "Please deactivate the Wordfence plugin before you delete all of its data.";
	const MSG_DELETED_ALL_DATA = "All Wordfence tables and data have been removed.";
	const MSG_FIREWALL_DISABLED = "Wordfence firewall has been disabled.";
	const MSG_INVALID_ACTION = "An invalid operation was requested.";
	const MSG_INVALID_TOKEN = "Your browser sent an invalid security token to Wordfence. Please try reloading this page or signing out and in again.";
	const MSG_NOT_ADMIN = "You appear to have logged out or you are not an admin. Please sign-out and sign-in again.";

	// Class specific values.
	const PLUGIN_FOLDER = '/wordfence-assistant-rebooted/';
	const PREFIX = 'wfa-rebooted';	// Unique Prefix used to register WordPress file handles.
	const VERSION = '0.0.1';
	
	// Initialize class variables.
	protected $filesToRemove = array(WFWAF_LOG_PATH . 'attack-data.php', WFWAF_LOG_PATH . 'ips.php', WFWAF_LOG_PATH . 'config.php', WFWAF_LOG_PATH . 'wafRules.rules', WFWAF_LOG_PATH . 'rules.php', WFWAF_LOG_PATH . '.htaccess');
	protected $options = array('wordfence_version', 'wordfenceActivated');
	protected $tablesAll = array('wfBadLeechers', 'wfBlocks', 'wfBlocksAdv', 'wfConfig', 'wfCrawlers', 'wfFileMods', 'wfHits', 'wfHoover', 'wfIssues', 'wfLeechers', 'wfLockedOut', 'wfLocs', 'wfLogins', 'wfNet404s', 'wfReverseCache', 'wfScanners', 'wfStatus', 'wfThrottleLog', 'wfVulnScanners');
	protected $tablesLiveTraffic = array('wfHits');
	protected $tablesLock = array('wfBlocks', 'wfBlocksAdv', 'wfLockedOut', 'wfScanners', 'wfLeechers');
	protected $tasks = array('wordfence_daily_cron', 'wordfence_hourly_cron', 'wordfence_scheduled_scan', 'wordfence_start_scheduled_scan');

    /**
     * Class Constructor.
	 *
     * @since 0.0.1
	 */
    function __construct() {
		add_action('init', array($this, 'admin_init'));
    }

    /**
     * Register and enqueue stylesheets.
	 *
     * @since 0.0.1
	 */
	public function enqueuePluginStyles()
	{
		if ($this->isAdmin()) {
			// Load only on our Plugin's page.
			if ($this->isAdminPage(self::ADMIN_MENU_SLUG)) {
				// Register and Enqueue the CSS.
				$cssStyleHandle = self::PREFIX . '-main-style';
				$cssStyleUrl = $this->getBaseUrl() . 'css/main.css';
				$cssStyleDependencies = array();	// WordPress Default. No dependencies.
				$cssStyleMediaType = 'all';			// WordPress Default. The media types for which this stylesheet has been defined.
				wp_register_style($cssStyleHandle, $cssStyleUrl, $cssStyleDependencies, self::VERSION, $cssStyleMediaType);
				wp_enqueue_style($cssStyleHandle);
			}
		}
	}

    /**
     * Register and enqueue scripts.
 	 *
     * @since 0.0.1
	 */
	public function enqueuePluginScripts()
	{
		if ($this->isAdmin()) {
			// Load only on our Plugin's page.
			if ($this->isAdminPage(self::ADMIN_MENU_SLUG)) {
				// Step 1 - Register a Handle to a Javascript file with WordPress.
				$jsScriptHandle = self::PREFIX . '-main-script';
				$jsScriptUrl = $this->getBaseUrl() . 'js/admin.js';
				$jsScriptDependencies = array('jquery');
				$loadInFooter = true;	// Load this script at the bottom of the page just before the </body> tag.
				wp_register_script($jsScriptHandle, $jsScriptUrl, $jsScriptDependencies, self::VERSION, $loadInFooter);
				
				// Step 2 - Use the Handle from (Step 1) to directly pass in a Javascript object by name and value.
				$jsObjectName  = 'wp_ajax_data';	// Use this name to referece the JS variable in code.
				$jsObjectValue = $this->getAjaxData();	// Value of the JS object.
				wp_localize_script($jsScriptHandle, $jsObjectName, $jsObjectValue);

				// Step 3 - Enqueue the script.
				$jsScriptHandle = self::PREFIX . '-main-script';
				wp_enqueue_script($jsScriptHandle);
			}
		}
	}

    /**
     * Delete the specified options from WordPress.
	 *
     * @since 0.0.1
	 */
	protected function _deleteOptions()
	{
		foreach ($this->options as $opt) {
			delete_option($opt);
		}
	}

    /**
     * Delete the Wordfence specific scheduled tasks/cron jobs from WordPress.
	 *
     * @since 0.0.1
	 */
	protected function _deleteTasks()
	{
		foreach ($this->tasks as $task) {
			wp_clear_scheduled_hook($task);
		}
	}

    /**
     * Disable the Wordfence Firewall (WAF).
	 *
     * @since 0.0.1
	 */
	protected function _disableFirewall()
	{
		/*
		global $wpdb;
		//Old Firewall
		$table = $wpdb->base_prefix . "wfConfig";
		$val = 0;	// Disable Firewall.
		$name = 'firewallEnabled';
		$result = $wpdb->query($wpdb->prepare("UPDATE {$table} SET val = %u WHERE name = %s", $val, $name));
		//$wpdb->query("update " . $wpdb->base_prefix . "wfConfig set val=0 where name='firewallEnabled'");
		if ($result > 0) {
			echo "Successfully Updated";
		} else {
			exit(var_dump($wpdb->last_query));
		}
		$wpdb->flush();
		*/

		//WAF
		//$response = json_encode(array('msg' => 'we made it into "_disableFirewall()"'));
		//die($response);

		foreach($this->filesToRemove as $path) {
			@unlink($path);
		}
		@rmdir(WFWAF_LOG_PATH);

		$wafUninstaller = new WfaWAFAutoPrependUninstaller();
		$wafUninstaller->uninstall();
	}

    /**
     * Drop all the tables Wordfence created.
	 *
     * @since 0.0.1
	 */
	protected function _dropTables()
	{
		global $wpdb;
		foreach ($this->tablesAll as $table) {
			$wpdb->query("drop table " . $wpdb->base_prefix . $table);
		}
	}

    /**
     * Initialize the plugin and link it to WordPress.
	 * 
	 * Note: Must be PUBLIC since its used as a callback from WordPress.
	 *
     * @since 0.0.1
	 */
	public function admin_init()
	{
		if ($this->isAdmin()) {
			// Register the AJAX callback with WordPress.
			add_action('wp_ajax_' . self::ACTION, array($this, 'ajax_do_callback'));		// IF is_user_logged_in() == TRUE
	    	add_action('wp_ajax_nopriv_' . self::ACTION, array($this, 'ajax_do_callback'));	// IF is_user_logged_in() == FALSE

			if (is_multisite()) {
				if ($this->isAdminPageMU()) {
					add_action('network_admin_menu', array($this, 'admin_menu')); //Wordfence\WordfenceAssistant::admin_menus');
					// Register stylesheets and scripts.
					$this->enqueuePluginStyles();
					$this->enqueuePluginScripts();
				}
			} else {
				add_action('admin_menu', array($this, 'admin_menu')); //Wordfence\WordfenceAssistant::admin_menus');
				// Register stylesheets and scripts.
				$this->enqueuePluginStyles();
				$this->enqueuePluginScripts();
			}
		}
	}

    /**
     * Insert the plugin's Administrative Menu into WordPress.
	 *
     * @since 0.0.1
	 */
	public function admin_menu()
	{
		if ($this->isAdmin()) {
			$iconUrl 	= $this->getBaseUrl() . 'images/' . self::ADMIN_MENU_IMAGE;

			$menuTitle 	= self::ADMIN_MENU_TITLE;
			$menuSlug   = self::ADMIN_MENU_SLUG;
			$function 	= array($this, 'mainMenu');
			add_menu_page(self::ADMIN_PAGE_TITLE, $menuTitle, self::ADMIN_MENU_CAPABILITY, $menuSlug, $function, $iconUrl);

			$menuTitle 	= self::ADMIN_SUBMENU_DISABLE_FIREWALL_TITLE;
			$menuSlug   = self::ADMIN_SUBMENU_DISABLE_FIREWALL_SLUG;
			//$function 	= 'Wordfence\WordfenceAssistant::mainMenu';
			add_submenu_page(self::ADMIN_MENU_SLUG, self::ADMIN_PAGE_TITLE, $menuTitle, self::ADMIN_MENU_CAPABILITY, $menuSlug, $function);

			$menuTitle 	= self::ADMIN_SUBMENU_DELETE_ALL_TITLE;
			$menuSlug   = self::ADMIN_SUBMENU_DELETE_ALL_SLUG;
			//$function 	= 'Wordfence\WordfenceAssistant::mainMenu';
			add_submenu_page(self::ADMIN_MENU_SLUG, self::ADMIN_PAGE_TITLE, $menuTitle, self::ADMIN_MENU_CAPABILITY, $menuSlug, $function);
			
			$menuTitle 	= self::ADMIN_SUBMENU_CLEAR_LOCKS_TITLE;
			$menuSlug   = self::ADMIN_SUBMENU_CLEAR_LOCKS_SLUG;
			//$function 	= 'Wordfence\WordfenceAssistant::mainMenu';
			add_submenu_page(self::ADMIN_MENU_SLUG, self::ADMIN_PAGE_TITLE, $menuTitle, self::ADMIN_MENU_CAPABILITY, $menuSlug, $function);
			
			$menuTitle 	= self::ADMIN_SUBMENU_CLEAR_LIVE_TRAFFIC_TITLE;
			$menuSlug   = self::ADMIN_SUBMENU_CLEAR_LIVE_TRAFFIC_SLUG;
			//$function 	= 'Wordfence\WordfenceAssistant::mainMenu';
			add_submenu_page(self::ADMIN_MENU_SLUG, self::ADMIN_PAGE_TITLE, $menuTitle, self::ADMIN_MENU_CAPABILITY, $menuSlug, $function);
		}
	}

    /**
     * Handle AJAX calls from our plugin's WordPress interface.
	 *
     * @since 0.0.1
	 */
	public function ajax_do_callback()
	{
		$response = json_encode(array('errorMsg' => MSG_NOT_ADMIN));
		if ($this->isAdmin()) {
			$action = $_POST['func'];
			$nonce = $_POST['nonce'];
			if (!wp_verify_nonce($nonce, self::NONCE)) { 
				$response = json_encode(array('errorMsg' => MSG_INVALID_TOKEN));
			} else {
				if ($action == 'deleteAll') {
					$response = $this->deleteAll();
				} elseif ($action == 'clearLocks') {
					$response = $this->clearLocks();
				} elseif ($action == 'disableFirewall') {
					$response = $this->disableFirewall();
				} elseif ($action == 'clearLiveTraffic') {
					$response = json_encode(array('msg' => self::MSG_CLEAR_LIVE_TRAFFIC));
					$response = $this->clearLiveTraffic();
				} else {
					$response = json_encode(array('errorMsg' => 'action == ' . $action . ' | ' . MSG_INVALID_ACTION));
				}
			}
		}
		exit($response);
	}

	protected function _clearTablesAdmin($tables, $msgSuccess = '', $msgError = self::MSG_NOT_ADMIN)
	{
		$response = json_encode(array('errorMsg' => $msgError));
		if ($this->isAdmin()) {
			global $wpdb;
			foreach ($tables as $table) {
				$wpdb->query("truncate table " . $wpdb->base_prefix . $table);	// Try truncating table first in case user has this permission.
				$wpdb->query("delete from " . $wpdb->base_prefix . $table);		// This should always work but its much slower.
			}
			$response = json_encode(array('msg' => $msgSuccess));
		}
		return $response;
	}

	public function clearLiveTraffic()
	{
		return $this->_clearTablesAdmin($this->tablesLiveTraffic, self::MSG_CLEAR_LIVE_TRAFFIC);
	}

	public function clearLocks()
	{
		return $this->_clearTablesAdmin($this->tablesLiveTraffic, self::MSG_CLEAR_LOCKS);
	}

	public function disableFirewall()
	{
		$response = json_encode(array('errorMsg' => $msgErrNotAdmin));
		if ($this->isAdmin()) {		
			$this->_disableFirewall();
			$reponse = json_encode(array('msg' => self::MSG_FIREWALL_DISABLED));
		}
		return $response;
	}

	public function deleteAll()
	{
		$response = json_encode(array('errorMsg' => self::MSG_NOT_ADMIN));
		if ($this->isAdmin()) {		
			$response = '';
			if (defined('WORDFENCE_VERSION')) {
				$response = json_encode(array('errorMsg' => self::MSG_DEACTIVATE_WORDFENCE_FIRST));
			} else {
				update_option('wordfenceActivated', 0);	// For WordPress Mulit-User installs.
				$this->_deleteOptions;
				$this->_deleteTasks; // Any additional scheduled scans will fail and won't be rescheduled.
				$this->_disableFirewall;
				$this->_dropTables;
				$response = json_encode(array('msg' => self::MSG_DELETED_ALL_DATA));
			}
		}
		return $response;
	}

	protected function getBaseURL()
	{
		return plugins_url() . self::PLUGIN_FOLDER;
	}

	public static function installPlugin() { }

	protected function isAdmin()
	{
		$isAdmin = fasle;
		if (is_multisite()) {
			if (current_user_can('manage_network')) {
				$isAdmin = true;
			}
		} else {
			if (current_user_can('manage_options')) {
				$isAdmin = true;
			}
		}
		return $isAdmin;
	}

    /**
	 * Check if we are on an Admin page specific to our plugin.
	 *
	 * @param string $pageSlug WordPress slug for the page we are on.
	 *
     * @since 0.0.1
     */
	protected function isAdminPage($pageSlug = '')
	{
		if (preg_match("/\/admin.php\?page=" . $pageSlug . "/", $_SERVER['REQUEST_URI'])) { 
			return true; 
		}
		return false;
	}

	protected function isAdminPageMU()
	{
		if (preg_match('/^[\/a-zA-Z0-9\-\_\s\+\~\!\^\.]*\/wp-admin\/network\//', $_SERVER['REQUEST_URI'])) { 
			return true; 
		}
		return false;
	}

	public function mainMenu()
	{
		require $this->getBaseUrl() . 'lib/mainMenu.php';
	}

	public static function uninstallPlugin() { }

    /**
     * Get the data we need to validate the AJAX request.
     *
     * @return array
     */
	protected function getAjaxData()
	{
		return array(
			'ajaxURL' => admin_url(self::ADMIN_AJAX_URL),
			'ajaxAction' => self::ACTION,
			'ajaxNonce' => wp_create_nonce(self::NONCE)
		);
/*
		return array(
			'action' => self::ACTION,
			'nonce' => wp_create_nonce(self::NONCE)
		);
*/
	}

// =======================================================================================================
// =======================================================================================================

    /**
	 * Hook the WordPress 'wp_loaded' event to call our custom Class Constructor.
	 *
     * @since 0.0.1
     */
	public function register()
	{
        add_action('wp_ajax_' . self::ACTION, array($this, 'handleAjax'));			// IF is_user_logged_in() == TRUE
	    add_action('wp_ajax_nopriv_' . self::ACTION, array($this, 'handleAjax'));	// IF is_user_logged_in() == FALSE
		add_action('wp_loaded', array($this, 'registerAjaxScript'));				// Register custom Class constructor.
	}

    /**
     * Register our AJAX JavaScript with WordPress.
	 *
     * @since 0.0.1
     */
    public function registerAjaxScripts()
    {
		// Step 1 - Register a Handle to a Javascript file with WordPress.
		$jsFileHandle  = self::HANDLE_AJAX;
		$jsFileUrl     = $this->getBaseUrl() . 'js/ajax.js'; //plugins_url('path/to/ajax.js', __FILE__);
        $success = wp_register_script($jsFileHandle, $jsFileUrl, null, self::VERSION, true);
        
		// Step 2 - Use the Handle from (Step 1) to directly pass in a Javascript object by name and value.
		// Note: Script referenced by the handle MUST be registered BEFORE with WordPress using wp_register_script().
		$jsObjectName  = 'wp_ajax_data';		// Name of the JS object that you use in the file from (Step 1).
		$jsObjectValue = $this->getAjaxData;	// Value of the JS object that you use in the file from (Step 1).
		$success = wp_localize_script($jsFileHandle, $jsObjectName, $jsObjectValue);

		// Step 3 - Enqueue Javascript file with our "localized" data.
        wp_enqueue_script($jsFileHandle);
    }

    /**
     * Handles the AJAX request for my plugin.
	 *
     * @since 0.0.1
     */
    public function handleAjax()
    {
        // Validate the request using the nonce that we passed in using wp_localize_script().
        $isValid = check_ajax_referer(self::NONCE, 'nonce', false);	
		if ($isValid) {
			// Stand back! I'm about to do... SCIENCE!
			die(json_encode(array("msg", "<h1>Stand back! I'm about to do... SCIENCE!</h1>")));
		}
    }
}
