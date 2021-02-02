<?php

/**
 * See https://premium.wpmudev.org/blog/activate-deactivate-uninstall-hooks/
 *
 * @package wp_bookmarker
 */

if ( ! current_user_can( 'activate_plugins' ) ) {
	return;
}
check_admin_referer( 'bulk-plugins' );

if ( __FILE__ !== WP_UNINSTALL_PLUGIN ) {
	return;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Uninstallation actions here
