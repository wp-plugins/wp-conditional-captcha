<?php
/*
Plugin Name: Conditional CAPTCHA for Wordpress
Plugin URI: http://rayofsolaris.net/blog/plugins/conditional-captcha-for-wordpress/
Description: A plugin that asks the commenter to complete a simple CAPTCHA if Akismet thinks their comment is spam. If they fail, the comment is automatically deleted, thereby leaving you with only the (possible) false positives to sift through.
Version: 2.1
Author: Samir Shah
Author URI: http://rayofsolaris.net/
Text Domain: wp-conditional-captcha
*/

/*  Copyright 2010 Samir Shah  (email : samir[at]rayofsolaris.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

*/

class conditional_captcha {
	private $akismet_installed;
	private $options;
	private $cssfile;
	private $trash_exists;
	const dom = 'wp-conditional-captcha'; # i18n domain
	
	function __construct() {
		global $wp_version;
		$this->cssfile = dirname( __FILE__ ) . '/captcha-style.css';
		
		$this->trash_exists = version_compare($wp_version, '2.9', '>=');
		
		add_action('activate_wp-conditional-captcha/wp-conditional-captcha.php', array(&$this, 'activate') );
		add_action('plugins_loaded', array(&$this, 'load') );
		add_action('admin_menu', array(&$this, 'settings_menu') );
		
	  load_plugin_textdomain(self::dom, false, dirname( plugin_basename( __FILE__ ) ). '/languages');
	}
	
	function activate() {
		$opts = get_option('conditional_captcha_options', array() );
		$defaults = array(
			'captcha-type' => 'default',
			'noscript' => 'default',
			'pass_action' => 'spam',
			'recaptcha-private-key' => '',
			'recaptcha-public-key' => '',
			'trash' => 'delete',
			'style' => file_get_contents($this->cssfile)
		);
		// set defaults of they don't exist
		foreach($defaults as $k => $v) if( !isset($opts[$k]) ) $opts[$k] = $v;
		update_option('conditional_captcha_options', $opts);
	}
	
	function load() {
		// check for akismet
		$this->akismet_installed = function_exists('akismet_auto_check_comment');
		if($this->akismet_installed) {
			add_filter('preprocess_comment', array(&$this, 'check_captcha'), 0); // BEFORE akismet
			add_action('rightnow_end', array(&$this, 'rightnow'), 11); // show stats after Akismet
		}
	}
	
	function settings_menu() {
		add_submenu_page('plugins.php', __('Conditional CAPTCHA Settings', self::dom), __('Conditional CAPTCHA', self::dom), 'manage_options', 'conditional_captcha_settings', array(&$this, 'settings_page') );
	}
	
