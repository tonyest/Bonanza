<?php
/*
Template Name: Single Prospress Post
*/
/**
 * This the default template for displaying a single Prospress post.
 * It includes all the basic elements for a Prospress post in a very 
 * neutral style.
 *
 * @package Prospress
 * @subpackage Theme
 * @since 0.1
 */
?>
<?php get_header(); ?>
	<div id="container">
		<div id="content">
		<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>
			<h2 class="pp-title entry-title"><?php the_title();?></h2>

			<?php the_bid_form(); ?>

			<div class="pp-content">
				<?php the_content(); ?>
			</div>

			<div id="nav-below" class="navigation">
				<div class="nav-index"><a href="<?php echo $market->get_index_permalink(); ?>"><?php printf( __("&larr; Return to %s Index", 'Prospress'), $market->name() ); ?></a></div>
			</div>

			<?php comments_template( '', true ); ?>

		<?php endwhile; // end of the loop. ?>
		</div>
	</div>

	<div id="sidebar" class="prospress-sidebar">
		<ul class="xoxo">
			<?php dynamic_sidebar( $market->name() . '-single-sidebar' ); ?>
		</ul>
	</div>
<?php get_footer(); ?>
