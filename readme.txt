=== Conditional CAPTCHA for Wordpress ===
Contributors: solarissmoke
Tags: akismet, captcha, spam
Requires at least: 2.7
Tested up to: 2.8.5
Stable tag: 1.2.1

Asks commenters to complete a simple CAPTCHA if Akismet thinks their comment is spam. If they fail, the comment is automatically deleted.

== Description ==

Akismet is great at detecting spam, but if you get lots of it then it means trawling through the spam queue in case there are any false positives. One alternative is to use a CAPTCHA - but this isn't very user friendly if every commenter has to complete one.

This plugin provides a CAPTCHA complement to Akismet. If Akismet identifies a comment as spam, it will ask the commenter to complete a simple CAPTCHA. If they fail, then the comment will be automatically discarded (and won't clutter up your spam queue). If they pass, it will be allowed into the spam queue. That way the spam queue will contain only the most likely false positives, making it much easier to find them. Meanwhile, genuine commenters (i.e., those not flagged by Akismet) will be able to comment on your blog hassle-free.

The default CAPTCHA is a simple text-based test. There is also the option to use [reCAPTCHA](http://recaptcha.net) if you want something more robust (note: this requires getting a free API key). You can style the CAPTCHA page to fit with your theme using CSS.

**Note: this plugin requires Akismet to be active in order to work.**

If you have any problems or suggestions, please [post a note here](http://rayofsolaris.co.uk/blog/plugins/conditional-captcha-for-wordpress/ "Plugin home page").

== Changelog ==

= 1.2.1 =
* Bugfix: PHP warning when accessing the settings page while Akismet is disabled.

= 1.2 =
* Added the ability for the user to modify the CSS used to display the CAPTCHA

= 1.1 =
* Added the option to use reCAPTCHA instead of the default text-based CAPTCHA. The settings can be accessed from the Plugins administration menu.

= 1.0 =
* First public release

== Screenshots ==

1. A sample of the CAPTCHA that will be displayed to potential spammers when using the default text-based CAPTCHA option.
2. A sample of the CAPTCHA that will be displayed to potential spammers when using reCAPTCHA.

== Installation ==

1. Upload `conditional-captcha.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The settings for the plugin can be accessed from the Plugins administration menu.