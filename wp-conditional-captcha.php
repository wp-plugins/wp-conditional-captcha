<?php
/*
Plugin Name: Conditional CAPTCHA for Wordpress
Plugin URI: http://rayofsolaris.co.uk/blog/plugins/conditional-captcha-for-wordpress/
Description: A plugin that asks the commenter to complete a simple CAPTCHA if Akismet thinks their comment is spam. If they fail, the comment is automatically deleted, thereby leaving you with only the (possible) false positives to sift through.
Version: 1.0
Author: Samir Shah
Author URI: http://rayofsolaris.co.uk/
*/

/*  Copyright 2009  Samir Shah  (email : samir[at]rayofsolaris.co.uk)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*  Parts of this code are based on the tantan spam plugin by wordpress.org user joetan */

class conditional_captcha {
	private $captcha_vars;
	private $key;
	
	/* constructor for PHP <5 */
	function conditional_captcha() { $this->__construct(); }
	
	function __construct() {
		if(!defined('AKISMET_VERSION')) return; /* akismet isn't installed, forget the rest */
		add_filter('preprocess_comment', array(&$this, 'check_captcha'), 0); /* BEFORE akismet */
		add_action('rightnow_end', array(&$this, 'conditional_captcha_rightnow'), 11); /* show stats after Akismet */
		$this->key = defined('SECRET_KEY') ? SECRET_KEY : 'alskdjghaskldgbLHSAFVGldshlSDHGBsdg'.DB_USER;
	}
	
	function check_captcha($comment) {
		if (isset($_POST['captcha_hash']) ) {
			/* then a captcha has been completed... verify, and kill if it fails */
			$result = $this->captcha_is_valid();
			if($result !== true) {
				wp_die ($result.' Your comment will not be accepted.', 'Comment Rejected', array('response'=>403) );
			}
			else {
				/* rewind the stats */
				update_option('conditional_captcha_count', get_option('conditional_captcha_count') - 1 ); 
			}
		}
		else {
			/* set up to intercept akismet spam */
			add_action('akismet_spam_caught', array(&$this, 'do_captcha')); 
		}
		return $comment;
	}

	function do_captcha() {
		$this->create_captcha();
		$html = '</p><form method="post">';
		/* insert the original post contents as hidden values */
		foreach ($_POST as $k => $v) $html .= '<input type="hidden" name="'.htmlentities($k).'" value="'.htmlentities(stripslashes($v) ).'" />';
		/* and then the captcha */
		$html .= '
			<p><label for="captcha_response">What are the <strong>'.$this->number_ordinal($this->captcha_vars['num1']).'</strong> and <strong>'.$this->number_ordinal($this->captcha_vars['num2']).'</strong> characters of the following sequence?</label></p>
			<p><strong><span style="color: red">'.$this->captcha_vars['challenge'].'</span></strong>&nbsp;&nbsp;<input id="captcha_response" name="captcha_response" type="text" size="5" maxlength="2" value="" tabindex="1" /></p>
			<input type="hidden" id="captcha_hash" name="captcha_hash" value="'.$this->captcha_vars['hash'].'" />'.
			wp_nonce_field('conditional_captcha', 'captcha_nonce', false, false).
			'<input type="submit" value="I\'m human!" /></form><p>';
		
		/* stats - this count will be reversed if they correctly complete the CAPTCHA */
		update_option('conditional_captcha_count', get_option('conditional_captcha_count') + 1); 
		wp_die('Sorry, but I think you might be a spambot. Please complete the CAPTCHA below to prove that you are human.' . $html, 'Verification Required', array('response' => 403) );
	}
	
	function conditional_captcha_rightnow() {
		if ($count = get_option('conditional_captcha_count') ) {
			$text = sprintf('%1$s spam comments have been automatically discarded by the <em>Conditional Captcha</em> plugin.', number_format_i18n($count) );
			echo "<p class='conditional-comments-stats'>$text</p>\n";
		}
	}

	private function create_captcha() {
		$chall = strtoupper(substr(sha1($this->key.rand()),0,6));	/* random string with 6 characters */
		$num1 = rand(1,5);	/* random number between 1 and 5 */
		$num2 = rand($num1 + 1,6);	/* random number between $num1 and 6 */
		$ans1 = substr($chall,$num1-1,1);
		$ans2 = substr($chall,$num2-1,1);
		$hash = substr(sha1($ans1.$ans2.$this->key),0,5);
		
		/* load vars */
		$this->captcha_vars = array('num1' => $num1, 
																'num2' => $num2, 
																'challenge' => $this->add_spaces($chall), 
																'hash' => $hash
																);
	}
		
	private function captcha_is_valid() {
		/* check that the nonce is valid */
		if(!wp_verify_nonce($_POST['captcha_nonce'], 'conditional_captcha') ) 
			return 'Trying something funny, are we?';
		/* ...and then the captcha */
		$resp = strtoupper($_POST['captcha_response']);
		return ($_POST['captcha_hash'] == substr(sha1($resp.$this->key),0,5) ) ? 
			true : 'Sorry, the CAPTCHA wasn\'t entered correctly.';
	}
		
	private function add_spaces($str) {
		/* adds spaces between each character of a string */
		$strarray = str_split($str);
		$n = sizeof($strarray);
		$output = '';
		for($i=0; $i < $n-1; $i++) {
			$output .= $strarray[$i].' ';
		}
		$output .= $strarray[$n-1];	// last char doesn't get a space
		return $output;
	}
		
	private function number_ordinal($n) {
		$ordinals = array('zeroth','first','second','third','fourth','fifth','sixth','seventh','eighth','ninth','tenth');
		return $ordinals[$n];
	}
} /* class */

$conditional_captcha = new conditional_captcha();
?>