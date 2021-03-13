<?php

require_once 'simplehtmldom_1_9_1/simple_html_dom.php';
require_once 'url_to_absolute/url_to_absolute.php';

inspect( 'loading' );

// $url = isset( $_GET['u'] ) ? esc_url( ( $_GET['m'] ? 'https://' : 'http://' ) . $_GET['u'] ) : '';
$url = 'http://' . $_GET['u'];

$html = file_get_html( $url );
foreach ( $html->find( 'img' ) as $element ) {
	inspect( url_to_absolute( $url, $element->src ) );
}