	function settings_page() {
		$opts = get_option('conditional_captcha_options');
		$message = '';
		if ( isset($_POST['submit']) ) {
			$opts['pass_action'] = (isset($_POST['pass_action']) && $_POST['pass_action'] == 'approve') ? 'approve' : 'spam';
			$opts['style'] = trim( strip_tags( stripslashes( $_POST['style'] ) ) ); // css only please
			if( empty($opts['style']) ) $opts['style'] = file_get_contents($this->cssfile);	// fall back to default
			$opts['captcha-type'] = $_POST['captcha-type'];
			$opts['recaptcha-private-key'] = $_POST['recaptcha-private-key'];
			$opts['recaptcha-public-key'] = $_POST['recaptcha-public-key'];
			$opts['noscript'] = $_POST['noscript'];
			$opts['trash'] = $this->trash_exists ? $_POST['trash'] : 'delete';
			$keys_set = !empty($opts['recaptcha-private-key']) && !empty($opts['recaptcha-public-key']);
			
			// check that keys have been supplied for recaptcha
			if($opts['captcha-type'] == 'recaptcha' && !$keys_set) {
				$message = '<p style="color: red"><strong>'.__('You need to supply reCAPTCHA API keys if you want to use reCAPTCHA. Please enter private and public key values.', self::dom).'</strong></p>';
				$opts['captcha-type'] = 'default';	// revert to default
			}
			
			update_option('conditional_captcha_options', $opts);
			if(empty($message)) $message = '<p><strong>'.__('Options updated.', self::dom).'</strong></p>';
			echo '<div id="message" class="updated fade">'.$message.'</div>';
		}
	?>
	<style>
	.indent {padding-left: 2em}
	</style>
	<div class="wrap">
	<h2><?php _e('Conditional CAPTCHA Settings', self::dom);?></h2>
	<?php if(!$this->akismet_installed) echo '<div id="message" class="error fade"><p style="font-weight:bold; line-height:140%">'.__('Akismet does not appear to be active. This plugin requires Akismet to be active in order to work. Please activate Akismet before changing the settings below.', self::dom).'</p></div>';?>
	<div id="settings" <?php if(!$this->akismet_installed) echo 'style="color: #999"';?>>
	<p><?php _e("This plugin provides a CAPTCHA complement to Akismet. If Akismet identifies a comment as spam, it will ask the commenter to complete a simple CAPTCHA. If they fail, then the comment will be automatically discarded or trashed (and won't clutter up your spam queue). If they pass, it will be allowed into the spam queue. That way the spam queue will contain only the most likely false positives, making it much easier to find them.", self::dom);?></p>
	<form action="" method="post" id="conditional-captcha-settings">
	<h3><?php _e('CAPTCHA Method', self::dom);?></h3>
	<p><?php printf(__('The default captcha is a simple text-based test (<a href="%1$s" target="_blank">check out the screenshot here</a>), but if you prefer you can also use a <a href="%2$s" target="_blank">reCAPTCHA</a>. Note that you will need an API key to use reCAPTCHA.', self::dom), 'http://wordpress.org/extend/plugins/wp-conditional-captcha/screenshots/', 'http://recaptcha.net');?></p>
	<ul class="indent">
	<li><input type="radio" name="captcha-type" class="captcha-type" id="type-default" value="default" <?php if($opts['captcha-type']=='default') echo 'checked="checked"'?> /> <?php _e('Use the default text-based CAPTCHA', self::dom);?></li>
	<li><input type="radio" name="captcha-type" class="captcha-type" id="type-recaptcha" value="recaptcha" <?php if($opts['captcha-type']=='recaptcha') echo 'checked="checked"'?> /> <?php _e('Use reCAPTCHA', self::dom);?></li>
	</ul>
	<div id="recaptcha-settings" class="indent">
		<p><?php _e('If you wish to use reCAPTCHA, please enter your keys here:', self::dom);?></p>
		<ul class="indent">
		<li><label><?php _e('Public key:', self::dom);?></label> <input type="text" name="recaptcha-public-key" size="50" value="<?php echo $opts['recaptcha-public-key'] ?>" /></li>
		<li><label><?php _e('Private key:', self::dom);?></label> <input type="text" name="recaptcha-private-key" size="50" value="<?php echo $opts['recaptcha-private-key'] ?>" /></li>
		</ul>
		<p><small><?php printf(__('You can <a href="%s" target="_blank">sign up for a key here</a> (it\'s free)', self::dom), 'http://recaptcha.net/api/getkey');?></small></p>
		<p><?php _e('The client will have to have Javascript enabled in order for reCAPTCHA to work. In cases where Javascript is disabled, the plugin can:', self::dom);?></p>
		<ul class="indent">
		<li><input type="radio" name="noscript" value="default" <?php if($opts['noscript']=='default') echo 'checked="checked"'?> /> <label><?php _e('Revert to the default CAPTCHA method (recommended)', self::dom);?></label></li>
		<li><input type="radio" name="noscript" value="die" <?php if($opts['noscript']=='die') echo 'checked="checked"'?> /> <label><?php _e('Deny the user the opportunity to complete a CAPTCHA', self::dom);?></label></li>
		</ul>
	</div>
	<h3><?php _e('Comment Handling', self::dom);?></h3>
	<p><?php _e('If a CAPTCHA is successfully completed, the default action of the plugin is to leave it in the spam queue. If you would like the comment to be approved instead, check the box below:', self::dom);?></p>
	<p class="indent"><input type="checkbox" name="pass_action" value="approve" <?php if($opts['pass_action'] == 'approve') echo 'checked="checked"';?>/> <label><?php _e('Automatically approve comments if CAPTCHA is completed correctly.', self::dom);?></label></p>
	<?php if($this->trash_exists) { ?>
	<p><?php _e('When a CAPTCHA is <strong>not</strong> completed correctly:', self::dom);?></p>
	<ul class="indent">
	<li><input type="radio" name="trash" value="delete" <?php if(!isset($opts['trash']) || $opts['trash']=='delete') echo 'checked="checked"'?> /> <label><?php _e('Delete the comment permanently', self::dom);?></label></li>
	<li><input type="radio" name="trash" value="trash" <?php if($opts['trash']=='trash') echo 'checked="checked"'?> /> <label><?php _e('Trash the comment', self::dom);?></label></li>
	</ul>
	<?php } ?>
	<h3><?php _e('CAPTCHA Page Style', self::dom);?></h3>
	<p><?php _e('If you want to style your CAPTCHA page to fit with your own theme, you can modify the default CSS below:', self::dom);?></p>
	<textarea name="style" rows="8" cols="80" style="font-family: Courier,sans-serif"><?php echo $opts['style'];?></textarea>
	<p><small><?php _e('Empty this box completely to revert back to the default style.', self::dom);?></small></p>
	<p class="submit"><input type="submit" name="submit" value="Update settings" /></p>
	</form>
	</div>
	</div>
	<script type="text/javascript">
	jQuery('input[name="captcha-type"]').change(function(){
		if(jQuery('#type-recaptcha:checked').length == 1) jQuery('#recaptcha-settings').slideDown('slow');
		else jQuery('#recaptcha-settings').slideUp('slow');
	});
	jQuery(document).ready(function(){
		if(jQuery('#type-recaptcha:checked').length == 0) jQuery('#recaptcha-settings').hide();
	});
	</script>
<?php
	}
	
