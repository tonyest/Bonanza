<?php
/**
 * Feedback Form.
 *
 * @package Prospress
 */

if( !isset( $disabled ) )
	$disabled = ( get_option( 'edit_feedback' ) == 'true' ) ? '' : 'disabled="disabled"';

?>

<div class="wrap" id="feedback">
	<?php if( isset ( $feedback_msg ) ): //if( isset ( $_POST [ 'feedback_submit' ] ) ):?>
		<div id="message" class="updated fade">
			<p><strong>
				<?php echo $feedback_msg; //_e('Feedback Submitted!', 'prospress' ); ?>
			</strong></p>
		</div>
	<?php endif; ?>
	<?php if ( isset( $errors ) && is_wp_error( $errors ) ) : ?>
		<div class="error">
			<ul>
			<?php foreach( $errors->get_error_messages() as $message )
				echo "<li>$message</li>";
			?>
			</ul>
		</div>
	<?php endif; ?>
	<?php screen_icon(); ?>
	<h2><?php echo esc_html( $title ); ?></h2>
	<form name="feedback-form" id="feedback-form" action="" method="post">
		<input type="hidden" name="feedback_recipient" value="<?php echo $feedback_recipient ?>" />
		<input type="hidden" name="post_id" value="<?php echo $post->ID ?>" />
		<table class="form-table">
			<tr>
				<th scope="row" >
					<label for="feedback_comment"><?php _e( 'Comment', 'prospress' ) ?></label>
				</th>
				<td>
					<input type="textarea" name="feedback_comment" id="feedback_comment" class="regular-text" cols="22" rows="5" value="<?php echo $feedback_comment; ?>" 
					<?php echo $disabled; ?> 
					/>
				</td>
			</tr>
			<tr>
				<th scope="row" >
					<label for="feedback_score"><?php _e( 'Rating', 'prospress' ) ?></label>
				</th>
				<td><fieldset>
					<label for="rating_2">
						<input name="feedback_score" type="radio" id="rating_2" value='2' <?php echo $disabled;
						echo ( isset( $feedback_score ) && $feedback_score == 2 ) ? 'checked="checked"' : ''; ?> /> 
						<?php _e( 'Positive', 'prospress' ); ?>
					</label>
					<label for="rating_1">
						<input name="feedback_score" type="radio" id="rating_1" value='1' <?php echo $disabled;
						echo ( isset( $feedback_score ) && $feedback_score == 1 ) ? 'checked="checked"' : ''; ?> /> 
						<?php _e( 'Neutral', 'prospress' ); ?>
					</label>
					<label for="rating_0">
						<input name="feedback_score" type="radio" id="rating_0" value='0' <?php echo $disabled;
						echo ( isset( $feedback_score ) && $feedback_score == 0 ) ? 'checked="checked"' : ''; ?> /> 
						<?php _e( 'Negative', 'prospress' ); ?>
					</label>
				</fieldset></td>
			</tr>
		</table>
		<p id="GiveFeedback_Submit" class="submit">
			<input type="submit" name="feedback_submit" id="feedback_submit" class="button-primary action" value="<?php _e('Submit Feedback', 'prospress' ); ?>" tabindex="100"  
			<?php echo $disabled; ?> />
		</p>
	</form>
</div>