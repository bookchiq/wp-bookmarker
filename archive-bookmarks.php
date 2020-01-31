<?php
/**
 * The template for displaying archive pages.
 *
 * @package my_bookmarks
 */

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<?php if ( have_posts() ) : ?>
			<header class="page-header">
				<?php
					the_archive_title( '<h1 class="page-title">', '</h1>' );
					the_archive_description( '<div class="archive-description">', '</div>' );
				?>
			</header><!-- .page-header -->
			<?php
			/* Start the Loop */
			while ( have_posts() ) :
				the_post();
				?>
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<div class="my_bookmarks_bookmark">   
						<?php the_title( '<a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a>' ); ?>
						<?php the_excerpt(); ?>
						<span class="my_bookmarks_tags">Tagged <?php the_terms( get_the_ID(), 'bookmark_tag' ); ?></span>
						<span class="my_bookmarks_visibility">
							<?php
							if ( get_post_status ( $ID ) == 'private' ) {
								echo '<i class="fa fa-lock" aria-hidden="true"></i>' . PHP_EOL;
							} else {
								echo '<i class="fa fa-unlock" aria-hidden="true"></i>' . PHP_EOL;
							}
							?>
						</span>
						<span class="my_bookmarks_added">Saved on <?php the_time('d F Y'); ?></span>
					</div><!-- /bookmark -->
				</article><!-- #post-## -->
				<?php
			endwhile;

			the_posts_navigation();
		else :
			get_template_part( 'template-parts/content', 'none' );
		endif;
		?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_sidebar();
get_footer();
