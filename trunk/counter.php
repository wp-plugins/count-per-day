<?php
/*
Plugin Name: Count Per Day
Plugin URI: http://www.tomsdimension.de/wp-plugins/count-per-day
Description: Counter, shows reads per page; today, yesterday, last week, last months ... on dashboard and widget.
Version: 2.4.2
License: GPL
Author: Tom Braider
Author URI: http://www.tomsdimension.de
*/

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

	// creates image recources
	$this->setRecources();
	
	// adds stylesheet
	wp_enqueue_style('cpd_css', $this->dir.'/counter.css');
	
	// widget setup
	add_action('plugins_loaded', array(&$this, 'widgetCpdInit'));

	// activation hook
	register_activation_hook(__FILE__, array(&$this, 'createTables'));
	
	// uninstall hook
	if ( function_exists('register_uninstall_hook') )
		register_uninstall_hook(__FILE__, array(&$this, 'uninstall'));
	
	$this->connect_db();
}

/**
 * direct database connection without wordpress functions saves memory
 */
function connect_db()
{
	$this->dbcon = @mysql_connect(DB_HOST, DB_USER, DB_PASSWORD, true);
	@mysql_select_db(DB_NAME, $this->dbcon);
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
	$v = $wpdb->get_var("SELECT count(*) FROM ".CPD_C_TABLE." WHERE page='$page';");
	
	if ( $show )
		echo $before.$v.$after;
	else
		return $v;
}

/**
 * counts visits (without show)
 */
