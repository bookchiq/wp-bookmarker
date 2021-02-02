<?php
/**
 * Plugin Name: WP Bookmarker
 * Description: Convert your blog into a personal social bookmarking service.
 * Version: 0.2
 * Author: Sarah Lewis and Anthony Lopez-Vito
 * Author URI: http://anothercoffee.net
 *
 * @package wp_bookmarker
 */

/**
 * For security as specified in
 * http://codex.wordpress.org/Writing_a_Plugin
 */
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Define useful constants.
 */

// Define global constants based on the plugin name, version, and text domain.
$plugin_data = get_file_data(
	__FILE__,
	array(
		'name'    => 'Plugin Name',
		'version' => 'Version',
		'text'    => 'Text Domain',
	)
);

define( 'WP_BOOKMARKER_VERSION', $plugin_data['version'] );
define( 'WP_BOOKMARKER_REQUIRED_WP_VERSION', '4.0' );
define( 'WP_BOOKMARKER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WP_BOOKMARKER_PLUGIN_NAME', $plugin_data['name'] );
define( 'WP_BOOKMARKER_PLUGIN_SLUG', dirname( WP_BOOKMARKER_PLUGIN_BASENAME ) );
define( 'WP_BOOKMARKER_PLUGIN_DIR', untrailingslashit( dirname( __FILE__ ) ) );
define( 'WP_BOOKMARKER_PLUGIN_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );
define( 'WP_BOOKMARKER_PLUGIN_MODULES_DIR', WP_BOOKMARKER_PLUGIN_DIR . '/modules' );
define( 'WP_BOOKMARKER_POST_TYPE', 'bookmarker' );
define( 'WP_BOOKMARKER_TAXONOMY', 'bookmarker-tag' );
define( 'WP_BOOKMARKER_BOOKMARK_SINGLE_TEMPLATE', '/single-bookmark.php' );
define( 'WP_BOOKMARKER_BOOKMARK_ARCHIVE_TEMPLATE', '/archive-bookmarks.php' );

// Include useful utilities.
require_once WP_BOOKMARKER_PLUGIN_DIR . '/includes/debug.php';
require_once WP_BOOKMARKER_PLUGIN_DIR . '/includes/logger.php'; // Add logging for longer-term plugin information.


/**
 * The core of our plugin.
 */
class WPBookmarker {
	/**
	 * Fired when plugin file is loaded.
	 */
	public function __construct() {
		// Activation and deactivation procedures.
		register_activation_hook( __FILE__, array( $this, 'activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );

		add_action( 'init', array( $this, 'register_bookmark_cpt' ) );
		add_action( 'init', array( $this, 'register_bookmark_tags_taxonomy' ) );

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'save_post', array( $this, 'save_url' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_menu', array( $this, 'admin_add_page' ) );

		add_filter( 'single_template', array( $this, 'single_template' ) );
		add_filter( 'archive_template', array( $this, 'get_custom_post_type_template' ) );
		add_filter( 'get_the_archive_title', array( $this, 'custom_title_for_archive' ) );
		add_filter( 'private_title_format', array( $this, 'remove_private_protected_from_titles' ) );
	}

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public function activation() {
		inspect( 'Plugin activated' );
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivation() {
		inspect( 'Plugin deactivated' );
	}

	/**
	 * Register the Bookmark Custom Post Type
	 *
	 * @return void
	 */
	public function register_bookmark_cpt() {
		$labels = array(
			'name'               => _x( 'Bookmark', 'wp-bookmarker' ),
			'singular_name'      => _x( 'Bookmark', 'wp-bookmarker' ),
			'add_new'            => _x( 'Add New Bookmark', 'wp-bookmarker' ),
			'add_new_item'       => _x( 'Add New Bookmark', 'wp-bookmarker' ),
			'edit_item'          => _x( 'Edit Bookmark', 'wp-bookmarker' ),
			'new_item'           => _x( 'New Bookmark', 'wp-bookmarker' ),
			'view_item'          => _x( 'View Bookmark', 'wp-bookmarker' ),
			'search_items'       => _x( 'Search Bookmarks', 'wp-bookmarker' ),
			'not_found'          => _x( 'No bookmarks found', 'wp-bookmarker' ),
			'not_found_in_trash' => _x( 'No bookmarks found in Trash', 'wp-bookmarker' ),
			'parent_item_colon'  => _x( 'Parent Position:', 'wp-bookmarker' ),
			'menu_name'          => _x( 'Bookmarks', 'wp-bookmarker' ),
		);

		$args = array(
			'labels'              => $labels,
			'hierarchical'        => true,
			'description'         => 'Bookmark',
			'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'revisions' ),
			'taxonomies'          => array( 'tags' ),
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-book-alt',
			'show_in_nav_menus'   => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => false,
			'has_archive'         => true,
			'query_var'           => true,
			'can_export'          => true,
			'rewrite'             => true,
			'capability_type'     => 'post',
		);

		register_post_type( WP_BOOKMARKER_POST_TYPE, $args );
	}

	/**
	 * Create the taxonomy as non-hierarchical tags.
	 *
	 * @return void
	 */
	public function register_bookmark_tags_taxonomy() {
		register_taxonomy(
			WP_BOOKMARKER_TAXONOMY,
			WP_BOOKMARKER_POST_TYPE,
			array(
				'hierarchical' => false,
				'label'        => 'Bookmark Tags',
				'query_var'    => true,
				'rewrite'      => array(
					'slug'       => WP_BOOKMARKER_TAXONOMY,
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Create the URL metabox and settings.
	 */
	public function admin_init() {
		add_meta_box( 'bookmark_url_meta', 'URL', array( $this, 'bookmark_url_metabox_html' ), WP_BOOKMARKER_POST_TYPE, 'normal', 'low' );

		register_setting(
			'wp_bookmarker_options_group',
			'wp_bookmarker_option_name',
			'wp_bookmarker_options_validate'
		);
		add_settings_section(
			'wp_bookmarker_main',
			'Bookmarklet settings',
			'__return_empty_string',
			'wp_bookmarker_settings'
		);
		add_settings_field(
			'wp_bookmarker_bookmarklet',
			'Bookmarklet',
			array( $this, 'show_bookmarklet' ),
			'wp_bookmarker_settings',
			'wp_bookmarker_main'
		);
	}

	/**
	 * Output the HTML for the URL metabox.
	 *
	 * @return void
	 */
	public function bookmark_url_metabox_html() {
		global $post;
		$custom       = get_post_custom( $post->ID );
		$bookmark_url = $custom['bookmark_url'][0];
		?>
		<label>URL:</label>
		<input name="bookmark_url" size="60" value="<?php echo esc_url( $bookmark_url ); ?>" />
		<?php
	}

	/**
	 * Save the URL from the editor metabox.
	 *
	 * @return void
	 */
	public function save_url() {
		global $post;

		if ( ! empty( $_POST['bookmark_url'] ) ) {
			update_post_meta( $post->ID, 'bookmark_url', esc_url_raw( wp_unslash( $_POST['bookmark_url'] ) ) );
		}
	}


	/**
	 * Template for single bookmark post
	 */
	public function single_template( $single_template ) {
		global $post;
		if ( WP_BOOKMARKER_POST_TYPE === $post->post_type ) {
			$single_template = WP_BOOKMARKER_PLUGIN_DIR . WP_BOOKMARKER_BOOKMARK_SINGLE_TEMPLATE;
		}
		return $single_template;
	}


	/**
	 * Customize the archive template.
	 * TODO: make this pluggable.
	 *
	 * @param string $archive_template Path to the archive template.
	 * @return string
	 */
	public function get_custom_post_type_template( $archive_template ) {
		global $post;

		if ( is_post_type_archive( WP_BOOKMARKER_POST_TYPE ) ) {
			$archive_template = WP_BOOKMARKER_PLUGIN_DIR . WP_BOOKMARKER_BOOKMARK_ARCHIVE_TEMPLATE;
		}
		return $archive_template;
	}


	/**
	 * Customize the title for the archive page.
	 *
	 * @param string $title The default archive title.
	 * @return string
	 */
	public function custom_title_for_archive( $title ) {

		if ( is_post_type_archive( WP_BOOKMARKER_POST_TYPE ) ) {
			$title = 'Bookmarks';
		}

		return $title;
	}

	/**
	 * Remove 'Private' from post titles for our CPT.
	 * See:
	 * https://codex.wordpress.org/Plugin_API/Filter_Reference/private_title_format
	 *
	 * @param string $format Format for the prefix and the title. Default 'Private: %s'.
	 * @return string
	 */
	public function remove_private_protected_from_titles( $format ) {
		$title = $format;
		if ( is_post_type_archive( WP_BOOKMARKER_POST_TYPE ) ) {
			$title = '%s';
		}

		return $title;
	}

	/**
	 * Enqueue the front-end CSS.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'wp-bookmarker-style', WP_BOOKMARKER_PLUGIN_URL . '/assets/wp-bookmarker.css', array(), WP_BOOKMARKER_VERSION );
		wp_enqueue_style( 'font-awesome', WP_BOOKMARKER_PLUGIN_URL . '/includes/font-awesome/css/font-awesome.css', array(), WP_BOOKMARKER_VERSION );
	}

	/**
	 * Use the Settings API to handle plugin settings.
	 *
	 * @return void
	 */
	public function admin_add_page() {
		add_options_page(
			'WP Bookmarker Settings',
			'WP Bookmarker',
			'manage_options',
			'wp_bookmarker_settings',
			array( $this, 'output_options_page' )
		);
	}

	/**
	 * Output the HTML for the options page.
	 *
	 * @return void
	 */
	public function output_options_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			die( 'You do not have access to this page' );
		}

		?>
		<div>
		<h2>WP Bookmarker</h2>
		<form action="options.php" method="post">
		<?php settings_fields( 'wp_bookmarker_options_group' ); ?>
		<?php do_settings_sections( 'wp_bookmarker_settings' ); ?>

		<?php
	}

	/**
	 * Hat-tip to the Linkmarklet plugin
	 * https://github.com/jchristopher/linkmarklet
	 * Linkmarklet author: Jonathan Christopher
	 * Linkmarklet author URI: http://mondaybynoon.com/
	 *
	 * @return void
	 */
	public function show_bookmarklet() {
		$bookmarklet = "javascript:var%20d=document,w=window,e=w.getSelection,k=d.getSelection,x=d.selection,s=(e?e():(k)?k():(x?x.createRange().text:0)),f='" . WP_BOOKMARKER_PLUGIN_URL . "',l=d.location,e=encodeURIComponent,u=f+'?u='+e(l.href.replace(new RegExp('(https?:\/\/)','gm'),''))+'&t='+e(d.title)+'&s='+e(s)+'&v=4&m='+(((l.href).indexOf('https://',0)===0)?1:0);a=function(){if(!w.open(u,'t','toolbar=0,resizable=1,scrollbars=1,status=1,width=720,height=570'))l.href=u;};if%20(/Firefox/.test(navigator.userAgent))%20setTimeout(a,%200);%20else%20a();void(0)";
		?>
		<p>Drag the bookmarklet to your bookmark bar: <a href="<?php echo esc_html( $bookmarklet ); ?>">Bookmarklet</a></p>
		<?php
	}
}

// Load the plugin class.
$GLOBALS['wp_bookmarker_plugin'] = new WPBookmarker();
