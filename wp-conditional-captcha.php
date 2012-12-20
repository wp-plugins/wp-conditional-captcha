<?php
/*
Plugin Name: Conditional CAPTCHA for Wordpress
Plugin URI: http://wordpress.org/extend/plugins/wp-conditional-captcha/
Description: A plugin that asks the commenter to complete a simple CAPTCHA if Akismet thinks their comment is spam. All other commenters never see a CAPTCHA.
Version: 3.3
Author: Samir Shah
Author URI: http://rayofsolaris.net/
License: GPL2
Text Domain: wp-conditional-captcha
*/

if( !defined( 'ABSPATH' ) )
	exit;

class Conditional_Captcha {
	private $ready = false;
	private $options, $cssfile, $antispam;
	const db_version = 6;					// options version, introduced in v2.6
	
	function __construct() {
		$this->cssfile = dirname( __FILE__ ) . '/captcha-style.css';
		load_plugin_textdomain( 'wp-conditional-captcha', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		add_action( 'wp_loaded', array( $this, 'load' ) );
		$this->load_options();
	}
	
	private function load_options() {
		// load options into $this->options, checking for version changes
		$this->options = get_option( 'conditional_captcha_options', array() );
		
		// If it looks like first run, check compat
		if ( empty( $this->options ) && version_compare( $GLOBALS['wp_version'], '3.2', '<' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			deactivate_plugins( __FILE__ );
			if ( isset( $_GET['action'] ) && ( $_GET['action'] == 'activate' || $_GET['action'] == 'error_scrape' ) )
				exit( 'Conditional CAPTCHA for WordPress requires WordPress version 3.2 or greater.' );
		}
		
		// if db_version has changed...
		if( !isset( $this->options['db_version'] ) || $this->options['db_version'] != self::db_version ) {
			$defaults = array(
				'captcha-type' => 'default', 'pass_action' => 'hold',
				'recaptcha-private-key' => '', 'recaptcha-public-key' => '', 'recaptcha_theme' => 'red', 'recaptcha_lang' => 'en',
				'fail_action' => 'spam', 'style' => '', 'prompt_text' => '', 'akismet_no_login' => false, 'akismet_no_history' => false
			);
			
			// set defaults if they don't exist
			foreach( $defaults as $k => $v ) if( !isset( $this->options[$k] ) ) $this->options[$k] = $v;
			
			// this was renamed
			if( isset( $this->options['trash'] ) )
				$this->options['fail_action'] = $this->options['trash'];
			
			// remove old options
			unset( $this->options['noscript'] );
			unset( $this->options['trash'] );
			
			// don't store CSS if it's the default
			if( trim( str_replace( "\r\n", "\n", file_get_contents( $this->cssfile ) ) ) == trim( str_replace( "\r\n", "\n", $this->options['style'] ) ) )
				$this->options['style'] = '';
			
			$this->options['db_version'] = self::db_version;
			update_option('conditional_captcha_options', $this->options);
		}
	}
	
	function load() {
		if( function_exists( 'akismet_auto_check_comment' ) ) {
			$this->antispam = array( 'name' => 'Akismet', 'check_function' => 'akismet_auto_check_comment', 'caught_action' => 'akismet_spam_caught' );
			$this->ready = true;
		}
		
		if( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'settings_menu' ) );
			add_action( 'rightnow_end', array( $this, 'rightnow'), 11 ); 	// after akismet
			if( !$this->ready  ) 
				add_action( 'admin_notices', array( $this, 'plugin_inactive' ) );
		}
		else {
			add_filter( 'preprocess_comment', array( $this, 'check_captcha' ), 0 ); // BEFORE akismet
		}
		
		if( $this->options['akismet_no_history'] ) {
			add_filter( 'add_comment_metadata', array( $this, 'prevent_akismet_history' ), 10, 3 );
		}
	}
	
	function prevent_akismet_history( $check, $object_id, $meta_key ) {
		if( in_array( $meta_key, array( 'akismet_result', 'akismet_history', 'akismet_user', 'akismet_user_result' ) ) )
			return false;
		return $check;
	}
	
