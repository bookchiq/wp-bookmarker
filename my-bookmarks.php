<?php
/**
 * Plugin Name: My Bookmarks
 * Plugin URI: http://anothercoffee.net
 * Description: Convert your blog into a personal social bookmarking service.
 * Version: 0.1
 * Author: Anthony Lopez-Vito
 * Author URI: http://anothercoffee.net
 *
 * @package my_bookmarks
 */

/**
 * For security as specified in
 * http://codex.wordpress.org/Writing_a_Plugin
 */
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Define useful constants.
 */
define( 'MY_BOOKMARKS_VERSION', '0.1' );
define( 'MY_BOOKMARKS_REQUIRED_WP_VERSION', '4.0' );
define( 'MY_BOOKMARKS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MY_BOOKMARKS_PLUGIN_NAME', trim( dirname( MY_BOOKMARKS_PLUGIN_BASENAME ), '/' ) );
define( 'MY_BOOKMARKS_PLUGIN_DIR', untrailingslashit( dirname( __FILE__ ) ) );
define( 'MY_BOOKMARKS_PLUGIN_URL', untrailingslashit( plugins_url( '', __FILE__ ) ) );
define( 'MY_BOOKMARKS_PLUGIN_MODULES_DIR', MY_BOOKMARKS_PLUGIN_DIR . '/modules' );
define( 'MY_BOOKMARKS_POST_TYPE', 'bookmarks' );
define( 'MY_BOOKMARKS_TAXONOMY', 'bookmark_tag' );
define( 'MY_BOOKMARKS_BOOKMARK_SINGLE_TEMPLATE', '/single-bookmark.php' );
define( 'MY_BOOKMARKS_BOOKMARK_ARCHIVE_TEMPLATE', '/archive-bookmarks.php' );



if ( ! function_exists( 'acc_write_log' ) ) {
	/**
	 * Add custom logging feature.
	 *
	 * @param mixed $log The debug information that should be logged.
	 * @return void
	 */
	function acc_write_log( $log ) {
		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( print_r( 'ACC: ' . $log, true ) );
		} else {
			error_log( 'ACC: ' . $log );
		}
	}
}

/**
 * Plugin cleanup
 */
register_activation_hook( __FILE__, 'acc_activation' );
function acc_activation() {
	acc_write_log("Plugin activated");
}

register_deactivation_hook( __FILE__, 'acc_deactivation' );
function acc_deactivation() {
	  acc_write_log("Plugin deactivated");
}


/**
 * Register the Bookmark Post Type
 */
