<?php /**
 * Plugin Name: WP Azure Offload
 * Description: Automatically copies media to Azure storage and deliver using CDN.
 * Author: Promact Infotech Pvt. Ltd.
 * Version: 2.0
 * Author URI: https://promactinfo.com
 * Network: True
 * License: GPLv3
 * Text Domain: wp-azure-offload
 *
 * @package WP Azure Offload
 */ /* Version check */ global $wp_version; $exit_msg = 'WP Azure This requires WordPress 4.4 or newer.Please update!'; if ( version_compare( $wp_version, '4.4', '<' ) ) {
	wp_die( esc_attr( $exit_msg ) );
}
/**
 * Initialization of the plugin
 */ function azure_init() {
	$abspath = dirname( __FILE__ );
	require_once $abspath . '/classes/class-azure-plugin-base.php';
	require_once $abspath . '/classes/class-azure-storage-services.php';
	require_once $abspath . '/classes/class-azure-storage-and-cdn.php';
	require_once $abspath . '/vendor/autoload.php';
	global $azure_storage_services;
	$azure_storage_services = new Azure_Storage_Services( __FILE__ );
	$azure_storage_and_cdn = new Azure_Storage_And_Cdn( __FILE__ , $azure_storage_services );
}
add_action( 'init','azure_init' );
