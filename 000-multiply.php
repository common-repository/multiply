<?php
/*
Plugin Name: Multiply
Version: 1.2.3
Plugin URI: http://www.rephrase.net/box/word/multiply/
Description: Allows multiple blogs from within the one administration interface. Includes one-click creation of new blogs, with per-blog user permissions, plugins, themes etc.
Author: Sam Angove
Author URI: http://rephrase.net/


And God spake unto Noah, saying . . . be fruitful, and multiply.
*/


// Remove tables from $mb_tables for greater integration between blogs,
// i.e. categories to share categories, links and linkcategories to centralize 
// the link database.
$mb_tables = array('categories', 'links', 'linkcategories', 'posts', 'post2cat', 'comments', 'postmeta', 'options');

$wpdb->multiply = $table_prefix . "multiply";
$wpdb->muser = $table_prefix . "muser";

$mb_real_prefix = $table_prefix;

load_plugin_textdomain('Multiply');

// Run as soon as the plugin is loaded.

if (strstr($_SERVER['PHP_SELF'], 'wp-admin/')) {
	mb_cookiemonster();
} else {
	mb_multiply();
}

if (isset($mb_id)) {
	$current_plugins = get_settings('active_plugins');
	if (is_array($current_plugins))	{
		foreach ($current_plugins as $plugin) {
			if ('' != $plugin && file_exists(ABSPATH . 'wp-content/plugins/' . $plugin))
				include_once(ABSPATH . 'wp-content/plugins/' . $plugin);
		}
	}

}

// in wp-admin we use cookies to get the right press
function mb_cookiemonster() {
		
	// If a new blog is selected, set the cookie.
	if (isset($_GET['set_press_id'])) {
		$press_id = (int) $_GET['set_press_id'];
		mb_set_cookie($press_id);
	} else {
		$press_id = mb_get_cookie();
	}
		
	// Super dodgy hack to allow on-post edit/delete links to work.
	// If you're referred to post.php & haven't come from a wp-admin
	// page, look for the cookie that was set on the blog page view.
	// See mb_multiply().
	// It's pretty fragile, but I figured it was better than nothing.
	// Or you could just edit the core, of course.
	$referrer = $_SERVER['HTTP_REFERER'];
	if (strlen($referrer) > 5 && !strstr($referrer, 'wp-admin')) {
		if (strstr($_SERVER['PHP_SELF'], 'post.php'))
			$press_id = mb_get_cookie('mbrid');
	}
			
	mb_set_press($press_id);
	
}	



// set press on page view
function mb_multiply() {
	global $mb_press_id;
	
	$press_id = (int) $_REQUEST['press_id'];
	
	// for those hapless fools using PATH_INFO
	if (!$press_id && $mb_press_id) $press_id = $mb_press_id;
	
	mb_set_press($press_id);
	
	// Used to make on-post, GET-submitted edit and delete links work *sometimes*.
	// That's the best I can do... See mb_cookiemonster()
	mb_set_cookie($press_id, 'mbrid');
}

// Add a hidden value to comment forms; without this, all comments
// show up in the main blog. 
function mb_comment_form($post_id) {
	global $mb_id;
	echo '<input type="hidden" name="press_id" value="'. $mb_id .'" />';
	return $post_id;	
}


function mb_set_cookie($press_id, $type = 'mbid', $expiration = '3000000') {
	if ($press_id) {
		setcookie("wordpress_{$type}_" . COOKIEHASH, $press_id, time() + $expiration, '/');
	} else {
		setcookie("wordpress_{$type}_" . COOKIEHASH, '0', time() + $expiration, '/');
	}
}


function mb_set_press($press_id) {
	global $wpdb, $table_prefix, $mb_id, $mb_tables, $mb_real_prefix, $cache_settings, $wp_rewrite;
	global $tableposts, $tableusers, $tablecategories, $tablepost2cat, $tablecomments, $tablelinks, $tablelinkcategories, $tableoptions, $tablepostmeta;
	
	if (!$press_id) return; 
	
	$press = $wpdb->get_var("SELECT press_id FROM $wpdb->multiply WHERE press_id = '$press_id'");
	
	if ($press) { 
		
		$table_prefix = $mb_real_prefix . $press_id . '_';
		
		foreach ($mb_tables as $table) {
			$wpdb->$table = $table_prefix . $table;
			
			// reset "$tableposts" etc. for legacy purposes
			${'table' . $table} = $table_prefix . $table;
		}
		
		// The $mb_id global is used in almost every function.
		$mb_id = $press_id;
				
		// Reset the settings cache, since it's already been populated
		// with the default blog's options. Re-initialize $wp_rewrite
		// for the same reason.
		$cache_settings = array();
		$wp_rewrite->init();
	}
}

