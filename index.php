<?php
/* Display bookmark post entry UI upon clicking bookmarklet
 *
 * This is a stripped-down and refactored version of the code from
 * the Linkmarklet plugin at https://github.com/jchristopher/linkmarklet
 *
 * Linkmarklet author: Jonathan Christopher
 * Linkmarklet author URI: http://mondaybynoon.com/
 * Linkmarklet license: GPLv2 or later
 * Linkmarklet license URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

define('IFRAME_REQUEST' , true);
define('MY_BOOKMARKS_POST_TYPE', 'bookmarks');
define('MY_BOOKMARKS_URL_FIELD', 'bookmark_url');
define('MY_BOOKMARKS_TAXONOMY', 'bookmark_tag');
define('BOOKMARKLET_PREFIX',   '_acc_bookmarklet_');
define('BOOKMARKLET_NONCE_FIELD_REFERRER',   'my-bookmarks-press-this');

ob_start();
require_once( preg_replace( "/wp-content.*/", "wp-load.php", __FILE__ ) );
require_once( preg_replace( "/wp-content.*/", "/wp-admin/includes/admin.php", __FILE__ ) );
/** WordPress Administration Bootstrap */
require_once( preg_replace( "/wp-content.*/", "/wp-admin/admin.php", __FILE__ ) );
ob_end_clean();

header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );

if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( get_post_type_object( MY_BOOKMARKS_POST_TYPE )->cap->create_posts ) ) {
    wp_die( __( 'Access Denied.' ) );
}

// let's create our post
$post       = get_default_post_to_edit( MY_BOOKMARKS_POST_TYPE, true );
$post_ID    = absint( $post->ID );

// Set Variables
$title = isset( $_GET['t'] ) ? trim( strip_tags( html_entity_decode( stripslashes( $_GET['t'] ) , ENT_QUOTES) ) ) : '';

$selection = '';
if ( ! empty( $_GET['s'] ) ) {
    $selection = str_replace( '&apos;', "'", stripslashes( $_GET['s'] ) );
    $selection = trim( htmlspecialchars( html_entity_decode( $selection, ENT_QUOTES ) ) );
}


// we stripped the protocol so as to avoid issues with certain
// webhosts (HostGator) that throw 404's if protocols are in GET vars
// but we tracked if it was HTTPS so we'll put the protocol back in
$url = isset( $_GET['u'] ) ? esc_url( ( $_GET['m'] ? 'https://' : 'http://' ) . $_GET['u'] ) : '';

define( 'BOOKMARKLET_URL',      rtrim( plugin_dir_url( __FILE__ ), '/' ) );


/*******************
 *
 *******************/
function acc_bookmarklet_post() {

    global $linkmarklet_debug;

    $settings = get_option( BOOKMARKLET_PREFIX . 'settings' );

    // by default it'll be right now
    $timestamp      = (int) current_time( 'timestamp' );
    $timestamp_gmt  = (int) current_time( 'timestamp', 1 );


    $settings   = get_option( BOOKMARKLET_PREFIX . 'settings' );
    
    $post       = get_default_post_to_edit(MY_BOOKMARKS_POST_TYPE);
    $post       = get_object_vars( $post );
    $post_ID    = $post['ID'] = intval( $_POST['post_id'] );

    if ( ! current_user_can( 'edit_post', $post_ID ) ) {
        wp_die( __( 'You are not allowed to edit this post.' ) );
    }

    // set our category
    $post['post_category']  = ! empty( $settings['category'] ) ? intval( $settings['category'] ) : 0;

    // set our post properties
    $post['post_title']     = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
    $content                = isset( $_POST['content'] ) ? $_POST['content'] : '';

    // Markdown on Save?
    if ( is_plugin_active( 'markdown-on-save/markdown-on-save.php' ) && ! empty( $settings['markdown'] ) ) {
        // we need to set up our post data to tell Markdown on Save we want to use it
        $post['cws_using_markdown']     = 1;
        $post['_cws_markdown_nonce']    = wp_create_nonce( 'cws-markdown-save' );
    }

    // set the post_content and status
    $post['post_content']   = wp_kses_post( $content );
    $post['post_status']    = 'draft';

    // set the category
    /* Categories currently not supported in plugin
    $post['post_category'] = array_map( 'absint', array( $post['post_category'] ) );
     */

    // set the slug
    $post['post_name'] = sanitize_title( $_POST['slug'] );

    // update what we've set
    $post_ID = wp_update_post( $post );

	// we also need to add our custom field link
    update_post_meta( $post_ID, MY_BOOKMARKS_URL_FIELD, esc_url( $_POST['url'] ) );

	// set our post tags if applicable
	if ( ! empty( $_POST['tags'] )) {
	    $received_tags_array = explode(',', $_POST['tags']);
	    $tag_id_array = array();
	    foreach( $received_tags_array as $tag ) {
            error_log("Tag[".$tag."]");
            $tag = get_term_by('name', trim($tag), MY_BOOKMARKS_TAXONOMY);
            $tag_id_array[] = intval($tag->term_id);
        }
        wp_set_object_terms( $post_ID, $tag_id_array, MY_BOOKMARKS_TAXONOMY );
    }
    
    if ( isset( $_POST['publish'] ) && current_user_can( 'publish_posts' ) ) {
        $post['post_status'] = 'publish';
    }

    // our final update
    $post_ID = wp_update_post( $post );

    return $post_ID;
}

