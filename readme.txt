=== XHTML Content Negotiation for Wordpress ===
Contributors: solarissmoke
Tags: akismet, captcha, spam
Requires at least: 2.7
Tested up to: 2.8.5
Stable tag: trunk

A plugin that asks the commenter to do a CAPTCHA if Akismet thinks their comment is spam. If they fail, the comment is automatically deleted.

== Description ==

Akismet is great at detecting spam, but if you get lots of it then it means trawling through the spam queue in case there are any false positives. One alternative is to use a CAPTCHA - but this isn't very user friendly if every commenter has to complete one.

This plugin provides a CAPTCHA complement to Akismet. If Akismet identifies a comment as spam, it will ask the commenter to complete a simple CAPTCHA. If they fail, then the comment will be automatically discarded (and won't clutter up your spam queue). If they pass, it will be allowed into the spam queue. That way the spam queue will contain only the most likely false positives, making it much easier to find them.

Meanwhile, genuine commenters (i.e., those not flagged by Akismet) will be able to comment on your blog hassle-free.

This plugin requires Akismet to be active in order to work.

== Installation ==

1. Upload `conditional-captcha.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress