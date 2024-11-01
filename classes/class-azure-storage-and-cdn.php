<?php
/**
 * The service access class
 *
 * @package     WP Azure Offload
 */

use WindowsAzure\Common\ServicesBuilder;
use WindowsAzure\Blob\Models\CreateContainerOptions;
use WindowsAzure\Blob\Models\PublicAccessType;
use WindowsAzure\Common\ServiceException;
use WindowsAzure\Blob\Models\CreateBlobOptions;

/**
 * The class provides functions through which
 * we can use the services of azure storage containers
 * Can copy media files to azure storage and
 * Serve them using CDN Endpoint
 */
class Azure_Storage_And_Cdn extends Azure_Plugin_Base {

	const SETTINGS_KEY = 'wp_azure_storage_details';
	const SETTINGS_CONSTANT = 'WPAZURE_SETTINGS';

	/**
	 * The construction
	 *
	 * @param string $plugin_file_path the path of file 'azure-cdn.php'.
	 * @param object $azure the object of azure storage service.
	 * @param string $slug the default is null.
	 */
	function __construct( $plugin_file_path, $azure, $slug = null ) {
		$this->plugin_slug = ( is_null( $slug ) ) ? 'azure-storage-and-cdn' : $slug;

		parent::__construct( $plugin_file_path );
		$this->azure = $azure;
		$connection_string = $this->cerate_connection_string( $azure );

		if ( is_multisite() ) {
			$this->plugin_permission = 'manage_network_options';
		} else {
			$this->plugin_permission = 'manage_options';
		}

		if ( ( $connection_string ) ) {
			$this->blobClient = ServicesBuilder::getInstance()->createBlobService( $connection_string );
			$this->init( $plugin_file_path );
		} else {
			return ;
		}
	}

