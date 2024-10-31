<?php
	
if (!file_exists('../wp-config.php')) die("There doesn't seem to be a wp-config.php file. Double check that you updated wp-config-sample.php with the proper database connection information and renamed it to wp-config.php.");
require('../wp-config.php');
timer_start();

$step = $_GET['step'];
if (!$step) $step = 0;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>WordPress Multiply &rsaquo; Upgrade</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<style media="screen" type="text/css">
	<!--
	html {
		background: #eee;
	}
	body {
		background: #fff;
		color: #000;
		font-family: Georgia, "Times New Roman", Times, serif;
		margin-left: 20%;
		margin-right: 20%;
		padding: .2em 2em;
	}
	
	h1 {
		color: #006;
		font-size: 18px;
		font-weight: lighter;
	}
	
	h2 {
		font-size: 16px;
	}
	
	p, li, dt {
		line-height: 140%;
		padding-bottom: 2px;
	}

	ul, ol {
		padding: 5px 5px 5px 20px;
	}
	#logo {
		margin-bottom: 2em;
	}
.step a, .step input {
	font-size: 2em;
}
.step, th {
	text-align: right;
}
#footer {
text-align: center; border-top: 1px solid #ccc; padding-top: 1em; font-style: italic;
}
	-->
	</style>
</head>
<body>
<h1 id="logo">Multiply</h1>
<?php
switch($step) {

	case 0:
?> 
<p><?php _e('This file upgrades your Multiply alternate presses. Please note, however, that (depending on changes in the latest version of WordPress) this script might not actually work. Check the Multiply home page for details, or -- at the very least -- back up your data first. If it works, it may take a while. Please be patient.'); ?></p> 
	<h2 class="step"><a href="upgrade.php?step=1"><?php _e('Upgrade &raquo;'); ?></a></h2>
<?php
	break;
	
	case 1:
	$presses = mb_presses();
	$allqueries = array();
	
	foreach ($mb_tables as $table) {
		$wpdb->$table = md5($table);
	}
	require_once('./upgrade-schema.php');

	foreach ($presses as $id => $press) {
		$prefix = $table_prefix . $id . '_';
		$queries = $wp_queries;
		foreach ($mb_tables as $table) {
			$wpdb->$table = $prefix . $table;
			$md5 = md5($table);
			$queries = preg_replace("#$md5#", $wpdb->$table, $queries); 
		}
				
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
		$allqueries[] = implode($queries, ';');
		
	}
	define('WP_INSTALLING', true);
	require_once(ABSPATH . '/wp-admin/upgrade-functions.php');
	foreach ($allqueries as $wp_queries) {
		make_db_current();
		upgrade_all();
	}
?> 
<h2><?php _e('Step 1'); ?></h2> 
	<p><?php printf(__("There's actually only one step. So if you see this, you're done. <a href='%s'>Have fun</a>!"), '../'); ?></p>

<!--
<pre>
<?php printf(__('%s queries'), $wpdb->num_queries); ?>

<?php printf(__('%s seconds'), timer_stop(0)); ?>
</pre>
-->

<?php
	break;
}
?> 
</body>
</html>
