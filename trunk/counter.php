<?php
/*
Plugin Name: Count Per Day
Plugin URI: http://www.tomsdimension.de/wp-plugins/count-per-day
Description: Counter, shows reads per page; today, yesterday, last week, last months ... on dashboard.
Version: 1.5.1
License: GPL
Author: Tom Braider
Author URI: http://www.tomsdimension.de
*/


/**
 */
global $table_prefix;
define('CPD_C_TABLE', $table_prefix.'cpd_counter');
define('CPD_CO_TABLE', $table_prefix.'cpd_counter_useronline');

/**
 * counts and shows visits
 *
 * @param string $before string before the number
 * @param string $after string after the number
 * @param boolean $show "echo" (true, standard) or "return"
 * @param boolean $count count visits (true, standard) or only show vistis
 * @return string counter string
 */
function cpdShow( $before='', $after=' reads', $show = true, $count = true )
{
	global $wpdb;
	$page = get_the_ID();
	// only count once
	if ( $count == true && get_option('cpd_autocount') == 0 )
		cpdCount();
	$visits = $wpdb->get_results("SELECT page FROM ".CPD_C_TABLE." WHERE page='$page';");
	$visits_per_page = count($visits);
	if ( $show == true )
		echo $before.$visits_per_page.$after;
	else
		return $visits_per_page;
}

/**
 * shows visits (without counting)
 */
function cpdCount()
{
	global $wpdb;
	cpdCreateTables(); // create tables if necessary
	
	// find PostID
	if ( get_option('cpd_autocount') == 1 )
	{
		if (have_posts()) : while ( have_posts() && $page == 0 ) : the_post();
			$page = get_the_ID();
		endwhile; endif;
		rewind_posts();
	}
	else if ( is_single() || is_page() )
		$page = get_the_ID();
	else
		$page = 0;
	
	$countUser = ( get_option('cpd_user') == 0 && is_user_logged_in() == true ) ? 0 : 1;
	
	// only count if: non bot, PostID exists, Logon is ok
	if ( cpdIsBot() == false && !empty($page) && $countUser == 1 )
	{
		$userip = $_SERVER['REMOTE_ADDR'];
		$client = $_SERVER['HTTP_USER_AGENT'];
		$date = date('ymd');
		// memorize UserIP 
		$user_ip = $wpdb->get_results("SELECT * FROM ".CPD_C_TABLE." WHERE ip='$userip' AND date='$date' AND page='$page';");
		if ( count($user_ip) == 0 )
			$wpdb->query("INSERT INTO ".CPD_C_TABLE." (page, ip, client, date) VALUES ('"
				.$wpdb->escape($page)."', '".$wpdb->escape($userip)."', '"
				.$wpdb->escape($client)."', '".$wpdb->escape($date)."');");
		
		$timestamp = time();  
		$timeout = $timestamp - get_option('cpd_onlinetime');
		
		$wpdb->query("REPLACE INTO ".CPD_CO_TABLE." (timestamp, ip, page) VALUES ('".$wpdb->escape($timestamp)."','".$wpdb->escape($userip)."','".$wpdb->escape($page)."');");
		$wpdb->query("DELETE FROM ".CPD_CO_TABLE." WHERE timestamp < $timeout;");
	}
}

/**
 * bot or human?
 * @param string $client USER_AGENT
 * @param array $bots strings to check
 */
function cpdIsBot( $client = '', $bots = '' )
{
	if ( empty($bots) )
		// load pattern
		$bots = explode( "\n", get_option('cpd_bots') );
	$isBot = false;
	foreach ( $bots as $bot )
	{
		$b = trim($bot);
		if ( !empty($b) )
		{
			if ( empty($client) )
			{
				if ( strpos( strtolower($_SERVER['HTTP_USER_AGENT']), strtolower($b) ) !== false )
					$isBot = true;
			}
			else
			{
				if ( strpos( strtolower($client), strtolower($b) ) !== false )
					$isBot = true;
			}
		}
	}
	return $isBot;
}


/**
 * create tables if not exists
 */
