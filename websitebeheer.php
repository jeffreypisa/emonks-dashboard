<?php
/**
 * Plugin Name: Websitebeheer
 * Description: Klantdashboard, handleidingen, bureauprofiel en branding van beheer en inloggen. Met aanvullende uitleg vanuit het actieve thema.
 * Version: 1.1.0
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Author: emonks
 * Author URI: https://emonks.nl/
 * License: GPL-2.0-or-later
 * Update URI: https://github.com/jeffreypisa/emonks-dashboard
 * Text Domain: websitebeheer
 */
namespace Emonks\Websitebeheer;

if (!defined('ABSPATH')) exit;

define('WEBSITEBEHEER_VERSION', '1.1.0');
define('WEBSITEBEHEER_URL', plugin_dir_url(__FILE__));
(require __DIR__ . '/includes/Updater.php')(__FILE__);
require_once __DIR__ . '/includes/Agency.php';
require_once __DIR__ . '/includes/Workspace.php';

// Wait for theme integrations; never show two competing dashboards during a staged upgrade.
add_action('after_setup_theme', static function () {
    if (class_exists('ThemeWorkspace', false) || class_exists('Emonks_Custom_Dashboard', false)) {
        add_action('admin_notices', static function () {
            if (current_user_can('activate_plugins')) echo '<div class="notice notice-warning"><p>Websitebeheer is nog niet gestart: werk Skeletor bij naar de versie met pluginondersteuning en deactiveer Emonks Custom Dashboard als die plugin actief is.</p></div>';
        });
        return;
    }
    new Agency();
    new Workspace();
}, 20);
