<?php
/**
 * @package Capital_Quotes
 * @author Brent Shepherd
 * @version 1.0
 */
/*
Plugin Name: Capital Quotes
Plugin URI: http://prospress.org
Description: This plugin retells the collective genius behind humanity's heightened prosperity. When activated you will see a random quote related to economics at the top of admin page. Its like Hello Dolly, but for budding economists. 
Author: Brent Shepherd
Version: 0.2
Author URI: http://brentshepherd.com/
*/

function get_capital_quote() {
	$quotes = "Poverty is unnecessary. - <span class='b'>Muhammad Yunus</span>
Underlying most arguments against the free market is a lack of belief in freedom itself. - <span class='b'>Milton Friedman</span>
Concentrated power is not rendered harmless by the good intentions of those who create it. - <span class='b'>Milton Friedman</span>
An idealist is a person who helps other people to be prosperous. - <span class='b'>Henry Ford</span>
Is the 'invisible hand' attached to a clothed arm? - <span class='b'>John McMillan</span>
The Internet is turning economics inside-out. - <span class='b'>Uri Geller</span>
Life is full of chances and changes, and the most prosperous of men may...meet with great misfortunes. - <span class='b'>Aristotle</span>
Unleash prosperity for everybody. - <span class='b'>Barack Obama</span>
No people ever yet benefited by riches if their prosperity corrupted their virtue - <span class='b'>Theodore Roosevelt<span class='b'>
Being free and prosperous in a world at peace. That's our ultimate goal. - <span class='b'>Ronald Reagan<span class='b'>
All money is a matter of belief. - <span class='b'>Adam Smith</span>";
//Freedom granted only when it is known beforehand that its effects will be beneficial is not freedom. - <span class='b'>Friedrich von Hayek</span>";

	// Here we split it into lines
	$quotes = explode("\n", $quotes);

	// And then randomly choose a line
	return wptexturize( $quotes[ mt_rand(0, count($quotes) - 1) ] );
}

// This just echoes the chosen line, we'll position it later
function capital_quotes() {
	$chosen = get_capital_quote();
	echo "<div id='capital-container'><blockquote id='capital'>$chosen</blockquote></div>";
}

// Now we set that function up to execute when the admin_footer action is called
add_action( 'admin_footer', 'capital_quotes' );
add_action( 'gg_assorted_instanity', 'capital_quotes' );

// We need some CSS to position the paragraph
function capital_admin_css() {
	echo "
<style type='text/css'>
#capital-container {position: absolute; top: 0.5em; left: 28em; right: 25em; margin: 0; }
#capital { margin: 0; font-weight: normal; color: #acacac; line-height: 1.5em; text-align: center; }
#capital .b {font-weight: bold;}
</style>";
}
add_action( 'admin_head', 'capital_admin_css' );

function capital_css() {
	echo "
<style type='text/css'>
#capital-container {margin: 0; background: transparent;float:left;}
#capital { 
	margin: 0; 
	font-weight: normal; 
	color: #464646; 
	font-size:20px;
	letter-spacing: 0.1em;
	text-align:left; 
	background: transparent; 
	border:none; 
	font-family: 'Diner-FattRegular',helvetica,arial,sans-serif; 
	padding:0;
	}
#capital .b {font-style:normal;}
</style>";
}
add_action( 'wp_print_styles', 'capital_css' );
