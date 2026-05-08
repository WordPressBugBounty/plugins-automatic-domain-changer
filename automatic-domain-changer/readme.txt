=== Automatic Domain Changer ===
Contributors: nuagelab
Donate link: http://www.nuagelab.com/products/wordpress-plugins/
Tags: admin, administration, links, domain change, migration
Requires at least: 5.0
Tested up to: 6.9.4
Requires PHP: 7.4
Stable tag: 3.0.1
License: GPLv2 or later

Automatically detects a domain name change, and updates all the WordPress tables in the database to reflect this change.

== Description ==

This plugin automatically detects a domain name change, and updates all the WordPress tables in the database to reflect this change.

= Features =

* Easily migrate a WordPress site from one domain to another
* Migrate www.domain.com and domain.com at once
* Migrate http and https links at once

= Feedback =
* We are open for your suggestions and feedback - Thank you for using or trying out one of our plugins!
* Drop us a line [@nuagelab](http://twitter.com/#!/nuagelab) on Twitter
* Follow us on [our Facebook page](https://www.facebook.com/pages/NuageLab/150091288388352)
* Drop us a line at [wordpress-plugins@nuagelab.com](mailto:wordpress-plugins@nuagelab.com)

== Installation ==

This section describes how to install the plugin and get it working.

= Requirements =

* The PHP CURL extension, usually installed on Un*x, Mac and Windows environments. See "Installing CURL on Linux" below for more help.
* Capability for your server to communicate with the outside work (or more specifically, to communicate with our servers)

= Installing the Plugin =

*(using the Wordpress Admin Console)*

1. From your dashboard, click on "Plugins" in the left sidebar
1. Add a new plugin
1. Search for "Automatic Domain Changer"
1. Install "Automatic Domain Changer"
1. Once Installed, if you want to manually change your domain, go to Tools > Domain Change
1. If your domain changes, a notice will appear at the top of the admin screen with a link to the domain changing tool

*(manually via FTP)*

1. Delete any existing 'auto-domain-change' folder from the '/wp-content/plugins/' directory
1. Upload the 'auto-domain-change' folder to the '/wp-content/plugins/' directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Once Installed, if you want to manually change your domain, go to Tools > Domain Change
1. If your domain changes, a notice will appear at the top of the admin screen with a link to the domain changing tool

= Making your blog/site address automatically reflect your server's name =

Add the following to your wp-config.php file:

<code>
define('WP_HOME', 'https://' . $_SERVER['SERVER_NAME']);
define('WP_SITEURL', 'https://' . $_SERVER['SERVER_NAME']);
</code>

See http://codex.wordpress.org/Editing_wp-config.php#WordPress_address_.28URL.29 for more information.

== Frequently Asked Questions ==

= What does this plugin do precisely? =

It scans all the tables with the same table prefix as WordPress. It fetches each row, unserialize values as needed, and replace the old domain by the new.

= Do you plan to localize this plugin in a near future? =

Yes, this plugin will be translated to french shortly. If you want to help with translation in other languages, we'll be happy to hear from you.

== Screenshots ==

1. The domain change and admin notice

== Changelog ==
= 3.0.1 =

Hotfix for a regression introduced in 3.0.0.

* Fix: rows whose serialized payload contains a PHP class instance (for example, the `_site_transient_update_plugins` transient or any plugin-stored object) were being corrupted into `__PHP_Incomplete_Class` after a domain change, which caused fatal errors in PHP 8 the next time something tried to mutate a property on those objects. The Replacer now leaves such rows byte-identical instead of round-tripping them through `unserialize()`. URLs inside class instances are no longer rewritten — but they are no longer destroyed either.
* Fix: no-op short-circuit when the old and new domains match (case-insensitively) and no protocol change was requested, so rerunning with the same values cannot itself damage the database.
* Test: add docker smoke-test cases that seed a `stdClass` option and an array containing a `stdClass`, and assert both survive byte-identical.

If you ran 3.0.0 with old == new and now see "incomplete object" fatals (for example from wp-migrate-db-pro's update-checker hook), clear the affected transients with `wp transient delete update_plugins --network` (and `update_themes`, `update_core`) — they regenerate on the next dashboard load.

= 3.0.0 =

Major modernization release. The user-facing behavior is unchanged: the same Tools → Change Domain page with the same options and the same backup buttons. Everything else has been rewritten.

**Security**

* Replaced the raw SQL `UPDATE` in the domain-change routine with prepared `$wpdb->update()` calls to eliminate a SQL injection risk on the primary-key value.
* `unserialize()` is now called with `allowed_classes => false` so a malicious serialized payload in a row cannot trigger PHP object injection while the plugin scans the database.
* Option writes (`auto_domain_change-https`, `auto_domain_change-www`) are now gated behind both nonce verification and an `update_core` capability check. Previously, any authenticated `POST` to the admin page could flip them.
* Dismissing the domain-change admin notice now requires a nonce and the `update_core` capability (previously a plain `?dismiss-domain-change=1` GET, vulnerable to CSRF).
* All `$_POST`, `$_GET`, and `$_SERVER` values are sanitized; `force-protocol` is validated against an allow-list; submitted domains are validated against a host-name pattern before being used.
* Explicit capability checks at the top of the admin page and both backup routines (defense in depth).
* Drops the PHP-4 `&$this` reference style and the manual `pluggable.php` require.

**Compatibility**

* `Requires PHP: 7.4`, `Requires at least: 5.0`. Older PHP silently ignored `unserialize()`'s `allowed_classes` option, defeating the object-injection guard, so older versions are now refused with a clear admin notice.
* Tested up to WordPress 6.9.4.

**Architecture**

* Restructured into a PSR-4 layout under `NuageLab\AutoDomainChanger\` with a tiny hand-rolled autoloader (no Composer required at runtime).
* Extracted the admin form to a template, the styles to `assets/css/admin.css`, and the click handler to vanilla `assets/js/admin.js` (no jQuery).
* The serialize/JSON walker (`Domain\Replacer`) is now a self-contained class that can be invoked independently of the admin UI.

= 2.0.2 =
* Tested up to WordPress 4.9.8
* Added a way to change the protocol to HTTP or HTTPS

= 2.0.1 =
* Tested up to WordPress 4.6.1
* Removed admin notice for users who don't have update_core permission

= 2.0.0 =
* Tested up to WordPress 4.4.2
* Added backup functionality
* Removed usage of mysql_* functions in favor of $wpdb

= 1.0.1 =
* Tested up to WordPress 4.2.2

= 1.0 =
* Tested up to WordPress 4.2.1

= 0.0.6 =
* Bug fix with the processValue function generating a warning (thanks to @sniemetz for letting us know about this issue)
* Slovak translation (thanks to Marek Letko)
* Tested up to WordPress 4.1.1

= 0.0.5 =
* Minor text change

= 0.0.4 =
* Added JSON detection to fix values not being handled for plugins like RevSlider (thanks to Alfred Dagenais for letting us know about this issue)
* Added double serialize detection for plugins like Global Content Blocks (thanks to @pixelkicks for letting us know about this issue)
* Tested plugin up to WordPress 4.0.0

= 0.0.3 =
* Tested plugin up to WordPress 3.8.0

= 0.0.2 =
* Added error suppression on unserialize calls, as failing unserialize are normal and part of the game. Thanks to Kailey Lampert for pointing this out.
* Added serialize(false) detection.

= 0.0.1 =
* First released version. Tested internally with about 10 sites.


== Upgrade Notice ==

= 3.0.1 =
Critical hotfix for 3.0.0. Stops the Replacer from corrupting serialized PHP class instances (for example the `_site_transient_update_plugins` transient), which could trigger fatal "incomplete object" errors in PHP 8. Update before running another domain change.

= 3.0.0 =
Recommended security update. Fixes a SQL-injection risk and a PHP object-injection risk in the domain-change routine, hardens the admin-page capability/nonce checks, and modernizes the codebase. Now requires PHP 7.4+ and WordPress 5.0+.

== Translations ==

* English
* French
* Spanish
* Slovak
