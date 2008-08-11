<?php
/*
Plugin Name: Count Per Day
Plugin URI: http://www.tomsdimension.de/wp-plugins/count-per-day
Description: Counter, shows reads per page; today, yesterday, last week, last months ... on dashboard.
Version: 1.2.2
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
 * Seitenaufruf zählen und Counter anzeigen
 *
 * @param String $before Text vor Zählerstand
 * @param String $after Text nach Zählerstand
 * @param boolean $show "echo" (true, standard) oder "return"
 * @param boolean $count zählen (true, standard) oder nur anzeigen
 *  * @return String Zählerstand
 */
function cpdShow( $before='', $after=' reads', $show = true, $count = true )
{
	global $wpdb;
	$page = get_the_ID();
	// nur zählen wenn Parameter stimmt und Autocounter aus ist (doppelt muss nicht sein)
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
 * Seitenaufruf nur zählen, keine Anzeige
 */
function cpdCount()
{
	global $wpdb;
	cpdCreateTables(); // DB-Tabellen erstellen, falls sie noch nicht existieren
	
	$page = 0;
	// Post-ID finden
	if (have_posts()) : while ( have_posts() && $page == 0 ) : the_post();
		$page = get_the_ID();
	endwhile; endif;
	rewind_posts();

	$countUser = ( get_option('cpd_user') == 0 && is_user_logged_in() == true ) ? 0 : 1;

	// nur zählen wenn: kein Bot, PostID vorhanden, Anmeldung passt
	if ( cpdIsBot() == false && !empty($page) && $countUser == 1 )
	{
		$userip = $_SERVER['REMOTE_ADDR'];
		$client = $_SERVER['HTTP_USER_AGENT'];
		$date = date('ymd');
		// UserIP für merken 
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
 * Bot oder Mensch?
 */
function cpdIsBot()
{
	// Strings die auf Suchmaschinen deuten
	$bots = explode( "\n", get_option('cpd_bots') );
	$isBot = false;
	foreach ( $bots as $bot )
	{
		if ( strpos( $_SERVER['HTTP_USER_AGENT'], trim($bot) ) !== false )
			$isBot = true;
	}
	return $isBot;
}

/**
 * Tabellen erstellen wenn nicht vorhanden
 */
function cpdCreateTables() {
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	global $wpdb;
	
	if ( $wpdb->get_var( "SHOW TABLES LIKE '".CPD_C_TABLE."'" ) != CPD_C_TABLE )
	{
		// Counter-Tabelle existieren nicht - anlegen
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
		// CounterOnline-Tabelle existieren nicht - anlegen
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

/**
 * Statistikseite
 */
function cpdDashbord()
{
	?>
	<div class="wrap"> 
		<h2>Count per Day - <?php _e('Statistics', 'cpd') ?></h2>
		<table class="cpd_table">
		<tr>
		<td>
			<div>
			<h3><?php _e('Reads at all', 'cpd') ?></h3>
			<ul>
				<li><?php _e('Reads at all', 'cpd') ?>: <span><?php cpdGetUserAll(); ?></span></li>
				<li><?php _e('Reads currently online', 'cpd') ?>: <span><?php cpdGetUserOnline(); ?></span></li>
				<li><?php _e('Reads today', 'cpd') ?>: <b><?php cpdGetUserToday(); ?></b></li>
				<li><?php _e('Reads yesterday', 'cpd') ?>: <b><?php cpdGetUserYesterday(); ?></b></li>
				<li><?php _e('Reads last week', 'cpd') ?>: <b><?php cpdGetUserLastWeek(); ?></b></li>
				<li><?php _e('Counter starts at', 'cpd') ?>: <b><?php cpdGetFirstCount(); ?></b></li>
				<li>&Oslash; <?php _e('Reads per day', 'cpd') ?>: <b><?php cpdGetUserPerDay(); ?></b></li>
			</ul>
			</div>
		</td>
		<td>
			<div><h3><?php _e('Reads per month', 'cpd') ?></h3><?php cpdGetUserPerMonth(); ?></div>
		</td>
		<td>
			<div><h3><?php _e('Reads per post', 'cpd') ?></h3><?php cpdGetUserPerPost(50); ?></div>
		</td>
		</tr>
		</table>
	</div>
	<?php
}

// Statistik-Funktionen

/**
 * zeigt momentane Besucher
 */
function cpdGetUserOnline()
{
	global $wpdb;
	$v = $wpdb->get_var("SELECT count(page) FROM ".CPD_CO_TABLE.";");
	echo $v;
}

/**
 * zeigt gesamte Besucher
 */
function cpdGetUserAll()
{
	global $wpdb;
	$v = $wpdb->get_results("SELECT page FROM ".CPD_C_TABLE." GROUP BY ip, date;");
	echo count($v);
}

/**
 * zeigt heutige Besucher
 */
function cpdGetUserToday()
{
	global $wpdb;
	$date = date('ymd',time());
	$v = $wpdb->get_results("SELECT page FROM ".CPD_C_TABLE." WHERE date = '$date' GROUP BY ip;");
	echo count($v);
}

/**
 * zeigt Besucher vom Vortag
 */
function cpdGetUserYesterday()
{
	global $wpdb;
	$date = date('ymd',time()-60*60*24);
	$v = $wpdb->get_results("SELECT page FROM ".CPD_C_TABLE." WHERE date = '$date' GROUP BY ip;");
	echo count($v);
}

/**
 * zeigt Besucher der letzten Woche (7 Tage)
 */
function cpdGetUserLastWeek()
{
	global $wpdb;
	$date = date('ymd',time()-60*60*24*7);
	$v = $wpdb->get_results("SELECT page FROM ".CPD_C_TABLE." WHERE date >= '$date' GROUP BY ip;");
	echo count($v);
}

/**
 * zeigt Besucher pro Monat
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
 * zeigt Besucher pro Artikel
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
		echo '<li><a href="'.get_bloginfo('wpurl').'?p='.$row->post_id.'">'.$row->post.'</a>: <b>'.$row->count.'</b></li>'."\n";
	echo '</ul>';
}

/**
 * zeigt Counterstart, erster Tag
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
 * zeigt Durchschnitt Besucher pro Tag
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
	$v = $wpdb->get_results("SELECT page FROM ".CPD_C_TABLE." GROUP BY ip, date;");
	$count = count($v) / $tage;
	if ( $count < 5 )
		echo number_format($count, 2);
	else
		echo number_format($count, 0);
}



/**
 * fügt Stylesheet in WP-Head ein
 *
 */
function cpdAddCSS() {
	echo '<link type="text/css" rel="stylesheet" href="'.get_bloginfo('wpurl').'/'.PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)).'/counter.css"></link>' . "\n";
}

/**
 * erstellt Menüeinträge
 * @param string $content WP-"Content"
 */
function cpdMenu($content)
{
	if (function_exists('add_options_page')) {
		add_options_page('CountPerDay', 'Count Per Day', 'manage_options', 'options-general.php?page='.dirname(plugin_basename(__FILE__)).'/counter-options.php') ;
		// Dashboard Menüpunkt
		add_submenu_page('index.php','CountPerDay','Count per Day',1,__FILE__,'cpdDashbord');
	}
}

/**
 * lädt lokale Sprachdatei
 */
function cpd_init_locale()
{
	$locale = get_locale();
	$mofile = dirname(__FILE__) . "/locale/".$locale.".mo";
	load_textdomain('cpd', $mofile);
	load_plugin_textdomain('cpd', dirname(__FILE__));
}

/**
 * lädt automatischen Counter
 * Zum reinen Zählen ist kein Eingriff ins Template mehr notwendig.
 */
function cpd_autocount( )
{
	if ( is_single() || is_page() )
		cpdCount();
}

// Funktionen adden
add_action('init', 'cpd_init_locale', 98);
add_action('admin_menu', 'cpdMenu');
register_activation_hook(__FILE__,'cpdCreateTables');

// Stylesheet nur bei Statistik-Seite laden
if ( eregi( "count-per-day", $_REQUEST['page']) )
	add_action( 'admin_head', 'cpdAddCSS', 100 );

// Autocounter laden, wenn in Optionen angegeben
if ( get_option('cpd_autocount') == 1 )	
	add_action('wp', 'cpd_autocount');
	
?>