<?php
/**
 * Plugin Name: Envira Gallery
 * Plugin URI:  http://enviragallery.com
 * Description: Envira Gallery is best responsive WordPress gallery plugin.
 * Author:      Envira Gallery Team
 * Author URI:  http://enviragallery.com
 * Version:     1.5.9.2
 * Text Domain: envira-gallery
 * Domain Path: languages
 *
 * Envira Gallery is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Envira Gallery is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Envira Gallery. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package Envira
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 * @since 1.0.0
 *
 * @package Envira_Gallery
 * @author  Thomas Griffin
 */
class Envira_Gallery {

	/**
	 * Holds the class object.
	 *
	 * @since 1.0.0
	 *
	 * @var object
	 */
	public static $instance;

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $version = '1.5.9.2';

	/**
	 * The name of the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $plugin_name = 'Envira Gallery';

	/**
	 * Unique plugin slug identifier.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $plugin_slug = 'envira-gallery';

	/**
	 * Plugin file.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $file = __FILE__;

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		if ( is_plugin_active( 'envira-albums/envira-albums.php' ) ) {
			// We need to define a constant so Albums can still work
			if ( ! defined( 'ENVIRA_STANDALONE_PLUGIN_NAME' ) ) {
				define( 'ENVIRA_STANDALONE_PLUGIN_NAME', 'Envira Gallery - Standalone Integrated' );
			}
		}

		// Fire a hook before the class is setup.
		do_action( 'envira_gallery_pre_init' );

		// Load the plugin textdomain.
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

		// Load the plugin widget.
		add_action( 'widgets_init', array( $this, 'widget' ) );

		// Load the plugin.
		add_action( 'init', array( $this, 'init' ), 0 );

		add_action( 'pre_get_posts', array( $this, 'standalone_pre_get_posts' ) );
		add_action( 'wp_head', array( $this, 'standalone_maybe_insert_shortcode' ) );

		if ( class_exists( 'Envira_Albums' ) && version_compare( Envira_Albums::get_instance()->version, '1.3.1', '<' ) ) {
			// this is for old versions of albums
			add_action( 'pre_get_posts', array( $this, 'envira_albums_standalone_pre_get_posts' ) );
			add_action( 'wp_head', array( $this, 'envira_albums_standalone_maybe_insert_shortcode' ) );
			add_filter( 'envira_albums_post_type_args', array( $this, 'envira_albums_post_type' ) );
			add_filter( 'envira_albums_metabox_ids', array( $this, 'envira_standalone_slug_box' ) );
		}

	}

	/**
	 * Loads the plugin textdomain for translation.
	 *
	 * @since 1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain( $this->plugin_slug, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	}

	/**
	 * Registers the Envira Gallery widget.
	 *
	 * @since 1.0.0
	 */
	public function widget() {

		register_widget( 'Envira_Gallery_Widget' );

	}

