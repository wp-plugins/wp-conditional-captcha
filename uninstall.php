<?php 
if(!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') ) die();
delete_option('conditional_captcha_count');
delete_option('conditional_captcha_options');
?>