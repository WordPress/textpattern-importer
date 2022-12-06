=== Plugin Name ===
Contributors: wordpressdotorg
Donate link:
Tags: importer, textpattern
Requires at least: 3.0
Tested up to: 6.1
Stable tag: 0.3.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Import categories, users, posts, comments, and links from a TextPattern blog.

== Description ==

Import categories, users, posts, comments, and links from a TextPattern blog.

== Installation ==

1. Upload the `textpattern-importer` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to the Tools -> Import screen, Click on TextPattern

== Frequently Asked Questions ==

== Screenshots ==

== Changelog ==

= 0.3.1 =
* Add support for TextPattern >= 4.0.2 and <= 4.8.8

= 0.3 =
* Fix: add support for PHP 7.2
* Fix: remove call to `set_magic_quotes_runtime`

= 0.2 =
* Add WP_LOAD_IMPORTERS check
* Add I18N for importers
* Add license header

= 0.1 =
* Initial release