function cpdCreateTables() {
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	global $wpdb;
	
	if ( $wpdb->get_var( "SHOW TABLES LIKE '".CPD_C_TABLE."'" ) != CPD_C_TABLE )
	{
		// table "counter" is not exists
		$sql ="CREATE TABLE IF NOT EXISTS `".CPD_C_TABLE."` (
			`id` int(10) NOT NULL auto_increment,
  			`ip` varchar(15) NOT NULL,
  			`client` varchar(100) NOT NULL,
  			`date` char(6) NOT NULL,
  			`page` int(11) NOT NULL,
  			PRIMARY KEY  (`id`)
			);";
		dbDelta($sql);
		add_option('cpd_cdb_version', '1.0');
	}
	
	if ( $wpdb->get_var( "SHOW TABLES LIKE '".CPD_CO_TABLE."'" ) != CPD_CO_TABLE )
	{
		// table "counter-online" is not exists
		$sql ="CREATE TABLE IF NOT EXISTS `".CPD_CO_TABLE."` (
			`timestamp` int(15) NOT NULL default '0',
			`ip` varchar(15) NOT NULL default '',
			`page` int(11) NOT NULL default '0',
			PRIMARY KEY  (`ip`)
			);";
		dbDelta($sql);
		add_option('cpd_codb_version', '1.0');
	}
	
	add_option( 'cpd_onlinetime', 300 );
	add_option( 'cpd_user', 0 );
	add_option( 'cpd_autocount', 0 );
	add_option( 'cpd_bots', "bot\nspider\nsearch\ncrawler\nask.com\nvalidator\nsnoopy\n".
		"suchen.de\nsuchbaer.de\nshelob\nsemager\nxenu\nsuch_de\nia_archiver\nMicrosoft URL Control\nnetluchs" );
}

register_activation_hook(__FILE__,'cpdCreateTables');


/**
 * statistics page
 */
function cpdDashbord()
{
	?>
	<div class="wrap"> 
		<h2>Count per Day - <?php _e('Statistics', 'cpd') ?></h2>
		<table class="cpd_table"><tr>
		<td>
			<table class="widefat">
			<thead><tr><th><?php _e('Reads at all', 'cpd') ?></th></tr></thead>
			<tbody><tr><td>
				<ul>
					<li><?php _e('Reads at all', 'cpd') ?>: <b><span><?php cpdGetUserAll(); ?></span></b></li>
					<li><?php _e('Visitors currently online', 'cpd') ?>: <b><span><?php cpdGetUserOnline(); ?></span></b></li>
					<li><?php _e('Reads today', 'cpd') ?>: <b><?php cpdGetUserToday(); ?></b></li>
					<li><?php _e('Reads yesterday', 'cpd') ?>: <b><?php cpdGetUserYesterday(); ?></b></li>
					<li><?php _e('Reads last week', 'cpd') ?>: <b><?php cpdGetUserLastWeek(); ?></b></li>
					<li><?php _e('Counter starts at', 'cpd') ?>: <b><?php cpdGetFirstCount(); ?></b></li>
					<li>&Oslash; <?php _e('Reads per day', 'cpd') ?>: <b><?php cpdGetUserPerDay(); ?></b></li>
				</ul>
			</td></tr></tbody>
			</table>
		</td>
		<td>
			<table class="widefat">
			<thead><tr><th><?php _e('Reads per month', 'cpd') ?></th></tr></thead>
			<tbody><tr><td><?php cpdGetUserPerMonth(); ?></td></tr></tbody>
			</table>
		</td>
		<td>
			<table class="widefat">
			<thead><tr><th><?php _e('Reads per post', 'cpd') ?></th></tr></thead>
			<tbody><tr><td><?php cpdGetUserPerPost(50); ?></td></tr></tbody>
			</table>
		</td>
		</tr></table>

	</div>
	<?php
}

// statistic functions, you can use is in your template too

/**
 * shows current visitors
 */
function cpdGetUserOnline()
{
	global $wpdb;
	$v = $wpdb->get_var("SELECT count(page) FROM ".CPD_CO_TABLE.";");
	echo $v;
}

/**
 * shows all visitors
 */
function cpdGetUserAll()
{
	global $wpdb;
	$v = $wpdb->get_results("SELECT page FROM ".CPD_C_TABLE." GROUP BY ip, date;");
	echo count($v);
}

/**
 * shows today visitors
 */
function cpdGetUserToday()
{
	global $wpdb;
	$date = date('ymd',time());
	$v = $wpdb->get_results("SELECT page FROM ".CPD_C_TABLE." WHERE date = '$date' GROUP BY ip;");
	echo count($v);
}

/**
 * shows yesterday visitors
 */
function cpdGetUserYesterday()
{
	global $wpdb;
	$date = date('ymd',time()-60*60*24);
	$v = $wpdb->get_results("SELECT page FROM ".CPD_C_TABLE." WHERE date = '$date' GROUP BY ip;");
	echo count($v);
}

/**
 * shows last week visitors (last 7 days)
 */
function cpdGetUserLastWeek()
{
	global $wpdb;
	$date = date('ymd',time()-60*60*24*7);
	$v = $wpdb->get_results("SELECT page FROM ".CPD_C_TABLE." WHERE date >= '$date' GROUP BY ip;");
	echo count($v);
}