	/**
	 * The initialization of media settings
	 *
	 * @param string $plugin_file_path the root path of the plugin.
	 */
	function init( $plugin_file_path ) {
		add_action( 'azure_admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'azure_plugin_load', $this );

		// container related actions.
		add_action( 'wp_ajax_manual-save-container', array( $this, 'ajax_save_container' ) );
		add_action( 'wp_ajax_azure-container-create', array( $this, 'ajax_create_container' ) );
		add_action( 'wp_ajax_get-container-list', array( $this, 'ajax_get_containers' ) );
		add_action( 'wp_ajax_container-exist', array( $this, 'ajax_container_exist' ) );
		add_action( 'wp_ajax_get_attachment_azure_details', array( $this, 'ajax_get_attachment_azure_details' ) );

		// add action feild to media library.
		add_filter( 'manage_media_columns', array( $this, 'add_action_column' ) );
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_action_field' ), 10, 2 );
		add_action( 'manage_media_custom_column', array( $this, 'add_action_column_content' ), 10, 2 );
		add_action( 'admin_print_scripts', array( $this, 'add_js' ) );
		add_action( 'admin_print_styles', array( $this, 'add_css' ) );

		// ajax from media js actions from media library.
		add_action( 'wp_ajax_delete-from-azure', array( $this, 'ajax_request_delete_files' ) );
		add_action( 'wp_ajax_copy-to-azure-from-library', array( $this, 'ajax_request_copy_file' ) );
		add_action( 'wp_ajax_bulk-action', array( $this, 'ajax_request_bulk_actions' ), 10, 2 );

		// Rewriting URLs.
		add_filter( 'wp_get_attachment_url', array( $this, 'wp_get_attachment_url' ), 99, 2 );

		// upload media to the azure storage.
		add_filter( 'wp_update_attachment_metadata', array( $this, 'wp_update_attachment_metadata' ), 110, 2 );
		add_action( 'upload_media_library', array( $this, 'copy_existing_files_to_azure' ) );

		// actions for the process bar.
		add_action( 'wp_ajax_process-start', array( $this, 'ajax_process_bar_file_count' ) );
		add_action( 'wp_ajax_check-media-cron', array( $this, 'ajax_check_media_cron' ) );
		add_action( 'wp_ajax_uploaded-count', array( $this, 'ajax_process_bar_file_count' ) );
		add_action( 'wp_ajax_media-cron', array( $this, 'run_cron' ) );
		add_action( 'wp_ajax_remove-cron-details', array( $this, 'ajax_remove_cron_details' ) );
		add_filter( 'cron_schedules', array( $this, 'offload_schedule' ) );
	}

	/*
	 * custom cron schedule
	 * 
	 * @param array $interval an array of schedule intervals
	 */
	public function offload_schedule( $interval ) {
		$interval['media_copy'] = array(
			'interval' => 300,
			'display'  => __( 'Every Minutes', 'textdomain' ),
		);
		return $interval;
	}
	/**
	 * Count of files to be uploaded for progess bar
	 */
	public function ajax_process_bar_file_count() {
		$total_file_count = $this->local_files_count();
		$out = array(
			'success' => 1,
			'data' => $total_file_count,
		);
		$this->end_ajax( $out );
	}

	/**
	 * If page refreshed check media cron is running
	 * and returns the total count of file saved in database
	 */
	function ajax_check_media_cron() {
		$out = array();
		$data = array();
		$is_cron_running = get_site_option( 'wp-azure-media-cron-running' );
		$total_count = get_site_option( 'wp-azure-media-total-count' );
		$data['total_count'] = $total_count;

		if(is_multisite()){
			switch_to_blog(1);
		}
		if ( $is_cron_running && (wp_get_schedule( 'upload_media_library' )) ) {
			$data['media_cron_running'] = 1;
		} else {
			delete_site_option( 'wp-azure-media-cron-running' );
			delete_site_option( 'wp-azure-media-total-count' );
			$data['media_cron_running'] = 0;
		}

		$out['data'] = $data;
		$out['success'] = 1;
		$this->end_ajax( $out );
	}

	/**
	 * Remove settings of progress bar
	 */
	function ajax_remove_cron_details() {
		$out = array();
		wp_clear_scheduled_hook( 'upload_media_library' );
		delete_site_option( 'wp-azure-media-cron-running' );
		delete_site_option( 'wp-azure-media-total-count' );
		$out['success'] = 1;
		$this->end_ajax( $out );
	}

	/**
	 * Start a media cron to copy all media to azure storage
	 */
	public function run_cron() {
		$total_file_count = 0;
		$copy_enable = true;

		if ( ! $this->get_serve_from_azure_setting() ) {
			$copy_enable = false;
			$copy_enable_notice = 'Please enable setting SERVE FILES USING CDN URL' ;
		}

		if ( ! $this->get_copy_to_azure_setting() ) {
			$copy_enable = false;
			$copy_enable_notice = 'Please enable setting COPY FILES TO AZURE STORAGE' ;
		}

		if ( $copy_enable ) {
			$total_file_count = $this->local_files_count();

			if ( $total_file_count > 0 ) {
				if(is_multisite()){
					switch_to_blog(1);
				}
				wp_clear_scheduled_hook( 'upload_media_library' );
				if ( ! wp_next_scheduled( 'upload_media_library' ) ) {
					wp_schedule_event( time(), 'media_copy', 'upload_media_library' );
				}

				update_site_option( 'wp-azure-media-cron-running', true );
				update_site_option( 'wp-azure-media-total-count', $total_file_count );
			}
		}
		$out = array(
			'success' => 1,
			'data' => $total_file_count,
			'enable_plugin' => $copy_enable,
			'notice' => $copy_enable_notice,
		);

		$this->end_ajax( $out );
	}

	/**
	 * Returns the count of files to be uploaded to azure storage
	 */
	private function local_files_count() {
		$query_images_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => - 1,
		);

		if ( is_multisite() ) {
			$all_sites = get_sites();
			$query_images = array();
			foreach ( $all_sites as $site ) {
				if ( 1 != $site->deleted ) {
					$site_id = $site->blog_id;
					switch_to_blog( $site->blog_id );
					$query_images[ $site_id ] = new WP_Query( $query_images_args );
					restore_current_blog();
				}
			}

			$images = array();
			foreach ( $query_images as $blog_id => $blog_imgs ) {
				foreach ( $blog_imgs->posts as $img ) {
					if ( ! $this->is_attachment_served_by_azure( $img->ID, $blog_id ) ) {
						switch_to_blog( $blog_id );
						$image['post_id'] = $img->ID ;
						$image['path'] = wp_get_attachment_url( $img->ID );
						$image['blog_id'] = $blog_id;
						$images[] = $image;
					}
				}
			}
		} else {
			$query_images = new WP_Query( $query_images_args );
			$images = array();
			foreach ( $query_images->posts as $img ) {
				if ( ! $this->is_attachment_served_by_azure( $img->ID ) ) {
					$image['post_id'] = $img->ID;
					$image['path'] = wp_get_attachment_url( $img->ID );
					$images[] = $image;
				}
			}
		}

		return count( $images );
	}

