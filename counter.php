<?php
/*
Plugin Name: Count Per Day
Plugin URI: http://www.tomsdimension.de/wp-plugins/count-per-day
Description: Counter, shows reads per page; today, yesterday, last week, last months ... on dashboard and widget.
Version: 2.13.1
License: Postcardware :)
Author: Tom Braider
Author URI: http://www.tomsdimension.de
*/

$cpd_dir_name = 'count-per-day';

/**
 * include GeoIP addon (just if no other plugin include it)
 */
//$cpd_path = ABSPATH.PLUGINDIR.'/'.dirname(plugin_basename(__FILE__));
$cpd_path = ABSPATH.PLUGINDIR.'/'.$cpd_dir_name.'/';
$cpd_path = str_replace('/', DIRECTORY_SEPARATOR, $cpd_path);

if ( !function_exists('geoip_country_code_by_name') && file_exists($cpd_path.'geoip/geoip.php') )
	include_once($cpd_path.'geoip/geoip.php');
$cpd_geoip = ( class_exists('CpdGeoIp') && file_exists($cpd_path.'geoip/GeoIP.dat') ) ? 1 : 0;

/**
 * Count per Day
 */
class CountPerDay
{
	
var $options;			// options array
var $dir;				// this plugin dir
var $dbcon;				// database connection
var $queries = array();	// queries times for debug
var $page;				// Post/Page-ID

/**
 * Constructor
 */
function CountPerDay()
{
	// variables
	global $table_prefix, $cpd_path, $cpd_dir_name;
	define('CPD_C_TABLE', $table_prefix.'cpd_counter');
	define('CPD_CO_TABLE', $table_prefix.'cpd_counter_useronline');
	define('CPD_N_TABLE', $table_prefix.'cpd_notes');
	define('CPD_METABOX', 'cpd_metaboxes');
	
	// use local time not UTC
	get_option('gmt_offset');
	
	$this->options = get_option('count_per_day');
	$this->dir = get_bloginfo('wpurl').'/'.PLUGINDIR.'/'.$cpd_dir_name;
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

	// javascript to count cached posts
	if ( $this->options['ajax'] == 1 )
		add_action('wp_footer', array(&$this,'addAjaxScript'));

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
		load_plugin_textdomain('cpd', false, $cpd_dir_name.'/locale');
		 
	// adds stylesheet
	add_action( 'admin_head', array(&$this, 'addCss') );
	add_action( 'wp_head', array(&$this, 'addCss') );
	
	// widget setup
	add_action('plugins_loaded', array(&$this, 'widgetCpdInit'));

	// activation hook
	register_activation_hook(ABSPATH.PLUGINDIR.'/count-per-day/counter.php', array(&$this, 'createTables'));
	
	// uninstall hook
	register_uninstall_hook($cpd_path.'counter.php', array(&$this, 'uninstall'));
	
	// query times debug
	if ( $this->options['debug'] )
	{
		add_action('wp_footer', array(&$this, 'showQueries'));
		add_action('admin_footer', array(&$this, 'showQueries'));
	}
	
	// add shorcode support
	$this->addShortcodes();
	
	// thickbox in backend only
	if ( strpos($_SERVER['SCRIPT_NAME'], '/wp-admin/') !== false )
		wp_enqueue_script( 'thickbox' );
		
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
	if ( $this->options['debug'] )
	{
		$t = microtime(true);
		$res = mysql_query($sql, $this->dbcon);
		$d = number_format( microtime(true) - $t , 5);
		$error = ($res) ? '' : '<b style="color:red">ERROR:</b> '.mysql_errno($this->dbcon).' - '.mysql_error($this->dbcon);
		$this->queries[] = $func.' : <b>'.$d.'</b><br/><code>'.$sql.'</code><br/>'.$error;
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
 * @param string/int $page PostID to count
 * @return string counter string
 */
function show( $before='', $after=' reads', $show = true, $count = true, $page = 'x' )
{
	global $wpdb;
	// count once only
	if ( $count && !$this->options['autocount'] )
		$this->count();
	if ( $page == 'x' )
		$page = get_the_ID();
	$res = $this->getQuery("SELECT count(*) FROM ".CPD_C_TABLE." WHERE page='$page'", 'show');
	$row = mysql_fetch_row($res);
	if ( $show )
		echo $before.$row[0].$after;
	else
		return $row[0];
}

/**
 * anonymize IP address (last bit) if option is set
 * @param $ip real IP address
 * @return new IP address
 */
function anonymize_ip( $ip )
{
	if ( $this->options['debug'] )
		$this->queries[] = 'called Function: <b style="color:blue">anonymize_ip</b> IP: <code>'.$ip.'</code>';
		
	if ($this->options['anoip'] == 1)
	{
		$i = explode('.', $ip);
		$i[3] += round( array_sum($i) / 4 + date_i18n('d') );
		if ( $i[3] > 255 )
			$i[3] -= 255;
		return implode('.', $i);	
	}
	else
		return $ip;
}

/**
 * gets PostID
 */
function getPostID()
{
	global $wp_query;
	
	// find PostID
	if ( !is_404() ) :
		if ( $this->options['autocount'] == 1 && is_singular() )
		{
			// single page with autocount on
			// make loop before regular loop is defined
			if (have_posts()) :
				while ( have_posts() && empty($p) ) :
					the_post();
					$p = get_the_ID();
				endwhile;
			endif;
			rewind_posts();
		}
		else if ( is_singular() )
			// single page with template tag show() or count()
			$p = get_the_ID();
			
		// "index" pages only with autocount	
		else if ( is_category() || is_tag() )
			// category or tag => negativ ID in CpD DB
			$p = 0 - $wp_query->get_queried_object_id();
		else
			// index, date, search and other "list" pages will count only once
			$p = 0;
			
		$this->page = $p;
		
		if ( $this->options['debug'] )
			$this->queries[] = 'called Function: <b style="color:blue">getPostID</b> page ID: <code>'.$p.'</code>';
		
		return $p;
	endif;
	
	return false;
}

/**
 * counts visits (without show)
 * @param $x some wp data (ignore it)
 * @param string/int $page PostID to count
 */
function count( $x, $page = 'x' )
{
	global $wpdb, $wp_query, $cpd_path, $cpd_geoip, $userdata;
	
	if ( $this->options['debug'] )
		$this->queries[] = 'called Function: <b style="color:blue">count</b> page: <code>'.$page.'</code>';
	
	if ( $page == 'x' )
		// normal counter
		$page = $this->getPostID();
	else
		// ajax counter on cached pages
		$page = intval($page);
	
	// get userlevel from role
	if ( isset($userdata->td_capabilities) )
	{
		$role = $userdata->td_capabilities;
		if ($role['administrator'])		$userlevel = 10;
		else if ($role['editor'])		$userlevel = 7;
		else if ($role['author'])		$userlevel = 2;
		else if ($role['contributor'])	$userlevel = 1;
		else if ($role['subscriber'])	$userlevel = 0;
		else							$userlevel = -1;
	}
		else $userlevel = -1;
	
	// count visitor?
	$countUser = 1;
	if ( $this->options['user'] == 0 && is_user_logged_in() ) $countUser = 0; // don't count loged user
	if ( $this->options['user'] == 1 && isset($userdata) && $this->options['user_level'] < $userlevel ) $countUser = 0; // loged user, but higher user level

	$isBot = $this->isBot();
	
	if ( $this->options['debug'] )
		$this->queries[] = 'called Function: <b style="color:blue">count (variables)</b> '
			.'isBot: <code>'.intval($isBot).'</code> '
			.'countUser: <code>'.$countUser.'</code> '
			.'page: <code>'.$page.'</code> '
			.'userlevel: <code>'.$userlevel.'</code>';
	
	// only count if: non bot, Logon is ok
	if ( !$isBot && $countUser && isset($page) )
	{
		$userip = $this->anonymize_ip($_SERVER['REMOTE_ADDR']);
		$client = $_SERVER['HTTP_USER_AGENT'];
		$referer = (isset($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : '';
		$date = date_i18n('Y-m-d');
		
		// new visitor on page?
		$res = $this->getQuery("SELECT count(*) FROM ".CPD_C_TABLE." WHERE ip=INET_ATON('$userip') AND date='$date' AND page='$page'", 'count check');
		$row = mysql_fetch_row($res);
		if ( $row[0] == 0 )
		{
			// save count
			if ( $cpd_geoip )
			{
				// with GeoIP addon save country
				$gi = geoip_open($cpd_path.'geoip/GeoIP.dat', GEOIP_STANDARD);
				$country = strtolower(geoip_country_code_by_addr($gi, $userip));
				$this->getQuery($wpdb->prepare("INSERT INTO ".CPD_C_TABLE." (page, ip, client, date, country, referer)
				VALUES (%s, INET_ATON(%s), %s, %s, %s, %s)", $page, $userip, $client, $date, $country, $referer), 'count insert');
			}
			else
				// without country
				$this->getQuery($wpdb->prepare("INSERT INTO ".CPD_C_TABLE." (page, ip, client, date, referer)
				VALUES (%s, INET_ATON(%s), %s, %s, %s)", $page, $userip, $client, $date, $referer), 'count insert');
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
 * @param string $ip IP adress
 */
function isBot( $client = '', $bots = '', $ip = '' )
{
	if ( empty($client) )
		$client = $_SERVER['HTTP_USER_AGENT'];
	if ( empty($ip) )
		$ip = $_SERVER['REMOTE_ADDR'];
		
	// empty/short client -> not normal browser -> bot
	if ( empty($client) || strlen($client) < 20 )
		return true;
	
	if ( empty($bots) )
		$bots = explode( "\n", $this->options['bots'] );

	$isBot = false;
	foreach ( $bots as $bot )
	{
		if (!$isBot) // loop until first bot was found only
		{
			$b = trim($bot);
			if ( !empty($b) && ( $ip == $b || strpos( strtolower($client), strtolower($b) ) !== false ) )
				$isBot = true;
		}
	}
	return $isBot;
}

/**
 * creates tables if not exists
 */
function createTables()
{
	global $wpdb, $table_prefix;

	// for plugin activation, creates $wpdb
	require_once(ABSPATH.'wp-admin/includes/upgrade.php');
	
	// table "counter"
	$sql = "CREATE TABLE IF NOT EXISTS `".CPD_C_TABLE."` (
	`id` int(10) NOT NULL auto_increment,
	`ip` int(10) unsigned NOT NULL,
	`client` varchar(150) NOT NULL,
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
	
	// referer
	$this->getQuery("SELECT referer FROM `".CPD_C_TABLE."`");
		if ((int) mysql_errno() == 1054)
			$this->getQuery("ALTER TABLE `".CPD_C_TABLE."` ADD `referer` VARCHAR(100) NOT NULL");
	
	// table "notes"
	$sql = "CREATE TABLE IF NOT EXISTS `".$table_prefix."cpd_notes` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`date` date NOT NULL,
	`note` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `date` (`date`) )";
	$this->getQuery($sql);
	
	// update options to array
	$this->UpdateOptions();
	
	// set directory mode
	@chmod(ABSPATH.PLUGINDIR.'/count-per-day/geoip', 0777);
}

/**
 * creates dashboard summary metabox content
 */
function dashboardReadsAtAll()
{
	?>
	<ul>
		<li><b><span><?php $this->getReadsAll(); ?></span></b><?php _e('Total reads', 'cpd') ?>:</li>
		<li><b><?php $this->getReadsToday(); ?></b><?php _e('Reads today', 'cpd') ?>:</li>
		<li><b><?php $this->getReadsYesterday(); ?></b><?php _e('Reads yesterday', 'cpd') ?>:</li>
		<li><b><span><?php $this->getUserAll(); ?></span></b><?php _e('Total visitors', 'cpd') ?>:</li>
		<li><b><span><?php $this->getUserOnline(); ?></span></b><?php _e('Visitors currently online', 'cpd') ?>:</li>
		<li><b><?php $this->getUserToday(); ?></b><?php _e('Visitors today', 'cpd') ?>:</li>
		<li><b><?php $this->getUserYesterday(); ?></b><?php _e('Visitors yesterday', 'cpd') ?>:</li>
		<li><b><?php $this->getUserLastWeek(); ?></b><?php _e('Visitors last week', 'cpd') ?>:</li>
		<li><b><?php $this->getUserPerDay($this->options['dashboard_last_days']); ?></b>&Oslash; <?php _e('Visitors per day', 'cpd') ?>:</li>
		<li><b><?php $this->getFirstCount(); ?></b><?php _e('Counter starts on', 'cpd') ?>:</li>
	</ul>
	<?php
}

/**
 * creates dashboard chart metabox content - page visits
 * @param integer $limit days to show
 * @param boolean $frontend limit function on frontend
 * @see dashboardChartDataRequest()
 */
function dashboardChart( $limit = 0, $frontend = false )
{
	global $table_prefix;
	if ( $limit == 0 )
		$limit = ( !empty($this->options['chart_days']) )? $this->options['chart_days'] : 30;
	$start = ( isset($_GET['cpd_chart_start']) ) ? $_GET['cpd_chart_start'] : date_i18n('Y-m-d');
		
	$sql = "
	SELECT	count(*) count, c.date,	n.note
	FROM	".CPD_C_TABLE." AS c
	LEFT	JOIN ".$table_prefix."cpd_notes AS n
			ON n.date = c.date
	WHERE	c.date <= '".$start."'
	GROUP	BY c.date
	ORDER	BY c.date DESC
	LIMIT	$limit";
	$r = $this->dashboardChartDataRequest($sql, $limit, $frontend);
	if ($frontend)
		return $r;
	else
		echo $r;
}

/**
 * creates dashboard chart metabox content - visitors
 * @param integer limit days to show
 * @param boolean $frontend limit function on frontend
 * @see dashboardChartDataRequest()
 */
function dashboardChartVisitors( $limit = 0, $frontend = false )
{
	global $table_prefix;
	if ( $limit == 0 )
		$limit = ( !empty($this->options['chart_days']) )? $this->options['chart_days'] : 30;
	$start = ( isset($_GET['cpd_chart_start']) ) ? $_GET['cpd_chart_start'] : date_i18n('Y-m-d');
	$sql = "
	SELECT count(*) count, t.date, n.note
	FROM (	SELECT	count(*) count, date
			FROM	".CPD_C_TABLE."
			GROUP	BY date, ip
			) AS t
	LEFT	JOIN ".$table_prefix."cpd_notes AS n
			ON n.date = t.date
	WHERE	t.date <= '".$start."'
	GROUP BY t.date
	ORDER BY t.date DESC
	LIMIT $limit";
	$r = $this->dashboardChartDataRequest($sql, $limit, $frontend);
	if ($frontend)
		return $r;
	else
		echo $r;	
}

/**
 * creates dashboard chart metabox content
 * @param string $sql SQL-Statement visitors or page visits
 * @param boolean $frontend limit function on frontend
 */
function dashboardChartDataRequest( $sql = '', $limit, $frontend = false )
{
	global $wp_locale;

	// get options
	$max_height = ( !empty($this->options['chart_height']) ) ? $this->options['chart_height'] : 200;
	
	$res = $this->getQuery($sql, 'Chart');
	if ( mysql_errno() || !mysql_num_rows($res) )
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
	$days = round(max(1, ($end_time - $start_time) / 86400 + 1));
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
	$r = '
		<div style="text-align:center;">
		<small style="display:block; float:right;">'.$days.' '.__('days', 'cpd').'</small>
		<small style="display:block; float:left;">Max: '.$max.'</small>';
	if ( !$frontend )
		$r .= '<small><a href="'.$this->dir.'/notes.php?KeepThis=true&amp;TB_iframe=true" title="Count per Day" class="thickbox">'.__('Notes', 'cpd').'</a></small>';
	$r .= '<small>&nbsp;</small></div>';
	
	$r .= '<p style="border-bottom:1px black solid; white-space:nowrap;">';
	
	$date_old = $start_time;
	
	// newest data will show right
	$res_array = array_reverse($res_array);
	foreach ( $res_array as $day )
	{
		$date = strtotime($day['date']);
		$note = ( $day['note'] != '' ) ? ' - '.$day['note'] : '';

		if ( $date >= $start_time )
		{
			// show the last $limit days only
			if ( $date - $date_old > 86400 )
			{
				// show space if no reads today
				$width = (($date - $date_old) / 86400 - 1) * $bar_width;
				if ( $frontend )
					$note = '';
				$r .= '<img src="'.$this->getResource('cpd_trans.png').'" title="'.__('no reads at this time', 'cpd').$note.'"
					style="width:'.$width.'%; height:'.$max_height.'px" />';
			}
	
			// show normal bar
			$height = max( round($day['count'] * $height_factor, 0), 1 );
			$date_str = mysql2date(get_option('date_format'), $day['date']);
			if ( !$frontend )
				$r .= '<a href="?page=cpd_metaboxes&amp;daytoshow='.$day['date'].'">';
			$r .= '<img src="';
			if ($note && !$frontend)
				$r .= $this->getResource('cpd_blau.png').'" title="'.$date_str.' : '.$day['count'].$note.'"';
			else
				$r .= $this->getResource('cpd_rot.png').'" title="'.$date_str.' : '.$day['count'].'"';
			$r .= ' style="width:'.$bar_width.'%; height:'.$height.'px" />';
			if ( !$frontend )
				$r .= '</a>';
			
			$date_old = $date;
		}
	}
	
	// legend
	$end_str = mysql2date(get_option('date_format'), $end);
	$start_str = mysql2date(get_option('date_format'), $start);
	$r .= '</p>
		<div style="height: 10px" class="cpd-l">
			<small>'.$start_str.'</small>
			<small class="cpd-r">'.$end_str.'</small>
		</div>';

	// buttons
	$date_back = date('Y-m-d', strtotime($start) - 86400);
	$date_forward = date('Y-m-d', strtotime($end) + 86400 * $limit);
	$r .= '<p style="text-align:center;">
		<a href="index.php?page=cpd_metaboxes&amp;cpd_chart_start='.$date_back.'" class="button">&lt;</a>
		<a href="index.php?page=cpd_metaboxes&amp;cpd_chart_start='.$date_forward.'" class="button">&gt;</a>
		</p>';
	
	return $r;
}

/**
 * shows current visitors
 */
function getUserOnline( $frontend = false )
{
	$res = $this->getQuery("SELECT count(*) FROM ".CPD_CO_TABLE, 'getUserOnline');
	$row = mysql_fetch_row($res);
	if ($frontend)
		return $row[0];
	else
		echo $row[0];
}

/**
 * shows all visitors
 */
function getUserAll( $frontend = false )
{
	$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." GROUP BY date, ip", 'getUserAll');
	$c = mysql_num_rows($res) + intval($this->options['startcount']);
	if ($frontend)
		return $c;
	else
		echo $c;
}

/**
 * shows all reads
 */
function getReadsAll( $frontend = false )
{
	$res = $this->getQuery("SELECT COUNT(*) FROM ".CPD_C_TABLE, 'getReadsAll');
	$row = mysql_fetch_row($res);
	$c = $row[0] + intval($this->options['startreads']);
	if ($frontend)
		return $c;
	else
		echo $c;
}

/**
 * shows today visitors
 */
function getUserToday( $frontend = false )
{
	$date = date_i18n('Y-m-d');
	$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." WHERE date = '$date' GROUP BY ip", 'getUserToday');
	$c = mysql_num_rows($res);
	if ($frontend)
		return $c;
	else
		echo $c;
}

/**
 * shows today reads
 */
function getReadsToday( $frontend = false )
{
	$date = date_i18n('Y-m-d');
	$res = $this->getQuery("SELECT COUNT(*) FROM ".CPD_C_TABLE." WHERE date = '$date'", 'getReadsToday');
	$row = mysql_fetch_row($res);
	if ($frontend)
		return $row[0];
	else
		echo $row[0];
}

/**
 * shows yesterday visitors
 */
function getUserYesterday( $frontend = false )
{
	$date = date_i18n('Y-m-d', time()-86400);
	$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." WHERE date = '$date' GROUP BY ip", 'getUserYesterday');
	$c = mysql_num_rows($res);
	if ($frontend)
		return $c;
	else
		echo $c;
}

/**
 * shows yesterday reads
 */
function getReadsYesterday( $frontend = false )
{
	$date = date_i18n('Y-m-d', time()-86400);
	$res = $this->getQuery("SELECT COUNT(*) FROM ".CPD_C_TABLE." WHERE date = '$date'", 'getReadsYesterday');
	$row = mysql_fetch_row($res);
	if ($frontend)
		return $row[0];
	else
		echo $row[0];
}

/**
 * shows last week visitors (last 7 days)
 */
function getUserLastWeek( $frontend = false )
{
	$date = date_i18n('Y-m-d', time()-86400*7);
	$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." WHERE date >= '$date' GROUP BY ip;", 'getUserLastWeek');
	$c = mysql_num_rows($res);
	if ($frontend)
		return $c;
	else
		echo $c;
}

/**
 * shows visitors per month
 */
function getUserPerMonth( $frontend = false )
{
	$m = $this->getQuery("SELECT LEFT(date,7) FROM ".CPD_C_TABLE." GROUP BY year(date), month(date) ORDER BY date DESC", 'getUserPerMonths');
	$r = '<ul class="cpd_front_list">';
	while ( $row = mysql_fetch_row($m) )
	{
		$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." WHERE LEFT(date,7) = '".$row[0]."' GROUP BY date, ip", 'getUserPerMonth');
		$r .= '<li><b>'.mysql_num_rows($res).'</b> '.$row[0].'</li>'."\n";
	}
	$r .= '</ul>';
	
	if ($frontend)
		return $r;
	else
		echo $r;
}

/**
 * shows visitors per post
 * @param integer $limit number of posts, -1 = all, 0 = get option from db, x = number
 * @param boolean $frontend limit function on frontend
 */
function getUserPerPost( $limit = 0, $frontend = false )
{
	global $wpdb;
	if ( $limit == 0 )
		$limit = $this->options['dashboard_posts'];

	$sql = "
	SELECT	COUNT(c.id) count,
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
	$r = $this->getUserPer_SQL( $sql, 'getUserPerPost', $frontend );
	if ($frontend)
		return $r;
	else
		echo $r;
}

/**
 * shows counter start, first day or given value
 */
function getFirstCount( $frontend = false )
{
	global $wp_locale;
	if (!empty($this->options['startdate']))
		$c = mysql2date(get_option('date_format'), $this->options['startdate'] );
	else
	{
		$res = $this->getQuery("SELECT date FROM ".CPD_C_TABLE." ORDER BY date LIMIT 1", 'getFirstCount');
		$row = mysql_fetch_row($res);
		$c = mysql2date(get_option('date_format'), $row[0] );
	}
	if ($frontend)
		return $c;
	else
		echo $c;
}

/**
 * shows averaged visitors per day
 * @param integer $days days to calc
 */
function getUserPerDay( $days = 0, $frontend = false )
{
	global $wpdb;
	$datemax = date_i18n('Y-m-d');
	if ( $days > 0 )
		// last $days days without today
		$datemin = date_i18n('Y-m-d', time() - ($days + 1) * 86400);
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
	
	$c = '<abbr title="last '.$days.' days without today">';
	if ( $count < 5 )
		$c .=  number_format($count, 2);
	else
		$c .=  number_format($count, 0);
	$c .=  '</abbr>';
	
	if ($frontend)
		return $c;
	else
		echo $c;
}

/**
 * shows most visited pages in last days
 * @param integer $days days to calc (last days)
 * @param integer $limit count of posts (last posts)
 */
function getMostVisitedPosts( $days = 0, $limit = 0, $frontend = false )
{
	global $wpdb;
	if ( $days == 0 )
		$days = $this->options['dashboard_last_days'];
	if ( $limit == 0 )
		$limit = $this->options['dashboard_last_posts'];
	$date = date_i18n('Y-m-d', time() - 86400 * $days);

	$sql = "
	SELECT	COUNT(c.id) count,
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
	$r =  '<small>'.sprintf(__('The %s most visited posts in last %s days:', 'cpd'), $limit, $days).'<br/>&nbsp;</small>';
	$r .= $this->getUserPer_SQL( $sql, 'getMostVisitedPosts', $frontend );
	if ($frontend)
		return $r;
	else
		echo $r;		
}

/**
 * shows visited pages at given day
 * @param integer $date day in mySql date format yyyy-mm-dd
 * @param integer $limit count of posts (last posts)
 */
function getVisitedPostsOnDay( $date = 0, $limit = 0 )
{
	global $wpdb, $cpd_path, $table_prefix;
	if (!empty($_POST['daytoshow']))
		$date = $_POST['daytoshow'];
	else if (!empty($_GET['daytoshow']))
		$date = $_GET['daytoshow'];
	else if ( $date == 0 )
		$date = date_i18n('Y-m-d');
	if ( $limit == 0 )
		$limit = $this->options['dashboard_last_posts'];

	// get note
	$notes = $wpdb->get_results("SELECT * FROM ".$table_prefix."cpd_notes WHERE date = '$date'", ARRAY_A);
	if ( $notes )
		$note = $notes[0]['note'];

	$sql = "
	SELECT	COUNT(c.id) count,
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
	WHERE	c.date = '$date'
	GROUP	BY c.page
	ORDER	BY count DESC
	LIMIT	$limit";
	
	echo '<form action="" method="post">
		  <input name="daytoshow" value="'.$date.'" size="10" />
		  <input type="submit" name="showday" value="'.__('Show').'" class="button" />
		  <a href="'.$this->dir.'/notes.php?KeepThis=true&amp;TB_iframe=true" title="Count per Day - '.__('Notes', 'cpd').'" class="button thickbox">'.__('Notes', 'cpd').'</a>
		  </form>';

	if ( isset($note) )
		echo '<p style="background:#eee; padding:2px;">'.$note.'</p>';

	echo $this->getUserPer_SQL( $sql, 'getVisitedPostsOnDay' );		
}

/**
 * shows little browser statistics
 */
function getClients( $frontend = false )
{
	global $wpdb;
//	$clients = array('Firefox', 'MSIE', 'Chrome', 'AppleWebKit', 'Opera');
	$c_string = $this->options['clients'];
	$clients = explode(',', $c_string);
	
	$res = $this->getQuery("SELECT COUNT(*) count FROM ".CPD_C_TABLE, 'getClients_all');
	$row = mysql_fetch_row($res);
	$all = max(1, $row[0]);
	$rest = 100;
	$r = '<ul id="cpd_clients" class="cpd_front_list">';
	foreach ($clients as $c)
	{
		$res = $this->getQuery("SELECT COUNT(*) count FROM ".CPD_C_TABLE." WHERE client like '%".trim($c)."%'", 'getClients_'.$c);
		$row = mysql_fetch_row($res);
		$percent = number_format(100 * $row[0] / $all, 0);
		$rest -= $percent;
		$r .= '<li>'.$c.'<b>'.$percent.' %</b></li>';
	}
	if ( $rest > 0 )
		$r .= '<li>'.__('Other', 'cpd').'<b>'.$rest.' %</b></li>';
	$r .= '</ul>';
	if ($frontend)
		return $r;
	else
		echo $r;
}


/**
 * shows top referers
 */
function getReferers( $limit = 0, $frontend = false )
{
	global $wpdb;
	if ( $limit == 0 )
		$limit = $this->options['dashboard_last_posts'];
	
	$res = $this->getQuery("SELECT COUNT(*) count, referer FROM ".CPD_C_TABLE." WHERE referer > '' GROUP BY referer ORDER BY count DESC LIMIT $limit", 'getReferers');

	$r = '<ul id="cpd_referers" class="cpd_front_list">';
	if ( @mysql_num_rows($res) )
		while ( $row = mysql_fetch_array($res) )
		{
			$ref2 = str_replace('http://', '', $row['referer']);
			$r .= '<li><a href="'.$row['referer'].'">'.$ref2.'</a><b>'.$row['count'].'</b></li>';
		}
	$r .= '</ul>';
	
	if ($frontend)
		return $r;
	else
		echo $r;
}

// end of statistic functions

/**
 * gets mass bots
 * @param int $limit only show IP if more than x page views per day
 */
function getMassBots( $limit = 0 )
{
	if ( $limit == 0 )
		$limit = 50;

	$sql = "
	SELECT	t.id, t.ip AS longip, INET_NTOA(t.ip) AS ip, t.date, t.posts,
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
 * @param boolean $frontend limit function on frontend
 */
function getUserPer_SQL( $sql, $name = '', $frontend = false )
{
	global $userdata;
	$m = $this->getQuery($sql, $name);
	$r = '<ul class="cpd_front_list">';
	while ( $row = mysql_fetch_assoc($m) )
	{
		$r .= '<li><b>'.$row['count'].'</b>';
		// link only for editors in backend
		if ( isset($userdata->user_level) && intval($userdata->user_level) >= 7 && !$frontend)
		{
			if ( $row['post_id'] > 0 )
				$r .= '<a href="post.php?action=edit&amp;post='.$row['post_id'].'"><img src="'.$this->getResource('cpd_pen.png').'" alt="[e]" title="'.__('Edit Post').'" style="width:9px;height:12px;" /></a> '
					.'<a href='.$this->dir.'/userperspan.php?page='.$row['post_id'].'&amp;KeepThis=true&amp;TB_iframe=true" class="thickbox" title="Count per Day"><img src="'.$this->getResource('cpd_calendar.png').'" alt="[v]" style="width:12px;height:12px;" /></a> ';
			else
				$r .= '<img src="'.$this->getResource('cpd_trans.png').'" alt="" style="width:25px;height:12px;" /> ';
		}
		
		if ( $frontend ) // no links and only posts in frontend
		{
			if ( $row['post_id'] < 0 && $row['tax'] == 'category' )
				//category
				$r .= '- '.$row['tag_cat_name'].' -';
			else if ( $row['post_id'] < 0 )
				// tag
				$r .= '- '.$row['tag_cat_name'].' -';
			else if ( $row['post_id'] == 0 )
				// homepage
				$r .= '- '.__('Front page displays').' -';
			else
			{
				// post/page
				$postname = $row['post'];
				if ( empty($postname) ) 
					$postname = '---';
				$r .= $postname;
			}
		}
		else
		{
			$r .= '<a href="'.get_bloginfo('url');
			if ( $row['post_id'] < 0 && $row['tax'] == 'category' )
				//category
				$r .= '?cat='.(0 - $row['post_id']).'">- '.$row['tag_cat_name'].' -';
			else if ( $row['post_id'] < 0 )
				// tag
				$r .= '?tag='.$row['tag_cat_slug'].'">- '.$row['tag_cat_name'].' -';
			else if ( $row['post_id'] == 0 )
				// homepage
				$r .= '">- '.__('Front page displays').' -';
			else
			{
				// post/page
				$postname = $row['post'];
				if ( empty($postname) ) 
					$postname = '---';
				$r .= '?p='.$row['post_id'].'">'.$postname;
			}
			$r .= '</a>';
		}
		$r .= '</li>'."\n";
	}
	$r .= '</ul>';
	
	return $r;
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
	foreach( $bots as $ip )
		if ( ip2long($ip) !== false )
			$this->getQuery('DELETE FROM '.CPD_C_TABLE.' WHERE INET_NTOA(ip) LIKE \''.$ip.'%\'', 'clenaDB_ip');
	
	// delete by client
	foreach ($bots as $bot)
		$this->getQuery("DELETE FROM ".CPD_C_TABLE." WHERE client LIKE '%$bot%'", 'cleanDB_client');
	
	// delete if a previously countered page was deleted
	$this->getQuery("DELETE FROM ".CPD_C_TABLE." WHERE page NOT IN ( SELECT id FROM ".$wpdb->posts.") AND page > 0", 'cleanDB_delPosts');
	
	$rows_after = $wpdb->get_var('SELECT COUNT(*) FROM '.CPD_C_TABLE);
	return $rows_before - $rows_after;
}

/**
 * adds menu entry to backend
 * @param string $content WP-"Content"
 */
function menu($content)
{
	global $cpd_dir_name;
	if (function_exists('add_options_page'))
	{
		$menutitle = '<img src="'.$this->getResource('cpd_menu.gif').'" alt="" style="width:9px;height:12px;" /> Count per Day';
		add_options_page('CountPerDay', $menutitle, 'manage_options', $cpd_dir_name.'/counter-options.php') ;
	}
}
	
/**
 * adds an "settings" link to the plugins page
 */
function pluginActions($links, $file)
{
	global $cpd_dir_name;
	if( $file == $cpd_dir_name.'/counter.php' )
	{
		$link = '<a href="options-general.php?page='.$cpd_dir_name.'/counter-options.php">'.__('Settings').'</a>';
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
	echo '</b></a> '.__('Total visitors', 'cpd').'<b> - ';
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
		'user_level' => 0,
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
		'countries' => 20,
		'startdate' => '',
		'startcount' => '',
		'startreads' => '',
		'anoip' => 0,
		'ajax' => 0,
		'massbotlimit' => 25,
		'debug' => 0,
		'clients' => 'Firefox, MSIE, Chrome, AppleWebKit, Opera');
		
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
	
	// set all default values
	$onew = get_option('count_per_day', '');
	if (!isset($onew['onlinetime']))			$onew['onlinetime'] = 300;
	if (!isset($onew['user']))					$onew['user'] = 0;
	if (!isset($onew['user_level']))			$onew['user_level'] = 0;
	if (!isset($onew['autocount']))				$onew['autocount'] = 1;
	if (!isset($onew['bots']))					$onew['bots'] = '';
	if (!isset($onew['dashboard_posts']))		$onew['dashboard_posts'] = 20;
	if (!isset($onew['dashboard_last_posts']))	$onew['dashboard_last_posts'] = 20;
	if (!isset($onew['dashboard_last_days']))	$onew['dashboard_last_days'] = 7;
	if (!isset($onew['widget_title']))			$onew['widget_title'] = 'Count per Day';
	if (!isset($onew['widget_functions']))		$onew['widget_functions'] = '';
	if (!isset($onew['show_in_lists']))			$onew['show_in_lists'] = 1;
	if (!isset($onew['chart_days']))			$onew['chart_days'] = 60;
	if (!isset($onew['chart_height']))			$onew['chart_height'] = 100;
	if (!isset($onew['countries']))				$onew['countries'] = 20;
	if (!isset($onew['startdate']))				$onew['startdate'] = '';
	if (!isset($onew['startcount']))			$onew['startcount'] = '';
	if (!isset($onew['startreads']))			$onew['startreads'] = '';
	if (!isset($onew['anoip']))					$onew['anoip'] = 0;
	if (!isset($onew['massbotlimit']))			$onew['massbotlimit'] = 25;
	if (!isset($onew['clients']))				$onew['clients'] = 'Firefox, MSIE, Chrome, AppleWebKit, Opera';
	if (!isset($onew['ajax']))					$onew['ajax'] = 0;
	if (!isset($onew['debug']))					$onew['debug'] = 0;

	update_option('count_per_day', $onew);
}

/**
 * add counter column to page/post lists
 */
function cpdColumn($defaults)
{
	if ( $this->options['show_in_lists']  )
		$defaults['cpd_reads'] = '<img src="'.$this->GetResource('cpd_menu.gif').'" alt="'.__('Reads', 'cpd').'" title="'.__('Reads', 'cpd').'" style="width:9px;height:12px;" />';
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
	$wpdb->query('DROP TABLE IF EXISTS '.CPD_N_TABLE);
	delete_option('count_per_day');
}

/**
 * gets image recource with given name
 */
function getResource( $r )
{
	return trailingslashit( $this->dir ).'img/'.$r;
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
					echo '<li style="text-align:left;"><span id="cpd_number_'.$s[0].'" style="float:right">';
					
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
			
			// for find this text for translation
			__('This post', 'cpd');
		}
	}
	wp_register_sidebar_widget('widgetCpd', 'Count per Day', 'widgetCpd');
	
	function widgetCpdControl()
	{
		global $count_per_day;

		// show the possible functions
		$funcs = array(
		'show' => 'This post',
		'getReadsAll' => 'Total reads',
		'getReadsToday' => 'Reads today',
		'getReadsYesterday' => 'Reads yesterday',
		'getUserAll' => 'Total visitors',
		'getUserToday' => 'Visitors today',
		'getUserYesterday' => 'Visitors yesterday',
		'getUserLastWeek' => 'Visitors last week',
		'getUserPerDay' => 'Visitors per day',
		'getUserAll' => 'Total visitors',
		'getUserOnline' => 'Visitors currently online',
		'getReadsAll' => 'Total reads',
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
	wp_register_widget_control('widgetCpd', 'Count per Day', 'widgetCpdControl');
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
	$menutitle = '<img src="'.$this->getResource('cpd_menu.gif').'" alt="" style="width:12px;height:12px;" /> Count per Day';
	$this->pagehook = add_submenu_page('index.php', 'CountPerDay', $menutitle, 1, CPD_METABOX, array(&$this, 'onShowPage'));
	add_action('load-'.$this->pagehook, array(&$this, 'onLoadPage'));
}

/**
 * backlink to Plugin homepage
 */
function cpdInfo()
{
	$t = date_i18n('Y-m-d H:i');
	echo '<p>';
	printf(__('Time for Count per Day: <code>%s</code>.', 'cpd'), $t);
	echo '<br />'.__('Bug? Problem? Question? Hint? Praise?', 'cpd').'<br/>';
	printf(__('Write a comment on the <a href="%s">plugin page</a>.', 'cpd'), 'http://www.tomsdimension.de/wp-plugins/count-per-day');
	echo '<br />'.__('License').': <a href="http://www.tomsdimension.de/postcards">Postcardware :)</a>';
	echo '</p>';
}

/**
 * function calls from metabox default parameters
 */
function getMostVisitedPostsMeta() { $this->getMostVisitedPosts(); }
function getUserPerPostMeta() { $this->getUserPerPost(); }
function getVisitedPostsOnDayMeta() { $this->getVisitedPostsOnDay( 0, 100); }
function dashboardChartMeta() { $this->dashboardChart( 0, false); }
function dashboardChartVisitorsMeta() { $this->dashboardChartVisitors( 0, false); }
function getCountriesMeta()	{ $this->getCountries(0, false); }
function getCountriesVisitorsMeta()	{ $this->getCountries(0, false, true); }
function getReferersMeta() { $this->getReferers(0, false); }

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
	add_meta_box('chart_visitors', __('Visitors per day', 'cpd'), array(&$this, 'dashboardChartVisitorsMeta'), $this->pagehook, 'cpdrow1', 'default');
	add_meta_box('chart_reads', __('Reads per day', 'cpd'), array(&$this, 'dashboardChartMeta'), $this->pagehook, 'cpdrow1', 'default');
	add_meta_box('reads_per_month', __('Visitors per month', 'cpd'), array(&$this, 'getUserPerMonth'), $this->pagehook, 'cpdrow2', 'default');
	add_meta_box('browsers', __('Browsers', 'cpd'), array(&$this, 'getClients'), $this->pagehook, 'cpdrow2', 'default');
	add_meta_box('reads_per_post', __('Visitors per post', 'cpd'), array(&$this, 'getUserPerPostMeta'), $this->pagehook, 'cpdrow3', 'default');
	add_meta_box('last_reads', __('Latest Counts', 'cpd'), array(&$this, 'getMostVisitedPostsMeta'), $this->pagehook, 'cpdrow4', 'default');
	add_meta_box('day_reads', __('Visitors per day', 'cpd'), array(&$this, 'getVisitedPostsOnDayMeta'), $this->pagehook, 'cpdrow4', 'default');
	add_meta_box('cpd_info', __('Plugin'), array(&$this, 'cpdInfo'), $this->pagehook, 'cpdrow1', 'low');
	add_meta_box('referers', __('Referer', 'cpd'), array(&$this, 'getReferersMeta'), $this->pagehook, 'cpdrow3', 'default');
	
	// countries with GeoIP addon only
	if ( $cpd_geoip )
	{
		add_meta_box('countries', __('Reads per Country', 'cpd'), array(&$this, 'getCountriesMeta'), $this->pagehook, 'cpdrow2', 'default');
		add_meta_box('countries2', __('Visitors per Country', 'cpd'), array(&$this, 'getCountriesVisitorsMeta'), $this->pagehook, 'cpdrow2', 'default');
	}
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
 * @param boolean $frontend limit function on frontend
 * @param boolean $visitors show visitors insteed of reads
 */
function getCountries( $limit = 0, $frontend, $visitors = false )
{
	global $cpd_path, $cpd_geoip;
	$c = '';

	// with GeoIP addon only
	if ( $cpd_geoip )
	{
		$gi = geoip_open($cpd_path.'geoip/GeoIP.dat', GEOIP_STANDARD);
		$geoip = new GeoIP();
		if ( $limit == 0 )
			$limit = max( 0, $this->options['countries'] );

		if ( $visitors )
			// visitors
			$res = $this->getQuery("
				SELECT country, COUNT(*) c
				FROM (	SELECT country, ip, COUNT(*) c
						FROM ".CPD_C_TABLE."
						WHERE ip > 0
						GROUP BY country, ip ) as t
				GROUP BY country
				ORDER BY c desc
				LIMIT $limit", 'getCountries');
		else
			// reads
			$res = $this->getQuery("SELECT country, COUNT(*) c FROM ".CPD_C_TABLE." WHERE ip > 0 GROUP BY country ORDER BY c DESC LIMIT $limit", 'getCountries');
		
		// map link
		if (!$frontend && file_exists($cpd_path.'map/map.php') )
		{
			$c .= '<div style="margin: 5px 0 10px 0;"><a href="'.$this->dir.'/map/map.php?map=';
			if ( $visitors )
				$c .= 'visitors';
			else
				$c .= 'reads';
			$c .= '&amp;KeepThis=true&amp;TB_iframe=true" title="Count per Day - '.__('Map', 'cpd').'" class="thickbox button">'.__('Map', 'cpd').'</a></div>';
		}
		
		if ( @mysql_num_rows($res) )
		{
			$c .= '<ul class="cpd_front_list">';
			while ( $r = mysql_fetch_array($res) )
			{
				$id = $geoip->GEOIP_COUNTRY_CODE_TO_NUMBER[strtoupper($r['country'])];
				if ( empty($id) )
				{
					$name = '???';
					$r['country'] = 'unknown';
				}
				else
					$name = $geoip->GEOIP_COUNTRY_NAMES[$id];
				$c .= '<li><b>'.$r['c'].'</b>
					<div class="cpd-flag cpd-flag-'.$r['country'].'"></div> '
					.$name.'&nbsp;</li>'."\n";
			}
			$c .= '</ul>';
		}
	}
	if ($frontend)
		return $c;
	else
		echo $c;
}

/**
 * adds some shortcodes to use functions in frontend
 */
function addShortcodes()
{
	add_shortcode('CPD_READS_THIS', array( &$this, 'shortShow'));
	add_shortcode('CPD_READS_TOTAL', array( &$this, 'shortReadsTotal'));
	add_shortcode('CPD_READS_TODAY', array( &$this, 'shortReadsToday'));
	add_shortcode('CPD_READS_YESTERDAY', array( &$this, 'shortReadsYesterday'));
	add_shortcode('CPD_VISITORS_TOTAL', array( &$this, 'shortUserAll'));
	add_shortcode('CPD_VISITORS_ONLINE', array( &$this, 'shortUserOnline'));
	add_shortcode('CPD_VISITORS_TODAY', array( &$this, 'shortUserToday'));
	add_shortcode('CPD_VISITORS_YESTERDAY', array( &$this, 'shortUserYesterday'));
	add_shortcode('CPD_VISITORS_LAST_WEEK', array( &$this, 'shortUserLastWeek'));
	add_shortcode('CPD_VISITORS_PER_DAY', array( &$this, 'shortUserPerDay'));
	add_shortcode('CPD_FIRST_COUNT', array( &$this, 'shortFirstCount'));
	add_shortcode('CPD_CLIENTS', array( &$this, 'shortClients'));
	add_shortcode('CPD_READS_CHART', array( &$this, 'shortChartReads'));
	add_shortcode('CPD_VISITORS_CHART', array( &$this, 'shortChartVisitors'));
	add_shortcode('CPD_VISITORS_PER_MONTH', array( &$this, 'shortUserPerMonth'));
	add_shortcode('CPD_VISITORS_PER_POST', array( &$this, 'shortUserPerPost'));
	add_shortcode('CPD_COUNTRIES', array( &$this, 'shortCountries'));
	add_shortcode('CPD_MOST_VISITED_POSTS', array( &$this, 'shortMostVisitedPosts'));
	add_shortcode('CPD_REFERERS', array( &$this, 'shortReferers'));
}
function shortShow()			{ return $this->show('', '', false, false); }
function shortReadsTotal()		{ return $this->getReadsAll(true); }
function shortReadsToday()		{ return $this->getReadsToday(true); }
function shortReadsYesterday()	{ return $this->getReadsYesterday(true); }
function shortUserAll()			{ return $this->getUserAll(true); }
function shortUserOnline()		{ return $this->getUserOnline(true); }
function shortUserToday()		{ return $this->getUserToday(true); }
function shortUserYesterday()	{ return $this->getUserYesterday(true); }
function shortUserLastWeek()	{ return $this->getUserLastWeek(true); }
function shortUserPerDay()		{ return $this->getUserPerDay($this->options['dashboard_last_days'], true); }
function shortFirstCount()		{ return $this->getFirstCount(true); }
function shortClients()			{ return $this->getClients(true); }
function shortChartReads()		{ return '<div class="cpd_front_chart">'.$this->dashboardChart(0, true).'</div>'; }
function shortChartVisitors()	{ return '<div class="cpd_front_chart">'.$this->dashboardChartVisitors(0, true).'</div>'; }
function shortUserPerMonth()	{ return $this->getUserPerMonth(true); }
function shortUserPerPost()		{ return $this->getUserPerPost(0, true); }
function shortCountries()		{ return $this->getCountries(0, true); }
function shortMostVisitedPosts(){ return $this->getMostVisitedPosts(0, 0, true); }
function shortReferers()		{ return $this->getReferers(0, true); }

/**
 * adds style sheet to admin header
 */
function addCss()
{
	global $text_direction;
	echo "\n".'<link rel="stylesheet" href="'.$this->dir.'/counter.css" type="text/css" />'."\n";
	if ( $text_direction == 'rtl' ) 
		echo '<link rel="stylesheet" href="'.$this->dir.'/counter-rtl.css" type="text/css" />'."\n";
	// thickbox style here because add_thickbox() breaks RTL in he_IL
	if ( strpos($_SERVER['SCRIPT_NAME'], '/wp-admin/') !== false )
		echo '<link rel="stylesheet" href="'.get_bloginfo('wpurl').'/wp-includes/js/thickbox/thickbox.css" type="text/css" />'."\n";
}

/**
 * adds ajax script to count cached posts
 */
function addAjaxScript()
{
	$this->getPostID();
	echo <<< JSEND
<script type="text/javascript">
<!-- Count per Day -->
//<![CDATA[
jQuery(document).ready( function($)
{
	jQuery.get('{$this->dir}/ajax.php?f=count&page={$this->page}', function(text)
	{
		var d = text.split('|');
		for(var i = 0; i < d.length; i++)
		{
			var v = d[i].split('===');
			document.getElementById('cpd_number_'+v[1]).innerHTML = v[0]; 
		}
	});
} );
//]]>
</script>
JSEND;
}

/**
 * shows time of queries
 */
function showQueries()
{
	global $cpd_path;
	echo '<div style="margin:10px; padding-left:30px; border:1px red solid">
		<b>Count per Day - DEBUG: '.$this->queries[0].' s</b><ol>';
	echo '<li>'
		.'<b>Server:</b> '.$_SERVER['SERVER_SOFTWARE'].'<br/>'
		.'<b>PHP:</b> '.phpversion().'<br/>'
		.'<b>mySQL Server:</b> '.mysql_get_server_info().'<br/>'
		.'<b>mySQL Client:</b> '.mysql_get_client_info().'<br/>'
		.'<b>WordPress:</b> '.get_bloginfo('version')
		.'</li>';
	echo '<li><b>Tables:</b><br><b>'.CPD_C_TABLE.'</b>: ';
	$res = $this->getQuery( "SHOW FIELDS FROM `".CPD_C_TABLE."`", 'showFields' );
	while ( $col = mysql_fetch_array($res) )
		echo '<span style="color:blue">'.$col['Field'].'</span> = '.$col['Type'].' &nbsp; ';
	echo '<br/><b>'.CPD_CO_TABLE.'</b>: ';
	$res = $this->getQuery( "SHOW FIELDS FROM `".CPD_CO_TABLE."`", 'showFields' );
	while ( $col = mysql_fetch_array($res) )
		echo '<span style="color:blue">'.$col['Field'].'</span> = '.$col['Type'].' &nbsp; ';
	echo '<br/><b>'.CPD_N_TABLE.'</b>: ';
	$res = $this->getQuery( "SHOW FIELDS FROM `".CPD_N_TABLE."`", 'showFields' );
	while ( $col = mysql_fetch_array($res) )
		echo '<span style="color:blue">'.$col['Field'].'</span> = '.$col['Type'].' &nbsp; ';
	echo '</li>';
	echo '<li><b>Options:</b><br /> ';
	foreach ( $this->options as $k=>$v )
		if ( $k != 'bots') // hoster restrictions
			echo $k.' = '.$v.'<br />';
	echo '</li>';
	foreach($this->queries as $q)
		if ($q != $this->queries[0] )
			echo '<li>'.$q.'</li>';
	echo '</ol>';
	?>
	<p>GeoIP: 
		d_ir=<?php echo substr(decoct(fileperms($cpd_path.'geoip/')), -3) ?>
		f_ile=<?php echo (is_file($cpd_path.'geoip/GeoIP.dat')) ? substr(decoct(fileperms($cpd_path.'geoip/GeoIP.dat')), -3) : '-'; ?>
		f_open=<?php echo (function_exists('fopen')) ? 'true' : 'false' ?>
		g_zopen=<?php echo (function_exists('gzopen')) ? 'true' : 'false' ?>
		a_llow_url_fopen=<?php echo (ini_get('allow_url_fopen')) ? 'true' : 'false' ?>
	</p>
	<?php
	$this->cpdInfo();
	echo '</div>';
}

} // class end

$count_per_day = new CountPerDay();
?>