	/**
	 * Loads the plugin into WordPress.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// Load global components.
		$this->require_global();

		// Load admin only components.
		if ( is_admin() ) {
			$this->check_installation();
			$this->require_admin();
			$this->require_updater();
		}

		// Run hook once Envira has been initialized.
		do_action( 'envira_gallery_init' );

		$standalone = get_option( 'envira_gallery_standalone_enabled' );

		//Make sure standalone has an option set
		if ( ! isset( $standalone ) ){

			update_option( 'envira_gallery_standalone_enabled', true );

		}

		if ( get_option( 'envira_gallery_standalone_enabled' ) ) {
			if ( ! get_option( 'envira-standalone-flushed' ) ) {
				// Flush rewrite rules.
				flush_rewrite_rules();
				// Set flag = true in options
				update_option( 'envira-standalone-flushed', true );
			}
		}

		add_filter( 'single_template', array( $this, 'standalone_get_custom_template' ), 99 );

		// Add hook for when Envira has loaded.
		do_action( 'envira_gallery_loaded' );

	}

	/**
	 * Display a nag notice if the user still has Lite activated, or they're on PHP < 5.3
	 *
	 * @since 1.3.8.2
	 */
	public function check_installation() {

		if ( class_exists( 'Envira_Gallery_Lite' ) ) {
			add_action( 'admin_notices', array( $this, 'lite_notice' ) );
		}

		if ( (float) phpversion() < 5.3 ) {
			add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
		}

		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		// Check if supersize plugin is active
		if ( is_plugin_active( 'envira-supersize/envira-supersize.php' ) ) {
			set_transient( 'envira_supersize_notice', true, 12 * HOUR_IN_SECONDS );
			deactivate_plugins( 'envira-supersize/envira-supersize.php' );
		}

		if ( ! empty( $_REQUEST['close_supersize_notice'] ) ) {
			delete_transient( 'envira_supersize_notice' );
		}

		if ( get_transient( 'envira_supersize_notice' ) ) {
			add_action( 'admin_notices', array( $this, 'supersize_notice' ) );
		}

		// Check if standalone plugin is active
		if ( is_plugin_active( 'envira-standalone/envira-standalone.php' ) ) {
			update_option( 'envira_gallery_standalone_enabled', true );
			set_transient( 'envira_standalone_notice', true, 12 * HOUR_IN_SECONDS );
			deactivate_plugins( 'envira-standalone/envira-standalone.php' );
		}

		if ( ! empty( $_REQUEST['close_standalone_notice'] ) ) {
			delete_transient( 'envira_standalone_notice' );
		}

		if ( get_transient( 'envira_standalone_notice' ) ) {
			add_action( 'admin_notices', array( $this, 'standalone_notice' ) );
		}

	}

	/**
	 * Output a nag notice if the user has supersize activated
	 *
	 * @since 1.5.7.2
	 */
	public function supersize_notice() {

		?>
		<div class="notice notice-error" style="position: relative;padding-right: 38px;">
			<p><?php _e( 'The Supersize addon was detected on your system. All features have been merged directly into Envira Gallery, so it is no longer necessary. It has been deactivated.', 'envira-gallery' ); ?></p>
			<a href="<?php echo add_query_arg( 'close_supersize_notice', 'true' ); ?>"><button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php _e( 'Dismiss this notice.', 'envira-gallery' ); ?></span></button></a>
		</div>
		<?php

	}

	/**
	 * Output a nag notice if the user has standalone activated
	 *
	 * @since 1.5.7.2
	 */
	public function standalone_notice() {

		?>
		<div class="notice notice-error" style="position: relative;padding-right: 38px;">
			<p><?php _e( 'The Standalone addon was detected on your system. All features have been merged directly into Envira Gallery, so it is no longer necessary. It has been deactivated.', 'envira-gallery' ); ?></p>
			<a href="<?php echo add_query_arg( 'close_standalone_notice', 'true' ); ?>"><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></a>
		</div>
		<?php

	}

	/**
	 * Output a nag notice if the user has both Lite and Pro activated
	 *
	 * @since 1.3.8.2
	 */
	public function lite_notice() {

		?>
		<div class="error">
			<p><?php _e( 'Please <a href="plugins.php">deactivate</a> the Envira Lite Plugin. Your premium version of Envira Gallery may not work as expected until the Lite version is deactivated.', 'envira-gallery' ); ?></p>
		</div>
		<?php

	}

	/**
	 * Output a nag notice if the user has a PHP version older than 5.3
	 *
	 * @since 1.4.1.6
	 */
	function php_version_notice() {

		?>
		<div class="error">
			<p><?php _e( 'Envira Gallery requires PHP 5.3 or greater for some specific functionality. Please have your web host resolve this.', 'envira-gallery' ); // WPCS: XSS OK. ?></p>
		</div>
		<?php

	}

	/**
	 * Loads all admin related files into scope.
	 *
	 * @since 1.0.0
	 */
	public function require_admin() {

		require plugin_dir_path( __FILE__ ) . 'includes/admin/ajax.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/capabilities.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/common.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/editor.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/export.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/import.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/license.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/media.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/media-view.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/metaboxes.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/notice.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/posttype.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/settings.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/table.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/addons.php';
		require plugin_dir_path( __FILE__ ) . 'includes/admin/review.php';

	}

