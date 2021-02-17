<?php
/**
 * Display bookmark post entry UI upon clicking bookmarklet
 *
 * This is a stripped-down and refactored version of the code from
 * the Linkmarklet plugin at https://github.com/jchristopher/linkmarklet
 *
 * Linkmarklet author: Jonathan Christopher
 * Linkmarklet author URI: http://mondaybynoon.com/
 * Linkmarklet license: GPLv2 or later
 * Linkmarklet license URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package wp_bookmarker
 */

define( 'IFRAME_REQUEST', true );
define( 'WP_BOOKMARKER_URL_FIELD', 'bookmark_url' );
define( 'BOOKMARKLET_PREFIX', '_acc_bookmarklet_' );
define( 'BOOKMARKLET_NONCE_FIELD_REFERRER', 'wp-bookmarker-press-this' );
if ( ! defined( 'WP_BOOKMARKER_POST_TYPE' ) ) {
	define( 'WP_BOOKMARKER_POST_TYPE', 'bookmarker' );
}

if ( ! empty( $_SERVER['SCRIPT_FILENAME'] ) ) {
	$script_path = $_SERVER['SCRIPT_FILENAME'];
} else {
	$script_path = __FILE__;
}

ob_start();
require_once preg_replace( '/wp-content.*/', 'wp-load.php', $script_path );
require_once preg_replace( '/wp-content.*/', '/wp-admin/includes/admin.php', $script_path );
/** WordPress Administration Bootstrap */
require_once preg_replace( '/wp-content.*/', '/wp-admin/admin.php', $script_path );

// Scripts for Pinterest-style image selection.
require_once 'includes/image-picker.php';

ob_end_clean();

header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );

if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( get_post_type_object( WP_BOOKMARKER_POST_TYPE )->cap->create_posts ) ) {
	wp_die( __( 'Access Denied.' ) );
}

// let's create our post
$new_post    = get_default_post_to_edit( WP_BOOKMARKER_POST_TYPE, true );
$new_post_id = absint( $new_post->ID );

// Set Variables
$new_title = isset( $_GET['t'] ) ? trim( wp_strip_all_tags( html_entity_decode( stripslashes( $_GET['t'] ), ENT_QUOTES ) ) ) : '';

$selection = '';
if ( ! empty( $_GET['s'] ) ) {
	$selection = str_replace( '&apos;', "'", stripslashes( $_GET['s'] ) );
	$selection = trim( htmlspecialchars( html_entity_decode( $selection, ENT_QUOTES ) ) );
}


// we stripped the protocol so as to avoid issues with certain
// webhosts (HostGator) that throw 404's if protocols are in GET vars
// but we tracked if it was HTTPS so we'll put the protocol back in
$url = isset( $_GET['u'] ) ? esc_url( ( $_GET['m'] ? 'https://' : 'http://' ) . $_GET['u'] ) : '';

define( 'BOOKMARKLET_URL', rtrim( plugin_dir_url( __FILE__ ), '/' ) );


/*******************
 *
 *******************/
