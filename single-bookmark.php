<?php
/**
 * The template for displaying all single posts.
 *
 * @package wp_bookmarker
 */

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<?php
		while ( have_posts() ) :
			the_post();
			?>

			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<?php
				if ( is_single() ) : ?>
					<header class="entry-header">
					<?php
					the_title( '<h1 class="entry-title">', '</h1>' );

					$terms = get_the_terms( $post->ID, 'bookmark_tag' );
					if ( $terms && ! is_wp_error( $terms ) ) :
						$term_slugs_arr = array();
						foreach ( $terms as $term ) {
							$term_slugs_arr[] = sprintf(
								'<a href="%1$s">%2$s</a>',
								esc_url( get_term_link( $term->slug, 'bookmark_tag' ) ),
								esc_html( $term->name )
							);
						}
						$terms_slug_str = join( ', ', $term_slugs_arr );
					endif;
					echo '<div class="tags">(' . $terms_slug_str . ')</div>';
					?>
					</header><!-- .entry-header -->
					<div class="entry-content">

					<?php
						the_content(
							sprintf(
								/* translators: %s: Name of current post. */
								wp_kses( __( 'Continue reading %s <span class="meta-nav">&rarr;</span>', 'yojimbokb' ), array( 'span' => array( 'class' => array() ) ) ),
								the_title( '<span class="screen-reader-text">"', '"</span>', false )
							)
						);

						wp_link_pages(
							array(
								'before' => '<div class="page-links">' . esc_html__( 'Pages:'),
								'after'  => '</div>',
							)
						);
					?>
				</div><!-- .entry-content -->
				<footer class="entry-footer">
					<?php // yojimbokb_entry_footer(); ?>
				</footer><!-- .entry-footer -->
				<?php
				else :
				?>
				<?php the_title( '<a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a>' ); ?>
				&nbsp;<span class="tags"><?php the_tags('(', ', ', ')' ); ?></span>
				<?php
				endif; ?>
			</article><!-- #post-## -->

			<?php
			$custom = get_post_custom();
			$bookmark_url = $custom["bookmark_url"][0];
			?>
			<a href='<?php echo $bookmark_url;  ?>'><?php echo $bookmark_url;  ?></a>
			<?php
			//the_post_navigation();

			// If comments are open or we have at least one comment, load up the comment template.
			if ( comments_open() || get_comments_number() ) :
				comments_template();
			endif;

		endwhile; // End of the loop.
		?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_sidebar();
get_footer();