	/**
	 * Loads a partial view for the Administration screen
	 *
	 * @since 1.5.0
	 *
	 * @param 	string 	$template 	PHP file at includes/admin/partials, excluding file extension
	 * @param 	array 	$data 		Any data to pass to the view
	 * @return 	void
	 */
	public function load_admin_partial( $template, $data = array() ){

		$dir = trailingslashit( plugin_dir_path( __FILE__ ) . 'includes/admin/partials' );

		if ( file_exists( $dir . $template . '.php' ) ) {
			require_once(  $dir . $template . '.php' );
			return true;
		}

		return false;

	}

	/**
	 * Loads all updater related files and functions into scope.
	 *
	 * @since 1.0.0
	 *
	 * @return null Return early if the license key is not set or there are key errors.
	 */
	public function require_updater() {

		// Retrieve the license key. If it is not set, return early.
		$key = $this->get_license_key();
		if ( ! $key ) {
			return;
		}

		// If there are any errors with the key itself, return early.
		if ( $this->get_license_key_errors() ) {
			return;
		}

		// Load the updater class.
		require plugin_dir_path( __FILE__ ) . 'includes/admin/updater.php';

		// Go ahead and initialize the updater.
		$args = array(
			'plugin_name' => $this->plugin_name,
			'plugin_slug' => $this->plugin_slug,
			'plugin_path' => plugin_basename( __FILE__ ),
			'plugin_url'  => trailingslashit( WP_PLUGIN_URL ) . $this->plugin_slug,
			'remote_url'  => 'http://enviragallery.com/',
			'version'     => $this->version,
			'key'         => $key,
		);

		$updater = new Envira_Gallery_Updater( $args );

		// Fire a hook for Addons to register their updater since we know the key is present.
		do_action( 'envira_gallery_updater', $key );

	}

	/**
	 * Loads all global files into scope.
	 *
	 * @since 1.0.0
	 */
	public function require_global() {

		require plugin_dir_path( __FILE__ ) . 'includes/global/common.php';
		require plugin_dir_path( __FILE__ ) . 'includes/global/posttype.php';
		require plugin_dir_path( __FILE__ ) . 'includes/global/shortcode.php';
		require plugin_dir_path( __FILE__ ) . 'includes/global/widget.php';

	}

	/**
	 * Overrides the template for the 'envira' custom post type if user has requested a different template in settings
	 *
	 * @since 1.5.7.3
	 *
	 * @param object $post The current post object.
	 */
	public function standalone_get_custom_template( $single_template ) {

		if ( ! get_option( 'envira_gallery_standalone_enabled' ) ) {
			return $single_template;
		}

		global $post;

		if ($post->post_type != 'envira') { return $single_template; }

		// check settings, if the user hasn't selected a custom template to override single.php, then go no further

		// $instance = Envira_Gallery_Metaboxes::get_instance();
		// $template = $instance->get_config( 'standalone_template', $instance->get_config_default( 'standalone_template' ) );

		$data = get_post_meta( $post->ID, '_eg_gallery_data', true );

		if ( !$data ) { return $single_template; }

		if ( !empty( $data['config']['standalone_template'] ) ) {
			$user_template = $data['config']['standalone_template'];
			// get path to current folder
			$new_template = locate_template( $user_template );
			if ( !file_exists( $new_template ) ) :
				// if it does not exist, then let's keep the default
				return $single_template;
			endif;
		} else {
			return $single_template;
		}

		return $new_template;
	}

	/**
	 * Returns a gallery based on ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id     The gallery ID used to retrieve a gallery.
	 * @return array|bool Array of gallery data or false if none found.
	 */
	public function get_gallery( $id ) {

		// Attempt to return the transient first, otherwise generate the new query to retrieve the data.
		if ( false === ( $gallery = get_transient( '_eg_cache_' . $id ) ) ) {
			$gallery = $this->_get_gallery( $id );
			if ( $gallery ) {
				$expiration = Envira_Gallery_Common::get_instance()->get_transient_expiration_time();
				set_transient( '_eg_cache_' . $id, $gallery, $expiration );
			}
		}

		// Return the gallery data.
		return $gallery;

	}

