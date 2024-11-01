=== WP Comment Notifier For All ===

Contributors: Fayçal Tirich
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=GEUJA8MV256VE
Tags: notify, comment, notifier
Requires at least: 3.0
Tested up to: 4.5.2
Stable tag: 2.4

Notify all Wordpress users (and not only the admin) on every comment approval.

== Description ==

Notify all Wordpress users (and not only the admin) on every comment approval. The notification is only sent once after the first comment post approving action (and not on possible status update).

== Installation ==

1. Upload `wp-comment-notifier-for-all` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Set your notification template

== Frequently Asked Questions ==


== Changelog ==
= 2.4 =
* Fixing return to line in comment content

= 2.3 =
* Adding [CATEGORIES] in email body template

= 2.2.1 =
* Fixing a text/html content type problem with some email clients

= 2.2 =
* Adding [BLOG_NAME] as template to the notification body
* Removing useless [LOGO] template, one can include its logo directly using img HTML tag

= 2.1 =
* Fixing the compatibility with Wordpress 3.x
* Adding the possibility to include the comment content in the email notification body
* using wp_mail instead of PHP mail
* s/pnfa/cnfa
* Fixing cnfa_get_users() used to send notification to all users even for other blogs (MuWP)

= 1.0 =
* First release 


== Screenshots ==

1. Admin settings
2. Enable/Disable for users