	/**
	 * Hook for admin menu
	 *
	 * @param object $azure the object of azure storage service.
	 */
	function admin_menu( $azure ) {
		$hook_suffix = $azure->add_page( 'WP Offload Azure', 'Media Storage', $this->plugin_permission, 'azure-storage-and-cdn', array( $this, 'display_page' ) );
		if ( false !== $hook_suffix ) {
			$this->hook_suffix = $hook_suffix;
			add_action( 'load-' . $this->hook_suffix, array( $this, 'plugin_page_load' ) );
		}
	}

	/**
	 * Plugin loads enqueued scripts and styles
	 */
	function plugin_page_load() {
		$version = $this->get_version();

		$src = plugins_url( 'assets/css/styles.css', $this->plugin_file_path );
		wp_register_style( 'azure-styles', $src );
		wp_enqueue_style( 'azure-styles' );

		$src = plugins_url( 'assets/js/validate.js', $this->plugin_file_path );
		wp_register_script( 'azure-validate', $src, array( 'jquery' ), $version, true );
		wp_enqueue_script( 'azure-validate' );

		$src = plugins_url( 'assets/js/circleprogress.js', $this->plugin_file_path );
		wp_register_script( 'azure-circleprogress', $src, array( 'jquery' ), $version, true );
		wp_enqueue_script( 'azure-circleprogress' );

		$src = plugins_url( 'assets/js/script.js', $this->plugin_file_path );
		wp_register_script( 'azure-script', $src, array( 'jquery' ), $version, true );
		wp_enqueue_script( 'azure-script' );

		wp_localize_script( 'azure-script',
			'azure',
			array(
				'strings'         => array(
					'create_container_error'      => __( 'Error creating container', 'azure-storage-and-cdn' ),
					'save_container_error'        => __( 'Error saving container', 'azure-storage-and-cdn' ),
					'get_container_error'           => __( 'Error fetching containers', 'azure-storage-and-cdn' ),
					'get_url_preview_error'       => __( 'Error getting URL preview: ', 'azure-storage-and-cdn' ),
				),
			)
		);

		$this->handle_request();
	}

