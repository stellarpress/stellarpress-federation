=== StellarPress Federation ===
Contributors: helmuthb, cajga, garth
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl.html
Tags: Stellar Federation Server, Lumens, Cryptocurrency
Requires at least: 4.8
Tested up to: 4.9.2
Stable tag: 0.1.0
Requires PHP: 5.2.4

Create your own readable address on the Stellar network by hosting your own Federation Server within WordPress.

== Description ==

### StellarPress Federation: the easy way to host a Stellar Federation server

Addresses in the Stellar network are usually long chains of numbers & characters, not really easy to remember.
With the StellarPress Federation others can use a simple name like "user*yourdomain.com" to send money to you. It implements the standardized Stellar Federation Server protocol so it is supported by almost all wallets for the Stellar network.
Usually you need a server with SSH access to host a Stellar Federation server. But with StellarPress Federation you can run your own Federation server within WordPress, all using minimal overhead and resources!

#### Settings

The StellarPress Federation plugin adds fields to your backend users.
Every backend user with a provided Stellar address will automatically be provided through StellarPress Federation.

### Bug reports

Bug reports for StellarPress Federation are [welcomed on GitHub](https://github.com/StellarPress/stellarpress-federation). Please note GitHub is not a support forum, and issues that aren’t properly qualified as bugs will be closed.

### Further Reading

For more info, check out the following articles:

* The official [Stellar homepage](https://www.stellar.org/)
* The [Stellar Federation](https://www.stellar.org/developers/guides/concepts/federation.html) concepts explained
* [Lumens](https://www.stellar.org/lumens/) FAQs

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

= 0.1.0 =
Release Date: 28 January 2018

First version of the plugin
