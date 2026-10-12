<?php
/**
 * Plugin Name: UTM Phone Switcher
 * Description: Change phone numbers on your WordPress site based on UTM or other URL parameters.
 * Plugin URI: https://needtome.com/utm-phone-switcher/
 * Version: 1.0.3
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Author: Parad0x
 * Author URI: https://www.linkedin.com/in/roman-klymenko-parad0x/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: utm-phone-switcher
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UTM_PHONE_SWITCHER_VERSION', '1.0.3' );
define( 'UTM_PHONE_SWITCHER_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-utm-phone-switcher.php';

\NeedToMe\UTMPhoneSwitcher\Plugin::boot();