	/**
	 * Handle request to save data to database
	 */
	function handle_request() {
		$action = filter_input( INPUT_POST, 'action' );
		if ( empty( $action ) || 'save' !== sanitize_key( $action ) ) { // input var okay.
			return;
		}

		// Make sure $this->settings has been loaded.
		$this->get_settings();

		$post_vars = array( 'copy-to-azure', 'serve-from-cdn', 'cdn-endpoint', 'find-and-replace', 'remove-local-files', 'max-age' );
		foreach ( $post_vars as $var ) {
			$this->unset_setting( $var );

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
	 * To render the templates
	 */
	function display_page() {
		$this->azure->display_view( 'header',
			array(
				'page_title' => 'WP Offload Azure and CDN',
			)
		);
		$this->display_view( 'container-setting' );
		$this->azure->display_view( 'footer' );
	}

	/**
	 * Set the settings
	 *
	 * @param string $key the key of the media settings.
	 * @param int    $value the value of the key.
	 */
	function set_setting( $key, $value ) {
		$value = apply_filters( 'set_setting_' . $key, $value );
		parent::set_setting( $key, $value );
	}

	/**
	 * Get container name from the database
	 */
	function get_container_name() {
		return $this->get_setting( 'container' );
	}

	/**
	 * Get value of the setting copy-to-azure
	 */
	public function get_copy_to_azure_setting() {
		return $this->get_setting( 'copy-to-azure' );
	}

	/**
	 * Get value of the setting serve-from-cdn
	 */
	public function get_serve_from_azure_setting() {
		return $this->get_setting( 'serve-from-cdn' );
	}

	/**
	 * Get value of cdn endpoint
	 */
	public function get_cdn_endpoint() {
		return $this->get_setting( 'cdn-endpoint' );
	}

	/**
	 * Get value of the setting find-and-replace
	 */
	public function get_find_and_replace() {
		return $this->get_setting( 'find-and-replace' );
	}

	/**
	 * Get value of the setting remove-local-files
	 */
	public function is_remove_local_files() {
		return $this->get_setting( 'remove-local-files' );
	}

	/**
	 * Get max age for cache control
	 *
	 * @return max-age.
	 */
	public function get_max_age() {
		return $this->get_setting( 'max-age' );
	}

	/**
	 * File uploading hook
	 *
	 * @param array $data the array meta data.
	 * @param int   $post_id The id of the post.
	 * @return array of the metadata.
	 */
	public function wp_update_attachment_metadata( $data, $post_id ) {
		$data = $this->upload_attachment_to_azure_storage( $post_id, $data );

		return $data;
	}

	/**
	 * Error while copying file
	 *
	 * @param array  $error the array of error.
	 * @param object $return default is null.
	 * @return object of excaption
	 */
	protected function return_upload_error( $error, $return = null ) {
		if ( is_null( $return ) ) {
			return new WP_Error( 'exception', $error );
		}
		return $return;
	}

	/**
	 * To copy files to azure storage while uploading to media library
	 *
	 * @param int     $post_id The id of the post.
	 * @param array   $data the array meta data.
	 * @param string  $file_path the local paths of file.
	 * @param boolean $blog_id yrue if multisite WordPress.
	 */
	public function upload_attachment_to_azure_storage( $post_id, $data = null, $file_path = null, $blog_id = false ) {
		$return_metadata = null;

		if ( ! $this->get_copy_to_azure_setting() ) {
			return $data;
		}

		if ( is_null( $data ) ) {
			$data = wp_get_attachment_metadata( $post_id, true );
		} else {
			$return_metadata = $data;
		}

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$file_path = get_attached_file( $post_id, true );

		if ( ! file_exists( $file_path ) ) {
			$error_msg = sprintf( __( 'File does not exist', 'azure-storage-and-cdn' ), $file_path );
			return $this->return_upload_error( $error_msg, $return_metadata );
		}

		/* set max age for cache control in header*/
		if ( $this->get_max_age() !== null ) {
			$max_age = $this->get_max_age();
		} else {
			$max_age = '2592000';
		}

		$type = $this->get_mime_type( $file_path );
		$option = new CreateBlobOptions();
		$option->setBlobContentType( $type );
		$option->setCacheControl( 'max-age=' . $max_age );
		$blob_name = basename( $file_path );
		$container = $this->get_container_name();

		$azure_storage_object = array(
			'container' => $container,
			'key'    => $blob_name,
		);

		$files_to_remove = array();
		$url = wp_get_attachment_url( $post_id );

		if ( file_exists( $file_path ) ) {
			$content = wp_remote_fopen( $url );
			try {
				$this->blobClient->createBlockBlob( $container, $blob_name, $content,$option );
				$files_to_remove[] = $file_path;
			} catch ( Exception $e ) {
				$error_msg = sprintf( __( 'Error uploading %$s to Azure Storage: %$s', 'azure-storage-and-cdn' ), $file_path, $e->getMessage() );
				return $this->return_upload_error( $error_msg, $return_metadata );
			}
		}

		delete_post_meta( $post_id, 'azurestorage_info' );
		$file_paths        = $this->get_images_file_paths( $post_id, true, $data );

		$additional_images = array();

		foreach ( $file_paths as $size => $path ) {
			if ( ! in_array( $path, $files_to_remove, true ) ) {
				$additional_images[] = $path;
			}
		}

		foreach ( $additional_images as $blob_path ) {
			$type = $this->get_mime_type( $blob_path );
			$opt = new CreateBlobOptions();
			$opt->setBlobContentType( $type );
			$opt->setCacheControl( 'max-age=' . $max_age );
			$blob_name = basename( $blob_path );
			$content = wp_remote_fopen( $url );
			try {
				$this->blobClient->createBlockBlob( $container, $blob_name, $content,$opt );
				$files_to_remove[] = $blob_path;
			} catch ( Exception $e ) {
				$error_msg = sprintf( __( 'Error uploading %$s to Azure Storage: %$s', 'azure-storage-and-cdn' ), $file_path, $e->getMessage() );
				return $this->return_upload_error( $error_msg, $return_metadata );
			}
		}

		$azure_storage_object['sizes'] = isset( $data['sizes'] ) ? $data['sizes'] : array();
		add_post_meta( $post_id, 'azurestorage_info', $azure_storage_object );

		if ( $this->is_remove_local_files() ) {
			$this->remove_local_files( $files_to_remove );
		}

		if ( ! is_null( $return_metadata ) ) {
			return $data;
		}

		return $azure_storage_object;
	}

	/**
	 * Remove files from the local server
	 *
	 * @param array $files_to_remove the local paths of files.
	 */
	public function remove_local_files( $files_to_remove ) {
		foreach ( $files_to_remove as $file ) {
			unlink( $file );
		}
	}

	/**
	 * The path of different sizes
	 *
	 * @param int     $post_id The id of the post.
	 * @param boolean $local_exist check files exist locally.
	 * @param boolean $meta the meta details of media files.
	 * @return the array of path of different sizes.
	 */
	public function get_images_file_paths( $post_id, $local_exist = true, $meta = false ) {
		$file_path = get_attached_file( $post_id, true );
		$paths     = array(
			'original' => $file_path,
		);

		if ( ! $meta ) {
			$meta = get_post_meta( $post_id, '_wp_attachment_metadata', true );
		}

		if ( is_wp_error( $meta ) ) {
			return $paths;
		}

		$file_name = basename( $file_path );

		if ( isset( $meta['thumb'] ) ) {
			$paths['thumb'] = str_replace( $file_name, $meta['thumb'], $file_path );
		}

		if ( isset( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size => $file ) {
				if ( isset( $file['file'] ) ) {
					$paths[ $size ] = str_replace( $file_name, $file['file'], $file_path );
				}
			}
		}

		$meta_data = get_post_meta( $post_id, '_wp_attachment_backup_sizes', true );

		if ( is_array( $meta_data ) ) {
			foreach ( $meta_data as $size => $file ) {
				if ( isset( $file['file'] ) ) {
					$paths[ $size ] = str_replace( $file_name, $file['file'], $file_path );
				}
			}
		}

		$paths = apply_filters( 'attachment_file_paths', $paths, $post_id, $meta );

		$paths = array_unique( $paths );

		if ( $local_exist ) {
			foreach ( $paths as $key => $path ) {
				if ( ! file_exists( $path ) ) {
					unset( $paths[ $key ] );
				}
			}
		}

		return $paths;
	}

	/**
	 * To replace media url with CDN url of specific media file by its post id
	 *
	 * @param string $url the local url of the post media.
	 * @param int    $post_id The id of the post.
	 * @return CDN url of given post id.
	 */
	public function wp_get_attachment_url( $url, $post_id ) {
		$new_url = $this->get_attachment_url( $post_id );

		if ( false === $new_url ) {
			return $url;
		}
		return $new_url;
	}

	/**
	 * Get azure storage information of specific media file by its post id
	 *
	 * @param int $post_id The id of the post.
	 * @return object of azure storage info.
	 */
	public function get_attachment_azure_info( $post_id ) {
		return get_post_meta( $post_id, 'azurestorage_info', true );
	}

	/**
	 * Whether to serve from azure storage or local server
	 *
	 * @param int $post_id The id of the post.
	 * @param int $blog_id for multisite WordPress.
	 */
	public function is_attachment_served_by_azure( $post_id, $blog_id = null ) {
		if ( ! $this->get_setting( 'serve-from-cdn' ) ) {
			return false;
		}

		if ( $blog_id ) {
			switch_to_blog( $blog_id );
		}

		if ( ! $this->get_attachment_azure_info( $post_id ) ) {
			return false;
		}
		$azure_storage_object = $this->get_attachment_azure_info( $post_id );
		return $azure_storage_object;
	}

	/**
	 * Get the file url
	 *
	 * @param int $post_id The id of the post.
	 * @return CDN url for the given post id
	 */
	public function get_attachment_url( $post_id ) {
		if ( ! $this->is_attachment_served_by_azure( $post_id ) ) {
			return false;
		}
		$azure_storage_bject = $this->is_attachment_served_by_azure( $post_id );
		$url = $this->get_attachment_azure_storage_url( $post_id, $azure_storage_bject );
		return $url;
	}

	/**
	 * Replace the  file url with CDN url
	 *
	 * @param int    $post_id The id of the post.
	 * @param object $azure_storage_object azure storage object.
	 */
	public function get_attachment_azure_storage_url( $post_id, $azure_storage_object ) {
		$container = $azure_storage_object['container'];
		$blob_name = $azure_storage_object['key'];
		$azure  = new Azure_Storage_Services( __FILE__ );
		$end_protocol = $azure->get_access_end_prorocol();
		$cdn_end_point = $this->get_cdn_endpoint();
		if ( ! $cdn_end_point ) {
			return false;
		}
		$url = $end_protocol . '://' . $cdn_end_point . '/' . $container . '/' . $blob_name;
		return $url;
	}


	/**
	 * Copy existed files to azure storage
	 */
	public function copy_existing_files_to_azure() {
		if ( is_multisite() ) {
			$this->copy_existed_for_multi_blog();
		} else {
			$this->copy_existed_for_normal_blog();
		}
	}

	/**
	 * Copy existed files to azure storage for multi site WordPress
	 */
	public function copy_existed_for_multi_blog() {
		$attachment_file_list = $this->get_existing_files_from_multi_blog();
		$data = array();
		if ( ! empty( $attachment_file_list ) ) {
			foreach ( $attachment_file_list as $file ) {
				switch_to_blog( $file['blog_id'] );
				$result = $this->upload_attachment_to_azure_storage( $file['post_id'], null, $file['path'], $file['blog_id'] );
				if ( ! is_wp_error( $result ) ) {
					$this->find_and_replace_content( $file['post_id'], $file['blog_id'] );
				}
				$data[] = $result;
				restore_current_blog();
			}

			if ( is_wp_error( $data[0] ) ) {
				$out['success'] = 'error';
				$out['data'] = $data[0]->get_error_message();
			} else {
				$out['success'] = 1;
				$out['data'] = 'Files successfully uploaded';
			}
		}
	}

	/**
	 * Returns an array of files to be copied to azure storage for multi site WordPress
	 */
	public function get_existing_files_from_multi_blog() {
		$query_images_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => - 1,
		);

		$all_sites = get_sites();
		$query_images = array();
		foreach ( $all_sites as $site ) {
			if ( 1 != $site->deleted ) {
				$site_id = $site->blog_id;
				switch_to_blog( $site->blog_id );
				$query_images[ $site_id ] = new WP_Query( $query_images_args );
				restore_current_blog();
			}
		}

		$images = array();
		foreach ( $query_images as $blog_id => $blog_imgs ) {
			foreach ( $blog_imgs->posts as $img ) {
				if ( ! $this->is_attachment_served_by_azure( $img->ID, $blog_id ) ) {
					switch_to_blog( $blog_id );
					$image['post_id'] = $img->ID ;
					$image['path'] = wp_get_attachment_url( $img->ID );
					$image['blog_id'] = $blog_id;
					$images[] = $image;
					restore_current_blog();
				}
			}
		}

		return $images;
	}

	/**
	 * Copy existed files to azure storage for single site WordPress
	 */
	public function copy_existed_for_normal_blog() {
		$list = $this->get_existing_files();
		$data = array();
		if ( ! empty( $list ) ) {
			foreach ( $list as $file ) {
				$result = $this->upload_attachment_to_azure_storage( $file['post_id'], null , $file['path'] );
				if ( ! is_wp_error( $result ) ) {
					$this->find_and_replace_content( $file['post_id'] );
				}
				$data[] = $result;
			}

			if ( is_wp_error( $data[0] ) ) {
				$out['success'] = 'error';
				$out['data'] = $data[0]->get_error_message();
			} else {
				$out['success'] = 1;
				$out['data'] = 'Files successfully uploaded';
			}
		}
	}

	/**
	 * Get the list of the alredy existing media files ie not in azure storage in single site WordPress
	 *
	 * @return array of existing files.
	 */
	public function get_existing_files() {
		$query_images_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => - 1,
		);

		$query_images = new WP_Query( $query_images_args );
		$images = array();
		foreach ( $query_images->posts as $img ) {
			if ( ! $this->is_attachment_served_by_azure( $img->ID ) ) {
				$image['post_id'] = $img->ID;
				$image['path'] = wp_get_attachment_url( $img->ID );
				$images[] = $image;
			}
		}

		return $images;
	}