function wp_bookmarker_bookmarklet_post() {

	global $linkmarklet_debug;

	$settings = get_option( BOOKMARKLET_PREFIX . 'settings' );

	// By default it'll be right now.
	$timestamp     = (int) current_time( 'timestamp' );
	$timestamp_gmt = (int) current_time( 'timestamp', 1 );

	$settings = get_option( BOOKMARKLET_PREFIX . 'settings' );

	$new_post    = get_default_post_to_edit( WP_BOOKMARKER_POST_TYPE );
	$new_post    = get_object_vars( $new_post );
	$new_post_id = $new_post['ID'] = intval( $_POST['post_id'] );

	if ( ! current_user_can( 'edit_post', $new_post_id ) ) {
		wp_die( __( 'You are not allowed to edit this post.' ) );
	}

	// Set our category.
	$new_post['post_category'] = ! empty( $settings['category'] ) ? intval( $settings['category'] ) : 0;

	// Set our post properties.
	$new_post['post_title'] = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
	$content                = isset( $_POST['content'] ) ? $_POST['content'] : '';

	// Markdown on Save?
	if ( is_plugin_active( 'markdown-on-save/markdown-on-save.php' ) && ! empty( $settings['markdown'] ) ) {
		// we need to set up our post data to tell Markdown on Save we want to use it
		$new_post['cws_using_markdown']  = 1;
		$new_post['_cws_markdown_nonce'] = wp_create_nonce( 'cws-markdown-save' );
	}

	// Set the post_content and status.
	$new_post['post_content'] = wp_kses_post( $content );
	$new_post['post_status']  = 'draft';

	// Set the author.
	$new_post['post_author'] = get_current_user_id();

	// Set the category.
	/* Categories currently not supported in plugin
	$new_post['post_category'] = array_map( 'absint', array( $new_post['post_category'] ) );
	 */

	// Set the slug.
	$new_post['post_name'] = sanitize_title( $_POST['slug'] );

	// Update what we've set.
	$new_post_id = wp_update_post( $new_post );

	// We also need to add our custom field link.
	update_post_meta( $new_post_id, WP_BOOKMARKER_URL_FIELD, esc_url( $_POST['url'] ) );

	// Set our post tags if applicable.
	if ( ! empty( $_POST['tags'] ) ) {
		$received_tags_array = array_map( 'trim', explode( ',', $_POST['tags'] ) );
		$tag_id_array = array();
		foreach ( $received_tags_array as $single_tag ) {
			if ( ! empty( $single_tag ) ) {
				$tag = get_term_by( 'name', trim( $single_tag ), WP_BOOKMARKER_TAXONOMY );

				if ( ! empty( $tag ) ) {
					// This is a tag that already exists in our taxonomy, so go ahead and use it.
					$tag_id_array[] = intval( $tag->term_id );
				} elseif ( current_user_can( 'manage_categories' ) ) {
					// The tag doesn't exist yet, so we need to first create and then assign it.
					$new_term = wp_insert_term( trim( $single_tag ), WP_BOOKMARKER_TAXONOMY );
					$tag_id_array[] = intval( $new_term->term_id );
				}
			}
		}
		wp_set_object_terms( $new_post_id, $tag_id_array, WP_BOOKMARKER_TAXONOMY );
	}

	if ( isset( $_POST['publish'] ) && current_user_can( 'publish_posts' ) ) {
		$new_post['post_status'] = 'publish';
	}

	// our final update
	$new_post_id = wp_update_post( $new_post );

	return $new_post_id;
}

wp_enqueue_script( 'underscore' );
wp_enqueue_script( 'jquery-ui-autocomplete' );
wp_enqueue_style( 'jquery-ui' );
wp_enqueue_style( 'wp-bookmarker-style', untrailingslashit( plugins_url( '', __FILE__ ) ) . '/assets/wp-bookmarker-bookmarklet.css', array(), WP_BOOKMARKER_VERSION );
?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>WP Bookmarker Bookmarklet</title>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
	<?php
		do_action( 'admin_print_scripts' );
		do_action( 'admin_head' );
	?>
	<style>
		<?php require dirname( __FILE__ ) . '/assets/jquery-ui.css'; ?>

		.ui-autocomplete {
			/* By default the list is positioned according to the field, but we want a bit different */
			width:200px !important;
			left:110px !important;
		}
	</style>
</head>
<body>
<div id="adminbar" class="wp_bookmarker_adminbar">
		<h1 id="current-site" class="current-site">
			<a class="current-site-link" href="<?php home_url(); ?>" target="_blank" rel="home">
				<span class="current-site-name"><?php echo get_bloginfo('name'); ?></span></a> <span style="font-size:smaller">Save bookmark</span>
		</h1>
	</div>
