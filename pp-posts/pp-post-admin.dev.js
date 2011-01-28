jQuery(document).ready( function($) {

	$('.column-author').removeClass('column-author');
	
	$('.curtime:first').removeClass('misc-pub-section-last');

	if ( $('#post-status-display').html() == '\n' || $('#post-status-display').html() == ' Ended' ){
		$('#post-status-display').html(' Ended');
		$('#save-post').hide();
		$('.edit-post-status').hide();
		$('#post_status').prepend('<option checked="checked" value="ended">Ended</option>');
		$('.edit-visibility').hide();
		$('.edit-timestamp').hide();
		$('#publish').val( ppPostL10n.update );
	}

	var stamp = $('#endtimestamp').html();

	function updateText() {
		var attemptedDate, originalDate, currentDate, endOn;

		attemptedDate = new Date( $('#yye').val(), $('#mme').val() -1, $('#dde').val(), $('#hhe').val(), $('#mne').val());
		originalDate = new Date( $('#hidden_yye').val(), $('#hidden_mme').val() -1, $('#hidden_dde').val(), $('#hidden_hhe').val(), $('#hidden_mne').val());
		currentDate = new Date( $('#cur_yye').val(), $('#cur_mme').val() -1, $('#cur_dde').val(), $('#cur_hhe').val(), $('#cur_mne').val());

		if ( $('#original_post_status').val() != 'ended' && $('#original_post_status').val() != 'draft' ){
			if ( attemptedDate <= currentDate ) {
				endOn = ppPostL10n.endedOn;
				$('#publish').val( ppPostL10n.end );
			} else if ( attemptedDate > currentDate ) {
				endOn = ppPostL10n.endOn;
				$('#publish').val( ppPostL10n.update );
			} else { endOn = ppPostL10n.endOn; }
		} else if ( $('#original_post_status').val() == 'draft' ) {
			endOn = ppPostL10n.endOn;
			$('#publish').val( ppPostL10n.publish );
		} else if ( $('#original_post_status').val() == 'ended' ) {
			//alert(originalDate.toUTCString());
			//alert(attemptedDate.toUTCString());
			if ( originalDate.toUTCString() == attemptedDate.toUTCString() ) {
				endOn = ppPostL10n.endOn;
				$('#endtimestamp').html(stamp);
				$('#publish').val( ppPostL10n.repost );
			} else {
	 			if ( attemptedDate <= currentDate ) {
					endOn = ppPostL10n.endedOn;
					$('#publish').val( ppPostL10n.update );
				} else if ( attemptedDate > currentDate ) {
					endOn = ppPostL10n.endOn;
					$('#publish').val( ppPostL10n.repost );
				} else {
					endOn = ppPostL10n.endOn;
				}
			}
		} else {
			endOn = ppPostL10n.endOn;
		}

		$('#endtimestamp').html(
			endOn + ' <b>' +
			$( '#mme option[value=' + $('#mme').val() + ']' ).text() + ' ' +
			$('#dde').val() + ', ' +
			$('#yye').val() + ' @ ' +
			$('#hhe').val() + ':' +
			$('#mne').val() + '</b> '
		);

		if ( attemptedDate > currentDate && originalDate.toUTCString() != attemptedDate.toUTCString() ){
			$('.edit-post-status').show();
			$('.edit-visibility').show();
			$('.edit-timestamp').show();
		}

		if ( $('#post_status :selected').val() == 'ended' && attemptedDate > currentDate ) {
			$('#publish').val( ppPostL10n.repost );
		}
		if ( $('#post_status :selected').val() == 'draft' && ( attemptedDate <= currentDate || $('#original_post_status').val() != 'draft' ) ) {
			$('#publish').val( ppPostL10n.update );
		}
	}

	$('.edit-endtimestamp').click(function () {
		if ($('#endtimestampdiv').is(":hidden")) {
			$('#endtimestampdiv').slideDown("normal");
			$('.edit-endtimestamp').hide();
		}
		return false;
	});

	$('.cancel-endtimestamp').click(function() {
		$('#endtimestampdiv').slideUp("normal");
		$('#mme').val($('#hidden_mme').val());
		$('#dde').val($('#hidden_dde').val());
		$('#yye').val($('#hidden_yye').val());
		$('#hhe').val($('#hidden_hhe').val());
		$('#mne').val($('#hidden_mne').val());
		$('.edit-endtimestamp').show();
		updateText();
		return false;
	});

	$('.save-endtimestamp').click(function () {
		$('#endtimestampdiv').slideUp("normal");
		$('.edit-endtimestamp').show();
		updateText();
		return false;
	});

	$('.save-post-status').click(function() {
		updateText();
		return false;
	});
	
});
