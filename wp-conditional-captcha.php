<?php
/*
Plugin Name: Conditional CAPTCHA for Wordpress
Plugin URI: http://rayofsolaris.co.uk/blog/plugins/conditional-captcha-for-wordpress/
Description: A plugin that asks the commenter to complete a simple CAPTCHA if Akismet thinks their comment is spam. If they fail, the comment is automatically deleted, thereby leaving you with only the (possible) false positives to sift through.
Version: 1.2
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

require_once (WP_PLUGIN_DIR.'/wp-conditional-captcha/recaptchalib.php');
class conditional_captcha {
	private $key;
	private $akismet_installed;
	private $options;
	private $cssfile;
	
	/* constructor for PHP <5 */
	function conditional_captcha() { return $this->__construct(); }
	
	function __construct() {
		$this->akismet_installed = defined('AKISMET_VERSION');
		add_action('admin_menu', array(&$this, 'settings_menu') );
		
		/* initiate options for backward compatibility */
		if(!get_option('conditional_captcha_options')) {
			update_option('conditional_captcha_options', array('captcha-type'=>'default') );
		}
		
		if($this->akismet_installed) {
			add_filter('preprocess_comment', array(&$this, 'check_captcha'), 0); /* BEFORE akismet */
			add_action('rightnow_end', array(&$this, 'conditional_captcha_rightnow'), 11); /* show stats after Akismet */
			$this->key = defined('SECRET_KEY') ? SECRET_KEY : 'alskdjghaskldgbLHSAFVGldshlSDHGBsdg'.DB_USER;
			$this->cssfile = WP_PLUGIN_DIR.'/wp-conditional-captcha/captcha-style.css';
		}
	}
	
	function settings_menu() {
		add_submenu_page('plugins.php', 'Conditional CAPTCHA Settings', 'Conditional CAPTCHA', 'manage_options', 'conditional_captcha_settings', array(&$this, 'settings_page') );
	}
	
	function settings_page() {
		$opts = get_option('conditional_captcha_options');
		if (isset($_POST['submit']) ) {
			$type = $_POST['captcha-type'];
			if($opts['captcha-type'] != $type) {
				/* user wants to change type */
				if($type == 'default') $opts['captcha-type'] = $type;
				elseif($type == 'recaptcha') {
					/* check that keys have been supplied */
					if(empty($_POST['recaptcha-private-key']) || empty($_POST['recaptcha-public-key']) ) {
						?>
						<div id="message" class="updated fade"><p style="color: red"><strong>You need to supply reCAPTCHA API keys if you want to use that option. Please enter a private and public key.</strong></p></div>
						<?php
					}
					else {
						$opts['captcha-type'] = $type;
						$opts['recaptcha-private-key'] = $_POST['recaptcha-private-key'];
						$opts['recaptcha-public-key'] = $_POST['recaptcha-public-key'];
						?>
						<div id="message" class="updated fade"><p><strong>Options updated.</strong></p></div>
						<?php
					}
				}
			}
			$opts['style'] = strip_tags($_POST['style']); /* css only please */
			update_option('conditional_captcha_options', $opts);
		}
	?>
	<div class="wrap">
	<h2>Conditional CAPTCHA Settings</h2>
	<?php if(!$this->akismet_installed) echo '<div id="message" class="updated fade"><p class="color: red"><strong>Akismet does not appear to be active. This plugin requires Akismet to be active in order to work.</strong></p></div>';?>
	<p>This plugin provides a CAPTCHA complement to Akismet. If Akismet identifies a comment as spam, it will ask the commenter to complete a simple CAPTCHA. If they fail, then the comment will be automatically discarded (and won't clutter up your spam queue). If they pass, it will be allowed into the spam queue. That way the spam queue will contain only the most likely false positives, making it much easier to find them.</p>
	<p>The default captcha is a simple text-based test (<a href="http://wordpress.org/extend/plugins/wp-conditional-captcha/screenshots/" target="_blank">check out the screenshot here</a>), but if you prefer you can also use a <a href="http://recaptcha.net" target="_blank">reCAPTCHA</a>. You can select which CAPTCHA to use below. Note that you will need an API key to use reCAPTCHA.</p>
	<form action="" method="post" id="conditional-captcha-settings" >
	<ul style="list-style-type: none">
	<li><input type="radio" name="captcha-type" class="captcha-type" id="type-default" value="default" <?php if($opts['captcha-type']=='default') echo 'checked="checked"'?> /> Use the default text-based CAPTCHA</li>
	<li><input type="radio" name="captcha-type" class="captcha-type" id="type-recaptcha" value="recaptcha" <?php if($opts['captcha-type']=='recaptcha') echo 'checked="checked"'?> /> Use reCAPTCHA</li>
	</ul>
	<div id="recaptcha-settings" <?php if($opts['captcha-type']=='default') echo 'style="color: #999"';?>>
		<p>If you wish to use reCAPTCHA, please enter your keys here:</p>
		<ul>
		<li><label for="recaptcha-private-key">Private key:</label> <input type="text" name="recaptcha-private-key" size="50" value="<?php echo $opts['recaptcha-private-key'] ?>" /></li>
		<li><label for="recaptcha-public-key">Public key:</label> <input type="text" name="recaptcha-public-key" size="50" value="<?php echo $opts['recaptcha-public-key'] ?>" /></li>
		</ul>
		<p><small>If you don't have reCAPTCHA key, you can <a href="http://recaptcha.net/api/getkey">sign up for one here</a> (it's free)</small></p>
	</div>
	<p>If you want to style your CAPTCHA page to fit with your own theme, you can modify the default CSS below:</p>
	<textarea name="style" rows="10" cols='100'><?php if(!empty($opts['style'])) echo $opts['style']; else echo(file_get_contents($this->cssfile) );?></textarea>
	<p><small>Empty this box completely if you want to revert back to the default style.</small></p>
	<p class="submit"><input type="submit" name="submit" value="Update settings" /></p>
	</form>
	</div>
	<script type="text/javascript">
	/* a little bit of user-friendliness */
	jQuery('.captcha-type').change(function () {highlight_conditional() ;} );
	function highlight_conditional() {
			var rcc = jQuery('#type-recaptcha:checked').length;
			if(rcc == 1) {jQuery('#recaptcha-settings').css('color', '#000');}
			else {jQuery('#recaptcha-settings').css('color', '#999');}
	}
	</script>
<?php
	}

	function check_captcha($comment) {
		$this->options = get_option('conditional_captcha_options');
		if($this->options['captcha-type'] == 'recaptcha') $lookfor = 'recaptcha_challenge_field';
		else $lookfor = 'captcha_hash'; // default
		
		if (isset($_POST[$lookfor]) ) {
			/* then a captcha has been completed... verify, and kill if it fails */
			$result = $this->captcha_is_valid();
			if($result !== true) {
				$this->conditional_captcha_page('Comment Rejected', '<p>'.$result.' Your comment will not be accepted.</p>');
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
		$html = '<p>Sorry, but I think you might be a spambot. Please complete the CAPTCHA below to prove that you are human.</p><form method="post">';
		/* insert the original post contents as hidden values */
		foreach ($_POST as $k => $v) $html .= '<input type="hidden" name="'.htmlentities($k).'" value="'.htmlentities(stripslashes($v) ).'" />';
		/* and then the captcha */
		$html .= $this->create_captcha();
		$html .= wp_nonce_field('conditional_captcha', 'captcha_nonce', false, false);
		$html .= '<input class="submit" type="submit" value="I\'m human!" /></form>';
		
		/* stats - this count will be reversed if they correctly complete the CAPTCHA */
		update_option('conditional_captcha_count', get_option('conditional_captcha_count') + 1); 
		$this->conditional_captcha_page('Verification required', $html);
	}
	
	function conditional_captcha_rightnow() {
		if ($count = get_option('conditional_captcha_count') ) {
			$text = sprintf('%1$s spam comments have been automatically discarded by the <em>Conditional CAPTCHA</em> plugin.', number_format_i18n($count) );
			echo "<p class='conditional-comments-stats'>$text</p>\n";
		}
	}

	private function conditional_captcha_page($title, $message) {
		$style = empty($this->options['style']) ? file_get_contents($this->cssfile) : $this->options['style'];
		/* generates a page where the captcha can be completed - style can be modified */
		if (!did_action('admin_head')) :
			if(!headers_sent() ){
				status_header(403);
				nocache_headers();
				header('Content-Type: text/html; charset=utf-8');
			}
		?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml" <?php if(function_exists('language_attributes')) language_attributes();?>>
		<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title><?php echo $title ?></title>
		<style type="text/css"><?php echo $style;?></style>
		</head>
		<body id="conditional_captcha">
		<?php endif; ?>
			<?php echo $message; ?>
		</body>
		<!-- Ticket #8942, IE bug fix: always pad the error page with enough characters such that it is greater than 512 bytes, even after gzip compression abcdefghijklmnopqrstuvwxyz1234567890aabbccddeeffgghhiijjkkllmmnnooppqqrrssttuuvvwwxxyyzz11223344556677889900abacbcbdcdcededfefegfgfhghgihihjijikjkjlklkmlmlnmnmononpopoqpqprqrqsrsrtstsubcbcdcdedefefgfabcadefbghicjkldmnoepqrfstugvwxhyz1i234j567k890laabmbccnddeoeffpgghqhiirjjksklltmmnunoovppqwqrrxsstytuuzvvw0wxx1yyz2z113223434455666777889890091abc2def3ghi4jkl5mno6pqr7stu8vwx9yz11aab2bcc3dd4ee5ff6gg7hh8ii9j0jk1kl2lmm3nnoo4p5pq6qrr7ss8tt9uuvv0wwx1x2yyzz13aba4cbcb5dcdc6dedfef8egf9gfh0ghg1ihi2hji3jik4jkj5lkl6kml7mln8mnm9ono -->
		</html>
		<?php
		die();
	}

private function create_captcha() {
		if($this->options['captcha-type'] == 'recaptcha') {
			/* get recaptcha form */
			$insert = recaptcha_get_html($this->options['recaptcha-public-key']);
		}
		else {
			/* default */
			$chall = strtoupper(substr(sha1($this->key.rand()),0,6));	/* random string with 6 characters */
			$num1 = rand(1,5);	/* random number between 1 and 5 */
			$num2 = rand($num1 + 1,6);	/* random number between $num1 and 6 */
			$ans1 = substr($chall,$num1-1,1);
			$ans2 = substr($chall,$num2-1,1);
			$hash = substr(sha1($ans1.$ans2.$this->key),0,5);
																	
			$insert = '<p><label for="captcha_response">What are the <strong>'.$this->number_ordinal($num1).'</strong> and <strong>'.$this->number_ordinal($num2).'</strong> characters of the following sequence?</label></p>
				<p><strong><span style="color: red">'.$chall.'</span></strong>&nbsp;&nbsp;<input id="captcha_response" name="captcha_response" type="text" size="5" maxlength="2" value="" tabindex="1" /></p>
				<input type="hidden" id="captcha_hash" name="captcha_hash" value="'.$hash.'" />';
		}
		return $insert;
	}
		
	private function captcha_is_valid() {
		/* check that the nonce is valid */
		if(!wp_verify_nonce($_POST['captcha_nonce'], 'conditional_captcha') ) 
			return 'Trying something funny, are we?';
		/* ...and then the captcha */
		if($this->options['captcha-type'] == 'recaptcha') {
			$resp = recaptcha_check_answer ($this->options['recaptcha-private-key'],
                                        $_SERVER["REMOTE_ADDR"],
                                        $_POST["recaptcha_challenge_field"],
                                        $_POST["recaptcha_response_field"]);
			return $resp->is_valid ? true : 'Sorry, the CAPTCHA wasn\'t entered correctly. (reCAPTCHA said: '.$resp->error.')';
		}
		else {
			/* do default validation */
			$resp = strtoupper($_POST['captcha_response']);
			return ($_POST['captcha_hash'] == substr(sha1($resp.$this->key),0,5) ) ? 
				true : 'Sorry, the CAPTCHA wasn\'t entered correctly.';
		}
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