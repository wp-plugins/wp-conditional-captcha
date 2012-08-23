=== Conditional CAPTCHA for WordPress ===
Contributors: solarissmoke
Tags: anti-spam, captcha, comments, spam, bot, robot, human, reCAPTCHA, Akismet, TypePad AntiSpam
Requires at least: 3.1
Tested up to: 3.4
Stable tag: trunk

Asks commenters to complete a simple CAPTCHA if Akismet thinks their comment is spam. Eliminates false positives.

== Description ==

Akismet is great at detecting spam, but if you get lots of it then you have to trawl through the spam queue in case there are any false positives.

This plugin provides a CAPTCHA complement to Akismet:

* If Akismet identifies a comment as spam, it will ask the commenter to complete a simple CAPTCHA.
* If they fail, then the comment will be automatically discarded or trashed (and won't clutter up your spam queue). 
* If they pass, it will be allowed into the spam queue (or approved, if you so choose).
* Meanwhile, genuine commenters (i.e., those not flagged by Akismet) will be able to comment on your site hassle-free.

The default CAPTCHA is a simple text-based test. There is also the option to use [reCAPTCHA](http://recaptcha.net) if you want something more robust (note: this requires getting a free API key). You can also style the CAPTCHA page to fit with your own Wordpress theme.

**Requirements:**

* You must have [Akismet](http://akismet.com) installed and active in order for this plugin to work.

If you come across any bugs or have suggestions, please use the plugin support forum or contact me at [rayofsolaris.net](http://rayofsolaris.net). I can't fix it if I don't know it's broken! Please check the [FAQs](http://wordpress.org/extend/plugins/wp-conditional-captcha/faq/) for common issues.

**Translations**

Thanks to the following people for contributing translations of this plugin:

Belorussian - [Marcis G](http://pc.de), Czech - [Ted](http://trumplin.com/), Danish - Jesper, Dutch - [Rene](http://wpwebshop.com/books/), Estonian - [Itransition](http://www.itransition.com), Finnish - Jani, French - [Laurent](http://android-software.fr), German - [Jochen](http://jochenpreusche.com), Hindi - [Outshine Solutions](http://outshinesolutions.com), Hungarian - [Gyula](http://www.televizio.sk), Italian - [Gianni](http://gidibao.net), Lithuanian - Mantas, Polish - [Pawel](http://www.spin.siedlce.pl), Romanian - [Web Hosting Geeks](http://webhostinggeeks.com), Russian - [Serge](http://verevkin.info), Spanish - [Reinventia](http://www.reinventia.net), Ukranian - [Stas](http://velokosiv.if.ua).

== Frequently Asked Questions ==

= I've installed it, now how do I check that it works? =

You can try posting a spammy comment on your blog (make sure you're logged out) to check that it works, and to see what it looks like. Posting a comment with `viagra-test-123` in the author/name field will always get it flagged by Akismet.

= Does this plugin work with other comment form modification plugins, or with themes that use Javascript to handle comment submission? =

*Conditional CAPTCHA* relies on WordPress' native form handling procedures. This means it will not work with plugins or themes that generate and process their own comment forms. Such plugins include WP AJAX Edit Comments, tdo-miniforms, Backtype and Contact Form 7. **If comment submissions on your site are processed using AJAX, then the plugin will not work.**

= I'm curious about how the plugin works. At what point is an unanswered CAPTCHA considered a failure, and what happens to the corresponding comment in the mean time? =

Basically the plugin will assume a flagged comment is spam unless a correctly solved CAPTCHA determines otherwise. The process depends partly on what your settings are.

*If the plugin is set to discard failed comments:* When a comment is flagged as spam, it is sent (as hidden data) back to the client along with the CAPTCHA (and not stored in the database). When the user submits the CAPTCHA, they resubmit the comment along with it.

*If the plugin is set to store failed comments in the trash or spam queue:* When the comment is flagged, it is added to the database as trash/spam. If the CAPTCHA is passed, then its status will be modified accordingly. You can configure what happens to passed comments.

There is a time limit of 10 minutes for the CAPTCHA to be submitted, otherwise it will be ignored even if it is correct.

= Can I see a demo of what the CAPTCHA looks like? =

[Yes](http://rayofsolaris.net/code/captcha/).

= Didn't you say before that the plugin works with TypePad Antispam? =

Yes, and it still does. But the TypePad Antispam plugin hasn't been updated in over 4 years, and is not fully compatible with the latest version of WordPress, causing users to see error notices on the CAPTCHA page. I will probably drop support for it altogether in the near future.

== Upgrade Notice ==

= 3.2.3 =
Performance improvement to reduce the amount of data stored in the database.

= 3.2.2 =
Minor modifications in response to changes in the latest version of Akismet.

= 3.2.1 =
Fixes a bug on the settings page that prevented some users from using the reCAPTCHA option.

= 3.2 =
Adds more flexibility to CAPTCHA pass/fail handling, and fixes a bug in the plugin's upgrade routine.

== Changelog ==

= 3.2.6 =
* Don't intercept comments submitted via AJAX.

= 3.2.5 =
* Add workaround for a bug in the latest version of Akismet, where comments from administrators can be flagged as spam.

= 3.2.4 =
* Added the option to customise the CAPTCHA prompt text.
* Minor tweaks to the settings page.

= 3.2.3 =
* Performance improvement to reduce size of plugin options
* Tweaked settings page to be more user friendly

= 3.2.2 =
* Minor changes to the behaviour of the plugin, as a result of changes in the latest version of Akismet.

= 3.2.1 =
* Bugfix: settings page Javascript caused errors when using jQuery < 1.6

= 3.2 =
* Added the option to leave comments for unsuccessful CAPTCHAs in the spam queue (provided the pass action something different)
* Bugfix: Options from previous versions of the plugin were not being properly upgraded

= 3.1.1 =
* Bugfix: Admin page Javascript was not compatible with jQuery >1.5.2

= 3.1 =
* Bugfix: Use blog character set instead of defaulting to UTF-8
* Better preview of CAPTCHA page
* Added basic validation of reCAPTCHA API keys
* Minor usability improvements

= 3.0 =
* Bugfix: don't mangle Unicode characters when submitting a CAPTCHA. Thanks to Mantas for pointing this out.

= 2.9 =
* Updated to fix issue with Akismet version 2.5.0 and Wordpress 3.0.3 when set to trash failed comments

= 2.8 =
* Added the ability to customise the appearance and language of reCAPTCHA

= 2.7 =
* Ensure that passed CAPTCHAs are reported as false positives to Akismet/TypePad Antispam. Thanks to [Kevin](http://www.investitwisely.com) for the suggestion.
* Added the option to place passed comments in the moderation queue

= 2.6 =
* Added support for non-js reCAPTCHA
* Updated reCAPTCHA API interface
* Modified upgrade routine because of changes to plugin update handling in Wordpress 3.1

= 2.5 =
* Added the ability to preview the CAPTCHA page from within the administration interface
* Minor performance optimisations
* Raised minimum Wordpress version to 2.8

= 2.4 =
* Bugfix: don't intercept spammy pingbacks and trackbacks. Thanks to [Kevin](http://www.investitwisely.com) for reporting this.

== Installation ==

1. Upload the wp-conditional-captcha folder to the `/wp-content/plugins/` directory (or use the Wordpress auto-install feature)
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The settings for the plugin can be accessed from the Plugins administration menu.