<?php
	// If the post was just saved, display a link to it.
	if ( isset( $_REQUEST['_wpnonce'] ) ) {
		check_admin_referer( BOOKMARKLET_NONCE_FIELD_REFERRER );
		$new_post_id = wp_bookmarker_bookmarklet_post();

		inspect( 'Post ID', $new_post_id );

		echo '<div class="message">' . PHP_EOL;
		echo '	<p>Entry posted. <a onclick="window.opener.location.replace(this.href); window.close();" href="<?php echo get_permalink( $new_post_id ); ?>">View post</a></p>' . PHP_EOL;
		echo '</div>' . PHP_EOL;
	} else {
	// Otherwise, show the bookmark entry UI.
	?>
	<?php $settings = get_option( BOOKMARKLET_PREFIX . 'settings' ); ?>
	<form action="" method="post">
		<div class="hidden">
			<?php wp_nonce_field( BOOKMARKLET_NONCE_FIELD_REFERRER ); ?>
			<input type="hidden" name="post_type" id="post_type" value="text"/>
			<input type="hidden" name="autosave" id="autosave" />
			<input type="hidden" id="original_post_status" name="original_post_status" value="draft" />
			<input type="hidden" id="prev_status" name="prev_status" value="draft" />
			<input type="hidden" id="post_id" name="post_id" value="<?php echo absint( $new_post_id ); ?>" />
		</div>
		<div class="field textfield" id="row-title">
			<label for="title">Title</label>
			<input type="text" name="title" id="title" value="<?php echo esc_attr( $new_title ); ?>" />
		</div>
		<div class="field textfield" id="row-url">
			<label for="url">Link URL</label>
			<input type="text" name="url" id="url" value="<?php echo esc_url( $url ); ?>" />
		</div>
		<div class="field textfield" id="row-slug">
			<label for="slug">Slug</label>
			<input type="text" name="slug" id="slug" value="<?php if ( isset( $settings['prepopulate_slug'] ) ) { echo sanitize_title( $new_title ); } ?>" />
		</div>

		<div class="field textfield" id="row-tags">
			<label for="url">Tags</label>
			<input type="text" name="tags" id="tags" value="" />
		</div>

		<div class="field textarea" id="row-content">
			<label for="content">Content</label>
			<textarea name="content" id="content"><?php echo esc_textarea( $selection ); ?></textarea>
		</div>

		<div class="field textarea" id="row-thumbnail">
			<label for="thumbnail">Thumbnail</label>
			<textarea name="thumbnail" id="thumbnail"><?php echo esc_textarea( $selection ); ?></textarea>
		</div>

		<div class="wp_bookmarker_actions" id="row-actions">
			<input type="submit" name="publish" id="publish" value="Publish" />        
			<input type="submit" name="save" id="save" value="Save" />
		</div>

	</form>
<?php } ?>
<script type="text/javascript">
	function reposition(){
		var window_height   = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight || 0;
		var actions         = document.getElementById('row-actions').offsetHeight;
		var title           = document.getElementById('row-title').offsetHeight;
		var url             = document.getElementById('row-url').offsetHeight;
		var slug            = document.getElementById('row-slug').offsetHeight;
		var height          = window_height - actions - title - url - slug - 25;
		var tags            = document.getElementById('row-tags').offsetHeight;
		height = height - tags;
		document.getElementById('content').style.height = height + 'px';
	}
	reposition();
	window.onresize = function(event) {
		reposition();
	}
</script>
<?php //if ( !empty( $settings['support_tags'] ) ) : ?>
<?php
	$tags = get_terms(
		WP_BOOKMARKER_TAXONOMY,
		array(
			'hide_empty' => false, // do not hide empty terms
		)
	);
	foreach ( $tags as $single_tag ) {
		$all_tags[] = '"' . str_replace( '"', '\"', esc_js( $single_tag->name ) ) . '"';
	}

	do_action( 'admin_footer' );
	do_action( 'admin_print_footer_scripts' );
?>
	<script type="text/javascript">
		var LINKMARKLET_TAGS = [<?php echo implode( ',', $all_tags ); ?>];
		function split( val ) {
			return val.split( /,\s*/ );
		}
		function extractLast( term ) {
			return split( term ).pop();
		}

		jQuery('#tags')
			// don't navigate away from the field on tab when selecting an item
			.bind( "keydown", function( event ) {
				if ( event.keyCode === jQuery.ui.keyCode.TAB &&
					jQuery( this ).data( "autocomplete" ).menu.active ) {
						event.preventDefault();
					}
			})
			.autocomplete({
				minLength: 0,
				source: function( request, response ) {
					// delegate back to autocomplete, but extract the last term
					response( jQuery.ui.autocomplete.filter(
						LINKMARKLET_TAGS, extractLast( request.term ) ) );
				},
				focus: function() {
					// prevent value inserted on focus
					return false;
				},
				select: function( event, ui ) {
					var terms = split( this.value );
					// remove the current input
					terms.pop();
					// add the selected item
					terms.push( ui.item.value );
					// add placeholder to get the comma-and-space at the end
					terms.push( "" );
					this.value = terms.join( ", " );
					return false;
				}
			});
	</script>
<?php
	//endif;
?>
</body>
</html>
