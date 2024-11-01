<?php
/**
 * The base class for The plugin and its addon
 *
 * @package     WP Azure Offload
 */

use WindowsAzure\Common\ServicesBuilder;
use WindowsAzure\Blob\Models\CreateContainerOptions;
use WindowsAzure\Blob\Models\PublicAccessType;
use WindowsAzure\Common\ServiceException;
use WindowsAzure\Blob\Models\CreateBlobOptions;
/**
 * The base class for media as well as for asset addon
 * This class contains the method for creating containers,
 * saving containers and displaying containers.
 *
 * There are two different options saved for asset container ane media container
 * The key for the option to save container for asset Addon 'asset-container' and for
 * media storage 'container' option.
 */
class Azure_Plugin_Base {

	/**
	 * The plugin file path
	 *
	 * @var string $plugin_file_path
	 */
	protected $plugin_file_path;
	/**
	 * The directory path of the plugin
	 *
	 * @var string $plugin_dir_path dir path.
	 */
	protected $plugin_dir_path;
	/**
	 * The plugin slug
	 *
	 * @var string $plugin_slug.
	 */
	protected $plugin_slug;
	/**
	 * The base name of the plugin
	 *
	 * @var string $plugin_basename Base name.
	 */
	protected $plugin_basename;
	/**
	 * The version of plugin
	 *
	 * @var int $plugin_version version of plugin.
	 */
	protected $plugin_version;
	/**
	 * The array of settings
	 *
	 * @var array $settings the array of settings.
	 */
	private $settings;
	/**
	 * The array of defined settings
	 *
	 * @var array $defined_settings the array of defined settings.
	 */
	private $defined_settings;

	/**
	 * The construction
	 *
	 * @param string $plugin_file_path the main file path of the plugin.
	 */
	function __construct( $plugin_file_path ) {
		$this->plugin_file_path = $plugin_file_path;
		$this->plugin_dir_path  = rtrim( plugin_dir_path( $plugin_file_path ), '/' );
		$this->plugin_basename  = plugin_basename( $plugin_file_path );
	}

	/**
	 * Get plugin version
	 */
	public function get_version() {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : $this->plugin_version;
	}

	/**
	 * Get the setting value from database
	 */
	function get_settings() {
		if ( is_null( $this->settings ) ) {
			// retrieve setting key value from database.
			$this->settings = $this->find_settings( get_site_option( static::SETTINGS_KEY ) );
		}
		return $this->settings;
	}

	/**
	 * Set a setting
	 *
	 * @param string $key the name of the setting stored in option table.
	 * @param string $value the value for the given key.
	 */
	function set_setting( $key, $value ) {
		$this->settings[ $key ] = $value;
	}

	/**
	 * Save the settings to the database options
	 */
	public function save_settings() {
		$this->save_site_option( static::SETTINGS_KEY, $this->settings );
	}

	/**
	 * Update option value
	 *
	 * @param string  $option the name of option key.
	 * @param string  $value the value of the option.
	 * @param boolean $autoload default value is true.
	 */
	public function save_site_option( $option, $value, $autoload = true ) {
		if ( is_multisite() ) {
			return update_site_option( $option, $value );
		}
		return update_option( $option, $value, $autoload );
	}

	/**
	 * Find the plug-in settings
	 *
	 * @param array $settings the array of settings.
	 * @return the array of settings
	 */
	function find_settings( $settings ) {
		// returns the array of defined settings.
		$defined_settings = $this->use_defined_settings();

		if ( empty( $defined_settings ) ) {
			return $settings;
		}

		foreach ( $defined_settings as $key => $value ) {
			$settings[ $key ] = $value;
		}

		return $settings;
	}

	/**
	 * Returns the array of defined settings
	 *
	 * @param boolean $force default is false.
	 * @return The array of defined settings.
	 */
	function use_defined_settings( $force = false ) {
		if ( is_null( $this->defined_settings ) || $force ) {
			$this->defined_settings = array();
			$unserialized_data = array();
			$class = get_class( $this );

			if ( defined( "$class::SETTINGS_CONSTANT" ) ) {
				$option = static::SETTINGS_CONSTANT;
				if ( defined( $option ) ) {
					// serialize option value to form an array.
					$unserialized_data = maybe_unserialize( constant( $option ) );
				}
			}

			$unserialized_data = is_array( $unserialized_data ) ? $unserialized_data : array();

			foreach ( $unserialized_data as $key => $value ) {
				if ( is_bool( $value ) || is_null( $value ) ) {
					$value = (int) $value;
				}

				if ( is_numeric( $value ) ) {
					$value = strval( $value );
				} else {
					$value = sanitize_text_field( $value );
				}

				$this->defined_settings[ $key ] = $value;
			}
		}
		return $this->defined_settings;
	}

	/**
	 * Display a view of a template file
	 *
	 * @param string $view the name of the file template to be viewd.
	 * @param array  $args the array of arguments passed to display in page.
	 */
	function display_view( $view, $args = array() ) {
		extract( $args );
		include $this->plugin_dir_path . '/view/' . $view . '.php';
	}

