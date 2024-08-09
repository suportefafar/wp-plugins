=== FAFAR CF7CRUD ===
Contributors: arshidkv12
Donate link: #
Tags: cf7, contact form 7, contact form 7 db, contact form db, contact form seven, contact form storage, export contact form, save contact form, wpcf7
Requires at least: 4.8
Tested up to: X.X
Stable tag: X.X
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 7.0

Save form Contact Form 7's data. Update existing submission from Contact Form 7.


== Description ==

= CREATE
The "FAFAR CF7CRUD" plugin saves contact form 7 submissions to your WordPress database.

= READ
The "FAFAR CF7CRUD" plugin creates a simple shortcode to show a certain submission by it's 'id'.

= UPDATE
This plugin reads the CF7 form, searching for a hidden input with name='id'.
If exists, "FAFAR CF7CRUD" does know that is a update form.

= DELETE
It makes available a button by a shortcode to delete a submission by 'id'. 



= GLOBAL

1 - On create, I have to put a 'preview' on inputs to image upload.
2 - On update, none of the input files says, by default, that it empty,
    even thougth it has a set a value to the 'value' property.

Every input[type=file] become a custom input for better control, on create AND update forms.


= NOT SUPPORTED

- Drag and Drop Multiple File Upload - Contact Form 7

== Installation ==

1. Download and extract plugin files to a wp-content/plugin directory.
2. Activate the plugin through the WordPress admin interface.
3. Done!


== Changelog ==

= 1.0.0 =
Basic






