	/**
	 * Internal method that returns a gallery based on ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id     The gallery ID used to retrieve a gallery.
	 * @return array|bool Array of gallery data or false if none found.
	 */
	public function _get_gallery( $id ) {

		$meta = get_post_meta( $id, '_eg_gallery_data', true );

		/**
		* Version 1.2.1+: Check if $meta has a value - if not, we may be using a Post ID but the gallery
		* has moved into the Envira CPT
		*/
		if ( empty( $meta ) ) {
			$gallery_id = get_post_meta( $id, '_eg_gallery_id', true );
			$meta = get_post_meta( $gallery_id, '_eg_gallery_data', true );
		}

		return $meta;

	}

	/**
	 * Returns the number of images in a gallery.
	 *
	 * @since 1.2.1
	 *
	 * @param int $id The gallery ID used to retrieve a gallery.
	 * @return int    The number of images in the gallery.
	 */
	public function get_gallery_image_count( $id ) {

		$gallery = get_post_meta( $id, '_eg_gallery_data', true );
		return ( isset( $gallery['gallery'] ) ? count( $gallery['gallery'] ) : 0 );

	}

	/**
	 * Returns a gallery based on slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The gallery slug used to retrieve a gallery.
	 * @return array|bool  Array of gallery data or false if none found.
	 */
	public function get_gallery_by_slug( $slug ) {

		// Attempt to return the transient first, otherwise generate the new query to retrieve the data.
		if ( false === ( $gallery = get_transient( '_eg_cache_' . $slug ) ) ) {
			$gallery = $this->_get_gallery_by_slug( $slug );
			if ( $gallery ) {
				$expiration = Envira_Gallery_Common::get_instance()->get_transient_expiration_time();
				set_transient( '_eg_cache_' . $slug, $gallery, $expiration );
			}
		}

		// Return the gallery data.
		return $gallery;

	}

	/**
	 * Internal method that returns a gallery based on slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The gallery slug used to retrieve a gallery.
	 * @return array|bool  Array of gallery data or false if none found.
	 */
	public function _get_gallery_by_slug( $slug ) {

		// Get Envira CPT by slug.
		$galleries = new WP_Query( array(
			'post_type' 	=> 'envira',
			'name' 			=> $slug,
			'fields'        => 'ids',
			'posts_per_page' => 1,
		) );
		if ( $galleries->posts ) {
			return get_post_meta( $galleries->posts[0], '_eg_gallery_data', true );
		}

		// Get Envira CPT by meta-data field (yeah this is an edge case dealing with slugs in shortcode and modified slug in the misc tab of the gallery).
		$galleries = new WP_Query( array(
			'post_type' 	=> 'envira',
			'meta_key' 		=> 'envira_gallery_slug',
			'meta_value' 	=> $slug,
			'fields'        => 'ids',
			'posts_per_page' => 1,
		) );
		if ( $galleries->posts ) {
			return get_post_meta( $galleries->posts[0], '_eg_gallery_data', true );
		}


		// If nothing found, get Envira CPT by _eg_gallery_old_slug.
		// This covers Galleries migrated from Pages/Posts --> Envira CPTs.
		$galleries = new WP_Query( array(
			'post_type'     => 'envira',
			'no_found_rows' => true,
			'cache_results' => false,
			'fields'        => 'ids',
			'meta_query'    => array(
				array(
					'key'	=> '_eg_gallery_old_slug',
					'value'	=> $slug,
				),
			),
			'posts_per_page' => 1,
		) );
		if ( $galleries->posts ) {
			return get_post_meta( $galleries->posts[0], '_eg_gallery_data', true );
		}

		// No galleries found.
		return false;

	}