	function plugin_inactive() {
		if( get_current_screen()->id != 'plugins_page_conditional_captcha_settings' )
			printf('<div class="updated fade"><p><strong>'.__('Conditional CAPTCHA is currently inactive. Please visit the plugin <a href="%s">configuration page</a> for information on how to fix this.', 'wp-conditional-captcha').'</strong></div>', admin_url( 'plugins.php?page=conditional_captcha_settings' ) );
	}
	
	function settings_menu() {
		add_submenu_page('plugins.php', __('Conditional CAPTCHA Settings', 'wp-conditional-captcha'), __('Conditional CAPTCHA', 'wp-conditional-captcha'), 'manage_options', 'conditional_captcha_settings', array($this, 'settings_page') );
	}
	
	function settings_page() {
		// check if we are showing a captcha preview 
		if( isset( $_GET['captcha_preview'] ) && isset( $_GET['noheader'] ) && check_admin_referer('conditional_captcha_preview') )
			$this->do_captcha( false, false );	// will exit

		$opts = $this->options;
		$message = '';
		$invalid_keys = __('The reCAPTCHA API keys you entered do not appear to be valid. Please check that each key is exactly 40 characters long and contains no spaces.', 'wp-conditional-captcha');
		$missing_keys = __('You need to supply reCAPTCHA API keys if you want to use reCAPTCHA. Please enter private and public key values.', 'wp-conditional-captcha');
		
		if ( isset($_POST['submit']) ) {
			$errors = array();
			foreach( array( 'pass_action', 'fail_action', 'style', 'captcha-type', 'recaptcha-private-key', 'recaptcha-public-key', 'recaptcha_theme', 'recaptcha_lang', 'prompt_text' ) as $o )
				$opts[$o] = trim( $_POST[$o] );
			
			$opts['style'] = str_replace( "\r\n", "\n", strip_tags( stripslashes( $opts['style'] ) ) ); // css only please
			if( $opts['style'] == trim( str_replace( "\r\n", "\n", file_get_contents( $this->cssfile ) ) ) )
				$opts['style'] = '';
				
			$opts['prompt_text'] = str_replace( "\r\n", "\n", strip_tags( stripslashes( $opts['prompt_text'] ) ) ); // text only please
			if( $opts['prompt_text'] == $this->prompt_text( true ) )
				$opts['prompt_text'] = '';

			// check pass/fail action conflicts
			if( $opts['pass_action'] == $opts['fail_action'] ) {
				$errors['action_conflict'] = __( 'You cannot select the same action for both correctly and incorrectly completed CAPTCHAs. The action for incorrectly completed CAPTCHAs has been reset to "Trash the comment".' );
				$opts['fail_action'] = 'trash';
			}
			
			// check that keys have been supplied for recaptcha
			if( 'recaptcha' == $opts['captcha-type'] ) {
				if( !$opts['recaptcha-private-key'] || !$opts['recaptcha-public-key'] ) 
					$errors['recaptcha'] = $missing_keys;
				elseif( strlen( $opts['recaptcha-private-key'] ) != 40 || strlen( $opts['recaptcha-public-key'] ) != 40 )
					$errors['recaptcha'] = $invalid_keys;
			}
			
			if( isset( $errors['recaptcha'] ) )
				$opts['captcha-type'] = 'default';	// revert to default
			
			$opts['akismet_no_login'] = isset( $_POST['akismet_no_login'] );
			$opts['akismet_no_history'] = isset( $_POST['akismet_no_history'] );
			
			update_option('conditional_captcha_options', $opts);
			$this->options = $opts;
			$message = $errors ? '<div class="error fade"><p>' . implode( '</p><p>', $errors ) . '</p></div>' : '<div id="message" class="updated fade"><p>'.__( 'Options updated.', 'wp-conditional-captcha' ) . '</p></div>';
		}
		if( !$this->ready )
			$message = '<div class="error fade" style="font-weight:bold; line-height:140%"><p>'.__( 'This plugin requires Akismet to be active in order to work. Please install and activate Akismet before changing the settings below.', 'wp-conditional-captcha' ).'</p></div>';
	?>
	<style>
	.indent {padding-left: 2em}
	#settings .input-error {border-color: red; background-color: #FFEBE8}
	.akismet-not-ready, .disabled-option {color: #999 !important }
	table textarea {font-family: Consolas,Monaco,monospace; background: #FAFAFA}
	.form-table tr {border-top: 1px solid #EEE}
	.form-table tr:first-child {border-top: none}
	</style>
	<div class="wrap">
	<?php screen_icon() ;?>
	<h2><?php _e('Conditional CAPTCHA Settings', 'wp-conditional-captcha');?></h2>
	<?php echo $message; ?>
	<div id="settings" <?php if(!$this->ready) echo 'class="akismet-not-ready"';?>>
	<p><?php _e("This plugin provides a CAPTCHA complement to spam detection plugins. If your spam detection plugin identifies a comment as spam, the commenter will be presented with a CAPTCHA to prove that they are human. The behaviour of the plugin can be configured below.", 'wp-conditional-captcha');?></p>
	<form action="" method="post" id="conditional-captcha-settings">
	
	<h3><?php _e( 'Basic setup', 'wp-conditional-captcha' ) ?></h3>
	<table class="form-table"><tbody>
	<tr><th><?php _e('Anti-spam Plugin', 'wp-conditional-captcha');?></th><td>
	<p><?php printf( __('Conditional CAPTCHA has detected that <strong>%1$s</strong> is installed and active on your site. It will serve a CAPTCHA when %1$s identifies a comment as spam.', 'wp-conditional-captcha'), $this->antispam['name']);?></p>
	</td></tr>
	<tr><th><?php _e('CAPTCHA Method', 'wp-conditional-captcha');?></th><td>
	<p><?php printf( __('The default captcha is a simple text-based test, but if you prefer you can also use a <a href="%s" target="_blank">reCAPTCHA</a>. Note that you will need an API key to use reCAPTCHA.', 'wp-conditional-captcha'), 'http://www.google.com/recaptcha');?></p>
	<ul class="indent">
	<li><label for="type-default"><input type="radio" name="captcha-type" class="captcha-type" id="type-default" value="default" <?php checked( $opts['captcha-type'], 'default' );?> /> <?php _e('Use the default text-based CAPTCHA', 'wp-conditional-captcha');?></label></li>
	<li><label for="type-recaptcha"><input type="radio" name="captcha-type" class="captcha-type" id="type-recaptcha" value="recaptcha" <?php checked( $opts['captcha-type'], 'recaptcha' );?> /> <?php _e('Use reCAPTCHA', 'wp-conditional-captcha');?></label></li>
	</ul>
	<div id="recaptcha-settings" class="indent">
		<p><?php _e('If you wish to use reCAPTCHA, please enter your keys here:', 'wp-conditional-captcha');?></p>
		<ul class="indent">
		<li><label for="recaptcha-public-key"><?php _e('Public key:', 'wp-conditional-captcha');?> <input type="text" name="recaptcha-public-key" id="recaptcha-public-key" size="50" value="<?php echo $opts['recaptcha-public-key'] ?>" /></label></li>
		<li><label for="recaptcha-private-key"><?php _e('Private key:', 'wp-conditional-captcha');?> <input type="text" name="recaptcha-private-key" id="recaptcha-private-key" size="50" value="<?php echo $opts['recaptcha-private-key'] ?>" /></label></li>
		</ul>
		<p><small><?php printf(__('You can <a href="%s" target="_blank">sign up for a key here</a> (it\'s free)', 'wp-conditional-captcha'), 'http://www.google.com/recaptcha/whyrecaptcha');?></small></p>
		<p><?php _e('reCAPTCHA offers some customisations that affect how it is displayed. You can modify these below.', 'wp-conditional-captcha') ?></p>
		<ul class="indent">
		<li><label for="recaptcha_theme"><?php printf( __('reCAPTCHA theme (see <a href="%s" target="_blank">here</a> for examples):', 'wp-conditional-captcha'), 'http://code.google.com/apis/recaptcha/docs/customization.html') ?>
		<select name="recaptcha_theme" id="recaptcha_theme">
			<?php
			$rc_themes = array('red' => 'Red (default)', 'white' => 'White', 'blackglass' => 'Blackglass', 'clean' => 'Clean');
			foreach( $rc_themes as $k => $v ) {
				$selected = ( $k == $opts['recaptcha_theme'] ) ? 'selected="selected"' : '';
				echo "<option value='$k' $selected>$v</option>";
			}
			?>
		</select></label></li>
		<li><label for="recaptcha_lang"><?php _e('reCAPTCHA language:', 'wp-conditional-captcha') ?>
		<select name="recaptcha_lang" id="recaptcha_lang">
			<?php
			$rc_langs = array('en' => 'English (default)', 'nl' => 'Dutch', 'fr' => 'French', 'de' => 'German', 'pt' => 'Portuguese', 'ru' => 'Russian', 'es' => 'Spanish', 'tr' => 'Turkish');
			foreach( $rc_langs as $k => $v ) {
				$selected = ( $k == $opts['recaptcha_lang'] ) ? 'selected="selected"' : '';
				echo "<option value='$k' $selected>$v</option>";
			}
			?>
		</select></label></li>
		</ul>
	</div>
	</td></tr>
	<tr><th><?php _e('Comment Handling', 'wp-conditional-captcha');?></th><td>
	<p><?php _e('When a CAPTCHA is completed correctly:', 'wp-conditional-captcha');?></p>
	<ul class="indent plugin-actions">
	<li><input type="radio" name="pass_action" id="pass_action_spam" value="spam" <?php checked($opts['pass_action'], 'spam');?> /> <label for="pass_action_spam"><?php _e('Leave the comment in the spam queue', 'wp-conditional-captcha');?></label></li>
	<li><input type="radio" name="pass_action" id="pass_action_hold" value="hold" <?php checked( $opts['pass_action'], 'hold');?> /> <label for="pass_action_hold"><?php _e('Queue the comment for moderation', 'wp-conditional-captcha');?></label></li>
	<li><input type="radio" name="pass_action" id="pass_action_approve" value="approve" <?php checked( $opts['pass_action'], 'approve');?> /> <label for="pass_action_approve"><?php _e('Approve the comment', 'wp-conditional-captcha');?></label></li>
	</ul>
	<p><?php _e('When a CAPTCHA is <strong>not</strong> completed correctly:', 'wp-conditional-captcha');?></p>
	<ul class="indent plugin-actions">
	<li><input type="radio" name="fail_action" id="fail_action_spam" value="spam" <?php checked( $opts['fail_action'], 'spam' );?> /> <label for="fail_action_spam"><?php _e('Leave the comment in the spam queue', 'wp-conditional-captcha');?></label></li>
	<li><input type="radio" name="fail_action" id="fail_action_trash" value="trash" <?php checked( $opts['fail_action'], 'trash' );?> /> <label for="fail_action_trash"><?php _e('Trash the comment', 'wp-conditional-captcha');?></label></li>
	<li><input type="radio" name="fail_action" id="fail_action_delete" value="delete" <?php checked( $opts['fail_action'], 'delete' );?> /> <label for="fail_action_delete"><?php _e('Delete the comment permanently', 'wp-conditional-captcha');?></label></li>
	</ul>
	</td></tr>
	</table>
	
	<h3><?php _e( 'Tweaks', 'wp-conditional-captcha' ) ?></h3>
	<table class="form-table">
	<tr><th><?php _e('CAPTCHA Page Style', 'wp-conditional-captcha');?></th><td>
	<p><?php _e('If you want to style your CAPTCHA page to fit with your own theme, you can modify the default style.', 'wp-conditional-captcha');?></p>
	<textarea id="captcha_css" name="style" rows="6" cols="80"><?php echo ( $opts['style'] ? $opts['style'] : file_get_contents( $this->cssfile ) );?></textarea>
	<p><small><?php _e( 'Empty this box to revert to the default.', 'wp-conditional-captcha' );?></small></p>
	</td></tr>
	<tr><th><?php _e('CAPTCHA Prompt', 'wp-conditional-captcha');?></th><td>
	<p><?php _e('Users will be presented with the following prompt text when a CAPTCHA is displayed. You can modify it if you want.', 'wp-conditional-captcha');?></p>
	<textarea id="prompt_text" name="prompt_text" rows="2" cols="80"><?php echo esc_html( $this->prompt_text() );?></textarea>
	<p><small><?php echo __( 'Empty this box to revert to the default.', 'wp-conditional-captcha' ) . ' ' . __( 'HTML is not allowed.', 'wp-conditional-captcha' );?></small></p>
	</td></tr>
	<tr id="captcha_preview_row" class="hide-if-no-js"><th><?php _e('CAPTCHA Preview', 'wp-conditional-captcha');?></th><td>
	<div id="captcha_preview">
		<p><?php _e('Click the button below to view a preview of what the CAPTCHA page will look like. If you have made changes above, please submit them first.', 'wp-conditional-captcha');?></p>
		<p><a class="button-secondary" target="_blank" href="<?php echo wp_nonce_url( menu_page_url('conditional_captcha_settings', false), 'conditional_captcha_preview' ) . '&amp;captcha_preview=1&amp;noheader=true'; ?>"><?php _e('Show preview of CAPTCHA page (opens in new window)', 'wp-conditional-captcha');?></a></p>
	</div>
	</td></tr>
	<tr><th><?php _e('Akismet Behaviour', 'wp-conditional-captcha');?></th><td>
	<ul>
	<li><input type="checkbox" name="akismet_no_login" id="akismet_no_login" value="1" <?php checked($opts['akismet_no_login']);?> /> <label for="akismet_no_login"><?php _e('Preventing Akismet from checking comments for logged-in users', 'wp-conditional-captcha');?></label></li>
	<li><input type="checkbox" name="akismet_no_history" id="akismet_no_history" value="1" <?php checked($opts['akismet_no_history']);?> /> <label for="akismet_no_history"><?php printf( __('Prevent Akismet from storing comment histories (see <a href="%s" target="_blank">the FAQs</a> for more on this)', 'wp-conditional-captcha'), 'http://wordpress.org/extend/plugins/wp-conditional-captcha/faq/' ) ;?></label></li>
	</ul>
	</td></tr>
	</tbody></table>
	<p class="submit"><input class="button-primary" type="submit" name="submit" value="<?php _e('Update settings', 'wp-conditional-captcha');?>" /></p>
	</form>
	</div>
	</div>
	<script type="text/javascript">
	jQuery(document).ready(function($){
		if(!$('#type-recaptcha').is(':checked')) $('#recaptcha-settings').hide();
		$('input.captcha-type, #captcha_css, #recaptcha_theme, #recaptcha_lang, #prompt_text').change( function(){
			$('#captcha_preview').html('<p><?php echo esc_html( __('You have changed some settings above that affect how the CAPTCHA is displayed. Please submit the changes to be able to see a preview.', 'wp-conditional-captcha') );?></p>')}
		);
			
		function resolve_conflicts(){
			var p = $('#pass_action_spam'), f = $('#fail_action_spam');
			p.attr('disabled', f.is(':checked'));	// this should really use prop() but only works for jQuery > 1.6 (WP > 3.2)
			f.attr('disabled', p.is(':checked'));
			
			p.parent().toggleClass('disabled-option',  p.is(':disabled'));
			f.parent().toggleClass('disabled-option', f.is(':disabled'));
				
			$('.plugin-actions li').unbind('click');
			$('li.disabled-option').click( function(){
				alert('<?php echo esc_html( __('You cannot select the same action for both successful and unsuccessful CAPTCHA responses.', 'wp-conditional-captcha') );?>');
			});
		}
		
		$('.plugin-actions input').click(resolve_conflicts);
		resolve_conflicts();
		
		$('input[name="captcha-type"]').change(function() {
			if($('#type-recaptcha').is(':checked')) $('#recaptcha-settings').slideDown();
			else $('#recaptcha-settings').slideUp();
		});
		
		$('#conditional-captcha-settings').submit( function(){
			// check API keys
			if( $('#type-recaptcha').is(':checked') ) {
				var msg = '';
				if( $('#recaptcha-private-key').val() == '' || $('#recaptcha-public-key').val() == '' ) 
					msg = '<?php echo esc_html( $missing_keys );?>';
				else if( $('#recaptcha-private-key').val().length != 40 || $('#recaptcha-public-key').val().length != 40 )
					msg = '<?php echo esc_html( $invalid_keys );?>';
					
				if( msg ) {
					alert( msg );
					$('#recaptcha-settings input').addClass('input-error');
					return false;
				}
			}
		});
		
		$("#conditional-captcha-settings :input").change( function(){
			$("#message").slideUp();
		});
	});
	</script>
<?php
	}
	
	function rightnow() {
		if ($n = get_option('conditional_captcha_count') ) printf('<p class="conditional-captcha-stats">'._n('%s spam comment has been automatically discarded by <em>Conditional CAPTCHA</em>.', '%s spam comments have been automatically discarded by <em>Conditional CAPTCHA</em>.', $n, 'wp-conditional-captcha').'</p>', number_format_i18n($n) );
	}

	function check_captcha($comment) {
		if( isset($_POST['captcha_nonce']) ) {	// then a captcha has been completed...
			$result = $this->captcha_is_valid();
			if($result !== true) {
				// they failed the captcha!
				$this->page(__('Comment Rejected', 'wp-conditional-captcha'), '<p>'.$result.' '.__('Your comment will not be accepted. If you want to try again, please use the back button in your browser.', 'wp-conditional-captcha').'</p>');
			}
			else {	
				// the captcha was passed, so rewind the stats
				update_option('conditional_captcha_count', get_option('conditional_captcha_count') - 1 );
				
				// if trash/spam is enabled, check for the comment
				if( 'delete' != $this->options['fail_action'] ) {
					if( $stored = get_comment( $_POST['trashed_id'] ) ) {
						// change status. this will call wp_notify_postauthor if set to approve
						// note, newer versions of Akismet will not register a false positive just from the status transition, because it explicitly checks to make sure the change was not made by a plugin
						wp_set_comment_status( $stored->comment_ID, $this->options['pass_action'] );

						// if set to hold, then trigger moderation notice
						if( 'hold' == $this->options['pass_action'] )
							wp_notify_moderator( $stored->comment_ID );
						
						// redirect like wp-comments-post does
						$location = empty($_POST['redirect_to']) ? get_comment_link($stored->comment_ID) : $_POST['redirect_to'] . '#comment-' . $stored->comment_ID;
						$location = apply_filters( 'comment_post_redirect', $location, $stored );
						wp_redirect($location);
						exit;
					}
					else {
						// the comment doesn't exist!
						$this->page(__('Comment rejected', 'wp-conditional-captcha'), '<p>'.__('Trying something funny, are we?', 'wp-conditional-captcha').'</p>');
					}
				}
				else {
					// remove the spam plugin's hook - there is no point in checking again
					remove_action( 'preprocess_comment', $this->antispam['check_function'], 1 );
					// hook to set the comment status ourselves
					add_filter( 'pre_comment_approved', array($this, 'set_passed_comment_status') );
				}
			}
		}
		elseif( !is_user_logged_in() && empty( $comment['comment_type'] ) && empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && ( !defined( 'XMLRPC_REQUEST' ) || !XMLRPC_REQUEST ) ) {	
			// don't mess with pingbacks and trackbacks and logged in users and AJAX/XML-RPC requests
			add_action( $this->antispam['caught_action'], array( $this, 'spam_handler' ) );	// set up spam intercept 
		}
		
		if( $this->options['akismet_no_login'] && is_user_logged_in() )
			remove_action( 'preprocess_comment', $this->antispam['check_function'], 1 );
		
		return $comment;
	}
	
	function set_passed_comment_status() {
		// comment status needs to be either 0, 1 or 'spam' at this stage, so translate pass_action accordingly
		$status = $this->options['pass_action'];
		if( 'approve' == $status ) $status = '1';
		if( 'hold' == $status ) $status = '0';
		return $status;
	}

	function spam_handler() {
		if( 'delete' != $this->options['fail_action'] ) {
			remove_filter( 'pre_comment_approved', 'akismet_result_spam' );		// Akismet breaks all filters on this hook by dynamic use of remove_filter
			add_filter( 'pre_comment_approved', array( $this, 'set_comment_status' ), 50 );
			add_action( 'comment_post', array( $this, 'do_captcha' ) ); // do captcha after comment is stored
		}
		else $this->do_captcha(); // otherwise do captcha now
	}
	
	function set_comment_status(){
		return $this->options['fail_action'];
	}
	
	function do_captcha($comment_id = false, $real = true) {
		// comment_id will be supplied by the comment_post action if this function is called from there
		// if $real is false, we are just showing a preview
		
		$nosubmit = $real ? '' : 'onsubmit=\'alert("'.__('This CAPTCHA is a visual preview only; you cannot submit it.', 'wp-conditional-captcha').'"); return false;\'';
		$html = '<p>' . esc_html( $this->prompt_text() ) . '</p><form method="post" '.$nosubmit.'>';
		
		if($real){
			// original post contents as hidden values, except the submit
			foreach ( $_POST as $k => $v ) 
				if( 'submit' != $k ) 
					$html .= '<input type="hidden" name="'.htmlspecialchars( $k ).'" value="'.htmlspecialchars( stripslashes($v) ).'" />';
			if('delete' != $this->options['fail_action']) $html .= '<input type="hidden" name="trashed_id" value="'.$comment_id.'" />';
			// nonce
			$html .= '<input type="hidden" name="captcha_nonce" value="'.$this->get_nonce().'">';
		}
		
		// the captcha
		$disabled = $real ? '' : 'disabled="disabled"';
		$html .= $this->create_captcha();
		$html .= '<p><input class="submit" type="submit" id="submit" value="'.__("I'm human!", 'wp-conditional-captcha').'" '. $disabled .'/></p></form>';
		
		if( !$real )
			$html .= '<script type="text/javascript"> document.getElementById("submit").disabled = false; </script>';	// onsubmit event will stop submission
		
		// stats - this count will be reversed if they correctly complete the CAPTCHA
		if( $real )
			update_option('conditional_captcha_count', get_option('conditional_captcha_count') + 1); 
		$this->page(__('Verification required', 'wp-conditional-captcha'), $html);
	}

	private function page($title, $message) {
		// generates a page where the captcha can be completed - style can be modified
		if( !is_admin() )
			@status_header(403);
		@header('Content-Type: text/html; charset=' . get_option('blog_charset') );
		echo "<!doctype html><html><head><title>$title</title>\n";
		echo "<style>\n".( $this->options['style'] ? $this->options['style'] : file_get_contents( $this->cssfile ) ) ."\n</style>\n";
		echo "</head><body id='conditional_captcha'><div id='conditional_captcha_message'>$message</div></body></html>";
		exit;
	}

	private function create_captcha() {
		if( 'recaptcha' == $this->options['captcha-type'] ) {	
			$html = '<script> var RecaptchaOptions = {theme: "' . $this->options['recaptcha_theme'] . '", lang: "' . $this->options['recaptcha_lang'] . '"}; </script>';
			$html .= '<script src="http://www.google.com/recaptcha/api/challenge?k='.$this->options['recaptcha-public-key'].'"></script><noscript><iframe id="recaptcha-no-js" src="http://www.google.com/recaptcha/api/noscript?k='.$this->options['recaptcha-public-key'].'" height="300" width="700" frameborder="0"></iframe><br><textarea name="recaptcha_challenge_field" rows="3" cols="40"></textarea>
       <input type="hidden" name="recaptcha_response_field" value="manual_challenge"></noscript>';
			return $html;
		}
		
		// otherwise do default
		$chall = str_split( $this->hash( rand() ) );		// random string with 6 characters, split into array
		$num1 = rand(1,5);									// random number between 1 and 5
		$num2 = rand($num1 + 1,6);							// random number between $num1+1 and 6
		$hash = $this->hash( $chall[$num1-1] . $chall[$num2-1] );
		
		$ords = array('',__('first', 'wp-conditional-captcha'),__('second','wp-conditional-captcha'),__('third', 'wp-conditional-captcha'),__('fourth', 'wp-conditional-captcha'),__('fifth', 'wp-conditional-captcha'),__('sixth', 'wp-conditional-captcha'));
																
		return '<p class="intro"><label for="captcha_response">'.sprintf(__('What are the %1$s and %2$s characters of the following sequence?', 'wp-conditional-captcha'), '<strong>'.$ords[$num1].'</strong>', '<strong>'.$ords[$num2].'</strong>').'</label></p><p class="challenge"><strong>'.implode(' ', $chall).'</strong>&nbsp;&nbsp;<input name="captcha_response" type="text" size="5" maxlength="2" value="" tabindex="1" /></p><input type="hidden" name="captcha_hash" value="'.$hash.'" />';
	}
	
	private function captcha_is_valid() {
		// check that the nonce is valid
		if( !$this->check_nonce( $_POST['captcha_nonce'] ) ) 
			return __('Trying something funny, are we?', 'wp-conditional-captcha');
		
		$status = true;

		// if reCAPTCHA
		if( 'recaptcha' == $this->options['captcha-type'] ) {
			$resp = $this->recaptcha_check( $_POST['recaptcha_challenge_field'], $_POST['recaptcha_response_field'] );

			if(true !== $resp) $status = sprintf( __("Sorry, the CAPTCHA wasn't entered correctly. (reCAPTCHA said: %s)", 'wp-conditional-captcha'), $resp );
		}
		else {
			// do default validation
			if($_POST['captcha_hash'] != $this->hash( strtoupper($_POST['captcha_response']) ) ) $status = __("Sorry, the CAPTCHA wasn't entered correctly.", 'wp-conditional-captcha');
		}
		
		return $status;
	}
	
	private function check_nonce($nonce) {
		$i = ceil( time() / 600 );
		return ($this->hash($i, 'nonce') == $nonce || $this->hash($i-1, 'nonce') == $nonce);
	}
	
	private function get_nonce() {
		// nonce valid for 10-20 minutes
		$i = ceil( time() / 600);
		return $this->hash($i, 'nonce');
	}
	
	private function hash($val, $type = 'auth') {
		return strtoupper( substr( sha1( $val . wp_salt( $type ) ), 0 ,6 ) );
	}

	private function recaptcha_post($data) {
		$req = http_build_query($data);
		$host = 'www.google.com';
		
		$headers = array(
			'POST /recaptcha/api/verify HTTP/1.0',
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
		$parts = explode("\r\n\r\n", $response, 2);			// [0] = response header, [1] = body
		return explode("\n", $parts[1]);					// [0] 'true' or 'false', [1] = error message
	}

	private function recaptcha_check ($challenge, $response) {
		if (!$challenge || !$response) return 'incorrect-captcha-sol';		// discard spam submissions upfront

		$response = $this->recaptcha_post( array('privatekey' => $this->options['recaptcha-private-key'], 'remoteip' => $_SERVER['REMOTE_ADDR'], 'challenge' => $challenge, 'response' => $response) );

		// if the first part isn't true then return the second part
		return (trim($response[0]) == 'true') ? true : $response[1];
	}
	
	private function prompt_text( $force_default = false ) {
		return ( ! $force_default && $this->options['prompt_text'] ) ? $this->options['prompt_text'] : __( 'Sorry, but I think you might be a spambot. Please complete the CAPTCHA below to prove that you are human.', 'wp-conditional-captcha' );
	}

} // class

// load
new Conditional_Captcha();