/*******************
 *
 *******************/

wp_enqueue_script( 'underscore' );
wp_enqueue_script( 'jquery-ui-autocomplete' );
wp_enqueue_style( 'jquery-ui' );
wp_enqueue_style( 'my-bookmarks-style', untrailingslashit( plugins_url( '', __FILE__ ) ).'/my-bookmarks-bookmarklet.css' );
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Bookmarks Bookmarklet</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
    <?php do_action( 'admin_print_scripts' ); do_action('admin_head'); ?>
    <style>
        <?php include dirname( __FILE__ ) . '/jquery-ui.css'; ?>

        .ui-autocomplete {
            /* By default the list is positioned according to the field, but we want a bit different */
            width:200px !important;
            left:110px !important;
        }
    </style>
</head>
<body>
<div id="adminbar" class="my_bookmarks_adminbar">
		<h1 id="current-site" class="current-site">
			<a class="current-site-link" href="<?php echo get_home_url(); ?>" target="_blank" rel="home">
				<span class="current-site-name"><?php echo get_bloginfo('name'); ?></span></a> <span style="font-size:smaller">Save bookmark</span>
		</h1>
	</div>
	
<?php
    ///////////////
    // Post saved
    if ( isset( $_REQUEST['_wpnonce'] ) ) {
        check_admin_referer( BOOKMARKLET_NONCE_FIELD_REFERRER );
        $posted = $post_ID = acc_bookmarklet_post();
        
        error_log("Post ID: ".$post_ID);
        ?>

        <div class="message">
            <p>Entry posted. <a onclick="window.opener.location.replace(this.href); window.close();" href="<?php echo get_permalink( $posted ); ?>">View post</a></p>
        </div>
<?php } else { 
    ///////////////
    // Show bookmark entry UI
    ?>
    <?php $settings = get_option( BOOKMARKLET_PREFIX . 'settings' ); ?>
    <form action="" method="post">
        <div class="hidden">
            <?php wp_nonce_field( BOOKMARKLET_NONCE_FIELD_REFERRER ); ?>
            <input type="hidden" name="post_type" id="post_type" value="text"/>
            <input type="hidden" name="autosave" id="autosave" />
            <input type="hidden" id="original_post_status" name="original_post_status" value="draft" />
            <input type="hidden" id="prev_status" name="prev_status" value="draft" />
            <input type="hidden" id="post_id" name="post_id" value="<?php echo absint( $post_ID ); ?>" />
        </div>
        <div class="field textfield" id="row-title">
            <label for="title">Title</label>
            <input type="text" name="title" id="title" value="<?php echo esc_attr( $title ); ?>" />
        </div>
        <div class="field textfield" id="row-url">
            <label for="url">Link URL</label>
            <input type="text" name="url" id="url" value="<?php echo esc_url( $url ); ?>" />
        </div>
        <div class="field textfield" id="row-slug">
            <label for="slug">Slug</label>
            <input type="text" name="slug" id="slug" value="<?php if( isset( $settings['prepopulate_slug'] ) ) { echo sanitize_title( $title ); } ?>" />
        </div>
        
        <div class="field textfield" id="row-tags">
            <label for="url">Tags</label>
            <input type="text" name="tags" id="tags" value="" />
        </div>

        <div class="field textarea" id="row-content">
            <label for="content">Content</label>
            <textarea name="content" id="content"><?php echo esc_textarea( $selection ); ?></textarea>
        </div>
        
        <div class="my_bookmarks_actions" id="row-actions">
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
    $tags = get_terms( MY_BOOKMARKS_TAXONOMY, [
      'hide_empty' => false, // do not hide empty terms
    ]);
    foreach( $tags as $tag ) {
        $all_tags[] = '"' . str_replace( '"', '\"', esc_js( $tag->name ) ) . '"';
    }
?>

<?php
    do_action('admin_footer');
    do_action('admin_print_footer_scripts');
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