function count()
{
	global $wpdb;
	global $cpd_path;
	global $cpd_geoip;
	global $wp_query;
	
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
		$date = date('ymd');
		
		// save count
		$v = $wpdb->get_var("SELECT count(*) FROM ".CPD_C_TABLE." WHERE ip='$userip' AND date='$date' AND page='$page';");
		if ( $v == 0 )
		{
			if ( $cpd_geoip )
			{
				// with GeoIP addon save country
				$gi = geoip_open($cpd_path.'/geoip/GeoIP.dat', GEOIP_STANDARD);
				$country = strtolower(geoip_country_code_by_addr($gi, $userip));
				$wpdb->query($wpdb->prepare("INSERT INTO ".CPD_C_TABLE." (page, ip, client, date, country)
				VALUES (%s, %s, %s, %s, %s)", $page, $userip, $client, $date, $country));

			}
			else
				// without country
				$wpdb->query($wpdb->prepare("INSERT INTO ".CPD_C_TABLE." (page, ip, client, date)
				VALUES (%s, %s, %s, %s)", $page, $userip, $client, $date));
		}
		
		// online counter
		$timestamp = time();
		$wpdb->query($wpdb->prepare("REPLACE INTO ".CPD_CO_TABLE." (timestamp, ip, page)
			VALUES ( %s, %s, %s)", $timestamp, $userip, $page));
	}
}

/**
 * deletes old online user 
 */
function deleteOnlineCounter()
{
	$timeout = time() - $this->options['onlinetime'];
	@mysql_query("DELETE FROM ".CPD_CO_TABLE." WHERE timestamp < $timeout", $this->dbcon);
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
	
	if ( $wpdb->get_var( "SHOW TABLES LIKE `".CPD_C_TABLE."`" ) != CPD_C_TABLE )
	{
		// table "counter" is not exists
		$sql = "CREATE TABLE IF NOT EXISTS `".CPD_C_TABLE."` (
		`id` int(10) NOT NULL auto_increment,
		`ip` varchar(15) NOT NULL,
		`client` varchar(100) NOT NULL,
		`date` char(6) NOT NULL,
		`page` int(11) NOT NULL,
		PRIMARY KEY  (`id`),
		KEY `idx_ip` (`ip`(3)),
		KEY `idx_date` (`date`),
		KEY `idx_page` (`page`)	);";
		dbDelta($sql);
	}
	
	if ( $wpdb->get_var( "SHOW TABLES LIKE `".CPD_CO_TABLE."`" ) != CPD_CO_TABLE )
	{
		// table "counter-online" is not exists
		$sql = "CREATE TABLE IF NOT EXISTS `".CPD_CO_TABLE."` (
		`timestamp` int(15) NOT NULL default '0',
		`ip` varchar(15) NOT NULL default '',
		`page` int(11) NOT NULL default '0',
		PRIMARY KEY  (`ip`) );";
		dbDelta($sql);
	}
	
	// make new keys if needed
	$keys = $wpdb->query( "SHOW KEYS FROM `".CPD_C_TABLE."`" );
	if ( sizeof($keys) == 1 )
	{
		$sql = "ALTER TABLE `".CPD_C_TABLE."`
		ADD KEY `idx_ip` (`ip`(3)),
		ADD KEY `idx_date` (`date`),
		ADD KEY `idx_page` (`page`)";
		$wpdb->query($sql);
	}
	
	// if GeoIP installed we need row "country"
	if ( class_exists('CpdGeoIp') )
	{
		@mysql_query("SELECT country FROM `".CPD_C_TABLE."`", $this->dbcon);
		if ((int) mysql_errno() == 1054)
			mysql_query("ALTER TABLE `".CPD_C_TABLE."` ADD `country` CHAR(2) NOT NULL", $this->dbcon);
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
	// get options
	if ( $limit == 0 )
		$limit = ( !empty($this->options['chart_days']) )? $this->options['chart_days'] : 30;
	
	$sql = "
	SELECT	count(*) as count,
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
	// get options
	if ( $limit == 0 )
		$limit = ( !empty($this->options['chart_days']) )? $this->options['chart_days'] : 30;
	
	$sql = "
	SELECT count(*) count, date
	FROM (	SELECT	count(*) AS count, date, ip
			FROM	".CPD_C_TABLE."
			GROUP	BY ip, date
			) AS temp
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
	global $wpdb, $wp_locale;

	// get options
	$max_height = ( !empty($this->options['chart_height']) ) ? $this->options['chart_height'] : 200;
	
	$res = $wpdb->get_results($sql);

	// find date end points
	foreach ( $res as $day )
	{
		if ( empty($end) )
			$end = $day->date;
		$start = $day->date;
	}
	
	$end_time = strtotime("20$end");
	$start_time = max( array($end_time - ($limit - 1) * 86400, strtotime("20$start")) );
	$days = max(1, ($end_time - $start_time) / 86400 + 1);
	$bar_width = round(100 / $days, 2); // per cent
	
	// find max count
	$max = 1;
	foreach ( $res as $day )
	{
		$date = strtotime('20'.$day->date);
		if ( $date >= $start_time && $day->count > $max )
			$max = max(1, $day->count);
	}

	$height_factor = $max_height / $max;
	
	// headline with max count
	echo '<small style="display:block;">Max: '.$max.'</small>
		<p style="border-bottom:1px black solid; white-space:nowrap;">';
	
	$date_old = $start_time;
	
	// newest data will show right
	$res = array_reverse($res);
	
	foreach ( $res as $day )
	{
		$date = strtotime('20'.$day->date);
		
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
			$height = max( round($day->count * $height_factor, 0), 1 );
			$date_str = date('j. ', $date).$wp_locale->get_month(date('m', $date)).date(' Y', $date);
			echo '<img src="'.$this->getResource('cpd_rot.png').'" title="'.$date_str.' : '.$day->count.'"
				style="width:'.$bar_width.'%; height:'.$height.'px" />';
			
			$date_old = $date;
		}
	}
	
	// legend
	$end_str = date('j. ', $end_time).$wp_locale->get_month(date('m', $end_time)).date(' Y', $end_time);
	$start_str = date('j. ', $start_time).$wp_locale->get_month(date('m', $start_time)).date(' Y', $start_time);
	echo '</p>
		<p style="text-align:center">
			<small>'.$days.' '.__('days', 'cpd').'</small>
			<small style="float:left">'.$start_str.'</small>
			<small style="float:right">'.$end_str.'</small>
		</p>';
}

// The following statistic functions you can use in your template too.
// use $count_per_day->getUserOnline()

/**
 * shows current visitors
 */
function getUserOnline()
{
	global $wpdb;
	$v = $wpdb->get_var("SELECT count(page) FROM ".CPD_CO_TABLE.";");
	echo $v;
}

/**
 * shows all visitors
 */
function getUserAll()
{
	$res = mysql_query("SELECT 1 FROM ".CPD_C_TABLE." GROUP BY ip, date;", $this->dbcon);
	echo mysql_num_rows($res);
}

/**
 * shows today visitors
 */
function getUserToday()
{
	$date = date('ymd',time());
	$res = mysql_query("SELECT 1 FROM ".CPD_C_TABLE." WHERE date = '$date' GROUP BY ip;", $this->dbcon);
	echo mysql_num_rows($res);
}

/**
 * shows yesterday visitors
 */
function getUserYesterday()
{
	$date = date('ymd', time()-86400);
	$res = mysql_query("SELECT 1 FROM ".CPD_C_TABLE." WHERE date = '$date' GROUP BY ip;", $this->dbcon);
	echo mysql_num_rows($res);
}

/**
 * shows last week visitors (last 7 days)
 */
function getUserLastWeek()
{
	$date = date('ymd', time()-86400*7);
	$res = mysql_query("SELECT 1 FROM ".CPD_C_TABLE." WHERE date >= '$date' GROUP BY ip;", $this->dbcon);
	echo mysql_num_rows($res);
}

/**
 * shows visitors per month
 */
function getUserPerMonth()
{
	global $wpdb;
	$m = $wpdb->get_results("SELECT left(date,4) as month FROM ".CPD_C_TABLE." GROUP BY left(date,4) ORDER BY date desc");
	echo '<ul>';
	foreach ( $m as $row )
	{
		$res = mysql_query("SELECT page FROM ".CPD_C_TABLE." WHERE left(date,4) = ".$row->month." GROUP BY ip, date;", $this->dbcon);
		echo '<li><b>'.mysql_num_rows($res).'</b> 20'.substr($row->month,0,2).'/'.substr($row->month,2,2).'</li>'."\n";
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
	SELECT	count(c.id) as count,
			p.post_title as post,
			c.page as post_id,
			t.name as tag_cat_name,
			t.slug as tag_cat_slug,
			x.taxonomy as tax
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
	$this->getUserPer_SQL( $sql );
}

/**
 * shows counter start, first day
 */
function getFirstCount()
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
function getUserPerDay( $days = 0 )
{
	global $wpdb;
	$datemax = date('ymd', time());
	if ( $days > 0 )
		// last $days days without today
		$datemin = date('ymd', time() - ($days + 1) * 86400);
	else
	{ 
		$v = $wpdb->get_results('SELECT MIN(date) as min, MAX(date) as max FROM '.CPD_C_TABLE);
		foreach ($v as $row)
		{
			$min = strtotime('20'.substr($row->min,0,2).'-'.substr($row->min,2,2).'-'.substr($row->min,4,2) );
			$max = strtotime('20'.substr($row->max,0,2).'-'.substr($row->max,2,2).'-'.substr($row->max,4,2) );
			$days =  (($max - $min) / 86400 + 1);
			$datemin = 0;
		}
	}
	$res = @mysql_query('SELECT 1 FROM '.CPD_C_TABLE.' WHERE date > '.$datemin.' AND date < '.$datemax.' GROUP BY ip,date', $this->dbcon);
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
	$date = date('ymd', time() - 86400 * $days);

	$sql = "
	SELECT	count(c.id) as count,
			p.post_title as post,
			c.page as post_id,
			t.name as tag_cat_name,
			t.slug as tag_cat_slug,
			x.taxonomy as tax
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
	$this->getUserPer_SQL( $sql );		
}

/**
 * shows little browser statistics
 */
function getClients()
{
	global $wpdb;
	$clients = array('Firefox', 'MSIE', 'Chrome', 'AppleWebKit', 'Opera');
	
	$all = max(1, $wpdb->get_var("SELECT COUNT(*) as count FROM ".CPD_C_TABLE));
	$rest = 100;
	echo '<ul>';
	foreach ($clients as $c)
	{
		$count = $wpdb->get_var("SELECT COUNT(*) as count FROM ".CPD_C_TABLE." WHERE client like '%$c%'");
		$percent = number_format(100 * $count / $all, 0);
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
	global $wpdb;
	$sql = "
	SELECT * FROM (
		SELECT	ip, date, client, count( * ) AS posts
		FROM	".CPD_C_TABLE."
		GROUP	BY ip, date
		ORDER	BY posts DESC ) AS x
	WHERE posts > $limit";
	return $wpdb->get_results($sql);
}

/**
 * creates counter lists
 * @param string $sql SQL Statement
 */
function getUserPer_SQL( $sql )
{
	global $wpdb;
	$m = $wpdb->get_results($sql);
	echo '<ul>';
	foreach ( $m as $row )
	{
		$postname = ( !empty($row->post) ) ? $row->post : '---';
		echo '<li><b>'.$row->count.'</b> <a href="'.get_bloginfo('url');
		if ( $row->post_id < 0 && $row->tax == 'category' )
			//category
			echo '?cat='.(0 - $row->post_id).'">- '.$row->tag_cat_name.' -';
		else if ( $row->post_id < 0 )
			// tag
			echo '?tag='.$row->tag_cat_slug.'">- '.$row->tag_cat_name.' -';
		else if ( $row->post_id == 0 )
			// homepage
			echo '">- '.__('Front page displays').' -';
		else
			// post/page
			echo '?p='.$row->post_id.'">'.$postname;
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
	@mysql_query('DELETE FROM '.CPD_C_TABLE.' WHERE ip IN ('.$ips.')', $this->dbcon);

	// delete by client
	foreach ($bots as $bot)
		@mysql_query("DELETE FROM ".CPD_C_TABLE." WHERE client LIKE '%".$bot."%'", $this->dbcon);
	
	// delete if a previously countered page was deleted
	$posts = $wpdb->get_results('SELECT id FROM '.$wpdb->posts);
	$pages = '-1';
	foreach ($posts as $post)
		$pages .= ','.$post->id;
	@mysql_query("DELETE FROM ".CPD_C_TABLE." WHERE page NOT IN ($pages)", $this->dbcon);
	
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
    	$reads = $wpdb->get_var("SELECT COUNT(*) FROM ".CPD_C_TABLE." WHERE page='$id';");
		echo (int) $reads;
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
 * defines base64 encoded image recources
 */
function setRecources()
{
	if ( isset($_GET['resource']) && !empty($_GET['resource']) )
	{
		# base64 encoding
		$resources = array(
		'cpd_menu.gif' => 'R0lGODlhDAAMAJECAP8AAAAAAP///wAAACH5BAEAAAIALAAAAAAMAAwAAAIdjI4ppsqNngA0PYDwZDrjUEGLGJGHBKFNwLYuWwAAOw==',
		'cpd_rot.png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAAsTAAALEwEAmpwYAAAADElEQVR42mP8z8AAAAMFAQHa4YgFAAAAAElFTkSuQmCC',
		'cpd_trans.png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAC0lEQVR42mNkAAIAAAoAAv/lxKUAAAAASUVORK5CYII=');
			 
		if ( array_key_exists($_GET['resource'], $resources) )
		{
			$content = base64_decode($resources[ $_GET['resource'] ]);
			$lastMod = filemtime(__FILE__);
			$client = ( isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false );
			if (isset($client) && (strtotime($client) == $lastMod))
			{
				header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastMod).' GMT', true, 304);
				exit;
			}
			else
			{
				header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastMod).' GMT', true, 200);
				header('Content-Length: '.strlen($content));
				header('Content-Type: image/' . substr(strrchr($_GET['resource'], '.'), 1) );
				echo $content;
				exit;
			}
		}
	}
}

/**
 * gets image recource with given name
 */
function getResource( $resourceID ) {
	return trailingslashit( get_bloginfo('url') ).'?resource='.$resourceID;
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
	$menutitle = '<img src="'.$this->GetResource('cpd_menu.gif').'" alt="" /> Count per Day';
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
	add_meta_box('chart', __('Reads per day', 'cpd'), array(&$this, 'dashboardChart'), $this->pagehook, 'cpdrow1', 'core');
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
		$cpd_style = 'style="width:'.round(98 / $screen_layout_columns, 1).'%;"';
		?>
		<div id="dashboard-widgets" class="metabox-holder cpd-dashboard">
			<div class="postbox-container" <?php echo $cpd_style; ?>><?php do_meta_boxes($this->pagehook, 'cpdrow1', $data); ?></div>
			<div class="postbox-container" <?php echo $cpd_style; ?>><?php do_meta_boxes($this->pagehook, 'cpdrow2', $data); ?></div>
			<div class="postbox-container" <?php echo $cpd_style; ?>><?php do_meta_boxes($this->pagehook, 'cpdrow3', $data); ?></div>
			<div class="postbox-container" <?php echo $cpd_style; ?>><?php do_meta_boxes($this->pagehook, 'cpdrow4', $data); ?></div>
			<br class="clear"/>
		</div>	
	</div>
	<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function($) {
			// close postboxes that should be closed
			$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
			// postboxes setup
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

		$res = mysql_query("SELECT country, COUNT(*) AS c FROM ".CPD_C_TABLE." WHERE IP > '' GROUP BY country ORDER BY COUNT(*) DESC LIMIT $limit;", $this->dbcon);
		
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

} // class end

$count_per_day = new CountPerDay();
?>