=== Multiply ===
Tags: multiblog, multi-blog
Contributors: random

Multiply is a plugin for WordPress 1.5.x which allows multiple blogs from within the one administration interface. Includes one-click creation of new blogs, with per-blog user permissions, themes etc. Read on for a list of caveats, plus installation, usage and technical details.

This documentation may not be up to date; please check here: 
  <http://rephrase.net/days/05/05/wordpress-multiplied>

== Installation ==

1. Upload `000-multiply.php` to your plugins folder, usually `wp-content/plugins/`.
2. Activate the plugin on the plugin screen.
3. Head over to the "Presses" submenu under "Manage" to create a new blog.
4. Use the dropdown in the top right-hand corner of the screen to change to the new blog.
5. Set up the blog options. It works best if each one has a separate 'Blog Address (URI)'. (Options->General)
6. If you use `mod_rewrite`, make sure to create a new `.htaccess` for each blog.
6. Create a new `index.php` file in the selected Blog Address directory. (See below.)
7. Enable pingback. (See below.)

X. Put `upgrade-multiply.php` in `wp-admin/`. Your alternate blogs will need to be upgraded separately when you upgrade your main blog to the next version of WordPress, and that's what this script is for. Until it actually comes out, though, I can't be 100% sure that the script is going to work properly -- caveat emptor.

== Frequently Asked Questions ==

= What's the catch? =

* You need to edit a few core files if you want to get pingbacks at the new blogs. (See below.)
* Every plugin activated on the main blog is also activated on new blogs, unless (once again) you make a small change to a core file. (See below.)
* Edit/delete links on posts and comments won't work all of the time. It's possible to fix this by editing a few more core files. 
* There may be difficulties with some plugins, but most should work. Some plugins which use their own database tables will need to be modified to install correctly.
* If you ever do a WordPress upgrade and run `upgrade.php`, you'll need to run `upgrade-multiply.php` as well.
* Trackbacks don't work without pretty URLs (i.e., if your permalinks look like `http://example.com?p=34` you're out of luck) unless you make a few additional changes to core files. The easiest fix in most cases is to switch to pretty URLs.

= What do I put in the new `index.php`? =

The file should contain something like this:

	<?php 
	$mb_press_id = 1;
	define('WP_USE_THEMES', true);
	require('../wp-blog-header.php');
	?>

... where `$mb_press_id` is whatever the press ID is, and you're requiring the relative path to `wp-blog-header.php`.

For example, if WordPress is installed at `http://example.com/wp/` and you want a new press (ID, say, #4) at `http://example.com/cats-are-super/`, your `http://example.com/cats-are-super/index.php` file should contain this:

	<?php 
	$mb_press_id = 4;
	define('WP_USE_THEMES', true);
	require('../wp/wp-blog-header.php');
	?>

If you're using `.htaccess`/mod_rewrite, you should generate a set of rules for each press, and (if the file isn't saved automatically) it should go in the same directory as the `index.php` you just made.

If you don't know the press ID, look under Manage->Presses.

= How do I enable pingback? =

First of all, you have to make two small changes to `wp-blog-header.php`. Search for "X-Pingback", and replace "`get_bloginfo('pingback_url')`" with "`mb_get_pingback_url()`". Please note that if you remove Multiply you will need to change this back. 

If you're using `.htaccess`/mod_rewrite, you're done. Multiply adds special rewrite rules to handle pingbacks. If you're not, or you're not sure, there's one more step.

For each press you add, you need to add a file called `xmlrpc-{$press_id}.php` to the WordPress install directory, where "`{$press_id}`" is the ID of the press. Multiply will try and create this file automatically, but on many hosts you will need to do it yourself. The contents are similar to those of the `index.php` you already made. For example, `xmlrpc-1.php` would contain this:

	<?php 
	$mb_press_id = 1;
	require('./xmlrpc.php');
	?>

This should be placed in the WordPress install directory -- the one containing `wp-blog-header.php`, `wp-login.php` and the rest.

= Can I post using W.Bloggar, Ecto or another XML-RPC client? =

If you've used the pingback fix above, you can post to your blog using a rich client like W.Bloggar or Ecto by pointing it at (e.g.) `xmlrpc-3.php` instead of the normal `xmlrpc.php`.

= How do I have separate plugins? =

You can already have plugins on the alternate blogs that aren't on the main one, just not *less*. To keep them entirely separate, you need to make a very small change to `wp-settings.php`.

Search for the line that says:

	if ('' != $plugin && file_exists(ABSPATH . 'wp-content/plugins/' . $plugin))

and change it to this:

	if (!isset($mb_id) && '' != $plugin && file_exists(ABSPATH . 'wp-content/plugins/' . $plugin))
	
And you're done.

== Screenshots ==

1. This is a shot of the blog-switching dropdown in the top right hand corner of the administrative interface.