function acc_register_bookmark() {
	$labels = array(
		'name'               => _x( 'Bookmark', 'my-bookmarks' ),
		'singular_name'      => _x( 'Bookmark', 'my-bookmarks' ),
		'add_new'            => _x( 'Add New Bookmark', 'my-bookmarks' ),
		'add_new_item'       => _x( 'Add New Bookmark', 'my-bookmarks' ),
		'edit_item'          => _x( 'Edit Bookmark', 'my-bookmarks' ),
		'new_item'           => _x( 'New Bookmark', 'my-bookmarks' ),
		'view_item'          => _x( 'View Bookmark', 'my-bookmarks' ),
		'search_items'       => _x( 'Search Bookmarks', 'my-bookmarks' ),
		'not_found'          => _x( 'No bookmarks found', 'my-bookmarks' ),
		'not_found_in_trash' => _x( 'No bookmarks found in Trash', 'my-bookmarks' ),
		'parent_item_colon'  => _x( 'Parent Position:', 'my-bookmarks' ),
		'menu_name'          => _x( 'Bookmarks', 'my-bookmarks' ),
	);

	$args = array(
		'labels'              => $labels,
		'hierarchical'        => true,
		'description'         => 'Bookmark',
		'supports'            => array( 'title', 'editor', 'thumbnail', 'revisions' ),
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

	register_post_type( MY_BOOKMARKS_POST_TYPE, $args );
}
add_action( 'init', 'acc_register_bookmark' );

/**
 * Create the taxonomy as non-hierarchical tags
 */
function bookmark_tags_taxonomy() {
	register_taxonomy(
		'bookmark_tag',
		MY_BOOKMARKS_POST_TYPE,
		array(
			'hierarchical' => false,
			'label'        => 'Bookmark tags',
			'query_var'    => true,
			'rewrite'      => array(
				'slug'       => 'bookmark_tag',
				'with_front' => false,
			),
		)
	);
}
add_action( 'init', 'bookmark_tags_taxonomy' );


/**
 * Create the URL metabox
 */
add_action( 'admin_init', 'admin_init' );
function admin_init() {
	add_meta_box( 'bookmark_url_meta', 'URL', 'bookmark_url', MY_BOOKMARKS_POST_TYPE, 'normal', 'low' );
}

function bookmark_url(){
	global $post;
	$custom       = get_post_custom( $post->ID );
	$bookmark_url = $custom['bookmark_url'][0];
	?>
	<label>URL:</label>
	<input name="bookmark_url" size="60" value="<?php echo $bookmark_url; ?>" />
	<?php
}

add_action( 'save_post', 'acc_save_url' );
function acc_save_url(){
	global $post;

	update_post_meta( $post->ID, 'bookmark_url', $_POST['bookmark_url'] );
}


/**
 * Template for single bookmark post
 */
add_filter( 'single_template', 'acc_bookmark_template' );
function acc_bookmark_template( $single_template ) {
	global $post;
	if ( MY_BOOKMARKS_POST_TYPE === $post->post_type ) {
		$single_template = MY_BOOKMARKS_PLUGIN_DIR . MY_BOOKMARKS_BOOKMARK_SINGLE_TEMPLATE;
	}
	return $single_template;
}


/**
 * Custom archive template
 */
function get_custom_post_type_template( $archive_template ) {
	global $post;

	if ( is_post_type_archive( MY_BOOKMARKS_POST_TYPE ) ) {
		$archive_template = MY_BOOKMARKS_PLUGIN_DIR . MY_BOOKMARKS_BOOKMARK_ARCHIVE_TEMPLATE;
	}
	return $archive_template;
}

add_filter( 'archive_template', 'get_custom_post_type_template' ) ;

/**
 * Custom title for archive page
 */
function acc_bookmark_custom_title( $title ) {

	if ( is_post_type_archive( MY_BOOKMARKS_POST_TYPE ) ) {
		$title = 'Bookmarks';
	}

	return $title;
}
add_filter( 'get_the_archive_title', 'acc_bookmark_custom_title' );

/**
 * Remove 'Private' from post titles
 * See:
 * https://codex.wordpress.org/Plugin_API/Filter_Reference/private_title_format
 */
function acc_bookmark_remove_private_protected_from_titles( $format ) {
	$title = $format;
	if ( is_post_type_archive ( MY_BOOKMARKS_POST_TYPE ) ) {
		$title = '%s';
	}

	return $title;
}
add_filter( 'private_title_format', 'acc_bookmark_remove_private_protected_from_titles' );

/**
 * Enqueue CSS
 */
function acc_bookmark_enqueuescripts() {
	wp_enqueue_style( 'my-bookmarks-style', MY_BOOKMARKS_PLUGIN_URL . '/my-bookmarks.css' );
	wp_enqueue_style( 'font-awesome', MY_BOOKMARKS_PLUGIN_URL . '/includes/font-awesome/css/font-awesome.css'); 
}
add_action( 'wp_enqueue_scripts', acc_bookmark_enqueuescripts );



/***
 * Use Settings API to handle plugin settings
 */
add_action('admin_menu', 'acc_bookmark_admin_add_page');
function acc_bookmark_admin_add_page() {
	add_options_page(
		'My Bookmarks Settings',
		'My Bookmarks',
		'manage_options',
		'acc_bookmark_settings',
		'acc_bookmark_options_page'
	);
}

function acc_bookmark_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		die( 'You do not have access to this page' );
	}

	?>
	<div>
	<h2>My Bookmarks</h2>
	<form action="options.php" method="post">
	<?php settings_fields( 'acc_bookmark_options_group' ); ?>
	<?php do_settings_sections( 'acc_bookmark_settings' ); ?>

	<?php
}

add_action('admin_init', 'acc_bookmark_admin_init');
function acc_bookmark_admin_init() {
	register_setting(
		'acc_bookmark_options_group',
		'acc_bookmark_option_name',
		'acc_bookmark_options_validate'
	);
	add_settings_section(
		'acc_bookmark_main',
		'Bookmarklet settings',
		'acc_bookmark_section_text',
		'acc_bookmark_settings'
	);
	add_settings_field(
		'acc_bookmark_bookmarklet',
		'Bookmarklet',
		'edit_bookmarklet',
		'acc_bookmark_settings',
		'acc_bookmark_main'
	);

}

/**
 * Hat-tip to the Linkmarklet plugin
 * https://github.com/jchristopher/linkmarklet
 * Linkmarklet author: Jonathan Christopher
 * Linkmarklet author URI: http://mondaybynoon.com/
 *
 * @return void
 */
function edit_bookmarklet() {
	$bookmarklet = "javascript:var%20d=document,w=window,e=w.getSelection,k=d.getSelection,x=d.selection,s=(e?e():(k)?k():(x?x.createRange().text:0)),f='" . MY_BOOKMARKS_PLUGIN_URL . "',l=d.location,e=encodeURIComponent,u=f+'?u='+e(l.href.replace(new RegExp('(https?:\/\/)','gm'),''))+'&t='+e(d.title)+'&s='+e(s)+'&v=4&m='+(((l.href).indexOf('https://',0)===0)?1:0);a=function(){if(!w.open(u,'t','toolbar=0,resizable=1,scrollbars=1,status=1,width=720,height=570'))l.href=u;};if%20(/Firefox/.test(navigator.userAgent))%20setTimeout(a,%200);%20else%20a();void(0)";
	?>
	<p>Drag the bookmarklet to your bookmark bar: <a href="<?php echo $bookmarklet; ?>">Bookmarklet</a></p>
	<?php
}
