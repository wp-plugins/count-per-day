<?php
/*
Plugin Name: Count Per Day
Plugin URI: http://www.tomsdimension.de/wp-plugins/count-per-day
Description: Counter, shows reads per page; today, yesterday, last week, last months ... on dashboard and widget.
Version: 2.5
License: GPL
Author: Tom Braider
Author URI: http://www.tomsdimension.de
*/

define('CPD_DEBUG', false);

/**
 * include GeoIP addon
 */
$cpd_path = ABSPATH.PLUGINDIR.'/'.dirname(plugin_basename(__FILE__));
if ( file_exists($cpd_path.'/geoip/geoip.php') )
	include_once($cpd_path.'/geoip/geoip.php');
$cpd_geoip = ( class_exists('CpdGeoIp') && file_exists($cpd_path.'/geoip/GeoIP.dat') ) ? 1 : 0;

/**
 * Count per Day
 */
class CountPerDay
{
	
var $options;	// options array
var $dir;		// this plugin dir
var $dbcon;		// database connection
var $queries = array();	// queries times for debug

/**
 * Constructor
 */
function CountPerDay()
{
	// variables
	global $table_prefix;
	define('CPD_C_TABLE', $table_prefix.'cpd_counter');
	define('CPD_CO_TABLE', $table_prefix.'cpd_counter_useronline');
	define('CPD_METABOX', 'cpd_metaboxes');
	
	$this->options = get_option('count_per_day');
	$this->dir = get_bloginfo('wpurl').'/'.PLUGINDIR.'/'.dirname(plugin_basename(__FILE__));
	$this->queries[0] = 0;

	// update online counter
	add_action('wp', array(&$this, 'deleteOnlineCounter'));
	
	// admin menu
	if ( is_admin() )
		add_action('admin_menu', array(&$this, 'menu'));
		
	// settings link on plugin page
	add_filter('plugin_action_links', array(&$this, 'pluginActions'), 10, 2);
	
	// auto counter
	if ( $this->options['autocount'] == 1 )	
		add_action('wp', array(&$this,'count'));

	// widget on dashboard page
	add_action('wp_dashboard_setup', array(&$this, 'dashboardWidgetSetup'));
	
	// CpD dashboard page
	add_filter('screen_layout_columns', array(&$this, 'screenLayoutColumns'), 10, 2);
	
	// register callback for admin menu  setup
	add_action('admin_menu', array(&$this, 'setAdminMenu')); 	
	
	// column page list
	add_action('manage_pages_custom_column', array(&$this, 'cpdColumnContent'), 10, 2);
	add_filter('manage_pages_columns', array(&$this, 'cpdColumn'));
	
	// column post list
	add_action('manage_posts_custom_column', array(&$this, 'cpdColumnContent'), 10, 2);
	add_filter('manage_posts_columns', array(&$this, 'cpdColumn'));
	
	// locale support
	if (defined('WPLANG') && function_exists('load_plugin_textdomain'))
		load_plugin_textdomain('cpd', '', dirname(plugin_basename(__FILE__)).'/locale');

	// adds stylesheet
	wp_enqueue_style('cpd_css', $this->dir.'/counter.css');
	
	// widget setup
	add_action('plugins_loaded', array(&$this, 'widgetCpdInit'));

	// activation hook
	register_activation_hook(__FILE__, array(&$this, 'createTables'));
	
	// uninstall hook
	if ( function_exists('register_uninstall_hook') )
		register_uninstall_hook(__FILE__, array(&$this, 'uninstall'));
	
	// query times debug
	if ( CPD_DEBUG )
	{
		add_action('wp_footer', array(&$this, 'showQueries'));
		add_action('admin_footer', array(&$this, 'showQueries'));
	}
	
	$this->connectDB();
}

/**
 * direct database connection without wordpress functions saves memory
 */
function connectDB()
{
	global $wpdb;
	$this->dbcon = @mysql_connect(DB_HOST, DB_USER, DB_PASSWORD, true);
	@mysql_select_db(DB_NAME, $this->dbcon);
	$this->getQuery("SET NAMES '".$wpdb->charset."'", 'SET NAMES');
}

/**
 * get results per own connection (shows time for debug)
 * @param string $sql SQL statement
 * @param string $func show this name before time
 * @return MySql result
 */
function getQuery( $sql, $func = '' )
{
	if ( CPD_DEBUG )
	{
		$t = microtime(true);
		$res = @mysql_query($sql, $this->dbcon);
		$d = number_format( microtime(true) - $t , 5);
	//	echo '<code>'.$func.' '.$d.'</code>';
		$this->queries[] = $func.' : <b>'.$d.'</b><br/><code>'.$sql.'</code>';
		$this->queries[0] += $d;
	}
	else
		$res = @mysql_query($sql, $this->dbcon);
	return $res;
}

/**
 * counts and shows visits
 *
 * @param string $before string before the number
 * @param string $after string after the number
 * @param boolean $show "echo" (true, standard) or "return"
 * @param boolean $count count visits (true, standard) or only show vistis
 * @return string counter string
 */
function show( $before='', $after=' reads', $show = true, $count = true )
{
	global $wpdb;
	// count once only
	if ( $count && !$this->options['autocount'] )
		$this->count();
	$page = get_the_ID();
	$res = $this->getQuery("SELECT count(*) FROM ".CPD_C_TABLE." WHERE page='$page'", 'show');
	$row = mysql_fetch_row($res);
	if ( $show )
		echo $before.$row[0].$after;
	else
		return $row[0];
}

/**
 * counts visits (without show)
 */
function count()
{
	global $wpdb, $wp_query, $cpd_path, $cpd_geoip;
	
	// find PostID
	if ( !is_404() ) :
		if ( $this->options['autocount'] == 1 && is_singular() )
		{
			// single page with autocount on
			// make loop before regular loop is defined
			if (have_posts()) :
				while ( have_posts() && $page == 0 ) :
					the_post();
					$page = get_the_ID();
				endwhile;
			endif;
			rewind_posts();
		}
		else if ( is_singular() )
			// single page with template tag show() or count()
			$page = get_the_ID();
			
		// "index" pages only with autocount	
		else if ( is_category() || is_tag() )
			// category or tag => negativ ID in CpD DB
			$page = 0 - $wp_query->get_queried_object_id();
		else
			// index, date, search and other "list" pages will count only once
			$page = 0;
	endif;
	$countUser = ( $this->options['user'] == 0 && is_user_logged_in() ) ? 0 : 1;
	
	// only count if: non bot, Logon is ok
	if ( !$this->isBot() && $countUser )
	{
		$userip = $_SERVER['REMOTE_ADDR'];
		$client = $_SERVER['HTTP_USER_AGENT'];
		$date = date('Y-m-d');
		
		// new visitor on page?
		$res = $this->getQuery("SELECT count(*) FROM ".CPD_C_TABLE." WHERE ip=INET_ATON('$userip') AND date='$date' AND page='$page'", 'count check');
		$row = mysql_fetch_row($res);
		if ( $row[0] == 0 )
		{
			// save count
			if ( $cpd_geoip )
			{
				// with GeoIP addon save country
				$gi = geoip_open($cpd_path.'/geoip/GeoIP.dat', GEOIP_STANDARD);
				$country = strtolower(geoip_country_code_by_addr($gi, $userip));
				$this->getQuery($wpdb->prepare("INSERT INTO ".CPD_C_TABLE." (page, ip, client, date, country)
				VALUES (%s, INET_ATON(%s), %s, %s, %s)", $page, $userip, $client, $date, $country), 'count insert');
			}
			else
				// without country
				$this->getQuery($wpdb->prepare("INSERT INTO ".CPD_C_TABLE." (page, ip, client, date)
				VALUES (%s, INET_ATON(%s), %s, %s)", $page, $userip, $client, $date), 'count insert');
		}
		
		// online counter
		$timestamp = time();
		$this->getQuery($wpdb->prepare("REPLACE INTO ".CPD_CO_TABLE." (timestamp, ip, page)
			VALUES ( %s, INET_ATON(%s), %s)", $timestamp, $userip, $page), 'count online');
	}
}

/**
 * deletes old online user 
 */
function deleteOnlineCounter()
{
	$timeout = time() - $this->options['onlinetime'];
	$this->getQuery("DELETE FROM ".CPD_CO_TABLE." WHERE timestamp < $timeout", 'deleteOnlineCounter');
}

/**
 * bot or human?
 * @param string $client USER_AGENT
 * @param array $bots strings to check
 */
function isBot( $client = '', $bots = '' )
{
	if ( empty($client) )
		$client = $_SERVER['HTTP_USER_AGENT'];

	// empty/short client -> not normal browser -> bot
	if ( empty($client) || strlen($client) < 20 )
		return true;
	
	if ( empty($bots) )
		$bots = explode( "\n", $this->options['bots'] );

	$isBot = false;
	foreach ( $bots as $bot )
	{
		$b = trim($bot);
		if ( !empty($b) && ( $_SERVER['REMOTE_ADDR'] == $b || strpos( strtolower($client), strtolower($b) ) !== false ) )
			$isBot = true;
	}
	return $isBot;
}

/**
 * creates tables if not exists
 */
function createTables()
{
	// for plugin activation, creates $wpdb
	require_once(ABSPATH.'wp-admin/includes/upgrade.php');
	global $wpdb;
	
	// table "counter"
	$sql = "CREATE TABLE IF NOT EXISTS `".CPD_C_TABLE."` (
	`id` int(10) NOT NULL auto_increment,
	`ip` int(10) unsigned NOT NULL,
	`client` varchar(100) NOT NULL,
	`date` date NOT NULL,
	`page` mediumint(9) NOT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_page` (`page`),
	KEY `idx_dateip` (`date`,`ip`) );";
	$this->getQuery($sql);

	// table "counter-online"
	$sql = "CREATE TABLE IF NOT EXISTS `".CPD_CO_TABLE."` (
	`timestamp` int(15) NOT NULL,
	`ip` int(10) UNSIGNED NOT NULL,
	`page` int(11) NOT NULL,
	PRIMARY KEY (`ip`) )";
	$this->getQuery($sql);
	
	// update fields in old table
	$field = $this->getQuery( "SHOW FIELDS FROM `".CPD_C_TABLE."` LIKE 'ip'" );
	$row = mysql_fetch_array($field);
	if ( strpos(strtolower($row['Type']), 'int') === false )
	{
		$queries = array (
		"ALTER TABLE `".CPD_C_TABLE."` ADD `ip2` INT(10) UNSIGNED NOT NULL AFTER `ip`",
		"UPDATE `".CPD_C_TABLE."` SET ip2 = INET_ATON(ip)",
		"ALTER TABLE `".CPD_C_TABLE."` DROP `ip`",
		"ALTER TABLE `".CPD_C_TABLE."` CHANGE `ip2` `ip` INT( 10 ) UNSIGNED NOT NULL",
		"ALTER TABLE `".CPD_C_TABLE."` CHANGE `date` `date` date NOT NULL",
		"ALTER TABLE `".CPD_C_TABLE."` CHANGE `page` `page` mediumint(9) NOT NULL");
		
		foreach ( $queries as $sql)
			$this->getQuery($sql, 'update old fields');
	}
	
	// make new keys
	$keys = $this->getQuery( "SHOW KEYS FROM `".CPD_C_TABLE."`" );
	$s = array();
	while ( $row = mysql_fetch_array($keys) )
		if ( $row['Key_name'] != 'PRIMARY' )
			$s[] = 'DROP INDEX `'.$row['Key_name'].'`';
	$s = array_unique($s);
		
	$sql = 'ALTER TABLE `'.CPD_C_TABLE.'` ';
	if ( sizeof($s) )
		$sql .= implode(',', $s).', ';
	$sql .= 'ADD KEY `idx_dateip` (`date`,`ip`), ADD KEY `idx_page` (`page`)';
	$this->getQuery($sql);
	
	// if GeoIP installed we need row "country"
	if ( class_exists('CpdGeoIp') )
	{
		$this->getQuery("SELECT country FROM `".CPD_C_TABLE."`");
		if ((int) mysql_errno() == 1054)
			$this->getQuery("ALTER TABLE `".CPD_C_TABLE."` ADD `country` CHAR(2) NOT NULL");
	}
	
	// update options to array
	$this->UpdateOptions();
}

/**
 * creates dashboard summary metabox content
 */
function dashboardReadsAtAll()
{
	?>
	<ul>
		<li><b style="float:right"><span><?php $this->getUserAll(); ?></span></b><?php _e('Total visitors', 'cpd') ?>:</li>
		<li><b style="float:right"><span><?php $this->getUserOnline(); ?></span></b><?php _e('Visitors currently online', 'cpd') ?>:</li>
		<li><b style="float:right"><?php $this->getUserToday(); ?></b><?php _e('Visitors today', 'cpd') ?>:</li>
		<li><b style="float:right"><?php $this->getUserYesterday(); ?></b><?php _e('Visitors yesterday', 'cpd') ?>:</li>
		<li><b style="float:right"><?php $this->getUserLastWeek(); ?></b><?php _e('Visitors last week', 'cpd') ?>:</li>
		<li><b style="float:right"><?php $this->getUserPerDay($this->options['dashboard_last_days']); ?></b>&Oslash; <?php _e('Visitors per day', 'cpd') ?>:</li>
		<li><b style="float:right"><?php $this->getFirstCount(); ?></b><?php _e('Counter starts on', 'cpd') ?>:</li>
	</ul>
	<?php
}

/**
 * creates dashboard chart metabox content - page visits
 * @param integer $limit days to show
 * @see dashboardChartDataRequest()
 */
function dashboardChart( $limit = 0 )
{
	if ( $limit == 0 )
		$limit = ( !empty($this->options['chart_days']) )? $this->options['chart_days'] : 30;
	
	$sql = "
	SELECT	count(*) count,
			date
	FROM	".CPD_C_TABLE."
	GROUP	BY date
	ORDER	BY date DESC
	LIMIT	$limit";
	$this->dashboardChartDataRequest($sql, $limit);
}

/**
 * creates dashboard chart metabox content - visitors
 * @param integer limit days to show
 * @see dashboardChartDataRequest()
 */
function dashboardChartVisitors( $limit = 0 )
{
	if ( $limit == 0 )
		$limit = ( !empty($this->options['chart_days']) )? $this->options['chart_days'] : 30;
	$sql = "
	SELECT count(*) count, date
	FROM (	SELECT	count(*) count, date
			FROM	".CPD_C_TABLE."
			GROUP	BY date, ip
			) AS t
	GROUP BY date
	ORDER BY date DESC
	LIMIT $limit";
	$this->dashboardChartDataRequest($sql, $limit);
}

/**
 * creates dashboard chart metabox content
 * @param string $sql SQL-Statement visitors or page visits
 */
function dashboardChartDataRequest( $sql = '', $limit )
{
	global $wp_locale;

	// get options
	$max_height = ( !empty($this->options['chart_height']) ) ? $this->options['chart_height'] : 200;
	
	$res = $this->getQuery($sql, 'Chart');
	
	if ( mysql_num_rows($res) == 0)
		return;
		
	$res_array = array();

	// find date end points
	while ( $day = mysql_fetch_assoc($res) )
	{
		$res_array[] = $day;
		if ( empty($end) )
			$end = $day['date'];
		$start = $day['date'];
	}
	
	$end_time = strtotime($end);
	$start_time = max( array($end_time - ($limit - 1) * 86400, strtotime($start)) );
	$days = max(1, ($end_time - $start_time) / 86400 + 1);
	$bar_width = round(100 / $days, 2); // per cent
	
	// find max count
	$max = 1;
	mysql_data_seek($res, 0);
	while ( $day = mysql_fetch_array($res) )
	{
		$date = strtotime($day['date']);
		if ( $date >= $start_time && $day['count'] > $max )
			$max = max(1, $day['count']);
	}
	
	$height_factor = $max_height / $max;
	
	// headline with max count
	echo '
		<small style="display:block; float:right;">'.$days.' '.__('days', 'cpd').'</small>
		<small style="display:block;">Max: '.$max.'</small>
		<p style="border-bottom:1px black solid; white-space:nowrap;">';
	
	$date_old = $start_time;
	
	// newest data will show right
	$res_array = array_reverse($res_array);
	foreach ( $res_array as $day )
	{
		$date = strtotime($day['date']);
		
		if ( $date >= $start_time )
		{
			// show the last $limit days only
			if ( $date - $date_old > 86400 )
			{
				// show space if no reads today
				$width = (($date - $date_old) / 86400 - 1) * $bar_width;
				echo '<img src="'.$this->getResource('cpd_trans.png').'" title="'.__('no reads at this time', 'cpd').'"
					style="width:'.$width.'%; height:'.$max_height.'px" />';
			}
	
			// show normal bar
			$height = max( round($day['count'] * $height_factor, 0), 1 );
			$date_str = mysql2date(get_option('date_format'), $day['date']);
			echo '<img src="'.$this->getResource('cpd_rot.png').'" title="'.$date_str.' : '.$day['count'].'"
				style="width:'.$bar_width.'%; height:'.$height.'px" />';
			
			$date_old = $date;
		}
	}
	
	// legend
	$end_str = mysql2date(get_option('date_format'), $end);
	$start_str = mysql2date(get_option('date_format'), $start);
	echo '</p>
		<div style="height: 10px">
			<small style="float:left">'.$start_str.'</small>
			<small style="float:right">'.$end_str.'</small>
		</div>';
}

// The following statistic functions you can use in your template too.
// use $count_per_day->getUserOnline()

/**
 * shows current visitors
 */
function getUserOnline()
{
	$res = $this->getQuery("SELECT count(*) FROM ".CPD_CO_TABLE, 'getUserOnline');
	$row = mysql_fetch_row($res);
	echo $row[0];
}

/**
 * shows all visitors
 */
function getUserAll()
{
	$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." GROUP BY date, ip", 'getUserAll');
	echo mysql_num_rows($res);
}

/**
 * shows today visitors
 */
function getUserToday()
{
	$date = date('Y-m-d');
	$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." WHERE date = '$date' GROUP BY ip", 'getUserToday');
	echo mysql_num_rows($res);
}

/**
 * shows yesterday visitors
 */
function getUserYesterday()
{
	$date = date('Y-m-d', time()-86400);
	$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." WHERE date = '$date' GROUP BY ip", 'getUserYesterday');
	echo mysql_num_rows($res);
}

/**
 * shows last week visitors (last 7 days)
 */
function getUserLastWeek()
{
	$date = date('Y-m-d', time()-86400*7);
	$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." WHERE date >= '$date' GROUP BY ip;", 'getUserLastWeek');
	echo mysql_num_rows($res);
}

/**
 * shows visitors per month
 */
function getUserPerMonth()
{
	$m = $this->getQuery("SELECT LEFT(date,7) FROM ".CPD_C_TABLE." GROUP BY year(date), month(date) ORDER BY date DESC", 'getUserPerMonths');
	echo '<ul>';
	while ( $row = mysql_fetch_row($m) )
	{
		$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." WHERE LEFT(date,7) = '".$row[0]."' GROUP BY date, ip", 'getUserPerMonth');
		echo '<li><b>'.mysql_num_rows($res).'</b> '.$row[0].'</li>'."\n";
	}
	echo '</ul>';
}

/**
 * shows visitors per post
 * @param integer $limit number of posts, -1 = all, 0 = get option from db, x = number
 */
function getUserPerPost( $limit = 0 )
{
	global $wpdb;
	if ( $limit == 0 )
		$limit = $this->options['dashboard_posts'];

	$sql = "
	SELECT	count(c.id) count,
			c.page post_id,
			p.post_title post,
			t.name tag_cat_name,
			t.slug tag_cat_slug,
			x.taxonomy tax
	FROM 	".CPD_C_TABLE." c
	LEFT	JOIN ".$wpdb->posts." p
			ON p.id = c.page
	LEFT	JOIN ".$wpdb->terms." t
			ON t.term_id = 0 - c.page
	LEFT	JOIN ".$wpdb->term_taxonomy." x
			ON x.term_id = t.term_id
	WHERE	c.page
	GROUP	BY c.page
	ORDER	BY count DESC";
	if ( $limit > 0 )
		$sql .= " LIMIT ".$limit;
	$this->getUserPer_SQL( $sql, 'getUserPerPost' );
}

/**
 * shows counter start, first day
 */
function getFirstCount()
{
	global $wp_locale;
	$res = $this->getQuery("SELECT date FROM ".CPD_C_TABLE." ORDER BY date LIMIT 1", 'getFirstCount');
	$row = mysql_fetch_row($res);
	echo mysql2date(get_option('date_format'), $row[0] );
}

/**
 * shows averaged visitors per day
 */
function getUserPerDay( $days = 0 )
{
	global $wpdb;
	$datemax = date('Y-m-d');
	if ( $days > 0 )
		// last $days days without today
		$datemin = date('Y-m-d', time() - ($days + 1) * 86400);
	else
	{ 
		$v = $wpdb->get_results('SELECT MIN(date) min, MAX(date) max FROM '.CPD_C_TABLE);
		foreach ($v as $row)
		{
			$min = strtotime($row->min);
			$max = strtotime($row->max);
			$days =  (($max - $min) / 86400 + 1);
			$datemin = 0;
		}
	}

	$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." WHERE date > '$datemin' AND date < '$datemax' GROUP BY ip, date", 'getUserPerDay');
	$count = @mysql_num_rows($res) / $days;
	
	echo '<abbr title="last '.$days.' days without today">';
	if ( $count < 5 )
		echo number_format($count, 2);
	else
		echo number_format($count, 0);
	echo '</abbr>';
}

/**
 * shows most visited pages in last days
 * @param integer $days days to calc (last days)
 * @param integer $limit count of posts (last posts)
 */
function getMostVisitedPosts( $days = 0, $limit = 0 )
{
	global $wpdb;
	if ( $days == 0 )
		$days = $this->options['dashboard_last_days'];
	if ( $limit == 0 )
		$limit = $this->options['dashboard_last_posts'];
	$date = date('Y-m-d', time() - 86400 * $days);

	$sql = "
	SELECT	count(c.id) count,
			c.page post_id,
			p.post_title post,
			t.name tag_cat_name,
			t.slug tag_cat_slug,
			x.taxonomy tax
	FROM	".CPD_C_TABLE." c
	LEFT	JOIN ".$wpdb->posts." p
			ON p.id = c.page
	LEFT	JOIN ".$wpdb->terms." t
			ON t.term_id = 0 - c.page
	LEFT	JOIN ".$wpdb->term_taxonomy." x
			ON x.term_id = t.term_id
	WHERE	c.date >= '$date'
	GROUP	BY c.page
	ORDER	BY count DESC
	LIMIT	$limit";
	echo '<small>'.sprintf(__('The %s most visited posts in last %s days:', 'cpd'), $limit, $days).'<br/>&nbsp;</small>';
	$this->getUserPer_SQL( $sql, 'getMostVisitedPosts' );		
}

/**
 * shows little browser statistics
 */
function getClients()
{
	global $wpdb;
	$clients = array('Firefox', 'MSIE', 'Chrome', 'AppleWebKit', 'Opera');
	
	$res = $this->getQuery("SELECT COUNT(*) count FROM ".CPD_C_TABLE, 'getClients_all');
	$row = mysql_fetch_row($res);
	$all = max(1, $row[0]);
	$rest = 100;
	echo '<ul>';
	foreach ($clients as $c)
	{
		$res = $this->getQuery("SELECT COUNT(*) count FROM ".CPD_C_TABLE." WHERE client like '%$c%'", 'getClients_'.$c);
		$row = mysql_fetch_row($res);
		$percent = number_format(100 * $row[0] / $all, 0);
		$rest -= $percent;
		echo '<li>'.$c.'<b>'.$percent.' %</b></li>';
	}
	if ( $rest > 0 )
		echo '<li>'.__('Other', 'cpd').'<b>'.$rest.' %</b></li>';
	echo '</ul>';
}

// end of statistic functions

/**
 * gets mass bots
 * @param int $limit only show IP if more than x page views per day
 */
function getMassBots( $limit = 0 )
{
	if ( $limit == 0 )
		return;
	$sql = "
	SELECT	t.id, INET_NTOA(t.ip) ip, t.date, t.posts,
			c.client
	FROM (	SELECT	id, ip, date, count(*) posts
			FROM	".CPD_C_TABLE."
			GROUP	BY ip, date
			ORDER	BY posts DESC ) AS t
	LEFT	JOIN ".CPD_C_TABLE." c
			ON c.id = t.id
	WHERE	posts > $limit";
	return $this->getQuery($sql, 'getMassBots');
}

/**
 * creates counter lists
 * @param string $sql SQL Statement
 * @param string $name function name for debug
 */
function getUserPer_SQL( $sql, $name = '' )
{
	$m = $this->getQuery($sql, $name);
	echo '<ul>';
	while ( $row = mysql_fetch_assoc($m) )
	{
		echo '<li><b>'.$row['count'].'</b> <a href="'.get_bloginfo('url');
		if ( $row['post_id'] < 0 && $row['tax'] == 'category' )
			//category
			echo '?cat='.(0 - $row['post_id']).'">- '.$row['tag_cat_name'].' -';
		else if ( $row['post_id'] < 0 )
			// tag
			echo '?tag='.$row['tag_cat_slug'].'">- '.$row['tag_cat_name'].' -';
		else if ( $row['post_id'] == 0 )
			// homepage
			echo '">- '.__('Front page displays').' -';
		else
		{
			// post/page
//			$postname = $wpdb->get_var('SELECT post_title FROM '.$wpdb->posts.' WHERE ID = '.$row->post_id);
			$postname = $row['post'];
			if ( empty($postname) ) 
				$postname = '---';
			echo '?p='.$row['post_id'].'">'.$postname;
		}
		echo "</a></li>\n";
	}
	echo '</ul>';
}

/**
 * deletes spam in table, if you add new bot pattern you can clean the db
 */
function cleanDB()
{
	global $wpdb;
	
	// get trimed bot array
	function trim_value(&$value) { $value = trim($value); }
	$bots = explode( "\n", $this->options['bots'] );
	array_walk($bots, 'trim_value');
	
	$rows_before = $wpdb->get_var('SELECT COUNT(*) FROM '.CPD_C_TABLE);

	// delete by ip
	$ips = "'".implode( "','", $bots )."'";
	$this->getQuery('DELETE FROM '.CPD_C_TABLE.' WHERE ip IN ('.$ips.')', 'clenaDB_ip');

	// delete by client
	foreach ($bots as $bot)
		$this->getQuery("DELETE FROM ".CPD_C_TABLE." WHERE client LIKE '%$bot%'", 'cleanDB_client');
	
	// delete if a previously countered page was deleted
//	$posts = $wpdb->get_results('SELECT id FROM '.$wpdb->posts);
//	$pages = '-1';
//	foreach ($posts as $post)
//		$pages .= ','.$post->id;
//	@mysql_query("DELETE FROM ".CPD_C_TABLE." WHERE page NOT IN ($pages)", $this->dbcon);
	$this->getQuery("DELETE FROM ".CPD_C_TABLE." WHERE page NOT IN ( SELECT id FROM ".$wpdb->posts.")", 'cleanDB_delPosts');
	
	$rows_after = $wpdb->get_var('SELECT COUNT(*) FROM '.CPD_C_TABLE);
	return $rows_before - $rows_after;
}

/**
 * adds menu entry to backend
 * @param string $content WP-"Content"
 */
function menu($content)
{
	if (function_exists('add_options_page'))
	{
		$menutitle = '<img src="'.$this->getResource('cpd_menu.gif').'" alt="" /> Count per Day';
		add_options_page('CountPerDay', $menutitle, 'manage_options', dirname(plugin_basename(__FILE__)).'/counter-options.php') ;
	}
}
	
/**
 * adds an "settings" link to the plugins page
 */
function pluginActions($links, $file)
{
	if( $file == plugin_basename(__FILE__) )
	{
		$link = '<a href="options-general.php?page='.dirname(plugin_basename(__FILE__)).'/counter-options.php">'.__('Settings').'</a>';
		array_unshift( $links, $link );
	}
	return $links;
}

/**
 * creates the little widget on dashboard
 */
function dashboardWidget()
{
	echo '<a href="?page=cpd_metaboxes"><b>';
	$this->getUserAll();
	echo '</b></a> '.__('Total visitors', 'cpd').' - <b>';
	$this->getUserPerDay($this->options['dashboard_last_days']);
	echo '</b> '.__('Visitors per day', 'cpd');
}

/**
 * adds widget to dashboard page
 */
function dashboardWidgetSetup()
{
	wp_add_dashboard_widget( 'cpdDashboardWidget', 'Count per Day', array(&$this,'dashboardWidget') );
}

/**
 * combines the options to one array, update from previous versions
 */
function updateOptions()
{
	$options = get_option('count_per_day', '');
	if ( empty($options) )
	{
		$onlinetime = get_option('cpd_onlinetime', 300);
		$user = get_option('cpd_user', 0);
		$autocount = get_option('cpd_autocount', 1);
		$bots = get_option('cpd_bots', "bot\nspider\nsearch\ncrawler\nask.com\nvalidator\nsnoopy\nsuchen.de\nsuchbaer.de\nshelob\nsemager\nxenu\nsuch_de\nia_archiver\nMicrosoft URL Control\nnetluchs");
		
		$o = array(
		'onlinetime' => $onlinetime,
		'user' => $user,
		'autocount' => $autocount,
		'bots' => $bots,
		'dashboard_posts' => 20,
		'dashboard_last_posts' => 20,
		'dashboard_last_days' => 7,
		'widget_title' => 'Count per Day',
		'widget_functions' => '',
		'show_in_lists' => 1,
		'chart_days' => 60,
		'chart_height' => 100,
		'countries' => 20);
		
		// add array
		add_option('count_per_day', $o);
		
		// delete all old options
		delete_option('cpd_cdb_version');
		delete_option('cpd_codb_version');
		delete_option('cpd_onlinetime');
		delete_option('cpd_user');
		delete_option('cpd_autocount');
		delete_option('cpd_bots');
	}
}

/**
 * add counter column to page/post lists
 */
function cpdColumn($defaults)
{
	if ( $this->options['show_in_lists']  )
		$defaults['cpd_reads'] = '<img src="'.$this->GetResource('cpd_menu.gif').'" alt="'.__('Reads', 'cpd').'" title="'.__('Reads', 'cpd').'" />';
	return $defaults;
}

/**
 * adds content to the counter column
 */
function cpdColumnContent($column_name, $id = 0)
{
	global $wpdb;
	if( $column_name == 'cpd_reads' )
    {
    	$res = $this->getQuery("SELECT COUNT(*) FROM ".CPD_C_TABLE." WHERE page='$id'", 'cpdColumn_'.$id);
    	$row = mysql_fetch_row($res);
		echo (int) $row[0];
    }
}

/**
 * uninstall functions, deletes tables and options
 */
function uninstall()
{
	global $wpdb;
	$wpdb->query('DROP TABLE IF EXISTS '.CPD_C_TABLE);
	$wpdb->query('DROP TABLE IF EXISTS '.CPD_CO_TABLE);
	delete_option('count_per_day');
}

/**
 * gets image recource with given name
 */
function getResource( $r )
{
	return trailingslashit( $this->dir ).$r;
}

/**
 * creates sidebar widget
 */
function widgetCpdInit()
{
	if (! function_exists('register_sidebar_widget'))
		return;
	
	function widgetCpd($args)
	{
		global $count_per_day;
		extract($args);
		if ( !empty($count_per_day->options['widget_functions']) )
		{
			// show widget only if functions are defined
			$title = (!empty($count_per_day->options['widget_title'])) ? $count_per_day->options['widget_title'] : 'Count per Day';
			echo $before_widget;
			echo $before_title.$title.$after_title;
			echo '<ul class="cpd">';
			foreach ( $count_per_day->options['widget_functions'] as $f )
			{
				$s = explode('|', $f);
				if ( ($s[0] == 'show' && is_singular()) || $s[0] != 'show' )
				{
					$name = (!empty($count_per_day->options['name_'.$s[0]])) ? $count_per_day->options['name_'.$s[0]] : __($s[1], 'cpd');
					echo '<li><span style="float:right">';
					
					// parameters only for special functions
					if ( $s[0] == 'getUserPerDay' )
						eval('echo $count_per_day->getUserPerDay('.$count_per_day->options['dashboard_last_days'].');');
					else if ( $s[0] == 'show' )
						eval('echo $count_per_day->show("","",false,false);');
					else
						eval('echo $count_per_day->'.$s[0].'();');
						
					echo '</span>'.$name.':</li>';
				}
			}
			echo '</ul>';
			echo $after_widget;
			
			// to find this text for translation
			__('This post', 'cpd');
		}
	}
	register_sidebar_widget('Count per Day', 'widgetCpd');
	
	function widgetCpdControl()
	{
		global $count_per_day;

		// show the possible functions
		$funcs = array(
		'show' => 'This post',
		'getUserToday' => 'Visitors today',
		'getUserYesterday' => 'Visitors yesterday',
		'getUserLastWeek' => 'Visitors last week',
		'getUserPerDay' => 'Visitors per day',
		'getUserAll' => 'Total visitors',
		'getUserOnline' => 'Visitors currently online',
		'getFirstCount' => 'Counter starts on',
		);

		if ( !empty($_POST['widget_cpd_title']) )
		{
			$count_per_day->options['widget_title'] = stripslashes($_POST['widget_cpd_title']);
			$count_per_day->options['widget_functions'] = $_POST['widget_cpd_functions'];
			// custom names
			foreach ( $funcs as $k=>$v )
				$count_per_day->options['name_'.$k] = stripslashes($_POST['name_'.$k]);
			update_option('count_per_day', $count_per_day->options);
		}
		
		$title = (!empty($count_per_day->options['widget_title'])) ? $count_per_day->options['widget_title'] : 'Count per Day';
		echo '<p><label for="widget_cpd_title">'.__('Title:').' <input style="width: 150px;" id="widget_cpd_title" name="widget_cpd_title" type="text" value="'.$title.'" /></label></p>'."\n";

		foreach ( $funcs as $k=>$v )
		{
			echo '<p><label for="widget_cpd_'.$k.'"><input type="checkbox" id="widget_cpd_'.$k.'" name="widget_cpd_functions[]"
				value="'.$k.'|'.$v.'" ';
			if ( !empty($count_per_day->options['widget_functions']) && in_array($k.'|'.$v, $count_per_day->options['widget_functions']) )
				echo 'checked="checked"';
			echo '/> '.__($v, 'cpd').'</label><br />'."\n";
			// custom names
			$name = (isset($count_per_day->options['name_'.$k])) ? $count_per_day->options['name_'.$k] : '';
			echo '&nbsp; &nbsp; &nbsp;'.__('Label', 'cpd').': <input name="name_'.$k.'" value="'.$name.'" type="text" title="'.__('empty = name above', 'cpd').'" /></p>';
		}
	}
	register_widget_control('Count per Day', 'widgetCpdControl');
}

/**
 * sets columns on dashboard page
 */ 
function screenLayoutColumns($columns, $screen)
{
	if ($screen == $this->pagehook)
		$columns[$this->pagehook] = 4;
	return $columns;
}

/**
 * extends the admin menu 
 */
function setAdminMenu()
{
	$menutitle = '<img src="'.$this->getResource('cpd_menu.gif').'" alt="" /> Count per Day';
	$this->pagehook = add_submenu_page('index.php', 'CountPerDay', $menutitle, 1, CPD_METABOX, array(&$this, 'onShowPage'));
	add_action('load-'.$this->pagehook, array(&$this, 'onLoadPage'));
}

/**
 * function calls from metabox default parameters
 */
function getMostVisitedPostsMeta() { $this->getMostVisitedPosts(); }
function getUserPerPostMeta() { $this->getUserPerPost(); }

/**
 * will be executed if wordpress core detects this page has to be rendered
 */
function onLoadPage()
{
	global $cpd_geoip;
	// needed javascripts
	wp_enqueue_script('common');
	wp_enqueue_script('wp-lists');
	wp_enqueue_script('postbox');

	// add the metaboxes
	add_meta_box('reads_at_all', __('Total visitors', 'cpd'), array(&$this, 'dashboardReadsAtAll'), $this->pagehook, 'cpdrow1', 'core');
	add_meta_box('chart_visitors', __('Visitors per day', 'cpd'), array(&$this, 'dashboardChartVisitors'), $this->pagehook, 'cpdrow1', 'core');
	add_meta_box('chart_reads', __('Reads per day', 'cpd'), array(&$this, 'dashboardChart'), $this->pagehook, 'cpdrow1', 'core');
	add_meta_box('reads_per_month', __('Visitors per month', 'cpd'), array(&$this, 'getUserPerMonth'), $this->pagehook, 'cpdrow2', 'core');
	add_meta_box('browsers', __('Browsers', 'cpd'), array(&$this, 'getClients'), $this->pagehook, 'cpdrow2', 'core');
	add_meta_box('reads_per_post', __('Visitors per post', 'cpd'), array(&$this, 'getUserPerPostMeta'), $this->pagehook, 'cpdrow3', 'core');
	add_meta_box('last_reads', __('Latest Counts', 'cpd'), array(&$this, 'getMostVisitedPostsMeta'), $this->pagehook, 'cpdrow4', 'core');
	
	// countries with GeoIP addon only
	if ( $cpd_geoip )
		add_meta_box('countries', __('Reads per Country', 'cpd'), array(&$this, 'getCountries'), $this->pagehook, 'cpdrow2', 'core');
}

/**
 * creates dashboard page
 */
function onShowPage()
{
	global $screen_layout_columns, $count_per_day;
	if ( empty($screen_layout_columns) )
		$screen_layout_columns = 4;
	$data = '';
	?>
	<div id="cpd-metaboxes" class="wrap">
		<h2><img src="<?php echo $this->getResource('cpd_menu.gif') ?>" alt="" style="width:24px;height:24px" /> Count per Day - <?php _e('Statistics', 'cpd') ?></h2>
		<?php
		wp_nonce_field('cpd-metaboxes');
		wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false );
		wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false );
		$css = 'style="width:'.round(98 / $screen_layout_columns, 1).'%;"';
		?>
		<div id="dashboard-widgets" class="metabox-holder cpd-dashboard">
			<div class="postbox-container" <?php echo $css; ?>><?php do_meta_boxes($this->pagehook, 'cpdrow1', $data); ?></div>
			<div class="postbox-container" <?php echo $css; ?>><?php do_meta_boxes($this->pagehook, 'cpdrow2', $data); ?></div>
			<div class="postbox-container" <?php echo $css; ?>><?php do_meta_boxes($this->pagehook, 'cpdrow3', $data); ?></div>
			<div class="postbox-container" <?php echo $css; ?>><?php do_meta_boxes($this->pagehook, 'cpdrow4', $data); ?></div>
			<br class="clear"/>
		</div>	
	</div>
	<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function($) {
			$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
			postboxes.add_postbox_toggles('<?php echo $this->pagehook; ?>');
		});
		//]]>
	</script>
	<?php
}

/**
 * gets country flags and page views
 * @param integer $limit count of countries
 */
function getCountries( $limit = 0 )
{
	global $cpd_path;
	global $cpd_geoip;

	// with GeoIP addon only
	if ( $cpd_geoip )
	{
		$gi = geoip_open($cpd_path.'/geoip/GeoIP.dat', GEOIP_STANDARD);
		$geoip = new GeoIP();
		if ( $limit == 0 )
			$limit = max( 0, $this->options['countries'] );

		$res = $this->getQuery("SELECT country, COUNT(*) c FROM ".CPD_C_TABLE." WHERE ip > 0 GROUP BY country ORDER BY COUNT(*) DESC LIMIT $limit", 'getCountries');
		
		echo '<ul>';
		while ( $r = mysql_fetch_array($res) )
		{
			$id = $geoip->GEOIP_COUNTRY_CODE_TO_NUMBER[strtoupper($r['country'])];
			$name = $geoip->GEOIP_COUNTRY_NAMES[$id];
			echo '<li><b>'.$r['c'].'</b>
			<img src="http://www.easywhois.com/images/flags/'.$r['country'].'.gif" alt="'.$r['country'].'" /> '
			.$name.'&nbsp;</li>'."\n";
		}
		echo '</ul>';
	}
}

/**
 * shows time of queries
 */
function showQueries()
{
	echo '<div style="margin:10px; padding-left:30px; border:1px red solid">
		<b>Count per Day - Queries: '.$this->queries[0].' s</b><ol>';
	foreach($this->queries as $q)
		if ($q != $this->queries[0] )
			echo '<li>'.$q.'</li>';
	echo '</ol></div>';
}

} // class end

$count_per_day = new CountPerDay();
//$count_per_day->createTables()
?>