	/**
	 * Add custom column to media library list view
	 *
	 * @param string $columns the name for the column.
	 */
	public function add_action_column( $columns ) {
		if ( $this->get_copy_to_azure_setting() && $this->get_serve_from_azure_setting() ) {
			$columns['action'] = 'Action';
		}
		return $columns;
	}

	/**
	 * To add action field to media library
	 */
	public function add_action_field() {
		if ( isset( $GLOBALS['post'] ) && 'attachment' === $GLOBALS['post']->post_type ) {
			$form_fields['action'] = array(
				'label' => __( 'Action' ),
				'input' => 'html',
				'html' => ($this->get_attachment_azure_info( $GLOBALS['post']->ID ) ) ? $this->get_action_field( $GLOBALS['post']->ID ) : $this->get_copy_field( $GLOBALS['post']->ID ),
			);
		}
		return $form_fields;
	}

	/**
	 * Added action field to media library
	 *
	 * @param string $column_name the name for the column.
	 * @param int    $post_id the post id.
	 */
	function add_action_column_content( $column_name, $post_id ) {
		if ( 'action' === $column_name ) {
			if ( $this->get_attachment_azure_info( $post_id ) ) {
				echo $this->get_action_field( $post_id );
			} elseif ( $this->get_copy_to_azure_setting() ) {
				echo $this->get_copy_field( $post_id );
			}
		}
	}