	/**
	 * Returns all galleries created on the site.
	 *
	 * @since 1.0.0
	 *
	 * @param 	bool 		$skip_empty 		Skip empty sliders.
	 * @param 	bool 		$ignore_cache 		Ignore Transient cache.
	 * @param 	string 		$search_terms 		Search for specified Galleries by Title
	 *
	 * @return array|bool 					Array of gallery data or false if none found.
	 */
	public function get_galleries( $skip_empty = true, $ignore_cache = false, $search_terms = '' ) {

		// Attempt to return the transient first, otherwise generate the new query to retrieve the data.
		if ( $ignore_cache || ! empty( $search_terms ) || false === ( $galleries = get_transient( '_eg_cache_all' ) ) ) {
			$galleries = $this->_get_galleries( $skip_empty, $search_terms );

			// Cache the results if we're not performing a search and we have some results
			if ( $galleries && empty( $search_terms ) ) {
				$expiration = Envira_Gallery_Common::get_instance()->get_transient_expiration_time();
				set_transient( '_eg_cache_all', $galleries, $expiration );
			}
		}

		// Return the gallery data.
		return $galleries;

	}

	/**
	 * Internal method that returns all galleries created on the site.
	 *
	 * @since 1.0.0
	 *
	 * @param bool 		$skip_empty 	Skip Empty Galleries.
	 * @param string 	$search_terms 	Search for specified Galleries by Title
	 * @return mixed 					Array of gallery data or false if none found.
	 */
	public function _get_galleries( $skip_empty = true, $search_terms = '' ) {

		// Build WP_Query arguments.
		$args = array(
			'post_type'     => 'envira',
			'post_status'   => 'publish',
			'posts_per_page'=> 99,
			'no_found_rows' => true,
			'fields'        => 'ids',
			'meta_query'    => array(
				array(
					'key'   => '_eg_gallery_data',
					'compare' => 'EXISTS',
				),
			),
		);

		// If search terms exist, add a search parameter to the arguments.
		if ( ! empty( $search_terms ) ) {
			$args['s'] = $search_terms;
		}

		// Run WP_Query.
		$galleries = new WP_Query( $args );
		if ( ! isset( $galleries->posts ) || empty( $galleries->posts ) ) {
			return false;
		}

		// Now loop through all the galleries found and only use galleries that have images in them.
		$ret = array();
		foreach ( $galleries->posts as $id ) {
			$data = get_post_meta( $id, '_eg_gallery_data', true );

			// Skip empty galleries.
			if ( $skip_empty && empty( $data['gallery'] ) ) {
				continue;
			}

			// Skip default/dynamic gallery types.
			$type = Envira_Gallery_Shortcode::get_instance()->get_config( 'type', $data );
			if ( 'defaults' === Envira_Gallery_Shortcode::get_instance()->get_config( 'type', $data ) || 'dynamic' === Envira_Gallery_Shortcode::get_instance()->get_config( 'type', $data ) ) {
				continue;
			}

			// Add gallery to array of galleries.
			$ret[] = $data;
		}

		// Return the gallery data.
		return $ret;

	}

	/**
	 * Returns the license key for Envira.
	 *
	 * @since 1.0.0
	 *
	 * @return string $key The user's license key for Envira.
	 */
	public function get_license_key() {

		$option = get_option( 'envira_gallery' );
		$key    = false;
		if ( empty( $option['key'] ) ) {
			if ( defined( 'ENVIRA_LICENSE_KEY' ) ) {
				$key = ENVIRA_LICENSE_KEY;
			}
		} else {
			$key = $option['key'];
		}

		return apply_filters( 'envira_gallery_license_key', $key );

	}

	/**
	 * Returns the license key type for Envira.
	 *
	 * @since 1.0.0
	 *
	 * @return string $type The user's license key type for Envira.
	 */
	public function get_license_key_type() {

		$option = get_option( 'envira_gallery' );
		return $option['type'];

	}

	/**
	 * Returns possible license key error flag.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if there are license key errors, false otherwise.
	 */
	public function get_license_key_errors() {

		$option = get_option( 'envira_gallery' );
		return isset( $option['is_expired'] ) && $option['is_expired'] || isset( $option['is_disabled'] ) && $option['is_disabled'] || isset( $option['is_invalid'] ) && $option['is_invalid'];

	}

