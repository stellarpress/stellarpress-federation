=== StellarPress Federation ===
Contributors: helmuthb
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: Stellar Federation Server, Lumens, Cryptocurrency
Requires at least: 4.0
Tested up to: 4.9.4
Stable tag: 1.1
Requires PHP: 5.2.4

Create your own readable address on the Stellar network by hosting your own Federation Server within WordPress. Attention: this requires a blog hosted via HTTPS to work!

== Description ==

### StellarPress Federation: the easy way to host a Stellar Federation server

Addresses in the Stellar network are usually long strings of numbers & characters, not easy to remember.
With the StellarPress Federation others can use a simple address like "user*yourdomain.com" to send money to you. It implements the standardized Stellar Federation Server protocol so it is supported by all wallets for the Stellar network.
Normally, to host a Stellar Federation server, you will need a server with direct root access. But with this plugin you can run your own Federation server within WordPress, all using minimal overhead and resources!

#### Settings

The StellarPress Federation plugin adds fields to your backend users.
Every backend user with a provided Stellar address will automatically be provided through StellarPress Federation.

#### Requirements

Your WordPress site must satisfy the following conditions for this plugin to work:
1. It must be hosted using HTTPS, with a valid and trusted certificate
2. In addition, it must be hosted in the root of your domain, not in a subfolder.

### Bug reports

Bug reports for StellarPress Federation are [welcomed on GitHub](https://github.com/StellarPress/stellarpress-federation). Please note that GitHub is not a support forum, and issues that are not properly qualified as bugs will be closed.

### Further Reading

For more info, check out the following articles:

* The official [Stellar homepage](https://www.stellar.org/)
* The [Stellar Federation](https://www.stellar.org/developers/guides/concepts/federation.html) concepts explained
* [Lumens FAQs](https://www.stellar.org/lumens/)

== Installation ==

=== From within WordPress ===

1. Visit 'Plugins > Add New'
1. Search for 'StellarPress Federation'
1. Activate StellarPress Federation from your Plugins page.
1. Go to "after activation" below.

=== Manually ===

1. Upload the `stellarpress-federation` folder to the `/wp-content/plugins/` directory
1. Activate the StellarPress Federation plugin through the 'Plugins' menu in WordPress
1. Go to "after activation" below.

=== After activation ===

1. Add the Stellar address in the backend user management
2. You're done!

== Frequently Asked Questions ==

You'll find answers to many of your questions on [stellarpress.org](https://stellarpress.org).

== Screenshots ==

TBD

== Changelog ==

= 1.0 =
Release Date: 28 January 2018

First version of the plugin
