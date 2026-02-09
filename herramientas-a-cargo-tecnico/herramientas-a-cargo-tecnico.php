<?php
/**
 * Plugin Name: Herramientas a Cargo Técnico
 * Description: Gestor frontend standalone (fichas + técnicos) con adjunto escaneado. Sin login.
 * Version: 2.2.2
 * Author: Rocket Solutions
 */

if (!defined('ABSPATH')) exit;

define('HAC_PLUGIN_VERSION', '2.2.2');
define('HAC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HAC_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HAC_PLUGIN_DIR . 'includes/class-hac-activator.php';
require_once HAC_PLUGIN_DIR . 'includes/class-hac-utils.php';
require_once HAC_PLUGIN_DIR . 'includes/class-hac-router.php';
require_once HAC_PLUGIN_DIR . 'includes/class-hac-admin.php';

register_activation_hook(__FILE__, ['HAC_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['HAC_Activator', 'deactivate']);

add_action('plugins_loaded', function () {
  HAC_Router::init();
  HAC_Admin::init();
});