/**
 * shows visitors per month
 */
function cpdGetUserPerMonth()
{
	global $wpdb;
	$m = $wpdb->get_results("SELECT left(date,4) as month FROM ".CPD_C_TABLE." GROUP BY left(date,4) ORDER BY date desc");
	echo '<ul>';
	foreach ( $m as $row )
	{
		$v = $wpdb->get_results("SELECT page FROM ".CPD_C_TABLE." WHERE left(date,4) = ".$row->month." GROUP BY ip, date;");
		echo '<li>20'.substr($row->month,0,2).'/'.substr($row->month,2,2).': <b>'.count($v).'</b></li>'."\n";
	}
	echo '</ul>';
}

/**
 * shows visitors per post
 *
 * @param integer $limit Sql-Limit, 0 = kein Limit
 */
function cpdGetUserPerPost( $limit = 0 )
{
	global $wpdb;
	global $table_prefix;
	$sql = "	SELECT	count(".CPD_C_TABLE.".id) as count,
							".$table_prefix."posts.post_title as post,
							".$table_prefix."posts.ID as post_id
				FROM 		".CPD_C_TABLE."
				LEFT		JOIN ".$table_prefix."posts
							ON ".$table_prefix."posts.id = ".CPD_C_TABLE.".page
				GROUP		BY ".CPD_C_TABLE.".page
				ORDER		BY count DESC";
	if ( $limit > 0 )
		$sql .= " LIMIT ".$limit;
	$m = $wpdb->get_results($sql);
	echo '<ul>';
	foreach ( $m as $row )
		echo '<li><a href="'.get_bloginfo('url').'?p='.$row->post_id.'">'.$row->post.'</a>: <b>'.$row->count.'</b></li>'."\n";
	echo '</ul>';
}

/**
 * shows counter start, first day
 */
function cpdGetFirstCount()
{
	global $wpdb;
	global $wp_locale;
	$v = $wpdb->get_var("SELECT date FROM ".CPD_C_TABLE." ORDER BY date LIMIT 1;");
	$date = strtotime( '20'.substr($v,0,2).'-'.substr($v,2,2).'-'.substr($v,4,2) );
	echo date('j. ', $date) . $wp_locale->get_month( substr($v,2,2) ) . date(' Y', $date);
}

/**
 * shows averaged visitors per day
 */
function cpdGetUserPerDay()
{
	global $wpdb;
	$v = $wpdb->get_results("SELECT MIN(date) as min, MAX(date) as max FROM ".CPD_C_TABLE.";");
	foreach ($v as $row)
	{
		$min = strtotime( '20'.substr($row->min,0,2).'-'.substr($row->min,2,2).'-'.substr($row->min,4,2) );
		$max = strtotime( '20'.substr($row->max,0,2).'-'.substr($row->max,2,2).'-'.substr($row->max,4,2) );
		$tage =  (($max - $min) / 86400 + 1);
	}
	$v = $wpdb->get_results('SELECT page FROM '.CPD_C_TABLE.' GROUP BY ip, date');
	$count = count($v) / $tage;
	if ( $count < 5 )
		echo number_format($count, 2);
	else
		echo number_format($count, 0);
}

/**
 * deletes spam in table, if you add new bot pattern you can clean the db
 */
function cpdCleanDB()
{
	global $wpdb;
	
	$bots = explode( "\n", get_option('cpd_bots') );
	$rows = 0;
	$v = $wpdb->get_results('SELECT * FROM '.CPD_C_TABLE);
	foreach ($v as $row)
	{
		if ( cpdIsBot($row->client, $bots) )
		{
			$wpdb->query('DELETE FROM '.CPD_C_TABLE.' WHERE id = '.$row->id);
			$rows++;
		}
	}
	return $rows;
}

/**
 * adds stylesheet
 */
function cpdAddCSS() {
	$this_dir = get_bloginfo('wpurl').'/'.PLUGINDIR.'/'.dirname(plugin_basename(__FILE__));
	wp_enqueue_style('cpd_css', $this_dir.'/counter.css', array());
	wp_print_styles('cpd_css');
}

// only on statistics page
if ( eregi( 'count-per-day', $_REQUEST['page']) )
	add_action( 'admin_head', 'cpdAddCSS', 100 );
	
/**
 * adds menu
 * @param string $content WP-"Content"
 */
