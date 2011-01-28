jQuery(document).ready(function($) {

	$(".bid-form").live( 'submit', function() {
		var link = window.location;
		var bidFormId = $(this).attr('id');
		var formData = $(this).serialize() + '&bid_submit=ajax';

		$("#bid_submit",this).attr("disabled", "true");
		$(this).css('background', 'url("http://localhost.localdomain/trunk/wp-content/plugins/prospress/images/loadroller.gif") no-repeat center');
		$(this).fadeTo(500,0.5,function(){
			$.post(link,formData,function(data) {
				try{ var jso = $.parseJSON(data);
					window.location.replace(jso.redirect);
					} catch(err){
						var htmlStr = $(data);
						if($(".bid_msg",$('#'+bidFormId)).html().length == 0){
							$(".bid_msg",htmlStr).hide();
						}
						$('#'+bidFormId).replaceWith(htmlStr.fadeTo(0,0.5));
						$('#'+bidFormId).fadeTo(500,1,function(){
							if($(".bid_msg",$('#'+bidFormId)).is(":hidden")){
								$(".bid_msg",$('#'+bidFormId)).slideDown();
							}
							fadeBidMsg($('#'+bidFormId));
						});
					}
			});
		});
		return false;
	});

	function fadeBidMsg(context){
		$(".bid_msg",context).fadeTo(500,0.5);
		$(".bid_msg",context).fadeTo(500,1);
		return false;
	}
	fadeBidMsg();

	final_countdown();
});						
/*
we're heading to venus...
Reads through document object for elements with class pp-end or countdown, reads id(Unix timestamp in seconds)
writes to element in html the remaining time updating every second
*/
function final_countdown(){
		jQuery(document).ready(function($) {
			$(".pp-end,.countdown").each( function () {
				var post_end = new Date();
				var now = new Date();
						//extract end date
				post_end.setTime($(this).attr('id')*1000);	//format to UTC(miliseconds)
			//calculate time difference
				var td=post_end-now;
			//convert time difference to groups: Years, Months, Weeks, Days, Hours, Minutes, Seconds remaining
				var one_day=1000*24*60*60;
				var one_hr=1000*60*60;
				var one_min=60000;
				var hours=Math.floor((td%one_day)/one_hr);
				var mins=Math.floor(((td%one_day)%one_hr)/one_min);
				var secs=Math.floor((td%one_day)%one_hr%one_min/1000);
			    var m_days = new Array(31,28,31,30,31,30,31,31,30,31,30,31);
				var days = post_end.getDate()-now.getDate();
				var months = post_end.getMonth() - now.getMonth(); 
				var years= post_end.getFullYear()-now.getFullYear();
			//wrap around for months
				if(months<0&&years>=0){months=12+months;years-=1}
			//wrap around for days
				if(days<0&&(post_end-now>0)){
					days=(post_end.getMonth()!=0)?(post_end.getDate()+m_days[post_end.getMonth()-1]-now.getDate()):
					post_end.getDate()+m_days[11]-now.getDate();
				};	
			//case for weeks remaining
				var weeks = Math.floor(days/7);
				var days_lw = days%7;
			//Separate output for y/m/w/d/h/m/s ('s)
				var y=(years>0&&years<2)?years+' year ':(years!=0)?years+' years ':'';
				var m=(months>0&&months<2)?months+' month ':(months>0)?months+' months ':'';
				var w=(weeks>0&&weeks<2)?weeks+' week ':(weeks!=0)?weeks+' weeks ':'';
				var dlw=(days_lw>0&&days_lw<2)?days_lw+' day':(days_lw!=0)?days_lw+' days ':'';
				var d=(days>0&&days<2&&td>one_day)?days+' day':(days!=0&&td>one_day)?days+' days':'';
				function _0(i){if (i<10)i="0"+i;return i;}//add leading zero
				var hms=(td<one_day)?_0(hours)+":"+_0(mins)+":"+_0(secs):'';

			//output string
				$(this).html((post_end-now<=0)?"Auction has ended":(weeks>0)? y+m+w+dlw+' remaining': y+m+d+hms+' remaining');
				return;
		});	
	});
				//refresh every second
	setTimeout('final_countdown()',1000);			
};