	/**
	 * Loads the default plugin options.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of default plugin options.
	 */
	public static function default_options() {

		$ret = array(
			'key'         => '',
			'type'        => '',
			'is_expired'  => false,
			'is_disabled' => false,
			'is_invalid'  => false,
		);

		return apply_filters( 'envira_gallery_default_options', $ret );

	}
	/**
	 * Utility function for debugging
	 *
	 * @access public
	 * @param array $array (default: array())
	 * @return void
	 * @since 1.5.8
	 */
	function pretty_print( $array = array() ){

		echo '<pre>';

		print_r( $array );

		echo '</pre>';


	}
	/**
	 * Run Gallery/Album Query if on an Envira Gallery or Album
	 *
	 * @since 1.5.7.3
	 *
	 * @param object $query The query object passed by reference.
	 * @return null         Return early if in admin or not the main query or not a single post.
	 */
	public function standalone_pre_get_posts( $query ) {

		// Return early if in the admin, not the main query or not a single post.
		if ( ! get_option( 'envira_gallery_standalone_enabled' ) || is_admin() || ! $query->is_main_query() || ! $query->is_single() ) {
			return;
		}

		// If not the proper post type (Envira), return early.
		$post_type = get_query_var( 'post_type' );

		if ( 'envira' == $post_type ) {
			do_action( 'envira_standalone_gallery_pre_get_posts', $query );
		}

	}

	/**
	 * Maybe inserts the Envira shortcode into the content for the page being viewed.
	 *
	 * @since 1.5.7.3
	 *
	 * @return null         Return early if in admin or not the main query or not a single post.
	*/
	public function standalone_maybe_insert_shortcode() {

		// Check we are on a single Post
		if ( ! get_option( 'envira_gallery_standalone_enabled' ) || ! is_singular() ) {
			return;
		}

		// If not the proper post type (Envira), return early.
		$post_type = get_query_var( 'post_type' );

		if ( 'envira' == $post_type ) {
			add_filter( 'the_content', array( $this, 'envira_standalone_insert_gallery_shortcode' ) );
		}

	}

	/**
	 * Inserts the Envira Gallery shortcode into the content for the page being viewed.
	 *
	 * @since 1.5.7.3
	 *
	 * @global object $wp_query The current query object.
	 * @param string $content   The content to be filtered.
	 * @return string $content  Amended content with our gallery shortcode prepended.
	 */
	public function envira_standalone_insert_gallery_shortcode( $content ) {

		// Display the gallery based on the query var available.
		$id = get_query_var( 'p' );
		if ( empty( $id ) ) {
			// _get_gallery_by_slug() performs a LIKE search, meaning if two or more
			// Envira Galleries contain the slug's word in *any* of the metadata, the first
			// is automatically assumed to be the 'correct' gallery
			// For standalone, we already know precisely which gallery to display, so
			// we can use its post ID.
			global $post;
			$id = $post->ID;
		}

		$shortcode = '[envira-gallery id="' . $id . '"]';

		return $shortcode . $content;

	}

	/**
	 * Run Album Query if on an Envira Gallery or Album
	 *
	 * @since 1.3.0.11
	 *
	 * @param object $query The query object passed by reference.
	 * @return null         Return early if in admin or not the main query or not a single post.
	 */
	public function envira_albums_standalone_pre_get_posts( $query ) {

		// Return early if in the admin, not the main query or not a single post.
		if ( ! get_option( 'envira_gallery_standalone_enabled' ) || is_admin() || ! $query->is_main_query() || ! $query->is_single() ) {
			return;
		}

		// If not the proper post type (Envira), return early.
		$post_type = get_query_var( 'post_type' );

		if ( 'envira_album' == $post_type ) {
			do_action( 'envira_standalone_album_pre_get_posts', $query );
		}

	}

	/**
	 * Maybe inserts the Envira shortcode into the content for the page being viewed.
	 *
	 * @since 1.3.0.11
	 *
	 * @return null         Return early if in admin or not the main query or not a single post.
	*/
	public function envira_albums_standalone_maybe_insert_shortcode() {

		// Check we are on a single Post
		if ( ! get_option( 'envira_gallery_standalone_enabled' ) || ! is_singular() ) {
			return;
		}

		// If not the proper post type (Envira), return early.
		$post_type = get_query_var( 'post_type' );

		if ( 'envira_album' == $post_type ) {
			add_filter( 'the_content', array( $this, 'envira_standalone_insert_album_shortcode' ) );
		}

	}

