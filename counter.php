<?php
/*
Plugin Name: Count Per Day
Plugin URI: http://www.tomsdimension.de/wp-plugins/count-per-day
Description: Counter, shows reads per page; today, yesterday, last week, last months ... on dashboard.
Version: 2.1
License: GPL
Author: Tom Braider
Author URI: http://www.tomsdimension.de
*/

/**
 * Count per Day
 */
class CountPerDay
{
	
var $options; // options array
var $dir; // this plugin dir
var $dbcon; // DB connection


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
		add_action('wp', array(&$this,'autocount'));

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
	global $wpdb, $count_per_day; //, $_options;
	// only count once
	if ( $count && $this->options['autocount'] == 0 )
		$this->count();
	$page = get_the_ID();
	$visits = $wpdb->get_results("SELECT page FROM ".CPD_C_TABLE." WHERE page='$page';");
	$visits_per_page = count($visits);
	if ( $show )
		echo $before.$visits_per_page.$after;
	else
		return $visits_per_page;
}



/**
 * counts visits (without show)
 */
function count()
{
	global $wpdb;
//	cpdCreateTables(); // create tables if necessary
	
	// find PostID
	if ( $this->options['autocount'] == 1 && is_singular() )
	{
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
		$page = get_the_ID();
	else
		$page = 0;
	
	$countUser = ( $this->options['user'] == 0 && is_user_logged_in() ) ? 0 : 1;
	
	// only count if: non bot, PostID exists, Logon is ok
	if ( !$this->isBot() && !empty($page) && $countUser )
	{
		$userip = $_SERVER['REMOTE_ADDR'];
		$client = $_SERVER['HTTP_USER_AGENT'];
		$date = date('ymd');
		
		// memorize UserIP 
		$user_ip = $wpdb->get_results("SELECT * FROM ".CPD_C_TABLE." WHERE ip='$userip' AND date='$date' AND page='$page';");
		if ( count($user_ip) == 0 )
			$wpdb->query($wpdb->prepare("INSERT INTO ".CPD_C_TABLE." (page, ip, client, date)
				VALUES (%s, %s, %s, %s)", $page, $userip, $client, $date));
		
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
	global $wpdb;
	$timeout = time() - $this->options['onlinetime'];
	$wpdb->query($wpdb->prepare("DELETE FROM ".CPD_CO_TABLE." WHERE timestamp < %s", $timeout));
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

	// empty client -> not normal browser -> bot
	if ( empty($client) )
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
function createTables() {
	require_once(ABSPATH.'wp-admin/includes/upgrade.php');
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
		<li><b style="float:right"><?php $this->getUserPerDay(); ?></b>&Oslash; <?php _e('Visitors per day', 'cpd') ?>:</li>
		<li><b style="float:right"><?php $this->getFirstCount(); ?></b><?php _e('Counter starts on', 'cpd') ?>:</li>
	</ul>
	<?php
}



/**
 * creates dashboard chart metabox content
 */
function dashboardChart()
{
	global $wpdb, $wp_locale;

	// get options
	$limit = ( !empty($this->options['chart_days']) )? $this->options['chart_days'] : 30;
	$max_height = ( !empty($this->options['chart_height']) ) ? $this->options['chart_height'] : 200;
	
	$sql = "SELECT	count(*) as count,
					date
			FROM	".CPD_C_TABLE."
			GROUP	BY date
			ORDER	BY date DESC
			LIMIT	$limit";
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
	$days = ($end_time - $start_time) / 86400 + 1;
	$bar_width = round( 100 / $days, 2); // as %
	
	// find max count
	$max = 0;
	foreach ( $res as $day )
	{
		$date = strtotime('20'.$day->date);
		if ( $date >= $start_time && $day->count > $max )
			$max = $day->count;
	}

	$hight_factor = $max_height / $max;
	
	// headline with max count
	echo '<small style="display:block;">Max: '.$max.'</small>
		<p style="border-bottom:1px black solid; white-space:nowrap;">';
	
	$date_old = $start_time;
	
	// neweset data will show right
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
			$height = max( round($day->count * $hight_factor, 0), 1 );
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
	$date = date('ymd',time()-86400);
	$res = mysql_query("SELECT 1 FROM ".CPD_C_TABLE." WHERE date = '$date' GROUP BY ip;", $this->dbcon);
	echo mysql_num_rows($res);
}



/**
 * shows last week visitors (last 7 days)
 */
function getUserLastWeek()
{
	$date = date('ymd',time()-86400*7);
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
 *
 * @param integer $limit number of posts, -1 = all, 0 = get option from db, x = number
 */
function getUserPerPost( $limit = 0 )
{
	global $wpdb;
	
	if ( $limit == 0 )
		$limit = $this->options['dashboard_posts'];
	
	$sql = "SELECT	count(c.id) as count,
					p.post_title as post,
					c.page as post_id
			FROM 	".CPD_C_TABLE." c
			LEFT	JOIN ".$wpdb->posts." p
					ON p.id = c.page
			GROUP	BY c.page
			ORDER	BY count DESC";
	if ( $limit > 0 )
		$sql .= " LIMIT ".$limit;
	$m = $wpdb->get_results($sql);
	echo '<ul>';
	foreach ( $m as $row )
	{
		$postname = ( !empty($row->post) ) ? $row->post : '---';
		echo '<li><b>'.$row->count.'</b> <a href="'.get_bloginfo('url').'?p='.$row->post_id.'">'.$postname.'</a></li>'."\n";
	}
	echo '</ul>';
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
function getUserPerDay()
{
	global $wpdb;
	$v = $wpdb->get_results("SELECT MIN(date) as min, MAX(date) as max FROM ".CPD_C_TABLE.";");
	foreach ($v as $row)
	{
		$min = strtotime( '20'.substr($row->min,0,2).'-'.substr($row->min,2,2).'-'.substr($row->min,4,2) );
		$max = strtotime( '20'.substr($row->max,0,2).'-'.substr($row->max,2,2).'-'.substr($row->max,4,2) );
		$tage =  (($max - $min) / 86400 + 1);
	}
	
	$res = @mysql_query("SELECT 1 FROM ".CPD_C_TABLE." GROUP BY ip, date;", $this->dbcon);
	$count = @mysql_num_rows($res) / $tage;
	
	if ( $count < 5 )
		echo number_format($count, 2);
	else
		echo number_format($count, 0);
}



/**
 * shows most visited pages in last days
 */
function getMostVisitedPosts()
{
	global $wpdb;
	
	$days = $this->options['dashboard_last_days'];
	$count = $this->options['dashboard_last_posts'];
	$date = date('ymd', time() - 86400 * $days);

	$sql = "SELECT	count(c.id) as count,
					p.post_title as post,
					c.page as post_id
			FROM	".CPD_C_TABLE." c
			LEFT	JOIN ".$wpdb->posts." p
					ON p.id = c.page
			WHERE	c.date >= '$date'
			GROUP	BY c.page
			ORDER	BY count DESC
			LIMIT	$count";
	$m = $wpdb->get_results($sql);

	echo '<small>'.sprintf(__('The %s most visited posts in last %s days:', 'cpd'), $count, $days).'<br/>&nbsp;</small>';
	echo '<ul>';
	foreach ( $m as $row )
	{
		$postname = ( !empty($row->post) ) ? $row->post : '---';
		echo '<li><b>'.$row->count.'</b> <a href="'.get_bloginfo('url').'?p='.$row->post_id.'">'.$postname.'</a></li>'."\n";
	}
	echo '</ul>';
}



// end of statistic functions



/**
 * deletes spam in table, if you add new bot pattern you can clean the db
 */
function cleanDB()
{
	global $wpdb;
	
	$bots = explode( "\n", $this->options['bots'] );
	$rows = 0;
	
	// delete by ip
	$ips = "'".implode( "','", $bots )."'";
	$rows += $wpdb->get_var('SELECT count(*) FROM '.CPD_C_TABLE.' WHERE ip in ('.$ips.')');
	$wpdb->query('DELETE FROM '.CPD_C_TABLE.' WHERE ip in ('.$ips.')');
	
	// delete by client
	$v = $wpdb->get_results('SELECT * FROM '.CPD_C_TABLE);
	foreach ($v as $row)
	{
		if ( $this->IsBot($row->client, $bots) )
		{
			$wpdb->query('DELETE FROM '.CPD_C_TABLE.' WHERE id = '.$row->id);
			$rows++;
		}
	}
	
	// delete if a previously countered page was deleted
	$posts = $wpdb->get_results('SELECT id FROM '.$wpdb->posts);
	
	$pages = array();
	foreach ($posts as $post)
		$pages[] = $post->id;
	$pages = implode("','", $pages);
	
	$sql = "SELECT	count(*) as count, page
			FROM	".CPD_C_TABLE."
			WHERE	page NOT IN ('$pages')
			GROUP	BY page";
	$counts = $wpdb->get_results($sql);

	foreach ($counts as $count)
		$rows += $count->count;
	
	$wpdb->query("DELETE FROM ".CPD_C_TABLE." WHERE page NOT IN ('$pages')");
	
	return $rows;
}



/**
 * adds menu entry to backend
 * @param string $content WP-"Content"
 */
function menu($content)
{
	global $wp_version;
	if (function_exists('add_options_page'))
	{
		$menutitle = '';
		if ( version_compare( $wp_version, '2.6.999', '>' ) )
			$menutitle = '<img src="'.$this->getResource('cpd_menu.gif').'" alt="" /> ';
		$menutitle .= 'Count per Day';

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
 * loads automatic counter
 */
function autocount( )
{
	if ( is_singular() )
		$this->count();
}


	
/**
 * creates the little widget on dashboard
 */
function dashboardWidget()
{
	echo '<a href="?page=cpd_metaboxes"><b>';
	$this->getUserAll();
	echo '</b></a> '.__('Total visitors', 'cpd').' - <b>';
	$this->getUserPerDay();
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
		$autocount = get_option('cpd_autocount', 0);
		$bots = get_option('cpd_bots', "bot\nspider\nsearch\ncrawler\nask.com\nvalidator\nsnoopy\nsuchen.de\nsuchbaer.de\nshelob\nsemager\nxenu\nsuch_de\nia_archiver\nMicrosoft URL Control\nnetluchs");
		
		$o = array(
			'onlinetime' => $onlinetime,
			'user' => $user,
			'autocount' => $autocount,
			'bots' => $bots,
			'dashboard_posts' => 50,
			'dashboard_last_posts' => 20,
			'dashboard_last_days' => 14,
			'widget_title' => 'Count per Day',
			'widget_functions' => '',
			'show_in_lists' => 1,
			'chart_days' => 30,
			'chart_height' => 200);
		
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
 * you MUST have WP >= 2.7
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
    	$reads = $wpdb->get_var("SELECT count(*) FROM ".CPD_C_TABLE." WHERE page='$id';");
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
			'cpd_menu.gif' =>
			'R0lGODlhDAAMAJECAP8AAAAAAP///wAAACH5BAEAAAIALAAAAA'.
			'AMAAwAAAIdjI4ppsqNngA0PYDwZDrjUEGLGJGHBKFNwLYuWwAA'.
			'Ow==',
			'cpd_rot.png' =>
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACX'.
			'BIWXMAAAsTAAALEwEAmpwYAAAADElEQVR42mP8z8AAAAMFAQHa'.
			'4YgFAAAAAElFTkSuQmCC',
			'cpd_trans.png' =>
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACX'.
			'BIWXMAAAsTAAALEwEAmpwYAAAAC0lEQVR42mNkAAIAAAoAAv/l'.
			'xKUAAAAASUVORK5CYII='
			);
			 
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
			$title = (!empty($count_per_day->options['widget_title'])) ? $count_per_day->options['widget_title'] : 'Count per Day vvv';
	
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
					eval('echo $count_per_day->'.$s[0].'("","",false,false);'); // params for 'show' only. don't count! ;)
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
			'show'=>'This post',
			'getUserToday'=>'Visitors today',
			'getUserYesterday'=>'Visitors yesterday',
			'getUserLastWeek'=>'Visitors last week',
			'getUserPerDay'=>'Visitors per day',
			'getUserAll'=>'Total visitors',
			'getUserOnline'=>'Visitors currently online',
			'getFirstCount'=>'Counter starts on',
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
		echo '<p><label for="widget_cpd_title">Title: <input style="width: 150px;" id="widget_cpd_title" name="widget_cpd_title" type="text" value="'.$title.'" /></label></p>'."\n";
		

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
 * sets columns on dashborad page
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
	//register callback gets call prior your own page gets rendered
	add_action('load-'.$this->pagehook, array(&$this, 'onLoadPage'));
}



/**
 * will be executed if wordpress core detects this page has to be rendered
 */
function onLoadPage()
{
	// needed javascripts
	wp_enqueue_script('common');
	wp_enqueue_script('wp-lists');
	wp_enqueue_script('postbox');

	//add the metaboxes
	add_meta_box('reads_at_all', __('Total visitors', 'cpd'), array(&$this, 'dashboardReadsAtAll'), $this->pagehook, 'cpdrow1', 'core');
	add_meta_box('chart', __('Reads per day', 'cpd'), array(&$this, 'dashboardChart'), $this->pagehook, 'cpdrow1', 'core');
	add_meta_box('reads_per_month', __('Visitors per month', 'cpd'), array(&$this, 'getUserPerMonth'), $this->pagehook, 'cpdrow2', 'core');
	add_meta_box('reads_per_post', __('Visitors per post', 'cpd'), array(&$this, 'getUserPerPost'), $this->pagehook, 'cpdrow3', 'core');
	add_meta_box('last_reads', __('Latest Counts', 'cpd'), array(&$this, 'getMostVisitedPosts'), $this->pagehook, 'cpdrow4', 'core');
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
			<div class="postbox-container" <?php echo $cpd_style; ?>>
				<?php do_meta_boxes($this->pagehook, 'cpdrow1', $data); ?>
			</div>
			<div class="postbox-container" <?php echo $cpd_style; ?>>
				<?php do_meta_boxes($this->pagehook, 'cpdrow2', $data); ?>
			</div>
			<div class="postbox-container" <?php echo $cpd_style; ?>>
				<?php do_meta_boxes($this->pagehook, 'cpdrow3', $data); ?>
			</div>
			<div class="postbox-container" <?php echo $cpd_style; ?>>
				<?php do_meta_boxes($this->pagehook, 'cpdrow4', $data); ?>
			</div>
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

} // class end


$count_per_day = new CountPerDay();
?>