	/**
	 * Get key specific setting from database
	 *
	 * @param string $key the name of the settings.
	 * @param string $default the default set null.
	 */
	function get_setting( $key, $default = '' ) {
		$this->get_settings();
		if ( isset( $this->settings[ $key ] ) ) {
			$settings = $this->settings[ $key ];
		} else {
			$settings = $default;
		}
		return apply_filters( 'azure_storage_services' , $settings, $key );
	}

	/**
	 * To unset the setting from the settings array
	 *
	 * @param string $key the name of settings.
	 */
	function unset_setting( $key ) {
		$this->get_settings();

		if ( isset( $this->settings[ $key ] ) ) {
			unset( $this->settings[ $key ] );
		}
	}

	/**
	 * Assign link and name to the tabs below plug-in name
	 *
	 * @param string $links the link for the setting tab below plugin description.
	 * @param string $file the name of the link file.
	 */
	function plugin_settings_link( $links, $file ) {
		$url  = $this->plugin_page_url();
		$text = $this->plugin_settings_text();

		$settings_link = '<a href="' . $url . '">' . esc_html( $text ) . '</a>';

		if ( $file === $this->plugin_basename ) {
			array_unshift( $links, $settings_link );
		}

		return $links;
	}

	/**
	 * Create connection string
	 *
	 * @param object $azure the azure object.
	 */
	public function cerate_connection_string( $azure ) {
		$access_protocol = $azure->get_access_end_prorocol();
		$account_name = $azure->get_access_account_name();
		$account_key = $azure->get_access_account_key();
		$connection_string = '';
		if ( ! empty( $access_protocol ) && ! empty( $account_name ) && ! empty( $account_key ) ) {
			$connection_string = 'DefaultEndpointsProtocol=' . $access_protocol . ';AccountName=' . $account_name . ';AccountKey=' . $account_key;
		}

		return $connection_string;
	}

	/**
	 * An ajax request to save container
	 */
	public function ajax_save_container() {
		$this->verify_permission_for_request();
		$container = $this->ajax_check_container();

		$manual = false;
		$asset = filter_input( INPUT_POST, 'action' );
		// are we container manually?
		if ( isset( $action ) && false !== strpos( sanitize_text_field( wp_unslash( $action ) ), 'manual-save-container' ) ) {
			$manual = true;
		}
		$asset = filter_input( INPUT_POST, 'asset' );
		$asset_container = sanitize_text_field( wp_unslash( $asset ) );

		$this->save_container_for_ajax( $container, $manual, $asset_container );
	}