	/**
	 * Added options to action field to media library
	 *
	 * @param int $post_id the post id.
	 */
	public function get_action_field( $post_id ) {
		ob_start(); ?>
			<div class="azure-actions">
				<button type="button" class="delete-from-azure" data-post="<?php echo esc_attr( $post_id ); ?>" >Delete from azure storage</button>
				<button type="button" style="display:none" class="copy-to-azure" data-post="<?php echo esc_attr( $post_id ); ?>" >copy to azure storage</button>
			</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Added options action field to media library
	 *
	 * @param int $post_id the post id.
	 */
	public function get_copy_field( $post_id ) {
		ob_start();
		?>
			<div class="azure-actions">
				<button type="button" class="copy-to-azure" data-post="<?php echo esc_attr( $post_id ); ?>" >copy to azure storage</button>
				<button type="button" style="display:none" class="delete-from-azure" data-post="<?php echo esc_attr( $post_id ); ?>" >Delete from azure storage</button>
			</div>
		<?php
			return ob_get_clean();
	}

	/**
	 * Add control to copy and remove files to media library
	 */
	public function add_js() {
		$req_post = sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'post' ) ) );
		$post = isset( $req_post ) ? get_post( $req_post ) : null;
		$is_media_edit_page = $post && 'attachment' === $post->post_type && 'post.php' === $GLOBALS['pagenow'];
		$is_media_listing_page = 'upload.php' === $GLOBALS['pagenow'];
		if ( $is_media_edit_page || $is_media_listing_page ) {
			$src = plugins_url( 'assets/js/media.js', $this->plugin_file_path );
			wp_enqueue_script( 'azure-media', $src, array() );
		?>
		<script type="text/javascript">
				AZURESettings = {
					'labels': {
						'copytoazure': '<?php echo  esc_attr( 'Copy To Azure' ); ?>',
						'deletefromazure': '<?php echo  esc_attr( 'Remove From Azure' ); ?>'
					}
				};
			</script>

		<?php
		}
	}

	/**
	 * Add control to copy and remove files to media library
	 */
	public function add_css() {
		$req_post = sanitize_text_field( wp_unslash( filter_input( INPUT_GET, 'post' ) ) );
		$post = isset( $req_post ) ? get_post( $req_post ) : null;
		$is_media_edit_page = $post && 'attachment' === $post->post_type && 'post.php' === $GLOBALS['pagenow'];
		$is_media_listing_page = 'upload.php' === $GLOBALS['pagenow'];
		if ( $is_media_edit_page || $is_media_listing_page ) {
			$src = plugins_url( 'assets/css/styles.css', $this->plugin_file_path );
			wp_enqueue_style( 'azure-styles', $src, array() );
		}
	}

	/**
	 * Copy single file to the azure storage from media library
	 */
	public function ajax_request_copy_file() {
		$post_id = sanitize_text_field( wp_unslash( filter_input( INPUT_POST, 'post_id' ) ) );

		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();
			switch_to_blog( $blod_id );
		}
		$data = $this->upload_attachment_to_azure_storage( $post_id, null, null, $blog_id );

		if ( is_multisite() ) {
			 restore_current_blog();
		}

		if ( ! is_wp_error( $data ) ) {
			$this->find_and_replace_content( $post_id );
		}
		if ( is_wp_error( $data ) ) {
			$out['success'] = 1;
			$out['error'] = $data->get_error_message();
		} else {
			$out['success'] = 1;
			$out['message'] = 'File copied to azure storage successfully.';
		}

		$this->end_ajax( $out );
	}

	/**
	 * Delete single post files from azure storage from media library
	 */
	public function ajax_request_delete_files() {
		$post_id = sanitize_text_field( wp_unslash( filter_input( INPUT_POST, 'post_id' ) ) );
		$out = $this->delete_files_from_azure( $post_id );
		$this->end_ajax( $out );
	}

	/**
	 * To delete image files from azure storage
	 *
	 * @param int $post_id The id of the post.
	 * @return the array of response whether file deleted or exception occured.
	 */
	public function delete_files_from_azure( $post_id ) {
		$file_details = $this->file_name_from_post_id( $post_id );
		if ( ! empty( $file_details ) ) {
			$container = $file_details['container'];
			foreach ( $file_details['file_names'] as $file ) {
				$response[] = $this->delete_from_azure( $file, $container );
			}

			if ( is_wp_error( $response[0] ) ) {
				$out['success'] = 1;
				$out['error'] = $response[0]->get_error_message();
			} else {
				delete_post_meta( $post_id, 'azurestorage_info' );
				$out['success'] = 1;
				$out['message'] = 'File deleted Successfully';
			}

			if ( ! is_wp_error( $out ) ) {
				$file_path = get_attached_file( $post_id, true );
				if ( ! file_exists( $file_path ) ) {
					wp_delete_post( $post_id, true );
				}
			}
			return $out;
		} else {
			// if not in azure storage.
			$out['success'] = 1;
			$out['message'] = 'Files are not in Azure Storage';
			return $out;
		}
	}

	/**
	 * Get file name from the post Id
	 *
	 * @param int $post_id the Id of the post.
	 * @return an array of file names attached with post.
	 */
	public function file_name_from_post_id( $post_id ) {
		if ( $this->is_attachment_served_by_azure( $post_id ) ) {
			$azurestorage = $this->is_attachment_served_by_azure( $post_id );
			$details = array();
			$details['container'] = $azurestorage['container'];
			$file_names = array();

			$file_names[] = $azurestorage['key'];
			// different sizes.
			if ( $azurestorage ) {
				foreach ( $azurestorage['sizes'] as $size => $file ) {
					$file_names[] = $file['file'];
				}
			}
			$details['file_names'] = $file_names;
			return $details;
		}
	}

	/**
	 * Delete blobs from azure storage
	 *
	 * @param string $file_name the name of file to be deleted from storage.
	 * @param string $container the container name from which the file should be deleted.
	 */
	public function delete_from_azure( $file_name, $container ) {
		try {
			$this->blobClient->deleteBlob( $container, $file_name );
		} catch ( Exception $e ) {
			$code = $e->getCode();
			$error_message = $e->getMessage();
			$error = $code . ': ' . $error_message;
			return new WP_Error( 'exception', $error );
		}
	}

	/**
	 * Bulk actions from media library
	 */
	public function ajax_request_bulk_actions() {
		$type = sanitize_text_field( wp_unslash( filter_input( INPUT_POST, 'method' ) ) );
		$posts = sanitize_text_field( wp_unslash( filter_input( INPUT_POST, 'post_ids' ) ) );
		$post_ids = explode( ';', $posts );
		$out = array();

		if ( 'copytoazure' === $type ) {
			// copy bulk posts to azure storage.
			foreach ( $post_ids as $post ) {
				$data[] = $this->upload_attachment_to_azure_storage( $post );
				if ( ! is_wp_error( $data ) ) {
					$this->find_and_replace_content( $post );
				}
			}

			foreach ( $data as $response ) {
				if ( is_wp_error( $response ) ) {
					$out['success'] = 1;
					$out['error'] = $response->get_error_message();
					break;
				} else {
					$out['success'] = 1;
					$out['message'] = 'File copied to azure storage successfully.';
				}
			}
		} elseif ( 'deletefromazure' === $type ) {
			// Delete bulk posts from azure storage.
			foreach ( $post_ids as $post ) {
				$data[] = $this->delete_files_from_azure( $post );
			}

			foreach ( $data as $response ) {
				if ( is_wp_error( $response ) ) {
					$out['success'] = 1;
					$out['error'] = $response->get_error_message();
					break;
				} else {
					$out['success'] = 1;
					$out['message'] = 'File deleted from azure storage successfully.';
				}
			}
		}

		$this->end_ajax( $out );
	}

	/**
	 * Replace file path if media is added inside into any post or page
	 *
	 * @param int     $post_id the value of post id.
	 * @param boolean $blog_id check for multisite.
	 */
	public function find_and_replace_content( $post_id, $blog_id = false ) {
		if ( $blog_id ) {
			switch_to_blog( $blog_id );
		}

		if ( $this->get_find_and_replace() ) {
			$azure  = new Azure_Storage_Services( __FILE__ );
			$end_protocol = $azure->get_access_end_prorocol();
			$cdn_end_point = $this->get_cdn_endpoint();

			$file_names = $this->file_name_from_post_id( $post_id );
			$container = $file_names['container'];

			$posts = array();
			foreach ( $file_names['file_names'] as $name ) {
				$query_images_args = array(
					'post_type'   => array( 'post', 'page' ),
					's' => $name,
				);
				$query_images = new WP_Query( $query_images_args );
				$post_result = $query_images->posts;
				if ( isset( $post_result ) ) {
					foreach ( $post_result as $post ) {
						$posts[] = $post;
					}
				}
			}

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					$content = $post->post_content;  // html content of the post.
					$doc = new DOMDocument();
					$doc->loadHTML( $content );
					$tags = $doc->getElementsByTagName( 'img' );  // get image tags exists inside post content.
					foreach ( $tags as $tag ) {
						$src = $tag->getAttribute( 'src' );
						$name = basename( $src );
						// check name matched with file name we search for.
						if ( in_array( $name, $file_names['file_names'], true ) ) {
							$new_src = $end_protocol . '://' . $cdn_end_point . '/' . $container . '/' . $name;
							$replaced_content = str_replace( $src, $new_src, $content );
							$post->post_content = $replaced_content;
							wp_update_post( $post );  // update post with new content.
						}
					}
				}
				// loop ends.
			}
		}

		if ( $blog_id ) {
			restore_current_blog();
		}
	}
}