function mb_get_cookie($type = 'mbid') {
	$press_id = (int) $_COOKIE["wordpress_{$type}_".COOKIEHASH];
		
	return $press_id;
}

// Builds the array which is used to generate the list of 
// available presses. 
function mb_authorized_presses() {
	global $user_ID, $user_level, $wpdb, $press_cache;
	
	$presses = array();
	
	// Presses with a default level below the user's.
	$wpdb->hide_errors();
	$results = $wpdb->get_results("
		SELECT 
			press_id, 
			press_name,
			min_level 
		FROM 
			$wpdb->multiply 
		WHERE 
			min_level <= '$user_level' 
		ORDER BY press_id
		");
	$wpdb->show_errors();
	
	// Install on plugin first-load?
	if (strpos(mysql_error(), $wpdb->multiply."' doesn't exist")) mb_install();
	
	if ($results) {
		foreach($results as $press) {
			$presses[$press->press_id]->name = $press->press_name;
			$presses[$press->press_id]->level = $user_level;
		}	
	}
	
	// Presses for which the user is specifically authorized.
	$results = $wpdb->get_results("
		SELECT 
			mu.press_id,
			mu.level,
			mb.press_id AS 'press_id', 
			mb.press_name AS 'press_name' 
		FROM 
			$wpdb->multiply mb, 
			$wpdb->muser mu 
		WHERE 
			mu.user_id = '$user_ID' AND 
			mu.press_id = mb.press_id 
		ORDER BY mb.press_id
		");

	if ($results) {
		foreach($results as $press) {
			// user level is never below their default, but it can be higher
			$level = ($press->level > $user_level) ? $press->level : $user_level;
				 
			$presses[$press->press_id]->level = $level;
			$presses[$press->press_id]->name = $press->press_name;
		}	
	}
	$press_cache = $presses;
	
	return $presses;
}

// Sets the user level to what it should be for the selected press.

function mb_user_auth() {
	global $userdata, $user_level, $mb_id;
	
	$presses = mb_authorized_presses();
	
	if (!$mb_id) return;
	
	if ($presses[$mb_id]) {
		$user_level = $presses[$mb_id]->level;
	} else {
		$user_level = 0;
	}
	mb_airport_security();
	$userdata->user_level = $user_level;
}


// As useful and robust as its namesake!
// If it seems overly complicated for what it does, it's because it used to hide more files
// than this. It hides files (i.e. shared files like users.php) and/or drops a magic user level
// back down to normal. Can't figure out how to make it work with plugin files, so it's not 
// too useful.
//
function mb_airport_security() {
	global $menu, $submenu, $userdata, $user_level, $mb_default_menu, $mb_default_submenu;
		
	$mb_default_menu = (array) $mb_default_menu;
	$mb_default_submenu = (array) $mb_default_submenu;
	
	$mb_default_submenu['profile.php'] = array('users.php');
	
	$files = array();
	foreach ($menu as $key => $value) {
		$file = $value[2];
		if (in_array($file, $mb_default_menu)) {
			$files[$file] = false;
			if ($value[1] > $userdata->user_level) {
				unset($menu[$key]);
				$files[$file] = true;
			}
		}
	}

	foreach ($mb_default_submenu as $sub_of => $sub) {
		foreach ($submenu[$sub_of] as $key => $value) {
			$file = $value[2];
			if (in_array($file, $mb_default_submenu[$sub_of])) {
				$files[$file] = false;
				if ($value[1] > $userdata->user_level) {
					unset($submenu[$sub_of][$key]);
					$files[$file] = true;
				}
			}
		}	
	}
	
	foreach ($files as $file => $redirect) {
		if (strpos($_SERVER['PHP_SELF'], "/$file")) {
			$user_level = $userdata->user_level;
			if ($redirect) header('Location: index.php');
		}
	}
}

// Press selection widget in the top right
function mb_select_press() {
	global $press_cache, $mb_id;
	
	if ($press_cache) {
	
		echo '<div id="mbdiv" style="position: absolute; top: 0px; right: 0px; text-align: right;">';

		echo '<form id="multiply" method="get">';
		//echo '<fieldset>';
		echo '<select name="set_press_id">';
		echo '<option value="0">' . __('Default Press', 'Multiply') . '</option>';
		foreach ($press_cache as $id => $press) {
			echo '<option value="' . $id . '"';
			if ($id == $mb_id) echo ' selected="selected"';
			echo '>'.$id.'. '.$press->name.'&nbsp;&nbsp;</option>';
		}
		echo '</select>';
		echo '<input value="' . __('Select', 'Multiply') . '" type="submit">';
		
		//echo '</fieldset>';
		echo '</form>';
		echo '</div>';
	}
}

function mb_login_redirect($link) {
	global $user_ID, $mb_id;
	
	if (!isset($mb_id)) $mb_id = 0;
	
	if ('' == $user_ID) {
		$link = '<a href="' . get_settings('siteurl') . '/wp-login.php?redirect_to=' . urlencode("wp-admin/?set_press_id=$mb_id") . '">' . __('Login') . '</a>';
	} 
	return $link;
}

// get all presses
function mb_presses() {
	global $wpdb;
	
	$presses = $wpdb->get_results("
		SELECT 
			press_id, 
			press_name,
			min_level
		FROM 
			$wpdb->multiply 
		WHERE 
			1 
		ORDER BY press_id
		");
		
	if ($presses) {
		$press_cache_all = array();
		foreach ($presses as $press) {
			$press_cache_all[$press->press_id]->press_name = $press->press_name;
			$press_cache_all[$press->press_id]->min_level = $press->min_level;	
		}
		return $press_cache_all;
	}
}

// get all users with magic user levels
function mb_press_users() {
	global $wpdb;

	$results = $wpdb->get_results("
		SELECT 
			mu.user_id,
			mu.level,
			mb.press_id AS 'press_id', 
			mb.press_name AS 'press_name'
					
		FROM 
			$wpdb->multiply mb, 
			$wpdb->muser mu 
		WHERE 
			mu.press_id = mb.press_id
		");
	
	if ($results) {
		$press_user_cache = array();
		foreach($results as $press) {
			$press_user_cache[$press->press_id][$press->user_id] = $press;
		}
		return $press_user_cache;	
	}
	
}	


function mb_management_page() {
	global $wpdb, $user_level, $mb_tables;
	
	check_admin_referer();
	if (!user_can_access_admin_page())
		die (__('Sorry, only the administrator can access this page.', 'Multiply'));
	//if ($user_level < 10)
	
	$press_edit_id = (int) $_REQUEST['press_edit_id'];
	$user_id = (int) $_REQUEST['user_id'];
	
	$action = $_REQUEST['action'];
	
	switch($action) {
		
		case 'add_user':
			$level = (int) $_REQUEST['level'];
			
			if (!$level) $level = 0;
			if ($level > 9) $level = 9;
			$var = $wpdb->get_var("SELECT level FROM $wpdb->muser WHERE press_id = '$press_edit_id' AND user_id = '$user_id'");
			if ($var) {
				$wpdb->query("UPDATE $wpdb->muser SET level = '$level' WHERE press_id = $press_edit_id AND user_id = $user_id");	
			} else {
				$wpdb->query("INSERT INTO $wpdb->muser (press_id, user_id, level) VALUES ('$press_edit_id', '$user_id', '$level')");
			}
			$status = __('User updated.', 'Multiply');
		break;
		
		case 'delete_user':
			$wpdb->query("DELETE FROM $wpdb->muser WHERE press_id = '$press_edit_id' AND user_id = '$user_id'");
			$status = __('User updated.', 'Multiply');
		break;
	
		case 'promote':
		case 'demote':
			$var = $wpdb->get_var("SELECT level FROM $wpdb->muser WHERE press_id = '$press_edit_id' AND user_id = '$user_id'");
			if ($action == 'promote' && $var < 9) {
				$dir = '+ 1';
			} elseif ($action == 'demote' && $var > 0) {
				$dir = '- 1';	
			}
			$wpdb->query("UPDATE $wpdb->muser SET level = level $dir WHERE press_id = $press_edit_id AND user_id = $user_id");	
			$status = __("User updated.", 'Multiply');
		break;
		
		case 'create':
			$name = addslashes($_POST['name']);
			$level = (int) $_POST['level'];
			$message = mb_create_press($name, $level);
			$status = $message . __("New press '$name' created! It can be accessed by users of level $level or greater, or by any user you add below.", 'Multiply');
		break;
		
		case 'edit':
			$name = addslashes($_POST['name']);
			$level = (int) $_POST['level'];
			if ($level > 10) $level = 10;
			$wpdb->query("UPDATE $wpdb->multiply SET `press_name` = '$name', `min_level` = '$level' WHERE `press_id` = '$press_edit_id'");
		break;
		
		case 'delete':
			
			$var = $wpdb->get_var("SELECT press_id FROM $wpdb->multiply WHERE press_id = $press_edit_id");
			if ($var) {
				mb_set_press($press_edit_id);
				foreach($mb_tables as $table) {
					$table = $wpdb->$table;
					$wpdb->query("DROP TABLE `$table`");
				}
				$wpdb->query("DELETE FROM `$wpdb->muser` WHERE press_id = $press_edit_id");
				$wpdb->query("DELETE FROM `$wpdb->multiply` WHERE press_id = $press_edit_id");
				$status = __('Press deleted. I hope you didn\'t need that...', 'Multiply');
				//header('Location: edit.php?page=000-multiply.php');
				
			} else {
				$status = __("Press ID #$press_edit_id does not exist.", 'Multiply');	
			}
			$press_edit_id = 0;
		break;
		
	}
	
	$presses = mb_presses();
	$press_user_cache = mb_press_users();
	$press_cache = mb_authorized_presses();
	
	if ($press_edit_id) {
		$header = __('Edit Press', 'Multiply');	
		$press = $presses[$press_edit_id];
		$act = 'edit';
	} else {
		$header = __('Add Press', 'Multiply');	
		$act = 'create';
	}
	?>			
	
	<?php if ($status) { ?>
		<div class="updated">
			<p>
			<strong>
				<?php 
					echo $status;
				?>
			</strong>
			</p>
		</div>
	<?php } ?>

		<div class="wrap">
		<form method="post" action="edit.php?page=000-multiply.php">
			<h2><?php echo $header; ?></h2>
			
			<fieldset>
			
				<label for="name">
					<?php _e('Press name', 'Multiply'); ?>
				</label>
				<input type="text" name="name" size="30" maxlength="50" value="<?php echo $press->press_name; ?>" />
					<label for="level">
						<?php _e('Default access level', 'Multiply'); ?>
					</label>
				<select name="level">
				<?php
					for ($i=0; $i<=10; $i++) {
						echo "<option value='$i'";
						if ($i == $press->min_level) echo " selected='selected'";
						echo ">$i</option>";
					}
				?>
				</select>
				<input type="hidden" name="press_edit_id" value="<?php echo $press_edit_id; ?>" /> 
				<input type="hidden" name="action" value="<?php echo $act; ?>" /> 
				<input type="submit" value="<?php _e('Update', 'Multiply'); ?>" />

			</fieldset>
		</form>
		</div>
		
	
	<?php if ($press_edit_id) : ?>
		
		<div class="wrap">
		<h2><?php _e('Press Users', 'Multiply'); ?></h2>
		<?php
		
		$users = $press_user_cache[$press_edit_id];
		if ($users) :
		
		?>	
			
		<table cellpadding="3" cellspacing="3" width="100%">
		<tr>
			<th scope="col">ID</th>
        	<th scope="col"><?php _e('Name'); ?></th>
        	<th scope="col"><?php _e('Default Level', 'Multiply'); ?></th>
	        <th scope="col"><?php _e('Press Level', 'Multiply'); ?></th>
    	    <th colspan="3"><?php _e('Action'); ?></th>
		</tr>
		
			<?php
			foreach ($users as $id => $row) {
				$user = get_userdata($id);
				$level = $row->level;
				++$count;
				if ( $count % 2 ) $style = ' class="alternate"';
					else $style = '';
					
				echo "<tr$style>";
				echo "<td align='center'>$id</td>";
				echo "<td>$user->user_nickname ($user->user_firstname $user->user_lastname)</td>";
				echo "<td align='center'>$user->user_level</td>";
				echo "<td align='center'>" . $level . "</td>";
				
				echo '<td align="center">';
				if ($level > 0) echo '<a class="edit" href="edit.php?page=000-multiply.php&amp;action=demote&amp;press_edit_id='. $press_edit_id .'&amp;user_id='.$id.'">-</a>';
				echo '</td>';
				echo '<td align="center">';
				if ($level < 9) echo '<a class="edit" href="edit.php?page=000-multiply.php&amp;action=promote&amp;press_edit_id='. $press_edit_id .'&amp;user_id='.$id.'">+</a>';
				echo '</td>';
				echo '<td align="center"><a class="delete" href="edit.php?page=000-multiply.php&amp;action=delete_user&amp;press_edit_id='. $press_edit_id .'&amp;user_id='.$id.'">'.__('Delete').'</a></td>';
			}
			
			
			?>
		</table>
			<?php endif; ?>
			
			
			<form method="post">	
			<fieldset name="blog_name">
				<legend><?php _e('Add User', 'Multiply'); ?></legend>
			
			<label for="user">
				<?php _e('User', 'Multiply'); ?>
			</label>
		
		<?php
		echo '<select name="user_id">';
		$users = $wpdb->get_results("SELECT * FROM $wpdb->users");
		foreach ($users as $user) :
			echo '<option value="' . $user->ID . '">'.$user->user_nickname.'</option>';
		endforeach;
		echo '</select>';
		?>
			<label for="level">
				<?php _e('Level', 'Multiply'); ?>
			</label>
			<select name="level">
			<!--<option value="0" selected="selected">0</option>-->
			<?php
				for ($i=0; $i<=9; $i++) {
					echo '<option value="'.$i.'">'.$i.'</option>';
				}
			?>
			</select>
			<input type="hidden" name="action" value="add_user" />
			<input type="hidden" name="press_edit_id" value="<?php echo $press_edit_id; ?>" />
			<input type="submit" value="<?php _e('Add', 'Multiply'); ?>" />
		</form>
			

		</div>
	<?php endif; ?>
		
		<div class="wrap">
		<h2><?php _e('Presses', 'Multiply'); ?></h2>
		<form method="post">
		<table cellpadding="3" cellspacing="3" width="100%">
		<tr>
			<th scope="col">ID</th>
        	<th scope="col"><?php _e('Name'); ?></th>
        	<th scope="col"><?php _e('Users', 'Multiply'); ?></th>
	        <th scope="col"><?php _e('Default Level', 'Multiply'); ?></th>
    	    <th colspan="2"><?php _e('Action'); ?></th>
		</tr>
		
		<?php
	
		if ($presses) {
									
			foreach ($presses as $id => $press) {
				++$count;
				if ( $count % 2 ) $style = ' class="alternate"';
					else $style = '';
				
				echo "<tr$style>";
				echo "<th scope='row'>$id</th>";
				echo "<td>$press->press_name</td>";
	
				echo '<td>';
				
				if ($users = $press_user_cache[$id]) {
					$i = 0;
					foreach ($users as $user_id => $row) {
						$user = get_userdata($user_id);
						echo "$user->user_nickname ($row->level)";
						if ($i < count($users)-1) echo ', ';
						$i++;
					}					
				}
				echo '</td>';
				echo '<td align="center">';
				echo $press->min_level;
				echo '</td>';
				echo '<td><a href="edit.php?page=000-multiply.php&amp;press_edit_id='. $id .'" class="edit">Edit</a></td>';
				echo '<td><a href="edit.php?page=000-multiply.php&amp;action=delete&amp;press_edit_id='. $id .'" onclick="return confirm(\''.__('Really delete this press?', 'Multiply') .'\')" class="delete">Delete</a></td>';
				echo '</tr>';
			
			}
		}
		
		?>
	</table>
	</form>
	</div>
 	<?
}

function mb_admin() {
	mb_user_auth();
	add_management_page(__('Manage Presses', 'Multiply'), __('Presses', 'Multiply'), 10, __FILE__, 'mb_management_page');	
}


// Create a new press.
//
function mb_create_press($name='', $min_level=0) {
	global $table_prefix, $wpdb, $mb_tables, $mb_real_prefix;
	
	$latest = $wpdb->get_row("SHOW TABLE STATUS LIKE '$wpdb->multiply'");
	$press_id = $latest->Auto_increment;
	
	$prefix = $mb_real_prefix . $press_id . '_';
	foreach ($mb_tables as $table) {
		$wpdb->$table = $prefix . $table;
	}
	
	if (!$name) $name = $press_id;
	
	$wpdb->query("INSERT INTO $wpdb->multiply ( `min_level`, `press_name` ) VALUES ( '$min_level', '$name' )");
			
	require_once(ABSPATH . 'wp-admin/upgrade-schema.php');
	$queries = $wp_queries; // from upgrade-schema

	if( !is_array($queries) ) {
		$queries = explode( ';', $queries );
		if('' == $queries[count($queries) - 1]) array_pop($queries);
	}
	foreach ($queries as $key => $query) {
		preg_match("# ([^ ]+?) \($#m", $query, $matches);
		$str = $matches[1];
		
		$str = preg_replace("#^$prefix#", '', $str);
			
		if (!in_array($str, $mb_tables)) {
			unset($queries[$key]);	
		} 
	}
	
	$queries = implode($queries, ';');
	
	// hook intended for plugins etc. to add per-blog tables
	$queries = apply_filters('mb_create_press', $queries);
		
	// $wp_queries has things we don't want, like a users table.
	// Get rid of those queries.
	require_once(ABSPATH . '/wp-admin/upgrade-functions.php');
	
	$alterations = dbDelta($queries);
	
	// Need a default category or errors are sprung.
	//	
	$wpdb->query("INSERT INTO $wpdb->categories (cat_ID, cat_name, category_nicename) VALUES ('0', '".addslashes(__('Uncategorized'))."', '".sanitize_title(__('Uncategorized'))."')");
	
	populate_options();	
	update_option('blogname', $name);
	update_option('active_plugins', array('000-multiply.php'));
	
	// GMT offset is a special case; it's added on install in `upgrade_110()`,
	// but it's a huge waste of queries to run that stuff here. options.php will
	// silently drop options it doesn't recognize, so it needs to be created here.
	add_option('gmt_offset', 0);
	
	// Try and create `xmlrpc-{$press_id}.php` so pingback can function.
	mb_create_xmlrpc_php($press_id);
}

function mb_xmlrpc_link_filter($link) {
	global $mb_id;
	if (strpos($link, 'xmlrpc.php') && $mb_id) {
		$link = mb_get_pingback_url();
	}
	return $link;
}

function mb_get_pingback_url() {
	global $mb_id, $wp_rewrite;	
	if ($mb_id)	{ 
		if ($wp_rewrite->using_mod_rewrite_permalinks()) {
			$link = trailingslashit(get_settings('home')) . 'xmlrpc.php';
		} else {
			$link = trailingslashit(get_settings('siteurl')) . "xmlrpc-{$mb_id}.php";	
		}
	} else {
		$link = get_bloginfo('pingback_url');
	}
	return $link;
}

// create xmlrpc-{$press_id}.php files so pingback etc. works
function mb_create_xmlrpc_php($id) {

	$contents = "<?php \n" .
		"/* " . __('This file generated by the Multiply multi-blog plugin.', 'Multiply') ."\n" .
		"       http://rephrase.net/box/word/multiply/ \n" .
		"   " . __('If Multiply has been deleted, it is safe to remove this file.', 'Multiply') . "\n" .
		"*/\n" .
		"\n" .
		"\$mb_press_id = $id;\n" .
		"require_once('./xmlrpc.php');\n" .
		"\n" .
		"?>\n";

	$path = ABSPATH;
	$filename = $path . "xmlrpc-{$id}.php";
	
	if ((!file_exists($filename) && is_writable($path)) || is_writable($filename)) {
		$f = fopen($filename, 'w');
		fwrite($f, $contents);
		fclose($f);
		return true;
	} else {
		return false;
	}	
}

function mb_rewrite($rules) {
	global $mb_id;

	$rules['xmlrpc.php'] = "xmlrpc.php?";
	if ($mb_id)
		$rules = array_map('mb_rewrite_add_id', $rules);
	
	return $rules;
}
function mb_rewrite_add_id($rule) {
	global $mb_id;
	return $rule .= "&press_id=$mb_id";
}

function mb_install() {
	global $wpdb, $table_prefix;

	$wpdb->multiply = $table_prefix . 'multiply';
	$wpdb->muser = $table_prefix . 'muser';
	
	$wpdb->query("
		CREATE TABLE `$wpdb->multiply` (
		  `press_id` int(11) NOT NULL auto_increment,
		  `press_name` varchar(200) NOT NULL default '',
		  `min_level` tinyint(2) NOT NULL default '0',
		  PRIMARY KEY  (`press_id`)
		  ); ");

	$wpdb->query("
		CREATE TABLE `$wpdb->muser` (
		  `rel_id` int(11) NOT NULL auto_increment,
		  `press_id` int(11) NOT NULL default '0',
		  `user_id` int(11) NOT NULL default '0',
		  `level` tinyint(2) NOT NULL default '0',
		  PRIMARY KEY  (`rel_id`)
		); ");

}
add_filter('bloginfo', 'mb_xmlrpc_link_filter');
add_filter('rewrite_rules_array', 'mb_rewrite');

add_action('comment_form', 'mb_comment_form');
add_action('admin_menu', 'mb_admin');
add_action('admin_footer', 'mb_select_press');
add_action('loginout', 'mb_login_redirect');

// Ripped from pluggable-functions.php with very few modifications.
// Pathetically few modifications, really.
if ( ! function_exists('wp_notify_postauthor') ) :
function wp_notify_postauthor($comment_id, $comment_type='') {
	global $wpdb, $mb_id;
    
    if ($mb_id) $set_press_link = "&set_press_id=$mb_id";
    
	$comment = $wpdb->get_row("SELECT * FROM $wpdb->comments WHERE comment_ID='$comment_id' LIMIT 1");
	$post = $wpdb->get_row("SELECT * FROM $wpdb->posts WHERE ID='$comment->comment_post_ID' LIMIT 1");
	$user = $wpdb->get_row("SELECT * FROM $wpdb->users WHERE ID='$post->post_author' LIMIT 1");

	if ('' == $user->user_email) return false; // If there's no email to send the comment to

	$comment_author_domain = gethostbyaddr($comment->comment_author_IP);

	$blogname = get_settings('blogname');
	
	if ( empty( $comment_type ) ) $comment_type = 'comment';
	
	if ('comment' == $comment_type) {
		$notify_message  = sprintf( __('New comment on your post #%1$s "%2$s"'), $comment->comment_post_ID, $post->post_title ) . "\r\n";
		$notify_message .= sprintf( __('Author : %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
		$notify_message .= sprintf( __('E-mail : %s'), $comment->comment_author_email ) . "\r\n";
		$notify_message .= sprintf( __('URI    : %s'), $comment->comment_author_url ) . "\r\n";
		$notify_message .= sprintf( __('Whois  : http://ws.arin.net/cgi-bin/whois.pl?queryinput=%s'), $comment->comment_author_IP ) . "\r\n";
		$notify_message .= __('Comment: ') . "\r\n" . $comment->comment_content . "\r\n\r\n";
		$notify_message .= __('You can see all comments on this post here: ') . "\r\n";
		$subject = sprintf( __('[%1$s] Comment: "%2$s"'), $blogname, $post->post_title );
	} elseif ('trackback' == $comment_type) {
		$notify_message  = sprintf( __('New trackback on your post #%1$s "%2$s"'), $comment->comment_post_ID, $post->post_title ) . "\r\n";
		$notify_message .= sprintf( __('Website: %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
		$notify_message .= sprintf( __('URI    : %s'), $comment->comment_author_url ) . "\r\n";
		$notify_message .= __('Excerpt: ') . "\r\n" . $comment->comment_content . "\r\n\r\n";
		$notify_message .= __('You can see all trackbacks on this post here: ') . "\r\n";
		$subject = sprintf( __('[%1$s] Trackback: "%2$s"'), $blogname, $post->post_title );
	} elseif ('pingback' == $comment_type) {
		$notify_message  = sprintf( __('New pingback on your post #%1$s "%2$s"'), $comment->comment_post_ID, $post->post_title ) . "\r\n";
		$notify_message .= sprintf( __('Website: %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
		$notify_message .= sprintf( __('URI    : %s'), $comment->comment_author_url ) . "\r\n";
		$notify_message .= __('Excerpt: ') . "\r\n" . sprintf( __('[...] %s [...]'), $comment->comment_content ) . "\r\n\r\n";
		$notify_message .= __('You can see all pingbacks on this post here: ') . "\r\n";
		$subject = sprintf( __('[%1$s] Pingback: "%2$s"'), $blogname, $post->post_title );
	}
	$notify_message .= get_permalink($comment->comment_post_ID) . "#comments\r\n\r\n";
	$notify_message .= sprintf( __('To delete this comment, visit: %s'), get_settings('siteurl').'/wp-admin/post.php?action=confirmdeletecomment&p='.$comment->comment_post_ID."&comment=$comment_id" . $set_press_link ) . "\r\n";

	if ('' == $comment->comment_author_email || '' == $comment->comment_author) {
		$from = "From: \"$blogname\" <wordpress@" . $_SERVER['SERVER_NAME'] . '>';
	} else {
		$from = 'From: "' . $comment->comment_author . "\" <$comment->comment_author_email>";
	}

	$message_headers = "MIME-Version: 1.0\n"
		. "$from\n"
		. "Content-Type: text/plain; charset=\"" . get_settings('blog_charset') . "\"\n";
	
	@wp_mail($user->user_email, $subject, $notify_message, $message_headers);
   
	return true;
}
endif;


/* wp_notify_moderator
   notifies the moderator of the blog (usually the admin)
   about a new comment that waits for approval
   always returns true
 */
if ( !function_exists('wp_notify_moderator') ) :
function wp_notify_moderator($comment_id) {
	global $wpdb, $mb_id;
	
	if ($mb_id) $set_press_link = "&set_press_id=$mb_id";
	
	if( get_settings( "moderation_notify" ) == 0 )
		return true; 
    
	$comment = $wpdb->get_row("SELECT * FROM $wpdb->comments WHERE comment_ID='$comment_id' LIMIT 1");
	$post = $wpdb->get_row("SELECT * FROM $wpdb->posts WHERE ID='$comment->comment_post_ID' LIMIT 1");
	$user = $wpdb->get_row("SELECT * FROM $wpdb->users WHERE ID='$post->post_author' LIMIT 1");

	$comment_author_domain = gethostbyaddr($comment->comment_author_IP);
	$comments_waiting = $wpdb->get_var("SELECT count(comment_ID) FROM $wpdb->comments WHERE comment_approved = '0'");

	$notify_message  = sprintf( __('A new comment on the post #%1$s "%2$s" is waiting for your approval'), $post->ID, $post->post_title ) . "\r\n";
	$notify_message .= get_permalink($comment->comment_post_ID) . "\r\n\r\n";
	$notify_message .= sprintf( __('Author : %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
	$notify_message .= sprintf( __('E-mail : %s'), $comment->comment_author_email ) . "\r\n";
	$notify_message .= sprintf( __('URI    : %s'), $comment->comment_author_url ) . "\r\n";
	$notify_message .= sprintf( __('Whois  : http://ws.arin.net/cgi-bin/whois.pl?queryinput=%s'), $comment->comment_author_IP ) . "\r\n";
	$notify_message .= __('Comment: ') . "\r\n" . $comment->comment_content . "\r\n\r\n";
	$notify_message .= sprintf( __('To approve this comment, visit: %s'),  get_settings('siteurl').'/wp-admin/post.php?action=mailapprovecomment&p='.$comment->comment_post_ID."&comment=$comment_id" . $set_press_link) . "\r\n";
	$notify_message .= sprintf( __('To delete this comment, visit: %s'), get_settings('siteurl').'/wp-admin/post.php?action=confirmdeletecomment&p='.$comment->comment_post_ID."&comment=$comment_id" . $set_press_link) . "\r\n";
	$notify_message .= sprintf( __('Currently %s comments are waiting for approval. Please visit the moderation panel:'), $comments_waiting ) . "\r\n";
	$notify_message .= get_settings('siteurl') . "/wp-admin/moderation.php?". $set_press_link . "\r\n";

	$subject = sprintf( __('[%1$s] Please moderate: "%2$s"'), get_settings('blogname'), $post->post_title );
	$admin_email = get_settings("admin_email");
	@wp_mail($admin_email, $subject, $notify_message);
    
	return true;
}
endif;



if ( !function_exists('get_userdata') ) :
function get_userdata($userid) {
	global $wpdb, $cache_userdata, $press_cache, $mb_id;
	$userid = (int) $userid;
	if ( empty($cache_userdata[$userid]) && $userid != 0) {
		$cache_userdata[$userid] = $wpdb->get_row("SELECT * FROM $wpdb->users WHERE ID = $userid");
		$cache_userdata[$cache_userdata[$userid]->user_login] =& $cache_userdata[$userid];
	} 
	
	if ($mb_id && $userid != 0) {
		if ( empty($press_cache[$mb_id]) ) {
			$presses = mb_authorized_presses();
		}
       $cache_userdata[$userid]->user_level = $press_cache[$mb_id]->level;
	}
	return $cache_userdata[$userid];
}
endif;

if ( !function_exists('get_userdatabylogin') ) :
function get_userdatabylogin($user_login) {
	global $cache_userdata, $wpdb, $press_cache, $mb_id, $user_level, $user_ID;
	
	if ( !empty($user_login) && empty($cache_userdata[$user_login]) ) {
		$user = $wpdb->get_row("SELECT * FROM $wpdb->users WHERE user_login = '$user_login'"); /* todo: get rid of this intermediate var */
		$cache_userdata[$user->ID] = $user;
		$cache_userdata[$user_login] =& $cache_userdata[$user->ID];
	} else {
		$user = $cache_userdata[$user_login];
	}
	
	$user_level = $user->user_level;
	$user_ID = $user->ID;
	
	if ($mb_id && $user->ID != 0) {
		if ( empty($press_cache[$mb_id]) ) {
			$presses = mb_authorized_presses();
		}
       $cache_userdata[$user->ID]->user_level = $press_cache[$mb_id]->level;
       $user = $cache_userdata[$user->ID];
	}
	return $user;
}
endif;
	
?>