	/**
	 * Check for the permissions
	 */
	private function verify_permission_for_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permissions to access this page.', 'azure-storage-and-cdn' );
		}
	}

	/**
	 * Verify the request
	 *
	 * @return container name
	 */
	public function ajax_check_container() {
		$container_name = filter_input( INPUT_POST, 'container_name' );
		$in_nonce = filter_input( INPUT_POST, '_wpnonce' );
		if ( isset( $container_name, $in_nonce ) ) {
			$nonce = wp_create_nonce( sanitize_text_field( wp_unslash( $in_nonce ) ) );
			if ( wp_verify_nonce( $nonce, 'container' ) ) {
				$container = sanitize_text_field( wp_unslash( $container_name ) );
				return strtolower( $container );
			}
		} else {
			$out = array(
				'error' => __( 'No container name provided.', 'azure-storage-and-cdn' ),
			);

			$this->end_ajax( $out );
		}
	}

	/**
	 * To save the container to the database
	 *
	 * @param string  $container the name of the container.
	 * @param boolean $manual_select if manually applied container name its true else false.
	 * @param boolean $asset_container only for asset addon plugin is on, while saving asset container value will be true
	 *                                 otherwise it will be false.
	 */
	public function save_container_for_ajax( $container, $manual_select = false, $asset_container ) {
		$res = $this->check_container_permission( $container );
		if ( is_wp_error( $res ) ) {
			$out = $this->prepare_container_error( $res );
			$this->end_ajax( $out );
		} else {
			$this->save_container( $container, $manual_select, $asset_container );
			$this->end_ajax( $out );
		}
	}

	/**
	 * The response for the ajax request
	 *
	 * @param array $return the response for the request.
	 */
	public function end_ajax( $return = array() ) {
		echo wp_json_encode( $return );
		exit;
	}

	/**
	 * Check the $container exists in azure storage
	 * if not then return exception with 'No such Container exist'
	 * which allows to create new container to the storage with the name $container
	 *
	 * @param string $container the name of container.
	 */
	public function check_container_permission( $container ) {
		$container_list = $this->get_container_list();
		if ( is_wp_error( $container_list ) ) {
			return $container_list;
		} else {
			if ( in_array( $container, $container_list, true ) ) {
				return true;
			} else {
				$error_msg = 'No such Container exist';
				return new WP_Error( 'exception', $error_msg );
			}
		}
	}

	/**
	 * Save container to the database
	 *
	 * @param string  $container_name the name of the container.
	 * @param boolean $manual if manually applied container name.
	 * @param boolean $asset_container for asset addon plugin while saving asset container value will be true.
	 */
	private function save_container( $container_name, $manual = false, $asset_container = false ) {
		if ( $container_name ) {
			$this->get_settings();
			if ( ! $asset_container ) {
				$this->set_setting( 'container', $container_name );
			} else {
				$this->set_setting( 'asset-container', $container_name );
			}

			if ( $manual ) {
				// record that we have entered the container via the manual form.
				$this->set_setting( 'manual_container', true );
			} else {
				$this->unset_setting( 'manual_container' );
			}

			$this->save_settings();
			$out = array(
				'success' => '1',
				'container' => $container_name,
			);
			$this->end_ajax( $out );
		}
	}

	/**
	 * Server side validation
	 *
	 * @param object $object the object.
	 */
	private function prepare_container_error( $object ) {
		if ( 'Access Denied' === $object->get_error_message() ) {
			// If the container error is access denied, show our notice message.
			$out = array(
				'error' => 'Access Denied',
			);
		} else {
			$out = array(
				'error' => $object->get_error_message(),
			);
		}
		return $out;
	}

	/**
	 * An ajax request to create new container
	 */
	public function ajax_create_container() {
		// if creating container for asset addon.
		$asset_addon = filter_input( INPUT_POST, 'asset' );
		$is_asset = isset( $asset_addon ) ? sanitize_text_field( wp_unslash( $asset_addon ) ) : false;

		$this->verify_permission_for_request();
		$container = $this->ajax_check_container();
		$this->create_container_for_ajax( $container, $is_asset );
	}

	/**
	 * While creating new container
	 * Check whether the container exists if yes then throws an error
	 *
	 * @param string $container_name the name of container.
	 * @param int    $is_asset when creating container for assets addon plugin.
	 */
	public function create_container_for_ajax( $container_name, $is_asset ) {
		$res = $this->check_container_permission( $container_name );
		if ( is_wp_error( $res ) ) {
			$out = $this->prepare_container_error( $res );

			if ( 'No such Container exist' === $out['error'] ) {
				$res = $this->azure_container_create( $container_name );
				if ( is_wp_error( $res ) ) {
					$out = $this->prepare_container_error( $res );
					$this->end_ajax( $out );
				} else {
					$this->save_container( $container_name, false, $is_asset );
					$this->end_ajax( $out );
				}
			} else {
				$this->end_ajax( $out );
			}
		} else {
			$error_msg = array(
				'error' => 'Container existed.',
			);
			$this->end_ajax( $error_msg );
		}
	}

	/**
	 * Create a new container to the azure storage
	 *
	 * @param string $container_name The name for the container.
	 */
	private function azure_container_create( $container_name ) {
		$createContainerOptions = new CreateContainerOptions();
		$createContainerOptions->setPublicAccess( PublicAccessType::CONTAINER_AND_BLOBS );
		try {
			$this->blobClient->createContainer( $container_name, $createContainerOptions );
		} catch ( Exception $e ) {
			$error_msg = $e->getMessage();
			return new WP_Error( 'exception', $error_msg );
		}
	}

	/**
	 * Function call on link 'Browse existing containers'
	 * An ajax request to return the list of containers
	 */
	public function ajax_get_containers() {
		$this->verify_permission_for_request();
		$containers = $this->get_container_list();
		if ( is_wp_error( $containers ) ) {
			$out = $this->prepare_container_error( $containers, false );
		} else {
			$out = array(
				'success' => '1',
				'containers' => $containers,
			);
		}
		$this->end_ajax( $out );
	}

	/**
	 * Returns the array of the container names, available in azure storage
	 */
	public function get_container_list() {
		$containers = array();
		try {
			$container_list = $this->blobClient->listContainers()->getContainers();
			foreach ( $container_list as $cl ) {
				$containers[] = $cl->getName();
			}
		} catch ( Exception $e ) {
			$error_msg = $e->getMessage();
			return new WP_Error( 'exception', $error_msg );
		}
		return $containers;
	}

	/**
	 * Function that returns container from database
	 */
	public function ajax_container_exist() {
		$out = array();
		$container = $this->get_setting( 'container' );
		if ( isset( $container ) ) {
			$out['success'] = '1';
			$out['container'] = $container;
		}
		$this->end_ajax( $out );
	}

	/**
	 * Get mime type
	 *
	 * @param string $file_path the directory path.
	 */
	public function get_mime_type( $file_path ) {
		$text_mime = array(
			'eot'   => 'application/vnd.ms-fontobject',
			'otf'   => 'application/x-font-opentype',
			'rss'   => 'application/rss+xml',
			'svg'   => 'image/svg+xml',
			'ttf'   => 'application/x-font-ttf',
			'woff'  => 'application/font-woff',
			'woff2' => 'application/font-woff2',
			'xml'   => 'application/xml',
		);
		$mime = wp_get_mime_types();
		$allowed_mime = array_merge( $mime, $text_mime );
		$file_type = wp_check_filetype_and_ext( $file_path, basename( $file_path ), $allowed_mime );

		return $file_type['type'];
	}
}