	/**
	 * Modifies the Envira Albums post type so that it is visible to the public.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args  Default post type args.
	 * @return array $args Amended array of default post type args.
	 */
	public function envira_albums_post_type( $args ) {

		// Get slug
		$slug = $this->envira_albums_standalone_get_slug( 'albums' );

		// Change the default post type args so that it can be publicly accessible.
		$args['rewrite']   = array( 'with_front' => false, 'slug' => $slug );
		$args['query_var'] = true;
		$args['public']    = true;
		$args['supports'][] = 'slug';

		return apply_filters( 'envira_standalone_post_type_args', $args );

	}

	/**
	 * Gets the slug from the options table. If blank or does not exist, defaults
	 * to 'envira'
	 *
	 * @since 1.0.1
	 *
	 * @param string $type Type (gallery|albums)
	 * @return string $slug Slug.
	 */
	public function envira_albums_standalone_get_slug( $type ) {

		// Get slug
		switch ($type) {
			case 'gallery':
				$slug = get_option( 'envira-gallery-slug');
				if ( !$slug OR empty( $slug ) ) {
					// Fallback to check for previous version option name.
					$slug = get_option( 'envira_standalone_slug' );
					if ( ! $slug || empty( $slug ) ) {
						$slug = 'envira';
					}
				}
				break;

			case 'albums':
				$slug = get_option( 'envira-albums-slug');
				if ( !$slug OR empty( $slug ) ) {
					$slug = 'envira_album';
				}
				break;

			default:
				$slug = 'envira'; // Fallback
				break;
		}

		return $slug;
	}


	/**
	 * Allows the following metaboxes to be output for managing gallery and album post names:
	 * - slugdiv
	 * - wpseo_meta
	 *
	 * @since 1.0.0
	 *
	 * @param array $ids  Default metabox IDs to allow.
	 * @return array $ids Amended metabox IDs to allow.
	 */
	public function envira_standalone_slug_box( $ids ) {

		$ids[] = 'slugdiv';
		$ids[] = 'authordiv';
		$ids[] = 'wpseo_meta';

		return $ids;

	}

	/**
	 * Inserts the Envira Album shortcode into the content for the page being viewed.
	 *
	 * @since 1.3.0.11
	 *
	 * @global object $wp_query The current query object.
	 * @param string $content   The content to be filtered.
	 * @return string $content  Amended content with our gallery shortcode prepended.
	 */
	public function envira_standalone_insert_album_shortcode( $content ) {

		// Display the album based on the query var available.
		$id = get_query_var( 'p' );
		if ( empty( $id ) ) {
			// _get_album_by_slug() performs a LIKE search, meaning if two or more
			// Envira Albums contain the slug's word in *any* of the metadata, the first
			// is automatically assumed to be the 'correct' album
			// For standalone, we already know precisely which album to display, so
			// we can use its post ID.
			global $post;
			$id = $post->ID;
		}

		$shortcode = '[envira-album id="' . $id . '"]';

		return $shortcode . $content;

	}

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @since 1.0.0
	 *
	 * @return object The Envira_Gallery object.
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Envira_Gallery ) ) {
			self::$instance = new Envira_Gallery();
		}

		return self::$instance;

	}
}

register_activation_hook( __FILE__, 'envira_gallery_activation_hook' );
/**
 * Fired when the plugin is activated.
 *
 * @since 1.0.0
 *
 * @global int $wp_version      The version of WordPress for this install.
 * @global object $wpdb         The WordPress database object.
 * @param boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false otherwise.
 */
