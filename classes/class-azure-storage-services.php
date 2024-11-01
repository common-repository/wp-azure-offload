<?php
/**
 * The service class
 *
 * @package     WP Azure Offload
 */

use WindowsAzure\Common\ServicesBuilder;
use WindowsAzure\Blob\Models\CreateContainerOptions;
use WindowsAzure\Blob\Models\PublicAccessType;
use WindowsAzure\Common\ServiceException;
/**
 * The service class
 * It takes user input such as 'access end protocol'
 * user's 'Account Name' and its 'Key' to store account Information
 * to use azure storage services.
 */
class Azure_Storage_Services extends Azure_Plugin_Base {

	/**
	 * The plugin file path
	 *
	 * @var string $plugin_file_path
	 */
	protected $plugin_file_path;
	/**
	 * The plugin title
	 *
	 * @var string $plugin_title
	 */
	private $plugin_title;
	/**
	 * The plugin title at dashboard menu
	 *
	 * @var string $plugin_menu_title
	 */
	private $plugin_menu_title;
	/**
	 * The access permission for user
	 *
	 * @var boolean $plugin_permission
	 */
	private $plugin_permission;
	const SETTINGS_KEY = 'azure_configuration';
	const SETTINGS_CONSTANT = 'AZURE_CONFIGURATION';

	/**
	 * The construction
	 *
	 * @param string $plugin_file_path the path of file 'azure-cdn.php'.
	 */
	function __construct( $plugin_file_path ) {
		$this->plugin_slug = 'azure-storage-services';
		parent::__construct( $plugin_file_path );
		do_action( 'azure_init', $this );

		if ( is_admin() ) {
			do_action( 'azure_admin_init', $this );
		}
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( $this, 'admin_plugin_menu' ) );
			$this->plugin_permission = 'manage_network_options';
		} else {
			add_action( 'admin_menu', array( $this, 'admin_plugin_menu' ) );
			$this->plugin_permission = 'manage_options';
		}

		$this->plugin_file_path = $plugin_file_path;
		$this->plugin_dir_path  = rtrim( plugin_dir_path( $plugin_file_path ), '/' );
		$this->plugin_basename  = plugin_basename( $plugin_file_path );
		$this->plugin_title      = __( 'Azure Configuration', 'azure-storage-services' );
		$this->plugin_menu_title = __( 'Azure', 'azure-storage-services' );

		// Add setting tab at plugin actions.
		add_filter( 'plugin_action_links', array( $this, 'plugin_settings_link' ), 10, 2 );
	}

	/**
	 * Display 'Settings' link below plugin description
	 */
	function plugin_settings_text() {
		return __( 'Configuration', 'azzure-web-services' );
	}

	/**
	 * Assign plugin page url
	 */
	function plugin_page_url() {
		return network_admin_url( 'admin.php?page=' . $this->plugin_slug );
	}

	/**
	 * Add the Azure menu item and sub pages
	 */
	function admin_plugin_menu() {
		$admin_menu_pages = array();
		$admin_menu_pages[] = add_menu_page( $this->plugin_title, $this->plugin_menu_title, $this->plugin_permission, $this->plugin_slug,
			array(
				$this,
				'display_page',
			), '', 10
		);

		global $submenu;
		add_submenu_page( $this->plugin_slug, $this->plugin_title, __( 'Configuration' ), $this->plugin_permission , $this->plugin_slug );
		do_action( 'azure_admin_menu', $this );

		foreach ( $admin_menu_pages as $page ) {
			add_action( 'load-' . $page, array( $this, 'plugin_page_load' ) );
		}
	}

	/**
	 * Display the output of a page
	 */
	function display_page() {
		$view       = 'access-settings';
		$page_title = __( 'Azure Storage Services - Configuration', 'azure-storage-services' );

		$get_page = filter_input( INPUT_GET, 'page' );
		if ( empty( $get_page ) ) {
			wp_die( 'Page is not available' );
		}

		$this->display_view( 'header',
			array(
				'page' => $view,
				'page_title' => $page_title,
			)
		);
		$this->display_view( $view );
		$this->display_view( 'footer' );
	}

	/**
	 * Add sub page to the Azure menu item
	 *
	 * @param string  $page_title the title of the page.
	 * @param string  $menu_title the menu title for the page.
	 * @param boolean $capability user access.
	 * @param string  $menu_slug slug value.
	 * @param string  $function default empty.
	 */
	function add_page( $page_title, $menu_title, $capability, $menu_slug, $function = '' ) {
		return add_submenu_page( $this->plugin_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
	}

	/**
	 * Plugin loads enqueued scripts and styles
	 */
	function plugin_page_load() {
		$version = $this->get_version();

		$src = plugins_url( 'assets/css/styles.css', $this->plugin_file_path );
		wp_register_style( 'azure-styles', $src, array(), $version );
		wp_enqueue_style( 'azure-styles' );

		$src = plugins_url( 'assets/js/validate.js', $this->plugin_file_path );
		wp_register_script( 'azure-validate', $src, array( 'jquery' ), $version, true );
		wp_enqueue_script( 'azure-validate' );

		$src = plugins_url( 'assets/js/script.js', $this->plugin_file_path );
		wp_register_script( 'azure-script', $src, array( 'jquery' ), $version, true );
		wp_enqueue_script( 'azure-script' );

		$page = filter_input( INPUT_GET, 'page' );
		if ( isset( $page ) ) {
			add_filter( 'admin_body_class', array( $this, 'plugin_body_class' ) );
			wp_enqueue_script( 'plugin-install' );
			add_thickbox();
		}

		$this->handle_request();
	}

	/**
	 * Adds a class to admin page same as the plugin directory page
	 *
	 * @param string $classes the the class name.
	 */
	function plugin_body_class( $classes ) {
		$classes .= 'plugin-install-php';
		return $classes;
	}

	/**
	 * Handle request to save data to database
	 */
	function handle_request() {
		$action = filter_input( INPUT_POST, 'action' );
		if ( empty( $action ) || 'save' !== sanitize_key( $action ) ) {
			return;
		}

		$this->get_settings();

		$post_data = array( 'access_end_prorocol', 'access_account_name', 'access_account_key' );
		foreach ( $post_data as $var ) {
			$post = filter_input( INPUT_POST, $var );
			if ( ! isset( $post ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $post ) );

			$this->set_setting( $var, $value );
		}
		$this->save_settings();
	}

	/**
	 * Get azure access account name
	 *
	 * @return account name
	 */
	function get_access_account_name() {
		return $this->get_setting( 'access_account_name' );
	}

	/**
	 * Get azure access account key
	 *
	 * @return the account key value of azure storage
	 */
	function get_access_account_key() {
		return $this->get_setting( 'access_account_key' );
	}

	/**
	 * Get azure end pont protocol
	 *
	 * @return access end protocol
	 */
	function get_access_end_prorocol() {
		return $this->get_setting( 'access_end_prorocol' );
	}
}
