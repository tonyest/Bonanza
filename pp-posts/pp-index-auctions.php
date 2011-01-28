<?php
/*
Template Name: Prospress Index
*/
/**
 * The main template file for marketplace listings.
 *
 * @package Prospress
 * @subpackage Theme
 * @since 0.1
 */
?>
<?php get_header(); ?>
	<div id="container" class="prospress-container">
		<div id="content" class="prospress-content">

		<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>

			<h1 class="prospress-title entry-title"><?php the_title(); ?></h1>
			<div class="prospres-content entry-content"><?php the_content(); ?></div>
			<div class="end-header"><?php _e( 'Ending', 'prospress' ); ?></div>
			<div class="price-header"><?php _e( 'Price', 'prospress' ); ?></div>

		<?php endwhile; ?>

		<?php $pp_loop = new WP_Query( array( 'post_type' => $market->name(), 'post_status' => 'publish' ) ); ?>

		<?php if ( $pp_loop->have_posts() ) : while ( $pp_loop->have_posts() ) : $pp_loop->the_post(); ?>

			<div class="pp-post">
				<div class="pp-post-content">
					<div class='pp-end' <?php echo "id='".get_post_end_time( $post_id, 'timestamp', 'gmt' )."'>";the_post_end_time( $the_ID, 2, '<br/>' );?></div>
					<div class="pp-price"><?php $market->the_winning_bid_value(); ?></div>
					<h2 class="pp-title entry-title">
						<a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>">
							<?php the_title(); ?>
						</a>
					</h2>
					<div class="pp-excerpt">
						<?php the_excerpt(); ?>
						<a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>">
							<?php printf( __( 'View %s &raquo', 'prospress' ), $market->labels[ 'singular_name' ] ); ?>
						</a>
					</div>
					<div class="pp-publish-details">
						<?php  _e( 'Published: ', 'prospress' ); the_time('F jS, Y'); ?>
						<?php _e( 'by ', 'prospress'); the_author(); ?>
					</div>
				</div>
			</div>

			<?php endwhile; else: ?>

				<p>No <?php echo $market->label; ?>.</p>

			<?php endif; ?>
		</div>
	</div>

	<div id="sidebar" class="prospress-sidebar">
		<ul class="xoxo">
			<?php dynamic_sidebar( $market->name() . '-index-sidebar' ); ?>
		</ul>
	</div>
<?php get_footer(); ?>