function envira_gallery_activation_hook( $network_wide ) {

	global $wp_version;
	if ( version_compare( $wp_version, '4.0.0', '<' ) && ! defined( 'ENVIRA_FORCE_ACTIVATION' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( sprintf( __( 'Sorry, but your version of WordPress does not meet Envira Gallery\'s required version of <strong>4.0.0</strong> to run properly. The plugin has been deactivated. <a href="%s">Click here to return to the Dashboard</a>.', 'envira-gallery' ), get_admin_url() ) );
	}

	$instance = Envira_Gallery::get_instance();

	if ( is_multisite() && $network_wide ) {
		$site_list = wp_get_sites();
		foreach ( (array) $site_list as $site ) {
			switch_to_blog( $site['blog_id'] );

			// Set default license option.
			$option = get_option( 'envira_gallery' );
			if ( ! $option || empty( $option ) ) {
				update_option( 'envira_gallery', Envira_Gallery::default_options() );
			}

			restore_current_blog();
		}
	} else {
		// Set default license option.
		$option = get_option( 'envira_gallery' );
		if ( ! $option || empty( $option ) ) {
			update_option( 'envira_gallery', Envira_Gallery::default_options() );
		}
	}

}

register_deactivation_hook( __FILE__, 'envira_gallery_deactivation_hook' );
/**
 * Fired when the plugin is deactivated to clear flushed permalinks flag and flush the permalinks.
 *
 * @since 1.5.7.2
 *
 * @param boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false otherwise.
 */
function envira_gallery_deactivation_hook( $network_wide ) {

	// Flush rewrite rules
	flush_rewrite_rules();

	// Set flag = false in options
	update_option( 'envira-standalone-flushed', false );

}

register_uninstall_hook( __FILE__, 'envira_gallery_uninstall_hook' );
/**
 * Fired when the plugin is uninstalled.
 *
 * @since 1.0.0
 *
 * @global object $wpdb The WordPress database object.
 */
function envira_gallery_uninstall_hook() {

	$instance = Envira_Gallery::get_instance();

	if ( is_multisite() ) {
		$site_list = wp_get_sites();
		foreach ( (array) $site_list as $site ) {
			switch_to_blog( $site['blog_id'] );
			delete_option( 'envira_gallery' );
			restore_current_blog();
		}
	} else {
		delete_option( 'envira_gallery' );
	}

}

// Load the main plugin class.
$envira_gallery = Envira_Gallery::get_instance();

// Conditionally load the template tag.
if ( ! function_exists( 'envira_gallery' ) ) {
	/**
	 * Primary template tag for outputting Envira galleries in templates.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $id 		The ID of the gallery to load.
	 * @param string $type    	The type of field to query.
	 * @param array  $args     	Associative array of args to be passed.
	 * @param bool   $return    Flag to echo or return the gallery HTML.
	 */
	function envira_gallery( $id, $type = 'id', $args = array(), $return = false ) {

		// If we have args, build them into a shortcode format.
		$args_string = '';
		if ( ! empty( $args ) ) {
			foreach ( (array) $args as $key => $value ) {
				$args_string .= ' ' . $key . '="' . $value . '"';
			}
		}

		// Build the shortcode.
		$shortcode = ! empty( $args_string ) ? '[envira-gallery ' . $type . '="' . $id . '"' . $args_string . ']' : '[envira-gallery ' . $type . '="' . $id . '"]';

		// Return or echo the shortcode output.
		if ( $return ) {
			return do_shortcode( $shortcode );
		} else {
			echo do_shortcode( $shortcode );
		}

	}
}
if ( ! function_exists( 'envira_mobile_detect' ) ) {

	/**
	 * Holder for mobile detect.
	 *
	 * @access public
	 * @return void
	 */
	function envira_mobile_detect(){

		if ( ! class_exists( 'Mobile_Detect' ) ){

			require_once trailingslashit( plugin_dir_path( __FILE__ ) ) . 'includes/global/Mobile_Detect.php';

		}

		return new Mobile_Detect;

	}

}

if ( ! function_exists( 'array_replace' ) ) {

	/**
	 * Emulate PHP 5.3 function
	 *
	 * @return type array
	 */
	function array_replace() {
		$arrays = func_get_args();
		for ( $i = 1; $i < count( $arrays ); $i++ ) {
			if ( ! is_array( $arrays[ $i ] ) ) {
				continue;
			}
			foreach ( $arrays[ $i ] as $key => $value ) {
				$arrays[0][ $key ] = $value;
			}
		}
		return $arrays[0];
	}

}