	function rightnow() {
		if ($count = get_option('conditional_captcha_count') ) {
			$text = sprintf(__('%s spam comments have been automatically discarded by the <em>Conditional CAPTCHA</em> plugin.', self::dom), number_format_i18n($count) );
			echo "<p class='conditional-captcha-stats'>$text</p>\n";
		}
	}

	function check_captcha($comment) {
		$this->options = get_option('conditional_captcha_options');
		if( isset($_POST['captcha_nonce']) ) {	// then a captcha has been completed...
			$result = $this->captcha_is_valid();
			if($result !== true) $this->page(__('Comment Rejected', self::dom), '<p>'.$result.' '.__('Your comment will not be accepted.', self::dom).'</p>');
			else {
				update_option('conditional_captcha_count', get_option('conditional_captcha_count') - 1 ); // rewind
				// if trash is enabled, check for the trashed comment
				if($this->options['trash'] == 'trash') {
					if( $trashed = get_comment($_POST['trashed_id']) ) {
						// change status from "trash" to one of "approve" or "spam"
						wp_set_comment_status( $trashed->comment_ID, $this->options['pass_action'] );
						// redirect like wp-comments-post does
						$location = empty($_POST['redirect_to']) ? get_comment_link($trashed->comment_ID) : $_POST['redirect_to'] . '#comment-' . $trashed->comment_ID;
						$location = apply_filters('comment_post_redirect', $location, $trashed);
						wp_redirect( $location );
						exit;
					}
					else $this->page(__('Comment rejected', self::dom), '<p>'.__('Trying something funny, are we? Your comment will not be accepted.', self::dom).'</p>'); // the trashed comment doesn't exist!
				}
				// if pass_action is 'approve', then remove akismet's hook
				else if($this->options['pass_action'] == 'approve') remove_action('preprocess_comment', 'akismet_auto_check_comment', 1);
			}
		}
		else add_action('akismet_spam_caught', array(&$this, 'spam_handler')); // set up to intercept akismet spam
		return $comment;
	}

	function spam_handler() {
		if($this->options['trash'] == 'trash') {
			add_filter('pre_comment_approved', create_function('', 'return "trash";'), 11); // after akismet marks spam
			add_action('comment_post', array(&$this, 'do_captcha')); // do captcha after comment is stored
		}
		else $this->do_captcha(); // otherwise do captcha now
	}
	
	function do_captcha($comment_id = false) {
		// comment_id will be supplied by the comment_post action if this function is called from there
		$html = '<p>'.__('Sorry, but I think you might be a spambot. Please complete the CAPTCHA below to prove that you are human.', self::dom).'</p><form method="post">';
		
		// nonce
		$nonce = $this->hash( rand() );
		$this->store_nonce($nonce);
		$html .= '<input type="hidden" name="captcha_nonce" value="'.$nonce.'">';
		
		// the captcha
		$html .= $this->create_captcha();
		// insert the original post contents as hidden values, except the submit
		foreach ($_POST as $k => $v) if($k != 'submit') $html .= '<input type="hidden" name="'.htmlentities($k).'" value="'.htmlentities(stripslashes($v) ).'" />';
		if($this->options['trash'] == 'trash') 
			$html .= '<input type="hidden" name="trashed_id" value="'.$comment_id.'" />';
		
		$html .= '<input class="submit" type="submit" value="'.__("I'm human!", self::dom).'" /></form>';
		
		// stats - this count will be reversed if they correctly complete the CAPTCHA
		update_option('conditional_captcha_count', get_option('conditional_captcha_count') + 1); 
		$this->page(__('Verification required', self::dom), $html);
	}

	private function page($title, $message) {
		// generates a page where the captcha can be completed - style can be modified
		if(!headers_sent() ){
			status_header(403);
			header('Content-Type: text/html; charset=utf-8');
		}
		$style = $this->options['style'];
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head><title>'.$title.'</title><style type="text/css">'."\n$style\n".'</style></head><body id="conditional_captcha">'.$message.'</body></html>';
		exit;
	}

