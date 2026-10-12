<?php
/**
 * Remove settings only when an administrator opted in to cleanup.
 *
 * @package UTM_Phone_Switcher
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$utm_phone_switcher_settings = get_option( 'utm_phone_switcher_settings', array() );
if ( is_array( $utm_phone_switcher_settings ) && ! empty( $utm_phone_switcher_settings['delete_data_on_uninstall'] ) ) {
	delete_option( 'utm_phone_switcher_settings' );
	delete_option( 'pdxphone_for_utm_options' );
	delete_option( 'pdxphone_for_utm_license' );
}