function cpdMenu($content)
{
	global $wp_version;
	if (function_exists('add_options_page'))
	{
		$menutitle = '';
		if ( version_compare( $wp_version, '2.6.999', '>' ) )
			$menutitle = '<img src="'.cpdGetResource('cpd_menu.gif').'" alt="" /> ';
		$menutitle .= 'Count per Day';

		add_options_page('CountPerDay', $menutitle, 'manage_options', dirname(plugin_basename(__FILE__)).'/counter-options.php') ;
		add_submenu_page('index.php','CountPerDay',$menutitle,1,__FILE__,'cpdDashbord');
		
//		$plugin = plugin_basename(__FILE__); 
//		add_filter( 'plugin_action_links_' . $plugin, 'cpd_plugin_actions' );
		
	}
}

if ( is_admin() )
	add_action('admin_menu', 'cpdMenu');
	
/**
 * adds an action link to the plugins page
 */
function cpdPluginActions($links, $file)
{
	if( $file == plugin_basename(__FILE__) )
	{
		$link = '<a href="options-general.php?page='.dirname(plugin_basename(__FILE__)).'/counter-options.php">'.__('Settings').'</a>';
		array_unshift( $links, $link );
	}
	return $links;
}

add_filter('plugin_action_links', 'cpdPluginActions', 10, 2);

/**
 * adds locale support
function cpdInitLocale()
{
	$locale = get_locale();
	$mofile = dirname(__FILE__) . "/locale/".$locale.".mo";
	load_textdomain('cpd', $mofile);
	load_plugin_textdomain('cpd', dirname(__FILE__));
}

add_action('init', 'cpdInitLocale', 98);
 */


/**
 * adds locale support
 */
if (defined('WPLANG') && function_exists('load_plugin_textdomain'))
	load_plugin_textdomain('cpd', '', dirname(plugin_basename(__FILE__)).'/locale');


/**
 * loads automatic counter
 */
function cpdAutocount( )
{
	if ( is_single() || is_page() )
		cpdCount();
}

if ( get_option('cpd_autocount') == 1 )	
	add_action('wp', 'cpdAutocount');
	
/**
 * dashboard widget
 */
function cpdDashboardWidget()
{
	echo '<a href="?page='.dirname(plugin_basename(__FILE__)).'/counter.php"><b>';
	cpdGetUserAll();
	echo '</b></a> '.__('Reads at all', 'cpd').' - <b>';
	cpdGetUserPerDay();
	echo '</b> '.__('Reads per day', 'cpd');
}

/**
 * adds dashboard widget
 */
function cpdDashboardWidgetSetup()
{
	wp_add_dashboard_widget( 'cpdDashboardWidget', 'Count per Day', 'cpdDashboardWidget' );
}

add_action('wp_dashboard_setup', 'cpdDashboardWidgetSetup');

/**
 * uninstall functions, deletes tables and options
 */
function cpdUninstall()
{
	global $wpdb;
	$wpdb->query('DROP TABLE IF EXISTS '.CPD_C_TABLE);
	$wpdb->query('DROP TABLE IF EXISTS '.CPD_CO_TABLE);
	delete_option('cpd_cdb_version');
	delete_option('cpd_codb_version');
	delete_option('cpd_onlinetime');
	delete_option('cpd_user');
	delete_option('cpd_autocount');
	delete_option('cpd_bots');
}

/**
 * defines base64 encoded image recources
 */
if( isset($_GET['resource']) && !empty($_GET['resource'])) {
	# base64 encoding
	$resources = array(
		'cpd_menu.gif' =>
		'R0lGODlhDAAMAJECAP8AAAAAAP///wAAACH5BAEAAAIALAAAAA'.
		'AMAAwAAAIdjI4ppsqNngA0PYDwZDrjUEGLGJGHBKFNwLYuWwAA'.
		'Ow==');
 
	if(array_key_exists($_GET['resource'], $resources)) {
 
		$content = base64_decode($resources[ $_GET['resource'] ]);
 
		$lastMod = filemtime(__FILE__);
		$client = ( isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false );
		if (isset($client) && (strtotime($client) == $lastMod)) {
			header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastMod).' GMT', true, 304);
			exit;
		} else {
			header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastMod).' GMT', true, 200);
			header('Content-Length: '.strlen($content));
			header('Content-Type: image/' . substr(strrchr($_GET['resource'], '.'), 1) );
			echo $content;
			exit;
		}
	}
}

/**
 * gets image recource with given name
 */
function cpdGetResource( $resourceID ) {
	return trailingslashit( get_bloginfo('url') ) . '?resource=' . $resourceID;
}

// since WP 2.7
if ( function_exists('register_uninstall_hook') )
	register_uninstall_hook(__FILE__, 'cpdUninstall'); 
?>