	private function create_captcha() {
		if($this->options['captcha-type'] == 'recaptcha') {
			$insert = '<script type="text/javascript" src="http://api.recaptcha.net/challenge?k='.$this->options['recaptcha-public-key'].'"></script>';
			$insert .= '<noscript>';
			if($this->options['noscript'] == 'default') $insert .= $this->get_default_captcha();
			else $insert .= '<p><strong>'.__('Sorry, Javascript must be enabled in order to complete the CAPTCHA.', self::dom).'</strong></p>';
			$insert .= '<input type="hidden" name="captcha_noscript" value="true" /></noscript>';
		}
		else $insert = $this->get_default_captcha();
		return $insert;
	}
	
	private function get_default_captcha() {
		$chall = str_split( $this->hash( rand() ) );	// random string with 6 characters, split into array
		$num1 = rand(1,5);									// random number between 1 and 5
		$num2 = rand($num1 + 1,6);							// random number between $num1+1 and 6
		$hash = $this->hash( $chall[$num1-1] . $chall[$num2-1] );
		
		$ords = array('',__('first', self::dom),__('second',self::dom),__('third', self::dom),__('fourth', self::dom),__('fifth', self::dom),__('sixth', self::dom));
																
		$insert = '<p class="intro"><label for="captcha_response">'.sprintf(__('What are the %1$s and %2$s characters of the following sequence?', self::dom), '<strong>'.$ords[$num1].'</strong>', '<strong>'.$ords[$num2].'</strong>').'</label></p><p class="challenge"><strong>'.implode(' ', $chall).'</strong>&nbsp;&nbsp;<input name="captcha_response" type="text" size="5" maxlength="2" value="" tabindex="1" /></p><input type="hidden" name="captcha_hash" value="'.$hash.'" />';
		
		return $insert;
	}
	
	private function captcha_is_valid() {
		$nonce = $_POST['captcha_nonce'];
		// check that the nonce is valid and hasn't already be completed successfully
		if( !get_transient("conditional_captcha_$nonce") ) return __('Trying something funny, are we?', self::dom);
		
		$status = true;
		// if a reCAPTCHA is submitted and there is no noscript
		if($this->options['captcha-type'] == 'recaptcha' && !isset($_POST['captcha_noscript'])) {
			$resp = $this->recaptcha_check($_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field']);

			if(true !== $resp) $status = sprintf(__("Sorry, the CAPTCHA wasn't entered correctly. (reCAPTCHA said: %s)", self::dom), $resp);
		}
		// if default captcha is used either as default or noscript fallback
		elseif($this->options['captcha-type'] == 'default' || 
						($this->options['noscript'] == 'default' && isset($_POST['captcha_noscript']) ) ) {
			// do default validation
			if($_POST['captcha_hash'] != $this->hash( strtoupper($_POST['captcha_response']) ) ) $status = __("Sorry, the CAPTCHA wasn't entered correctly.", self::dom);
		}
		else $status = __("Sorry, you cannot submit a CAPTCHA with Javascript disabled in your browser.", self::dom);
		
		if(true === $status) $this->clear_nonce($nonce);	// prevent reuse
		return $status;
	}
	
	private function store_nonce($nonce) {
		set_transient("conditional_captcha_$nonce", 1, 600);
	}
	
	private function clear_nonce($nonce) {
		delete_transient("conditional_captcha_$nonce");
	}
	
	private function hash($val, $type = 'auth') {
		return strtoupper( substr( sha1( $val.wp_salt($type) ),0,6 ) );
	}

	private function recaptcha_post($data) {
		$req = http_build_query($data);
		$host = 'api-verify.recaptcha.net';
		
		$headers = array(
			'POST /verify HTTP/1.0',
			'Host: ' . $host,
			'Content-Type: application/x-www-form-urlencoded;',
			'Content-Length: ' . strlen($req),
			'User-Agent: reCAPTCHA/PHP'
		);
		
		$http_request = implode("\r\n", $headers)."\r\n\r\n".$req;

		$response = '';

		$fs = @fsockopen($host, 80, $errno, $errstr, 10);
		if(!$fs) return array('false','recaptcha-not-reachable');

		fwrite($fs, $http_request);
		while ( !feof($fs) ) $response .= fgets($fs, 1160); // One TCP-IP packet
		fclose($fs);
		$parts = explode("\r\n\r\n", $response, 2);	// [0] = response header, [1] = body
		return explode("\n", $parts[1]);					// [0] 'true' or 'false', [1] = error message
	}

	private function recaptcha_check ($challenge, $response) {
		if (!$challenge || !$response) return 'incorrect-captcha-sol';		// discard spam submissions upfront

		$response = $this->recaptcha_post( array('privatekey' => $this->options['recaptcha-private-key'], 'remoteip' => $_SERVER['REMOTE_ADDR'], 'challenge' => $challenge, 'response' => $response) );

		// if the first part isn't true then return the second part
		return (trim($response[0]) == 'true') ? true : $response[1];
	}

} // class

// load
$conditional_captcha = new conditional_captcha();
?>