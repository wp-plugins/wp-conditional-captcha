=== Conditional CAPTCHA for Wordpress ===
Contributors: solarissmoke
Tags: captcha, spam, reCAPTCHA, Akismet, TypePad AntiSpam
Requires at least: 2.7
Tested up to: 3.0
Stable tag: trunk

Asks commenters to complete a simple CAPTCHA if a spam detection plugin thinks their comment is spam. Currently supports Akismet and TypePad AntiSpam.

== Description ==

Services like Akismet and TypePad AntiSpam are great at detecting spam, but if you get lots of it then you have to trawl through the spam queue in case there are any false positives.

This plugin provides a CAPTCHA complement to these spam detection plugins:

* If a spam detection plugin identifies a comment as spam, it will ask the commenter to complete a simple CAPTCHA.
* If they fail, then the comment will be automatically discarded or trashed (and won't clutter up your spam queue). 
* If they pass, it will be allowed into the spam queue (or approved, if you so choose).
* Meanwhile, genuine commenters (i.e., those not flagged by Akismet) will be able to comment on your site hassle-free.

The default CAPTCHA is a simple text-based test. There is also the option to use [reCAPTCHA](http://recaptcha.net) if you want something more robust (note: this requires getting a free API key). You can also style the CAPTCHA page to fit with your own Wordpress theme.

**Requirements:**

* *Conditional CAPTCHA* currently supports [Akismet](http://akismet.com) and [TypePad AntiSpam](http://antispam.typepad.com). You must have one of these plugins installed and active in order for this plugin to work.
* PHP 5 is required.

**Available translations:**

* Belorussian (thanks to [Marcis G](http://pc.de))
* Danish (thanks to Jesper)
* Finnish (thanks to Jani)
* French (thanks to Grieta)
* Polish (thanks to [Pawel](http://www.spin.siedlce.pl/))
* Russian (thanks to [Serge](http://verevkin.info))

If you have any problems, please [post a ticket here](http://wordpress.org/tags/wp-conditional-captcha).

== Frequently Asked Questions ==

= I've installed it, now how do I check that it works? =

You can try posting a spammy comment on your blog (make sure you're logged out) to check that it works, and to see what it looks like. Posting a comment with something like `[url]somethingsilly[/url]` in the body will usually get it flagged by Akismet.

= Does this plugin work with PHP 4? =

No. Please use it only with PHP 5.0 and above. Please don't email me about PHP errors when you try to install the plugin on a PHP 4 platform.

= Does this plugin work with other comment form modification plugins? =

*Conditional CAPTCHA* relies on Wordpress' native form handling procedures. This means it will not work with plugins that generate and process their own comment forms. Such plugins include WP AJAX Edit Comments, tdo-miniforms and Contact Form 7.

== Upgrade Notice ==

= 2.3 =
Bugfix: If you were using version 2.1 or 2.2 of the plugin, please upgrade immediately. The use of transients in those versions could result in the Wordpress options table being filled with rows that are never deleted. Details are in the changelog.

== Changelog ==

= 2.3 =
* Bugfix: use of transients was flawed and would result in accumulation of redundant rows in the options table. If you used version 2.1 or 2.2 and have such rows in your options table (containing `_transient_conditional_captcha_` in the name), please remove them manually. If you need help removing them, please [contact me](http://rayofsolaris.net/contact/). Apologies!

= 2.2 =
* Added support for TypePad AntiSpam. Thanks to eetu for the suggestion.

= 2.1 =
* Removed external reCAPTCHA library that causes conflicts when another plugin that also uses reCAPTCHA is installed.
* Prevent fatal error when Wordpress is unable to contact reCAPTCHA servers to validate a CAPTCHA.
* Prevent successful completion of the same CAPTCHA twice.
* Enforce strict 10 minute window for CAPTCHAs to be completed.
* Properly set default options for new installation to avoid PHP warnings.

= 2.0 =
* Changed CAPTCHA hash methods to use Wordpress' native salt function (more secure).
* Inserted missing noscript failure message for reCAPTCHA.
* Minor performance optimisations.

= 1.9 =
* Added support for internationalisation (thanks to Jani for the suggestion and for a Finnish translation). Translations welcome!

= 1.8 =
* Added the option to trash comments rather than delete them completely if the CAPTCHA is not passed. This feature requires Wordpress 2.9 or greater.

= 1.7 =
* Added noscript handling for reCAPTCHA - the plugin will now revert to the default CAPTCHA if the client has Javascript disabled. This behaviour can be controlled in the options page.
* Minor performance optimisation.

= 1.6 =
* Minor performance improvements and code optimisation. Saved a few milliseconds of runtime :).

= 1.5 =
* Bugfix: on some Wordpress installations, Akismet was not detected even if it was active.

= 1.4 =
* Bugfix: some correctly solved CAPTCHAs could be rejected based on the time taken to solve them.

= 1.3 =
* Added the option to approve comments after a CAPTCHA has been passed (rather than leave them in the spam queue).
* Improved nonce security
* Bugfix: CSS handler was adding slashes to CSS

= 1.2.1 =
* Bugfix: PHP warning when accessing the settings page while Akismet is disabled.

= 1.2 =
* Added the ability for the user to modify the CSS used to display the CAPTCHA

= 1.1 =
* Added the option to use reCAPTCHA instead of the default text-based CAPTCHA. The settings can be accessed from the Plugins administration menu.

== Screenshots ==

1. A sample of the CAPTCHA that will be displayed to potential spammers when using the default text-based CAPTCHA option.
2. A sample of the CAPTCHA that will be displayed to potential spammers when using reCAPTCHA.

== Installation ==

1. Upload the wp-conditional-captcha folder to the `/wp-content/plugins/` directory (or use the Wordpress auto-install feature)
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The settings for the plugin can be accessed from the Plugins administration menu.