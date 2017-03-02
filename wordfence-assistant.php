<?php
/*
Plugin Name: Wordfence Assistant Rebooted
Plugin URI: http://www.wordfence.com/
Description: Wordfence Assistant - Helps Wordfence users with miscellaneous Wordfence data management tasks.  
Author: Mark Maunder
Version: 1.0.4
Author URI: http://www.wordfence.com/
*/
namespace Wordfence;

require_once WP_PLUGIN_DIR . '/wordfence-assistant-rebooted/Wordfence/WordfenceAssistant.php';

use Wordfence\WordfenceAssistant as WordfenceAssistant;

register_activation_hook(WP_PLUGIN_DIR . '/wordfence-assistant-rebooted/wordfence-assistant.php', 'Wordfence\WordfenceAssistant::installPlugin');
register_deactivation_hook(WP_PLUGIN_DIR . '/wordfence-assistant-rebooted/wordfence-assistant.php', 'Wordfence\WordfenceAssistant::uninstallPlugin');

$wfa = new WordfenceAssistant;
