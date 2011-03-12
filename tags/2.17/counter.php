<?php
/*
Plugin Name: Count Per Day
Plugin URI: http://www.tomsdimension.de/wp-plugins/count-per-day
Description: Counter, shows reads per page; today, yesterday, last week, last months ... on dashboard, per shortcode or in widget.
Version: 2.17
License: Postcardware :)
Author: Tom Braider
Author URI: http://www.tomsdimension.de
*/

$cpd_dir_name = 'count-per-day';
$cpd_version = '2.17';

/**
 * include GeoIP addon
 */
$cpd_path = str_replace('/', DIRECTORY_SEPARATOR, ABSPATH.PLUGINDIR.'/'.$cpd_dir_name.'/');

if ( file_exists($cpd_path.'geoip/geoip.php') )
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
var $installed = false; // CpD installed in subblogs?

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
	
	// use local time, not UTC
	get_option('gmt_offset');
	
	$this->options = get_option('count_per_day');
	
	// manual debug mode
	if ( !empty($_GET['debug']) && WP_DEBUG )
		$this->options['debug'] = 1;
	
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
	{
		wp_enqueue_script('jquery');
		add_action('wp_footer', array(&$this,'addAjaxScript'));
	}

	// widget on dashboard page
	if (is_admin())
		add_action('wp_dashboard_setup', array(&$this, 'dashboardWidgetSetup'));
	
	// CpD dashboard page
	if (is_admin())
		add_filter('screen_layout_columns', array(&$this, 'screenLayoutColumns'), 10, 2);
	
	// CpD dashboard
	if (is_admin())
		add_action('admin_menu', array(&$this, 'setAdminMenu'));
	
	// column page list
	if (is_admin())
		add_action('manage_pages_custom_column', array(&$this, 'cpdColumnContent'), 10, 2);
		add_filter('manage_pages_columns', array(&$this, 'cpdColumn'));
	
	// column post list
	if (is_admin())
		add_action('manage_posts_custom_column', array(&$this, 'cpdColumnContent'), 10, 2);
		add_filter('manage_posts_columns', array(&$this, 'cpdColumn'));
	
	// locale support
	if (defined('WPLANG') && function_exists('load_plugin_textdomain'))
		load_plugin_textdomain('cpd', false, $cpd_dir_name.'/locale');
		 
	// adds stylesheet
	if (is_admin())
		add_action('admin_head', array(&$this, 'addCss'));
	if ( empty($this->options['no_front_css']) )
		add_action('wp_head', array(&$this, 'addCss'));
	
	// adds javascript
	if (is_admin())
		add_action('admin_head', array(&$this, 'addJS'));
	
	// widget setup
	add_action('widgets_init', array( &$this, 'register_widgets'));
	
	// activation hook
	register_activation_hook(ABSPATH.PLUGINDIR.'/count-per-day/counter.php', array(&$this, 'checkVersion'));
	
	// update hook
	if ( function_exists('register_update_hook') )
		register_update_hook(ABSPATH.PLUGINDIR.'/count-per-day/counter.php', array(&$this, 'checkVersion'));
	
	// uninstall hook
	register_uninstall_hook($cpd_path.'counter.php', 'count_per_day_uninstall');
	
	// query times debug
	if ( $this->options['debug'] )
	{
		add_action('wp_footer', array(&$this, 'showQueries'));
		add_action('admin_footer', array(&$this, 'showQueries'));
	}
	
	// add shortcode support
	$this->addShortcodes();
	
	// thickbox in backend only
	if ( strpos($_SERVER['SCRIPT_NAME'], '/wp-admin/') !== false )
	{
		wp_enqueue_script('thickbox');
		wp_enqueue_script('cpd_flot', $this->dir.'/js/jquery.flot.min.js', 'jQuery');
	}
	
	// Session
	add_action('init', array(&$this, 'startSession'));
	
	$this->connectDB();
}

/**
 * starts session to provide WP variables to "addons"
 */
function startSession()
{
	if (!session_id())
		session_start();
	$_SESSION['cpd_wp'] = ABSPATH;
}

/**
 * direct database connection without wordpress functions saves memory
 */
function connectDB()
{
	global $wpdb;

	$this->dbcon = @mysql_connect(DB_HOST, DB_USER, DB_PASSWORD, true);
	@mysql_select_db(DB_NAME, $this->dbcon);
	$this->getQuery("SET NAMES '".$wpdb->charset."'", 'SET NAMES'.__LINE__);
}

/**
 * get results per own connection (shows time for debug)
 * @param string $sql SQL statement
 * @param string $func show this name before time
 * @return MySql result
 */
function getQuery( $sql, $func = '' )
{
	global $wpdb;
	
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
	$res = $this->getQuery("SELECT COUNT(*) FROM ".CPD_C_TABLE." WHERE page='$page'", 'show'.__LINE__);
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
	global $wpdb, $wp_query, $cpd_path, $cpd_geoip, $userdata, $table_prefix;
	
	if ( $this->options['debug'] )
		$this->queries[] = 'called Function: <b style="color:blue">count</b> page: <code>'.$page.'</code>';
	
	if ( $page == 'x' )
		// normal counter
		$page = $this->getPostID();
	else
		// ajax counter on cached pages
		$page = intval($page);
	
	// get userlevel from role
	$caps = $table_prefix.'capabilities';
	if ( isset($userdata->$caps) )
	{
		$role = $userdata->$caps;
		if ($role['administrator'])		$userlevel = 10;
		else if ($role['editor'])		$userlevel = 7;
		else if ($role['author'])		$userlevel = 2;
		else if ($role['contributor'])	$userlevel = 1;
		else if ($role['subscriber'])	$userlevel = 0;
		else							$userlevel = -1;
	}
	else
		$userlevel = -1;

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
		$client = ($this->options['referers']) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$referer = ($this->options['referers'] && isset($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : '';
		$date = date_i18n('Y-m-d');
		
		// new visitor on page?
		$res = $this->getQuery("SELECT count(*) FROM ".CPD_C_TABLE." WHERE ip=INET_ATON('$userip') AND date='$date' AND page='$page'", 'count check'.__LINE__);
		$row = mysql_fetch_row($res);
		if ( $row[0] == 0 )
		{
			// save count
			if ( $cpd_geoip )
			{
				// with GeoIP addon save country
				$gi = cpd_geoip_open($cpd_path.'geoip/GeoIP.dat', GEOIP_STANDARD);
				$country = strtolower(cpd_geoip_country_code_by_addr($gi, $userip));
				$this->getQuery($wpdb->prepare("INSERT INTO ".CPD_C_TABLE." (page, ip, client, date, country, referer)
				VALUES (%s, INET_ATON(%s), %s, %s, %s, %s)", $page, $userip, $client, $date, $country, $referer), 'count insert'.__LINE__);
			}
			else
			{
				// without country
				$this->getQuery($wpdb->prepare("INSERT INTO ".CPD_C_TABLE." (page, ip, client, date, referer)
				VALUES (%s, INET_ATON(%s), %s, %s, %s)", $page, $userip, $client, $date, $referer), 'count insert'.__LINE__);
			}
		}
		
		// online counter
		$timestamp = time();
		$this->getQuery($wpdb->prepare("REPLACE INTO ".CPD_CO_TABLE." (timestamp, ip, page)
			VALUES ( %s, INET_ATON(%s), %s)", $timestamp, $userip, $page), 'count online'.__LINE__);
	}
}

/**
 * deletes old online user 
 */
function deleteOnlineCounter()
{
	$timeout = time() - $this->options['onlinetime'];
	$this->getQuery("DELETE FROM ".CPD_CO_TABLE." WHERE timestamp < $timeout", 'deleteOnlineCounter'.__LINE__);
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
	
	// variables for subblogs
	$cpd_c = $table_prefix.'cpd_counter';
	$cpd_o = $table_prefix.'cpd_counter_useronline';
	$cpd_n = $table_prefix.'cpd_notes';

	if (!empty ($wpdb->charset))
		$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
	if (!empty ($wpdb->collate))
		$charset_collate .= " COLLATE {$wpdb->collate}";
 	
	// table "counter"
	$sql = "CREATE TABLE IF NOT EXISTS `$cpd_c` (
	`id` int(10) NOT NULL auto_increment,
	`ip` int(10) unsigned NOT NULL,
	`client` varchar(150) NOT NULL,
	`date` date NOT NULL,
	`page` mediumint(9) NOT NULL,
	`referer` varchar(100) NOT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_page` (`page`),
	KEY `idx_dateip` (`date`,`ip`) )
	$charset_collate;";
	$this->getQuery($sql, __LINE__);
	
	// update fields in old table
	$field = $this->getQuery( "SHOW FIELDS FROM `$cpd_c` LIKE 'ip'" );
	$row = mysql_fetch_array($field);
	if ( strpos(strtolower($row['Type']), 'int') === false )
	{
		$queries = array (
		"ALTER TABLE `$cpd_c` ADD `ip2` INT(10) UNSIGNED NOT NULL AFTER `ip`",
		"UPDATE `$cpd_c` SET ip2 = INET_ATON(ip)",
		"ALTER TABLE `$cpd_c` DROP `ip`",
		"ALTER TABLE `$cpd_c` CHANGE `ip2` `ip` INT( 10 ) UNSIGNED NOT NULL",
		"ALTER TABLE `$cpd_c` CHANGE `date` `date` date NOT NULL",
		"ALTER TABLE `$cpd_c` CHANGE `page` `page` mediumint(9) NOT NULL");
		
		foreach ( $queries as $sql)
			$this->getQuery($sql, 'update old fields'.__LINE__);
	}
	
	// make new keys
	$keys = $this->getQuery( "SHOW KEYS FROM `$cpd_c`" );
	$s = array();
	while ( $row = mysql_fetch_array($keys) )
		if ( $row['Key_name'] != 'PRIMARY' )
			$s[] = 'DROP INDEX `'.$row['Key_name'].'`';
	$s = array_unique($s);
		
	$sql = "ALTER TABLE `$cpd_c` ";
	if ( sizeof($s) )
		$sql .= implode(',', $s).', ';
	$sql .= 'ADD KEY `idx_dateip` (`date`,`ip`), ADD KEY `idx_page` (`page`)';
	$this->getQuery($sql);
	
	// if GeoIP installed we need row "country"
	if ( class_exists('CpdGeoIp') )
	{
		$this->getQuery("SELECT country FROM `$cpd_c`");
		if ((int) mysql_errno() == 1054)
			$this->getQuery("ALTER TABLE `$cpd_c` ADD `country` CHAR(2) NOT NULL");
	}
	
	// referer
	$this->getQuery("SELECT referer FROM `$cpd_c`");
		if ((int) mysql_errno() == 1054)
			$this->getQuery("ALTER TABLE `$cpd_c` ADD `referer` VARCHAR(100) NOT NULL");
	
	// table "counter-online"
	$sql = "CREATE TABLE IF NOT EXISTS `$cpd_o` (
	`timestamp` int(15) NOT NULL,
	`ip` int(10) UNSIGNED NOT NULL,
	`page` int(11) NOT NULL,
	PRIMARY KEY (`ip`) )
	$charset_collate;";
	$this->getQuery($sql);
			
	// table "notes"
	$sql = "CREATE TABLE IF NOT EXISTS `$cpd_n` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`date` date NOT NULL,
	`note` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `date` (`date`) )
	$charset_collate;";
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
	$thisMonth = date_i18n('F');
	?>
	<ul>
		<li><b><span><?php $this->getReadsAll(); ?></span></b><?php _e('Total reads', 'cpd') ?>:</li>
		<li><b><?php $this->getReadsToday(); ?></b><?php _e('Reads today', 'cpd') ?>:</li>
		<li><b><?php $this->getReadsYesterday(); ?></b><?php _e('Reads yesterday', 'cpd') ?>:</li>
		<li><b><?php $this->getReadsLastWeek(); ?></b><?php _e('Reads last week', 'cpd') ?>:</li>
		<li><b><?php $this->getReadsThisMonth(); ?></b><?php _e('Reads', 'cpd') ?> <?php echo $thisMonth ?>:</li>
		<li><b><span><?php $this->getUserAll(); ?></span></b><?php _e('Total visitors', 'cpd') ?>:</li>
		<li><b><span><?php $this->getUserOnline(); ?></span></b><?php _e('Visitors currently online', 'cpd') ?>:</li>
		<li><b><?php $this->getUserToday(); ?></b><?php _e('Visitors today', 'cpd') ?>:</li>
		<li><b><?php $this->getUserYesterday(); ?></b><?php _e('Visitors yesterday', 'cpd') ?>:</li>
		<li><b><?php $this->getUserLastWeek(); ?></b><?php _e('Visitors last week', 'cpd') ?>:</li>
		<li><b><?php $this->getUserThisMonth(); ?></b><?php _e('Total visitors', 'cpd') ?> <?php echo $thisMonth ?>:</li>
		<li><b><?php $this->getUserPerDay($this->options['dashboard_last_days']); ?></b>&Oslash; <?php _e('Visitors per day', 'cpd') ?>:</li>
		<li><b><?php $this->getFirstCount(); ?></b><?php _e('Counter starts on', 'cpd') ?>:</li>
	</ul>
	<?php
}

/**
 * creates the big chart with reads and visotors
 * @param int $limit last x days
 */
function getFlotChart( $limit = 0 )
{
	global $table_prefix;
	if ( $limit == 0 )
		$limit = (!empty($this->options['chart_days'])) ? $this->options['chart_days'] : 30;
	$limit -= 1;
	
	 // last day
	$end_sql = (isset($_GET['cpd_chart_start'])) ? $_GET['cpd_chart_start'] : date_i18n('Y-m-d');
	$end_time = strtotime($end_sql);
	$end_str = mysql2date(get_option('date_format'), $end_sql);
	
	// first day
	$start_time = $end_time - $limit * 86400;
	$start_sql = date('Y-m-d', $start_time);
	$start_str = mysql2date(get_option('date_format'), $start_sql);
	
	// buttons
	$button_back = date('Y-m-d', $start_time - 86400);
	$button_forward = date('Y-m-d', $end_time + 86400 * ($limit + 1));
	
	// create data array
	$data = array();
	for  ( $day = $start_time; $day < $end_time; $day = $day + 86400 )
		$data[$day] = array(0, 0);

	// reads
	$sql = "
	SELECT	COUNT(*) count, c.date
	FROM	".CPD_C_TABLE." AS c
	WHERE	c.date BETWEEN '$start_sql' AND '$end_sql'
	GROUP	BY c.date";
	$res = $this->getQuery($sql, 'ChartReads'.__LINE__);
	if ( @mysql_num_rows($res) )
		while ( $row = mysql_fetch_array($res) )
			$data[strtotime($row['date'])][0] = $row['count'];
	
	// visitors
	$sql = "
	SELECT COUNT(*) count, t.date
	FROM (	SELECT	COUNT(*) count, date
			FROM	".CPD_C_TABLE."
			GROUP	BY date, ip
			) AS t
	WHERE	t.date BETWEEN '$start_sql' AND '$end_sql'
	GROUP	BY t.date";
	$res = $this->getQuery($sql, 'ChartVisitors'.__LINE__);
	if ( @mysql_num_rows($res) )
		while ( $row = mysql_fetch_array($res) )
			$data[strtotime($row['date'])][1] = $row['count'];
	
	// fill data array
	$reads = array();
	$visitors = array();
	foreach ( $data as $day => $values )
	{
		$reads[] = '['.$day.'000,'.$values[0].']';
		$visitors[] = '['.$day.'000,'.$values[1].']';
	}
	$reads_line = '['.implode(',', $reads).']';
	$visitors_line = '['.implode(',', $visitors).']';
	?>

	<div id="cpd-flot-place">
		<div id="cpd-flot-choice">
			<div style="float:left">
				<a href="index.php?page=cpd_metaboxes&amp;cpd_chart_start=<?php echo $button_back ?>" class="button">&lt;</a>
				<?php echo $start_str ?>
			</div>
			<div style="float:right">
				<?php echo $end_str ?>
				<a href="index.php?page=cpd_metaboxes&amp;cpd_chart_start=<?php echo $button_forward ?>" class="button">&gt;</a>
			</div>
		</div>
		<div id="cpd-flot" style="height:<?php echo (!empty($this->options['chart_height'])) ? $this->options['chart_height'] : 200; ?>px"></div>
	</div>
	
	<script type="text/javascript">
	//<![CDATA[
	jQuery(function() {
		var placeholder = jQuery("#cpd-flot");
		var choiceContainer = jQuery("#cpd-flot-choice");
		var colors = ['blue', 'red'];
		var datasets = {
			'reads': { data: <?php echo $reads_line ?>, label: '<?php _e('Reads per day', 'cpd') ?>' },
			'visitors' : { data: <?php echo $visitors_line ?>, label: '<?php _e('Visitors per day', 'cpd') ?>' }
			};
			
		// Checkboxen
		var i = 0;
		jQuery.each(datasets, function(key, val) {
			val.color = i;
			++i;
			choiceContainer.append(
				'<input type="checkbox" name="' + key + '" checked="checked" id="id' + key + '" \/> '
				+ '<label style="padding-left:3px;margin-right:10px;border-left:14px solid ' + colors[val.color] + '" for="id' + key + '">' + val.label + '<\/label> ');
		});
		choiceContainer.find("input").click(plotAccordingToChoices);

		function showTooltip(x, y, contents) {
			jQuery('<div id="cpd-tooltip">' + contents + '<\/div>').css({ top:y-70, left:x-80 }).appendTo("body").fadeIn(200);
		}

		var previousPoint = null;
		jQuery(placeholder).bind("plothover", function (event, pos, item) {
			if (item) {
				if (previousPoint != item.datapoint) {
					previousPoint = item.datapoint;
					jQuery("#cpd-tooltip").remove();
					var dx = new Date(item.datapoint[0]);
					var datum = dx.getDate() + '.' + (dx.getMonth() + 1) + '.' + dx.getFullYear();
					showTooltip(item.pageX, item.pageY,
						datum + '<br\/><b>' + item.datapoint[1] + '<\/b> ' + item.series.label);
				}
			}
			else {
				jQuery("#cpd-tooltip").remove();
				previousPoint = null;			
			}
		});

	    function weekendAreas(axes) {
	        var markings = [];
	        var d = new Date(axes.xaxis.min);
	        d.setUTCDate(d.getUTCDate() - ((d.getUTCDay() + 1) % 7));
	        d.setUTCSeconds(0);
	        d.setUTCMinutes(0);
	        d.setUTCHours(0);
	        var i = d.getTime();
	        do {
	            markings.push({ xaxis: { from: i, to: i + 2 * 24 * 60 * 60 * 1000 } });
	            i += 7 * 24 * 60 * 60 * 1000;
	        } while (i < axes.xaxis.max);
	        return markings;
	    }
		
		function plotAccordingToChoices() {
			var data = [];
			choiceContainer.find("input:checked").each(function () {
				var key = jQuery(this).attr("name");
				if (key && datasets[key])
					data.push(datasets[key]);
			});

			if (data.length > 0)
				jQuery.plot(jQuery(placeholder), data , { 
					xaxis: { mode: 'time', timeformat: '%d.%m.%y' },
					legend: { show: false },
					colors: colors,
					lines: { fill: true	},
					grid: { borderWidth: 1, borderColor: '#ccc', hoverable: true, markings: weekendAreas }
				});
		}

		plotAccordingToChoices();
	});
	//]]>
	</script>
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
	
	$res = $this->getQuery($sql, 'Chart'.__LINE__);
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
function getUserOnline( $frontend = false, $country = false )
{
	global $cpd_geoip, $cpd_path;
	$c = '';
	
	if ( $cpd_geoip && $country )
	{
		// map link
		if (!$frontend && file_exists($cpd_path.'map/map.php') )
			$c .= '<div style="margin: 5px 0 10px 0;"><a href="'.$this->dir.'/map/map.php?map=online'
				 .'&amp;KeepThis=true&amp;TB_iframe=true" title="Count per Day - '.__('Map', 'cpd').'" class="thickbox button">'.__('Map', 'cpd').'</a></div>';
		
		// countries list
		$geoip = new GeoIPCpd();
		$gi = cpd_geoip_open($cpd_path.'geoip/GeoIP.dat', GEOIP_STANDARD);
		
		$res = $this->getQuery("SELECT INET_NTOA(ip) AS ip FROM ".CPD_CO_TABLE, 'getUserOnline'.__LINE__);
		if ( @mysql_num_rows($res) )
		{
			$vo = array();
			while ( $r = mysql_fetch_array($res) )
			{
				$country = strtolower(cpd_geoip_country_code_by_addr($gi, $r['ip']));
				$id = $geoip->GEOIP_COUNTRY_CODE_TO_NUMBER[strtoupper($country)];
				if ( empty($id) )
				{
					$name = '???';
					$country = 'unknown';
				}
				else
					$name = $geoip->GEOIP_COUNTRY_NAMES[$id];
				$count = (isset($vo[$country])) ? $vo[$country][1] + 1 : 1;
				$vo[$country] = array($name, $count);
			}
			
			$c .= '<ul class="cpd_front_list">';
			foreach ( $vo as $k => $v )
				$c .= '<li><b>'.$v[1].'</b><div class="cpd-flag cpd-flag-'.$k.'"></div> '.$v[0].'&nbsp;</li>'."\n";
			$c .= "</ul>\n";
		}
	}
	else
	{
		// number only
		$res = $this->getQuery("SELECT count(*) FROM ".CPD_CO_TABLE, 'getUserOnline'.__LINE__);
		$row = mysql_fetch_row($res);
		$c = $row[0];
	}

	if ($frontend)
		return $c;
	else
		echo $c;
}

/**
 * shows all visitors
 */
function getUserAll( $frontend = false )
{
	$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." GROUP BY date, ip", 'getUserAll'.__LINE__);
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
	$res = $this->getQuery("SELECT COUNT(*) FROM ".CPD_C_TABLE, 'getReadsAll'.__LINE__);
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
	$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." WHERE date = '$date' GROUP BY ip", 'getUserToday'.__LINE__);
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
	$res = $this->getQuery("SELECT COUNT(*) FROM ".CPD_C_TABLE." WHERE date = '$date'", 'getReadsToday'.__LINE__);
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
	$date = date_i18n('Y-m-d', current_time('timestamp')-86400);
	$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." WHERE date = '$date' GROUP BY ip", 'getUserYesterday'.__LINE__);
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
	$date = date_i18n('Y-m-d', current_time('timestamp')-86400);
	$res = $this->getQuery("SELECT COUNT(*) FROM ".CPD_C_TABLE." WHERE date = '$date'", 'getReadsYesterday'.__LINE__);
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
	$date = date_i18n('Y-m-d', current_time('timestamp')-86400*7);
	$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." WHERE date >= '$date' GROUP BY date, ip;", 'getUserLastWeek'.__LINE__);
	$c = mysql_num_rows($res);
	if ($frontend)
		return $c;
	else
		echo $c;
}

/**
 * shows last week reads (last 7 days)
 */
function getReadsLastWeek( $frontend = false )
{
	$date = date_i18n('Y-m-d', current_time('timestamp')-86400*7);
	$res = $this->getQuery("SELECT COUNT(*) FROM ".CPD_C_TABLE." WHERE date >= '$date';", 'getReadsLastWeek'.__LINE__);
	$row = mysql_fetch_row($res);
	if ($frontend)
		return $row[0];
	else
		echo $row[0];
}

/**
 * shows this month visitors
 */
function getUserThisMonth( $frontend = false )
{
	$first = date_i18n('Y-m-', current_time('timestamp')).'01';
	$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." WHERE date >= '$first' GROUP BY date, ip;", 'getUserThisMonth'.__LINE__);
	$c = mysql_num_rows($res);
	if ($frontend)
		return $c;
	else
		echo $c;
}

/**
 * shows this month reads
 */
function getReadsThisMonth( $frontend = false )
{
	$first = date_i18n('Y-m-', current_time('timestamp')).'01';
	$res = $this->getQuery("SELECT COUNT(*) FROM ".CPD_C_TABLE." WHERE date >= '$first';", 'getReadsThisMonth'.__LINE__);
	$row = mysql_fetch_row($res);
	if ($frontend)
		return $row[0];
	else
		echo $row[0];
}

/**
 * shows visitors per month
 */
function getUserPerMonth( $frontend = false )
{
	$m = $this->getQuery("SELECT LEFT(date,7) FROM ".CPD_C_TABLE." GROUP BY year(date), month(date) ORDER BY date DESC", 'getUserPerMonths'.__LINE__);
	$r = '<ul class="cpd_front_list">';
	$d = array();
	$i = 1;
	while ( $row = mysql_fetch_row($m) )
	{
		$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." WHERE LEFT(date,7) = '".$row[0]."' GROUP BY date, ip", 'getUserPerMonth'.__LINE__);
		$r .= '<li><b>'.mysql_num_rows($res).'</b> '.$row[0].'</li>'."\n";
		$d[] = '[-'.$i++.','.mysql_num_rows($res).']';
	}
	$r .= '</ul>';
	if ($frontend)
		return $r;
	else
	{
		$r = $this->includeChartJS( 'cpd-flot-userpermonth', $d, $r );
		echo $r;
	}
}

/**
 * shows reads per month
 */
function getReadsPerMonth( $frontend = false )
{
	$res = $this->getQuery("SELECT COUNT(*), LEFT(date,7) FROM ".CPD_C_TABLE." GROUP BY year(date), month(date) ORDER BY date DESC", 'getReadsPerMonths'.__LINE__);
	$r = '<ul class="cpd_front_list">';
	$d = array();
	$i = 1;
	while ( $row = mysql_fetch_row($res) )
	{
		$r .= '<li><b>'.$row[0].'</b> '.$row[1].'</li>'."\n";
		$d[] = '[-'.$i++.','.$row[0].']';
	}
	$r .= '</ul>';
	if ($frontend)
		return $r;
	else
	{
		$r = $this->includeChartJS( 'cpd-flot-readspermonth', $d, $r );
		echo $r;
	}
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
	$r = $this->getUserPer_SQL( $sql, 'getUserPerPost'.__LINE__, $frontend );
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
		$res = $this->getQuery("SELECT date FROM ".CPD_C_TABLE." ORDER BY date LIMIT 1", 'getFirstCount'.__LINE__);
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
		$datemin = date_i18n('Y-m-d', current_time('timestamp') - ($days + 1) * 86400);
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

	$res = $this->getQuery("SELECT 1 FROM ".CPD_C_TABLE." WHERE date > '$datemin' AND date < '$datemax' GROUP BY ip, date", 'getUserPerDay'.__LINE__);
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
	$date = date_i18n('Y-m-d', current_time('timestamp') - 86400 * $days);
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
 * gets Post_IDs of most visited pages in last days with category filter
 * @param integer $days days to calc (last days)
 * @param integer $limit count of posts (last posts)
 * @param array/integer $cats IDs of category to filter
 * @param boolean $return_array returns an array with Post-ID and title, otherwise comma separated list of Post-IDs
 * @return string/array list of Post-IDs
 */
function getMostVisitedPostIDs( $days = 365, $limit = 10, $cats = false, $return_array = false )
{
	global $wpdb;
	$date = date_i18n('Y-m-d', current_time('timestamp') - 86400 * $days);
	if ( is_array($cats) )
	{
		if ( is_object($cats[0]) )
		{
			$catIDs = array();
			foreach( $cats as $cat )
				$catIDs[] = $cat->term_id;
		}
		else
			$catIDs = (array) $cats;
		$cats = implode(',', $catIDs);
	}
	$cat_filter = ($cats) ? 'AND x.term_id IN ('.$cats.')' : '';
	
	$q1 = ($return_array) ? ', p.post_title' : '';
	$q2 = ($return_array) ? ' LEFT JOIN '.$wpdb->posts.' p ON p.ID = c.page ' : '';
	
	$sql = "
	SELECT	COUNT(c.id) count,
			c.page post_id
			$q1
	FROM	".CPD_C_TABLE." c
	$q2
	LEFT	JOIN ".$wpdb->term_relationships." r 
			ON r.object_id = c.page
	LEFT	JOIN ".$wpdb->term_taxonomy." x
			ON x.term_taxonomy_id = r.term_taxonomy_id
	WHERE	c.date >= '$date'
	$cat_filter
	GROUP	BY c.page
	ORDER	BY count DESC
	LIMIT	$limit";
	$res = $this->getQuery($sql, 'getMostVisitedPostIDs'.__LINE__);

	$ids = array();
	if ( @mysql_num_rows($res) )
		while ( $row = mysql_fetch_array($res) )
		{
			if ( $return_array )
				$ids[] = array('id' => $row['post_id'], 'title' => $row['post_title'], 'count' => $row['count']);
			else
				$ids[] = $row['post_id'];
		}

	if ( $return_array )
		return $ids;
	else
		return implode(',', $ids);
}

/**
 * shows visited pages at given day
 * @param integer $date day in mySql date format yyyy-mm-dd
 * @param integer $limit count of posts (last posts)
 * @param boolean $show_form show form for date selection
 * @param boolean $show_notes show button to add notes in form
 */
function getVisitedPostsOnDay( $date = 0, $limit = 0, $show_form = true, $show_notes = true, $frontend = false )
{
	global $wpdb, $cpd_path, $table_prefix, $userdata;
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
	
	if ( $show_form )
	{
		echo '<form action="" method="post">
			  <input name="daytoshow" value="'.$date.'" size="10" />
			  <input type="submit" name="showday" value="'.__('Show').'" class="button" />';
		if ( $show_notes )	
			echo ' <a href="'.$this->dir.'/notes.php?KeepThis=true&amp;TB_iframe=true" title="Count per Day - '.__('Notes', 'cpd').'" class="button thickbox">'.__('Notes', 'cpd').'</a> ';
		echo '</form>';
	}

	if ( isset($note) )
		echo '<p style="background:#eee; padding:2px;">'.$note.'</p>';

	$r = $this->getUserPer_SQL( $sql, 'getVisitedPostsOnDay', $frontend );
	
	if ($frontend)
		return $r;
	else
		echo $r; 		
}

/**
 * shows little browser statistics
 */
function getClients( $frontend = false )
{
	global $wpdb;
	$c_string = $this->options['clients'];
	$clients = explode(',', $c_string);
	
	$res = $this->getQuery("SELECT COUNT(*) count FROM ".CPD_C_TABLE, 'getClients_all'.__LINE__);
	$row = @mysql_fetch_row($res);
	$all = max(1, $row[0]);
	$rest = 100;
	$r = '<ul id="cpd_clients" class="cpd_front_list">';
	foreach ($clients as $c)
	{
		$c = trim($c);
		$res = $this->getQuery("SELECT COUNT(*) count FROM ".CPD_C_TABLE." WHERE client like '%".$c."%'", 'getClients_'.$c.'_'.__LINE__);
		$row = @mysql_fetch_row($res);
		$percent = number_format(100 * $row[0] / $all, 0);
		$rest -= $percent;
		$r .= '<li class="cpd-client-logo cpd-client-'.strtolower($c).'">'.$c.'<b>'.$percent.' %</b></li>';
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
 * shows top referrers
 */
function getReferers( $limit = 0, $frontend = false, $days = 0 )
{
	global $wpdb;
	if ( $limit == 0 )
		$limit = $this->options['dashboard_referers'];
	if ( $days == 0 )
		$days = $this->options['referers_last_days'];
	
	// local url filter 
	$dayfiltre = "AND date > DATE_SUB('".date_i18n('Y-m-d')."', INTERVAL $days DAY)";
		
	$localref = ($this->options['localref']) ? '' : " AND referer NOT LIKE '".get_bloginfo('url')."%' ";
	$res = $this->getQuery("SELECT COUNT(*) count, referer FROM ".CPD_C_TABLE." WHERE referer > '' $dayfiltre $localref GROUP BY referer ORDER BY count DESC LIMIT $limit", 'getReferers'.__LINE__);
		$r =  '<small>'.sprintf(__('The %s referrers in last %s days:', 'cpd'), $limit, $days).'<br/>&nbsp;</small>';
	$r .= '<ul id="cpd_referrers" class="cpd_front_list">';
	if ( @mysql_num_rows($res) )
		while ( $row = mysql_fetch_array($res) )
		{
			$ref =  str_replace('&', '&amp;', $row['referer']);
			$ref2 = str_replace('http://', '', $ref);
			$r .= '<li><a href="'.$ref.'">'.$ref2.'</a><b>'.$row['count'].'</b></li>';
		}
	$r .= '</ul>';
	
	if ($frontend)
		return $r;
	else
		echo $r;
}

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
	return $this->getQuery($sql, 'getMassBots'.__LINE__);
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
	$m = $this->getQuery($sql, $name.__LINE__);
	$r = '<ul class="cpd_front_list">';
	while ( $row = mysql_fetch_assoc($m) )
	{
		$r .= '<li><b>'.$row['count'].'</b>';
		// link only for editors in backend
		if ( isset($userdata->user_level) && intval($userdata->user_level) >= 7 && !$frontend)
		{
			if ( $row['post_id'] > 0 )
				$r .= '<a href="post.php?action=edit&amp;post='.$row['post_id'].'"><img src="'.$this->getResource('cpd_pen.png').'" alt="[e]" title="'.__('Edit Post').'" style="width:9px;height:12px;" /></a> '
					.'<a href="'.$this->dir.'/userperspan.php?page='.$row['post_id'].'&amp;KeepThis=true&amp;TB_iframe=true" class="thickbox" title="Count per Day"><img src="'.$this->getResource('cpd_calendar.png').'" alt="[v]" style="width:12px;height:12px;" /></a> ';
			else
				$r .= '<img src="'.$this->getResource('cpd_trans.png').'" alt="" style="width:25px;height:12px;" /> ';
		}
		
		$r .= '<a href="'.get_bloginfo('url');
		if ( $row['post_id'] < 0 && $row['tax'] == 'category' )
			//category
			$r .= '?cat='.abs($row['post_id']).'">- '.$row['tag_cat_name'].' ('.__('Category').') -';
		else if ( $row['post_id'] < 0 )
			// tag
			$r .= '?tag='.$row['tag_cat_slug'].'">- '.$row['tag_cat_name'].' ('.__('Tag').') -';
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
			$this->getQuery('DELETE FROM '.CPD_C_TABLE.' WHERE INET_NTOA(ip) LIKE \''.$ip.'%\'', 'clenaDB_ip'.__LINE__);
	
	// delete by client
	foreach ($bots as $bot)
		$this->getQuery("DELETE FROM ".CPD_C_TABLE." WHERE client LIKE '%$bot%'", 'cleanDB_client'.__LINE__);
	
	// delete if a previously countered page was deleted
	$this->getQuery("DELETE FROM ".CPD_C_TABLE." WHERE page NOT IN ( SELECT id FROM ".$wpdb->posts.") AND page > 0", 'cleanDB_delPosts'.__LINE__);
	
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
	if( $file == $cpd_dir_name.'/counter.php'
		&& strpos( $_SERVER['SCRIPT_NAME'], '/network/') === false ) // not on network plugin page
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
	global $cpd_version;
	
	$o = get_option('count_per_day', array());
	$onew = array(
	'version'				=> $cpd_version,
	'onlinetime'			=> (isset($o['onlinetime'])) ? $o['onlinetime'] : 300,
	'user'					=> (isset($o['user'])) ? $o['user'] : 0,
	'user_level'			=> (isset($o['user_level'])) ? $o['user_level'] : 0,
	'autocount'				=> (isset($o['autocount'])) ? $o['autocount'] : 1,
	'bots'					=> (isset($o['bots'])) ? $o['bots'] : "bot\nspider\nsearch\ncrawler\nask.com\nvalidator\nsnoopy\nsuchen.de\nsuchbaer.de\nshelob\nsemager\nxenu\nsuch_de\nia_archiver\nMicrosoft URL Control\nnetluchs",
	'dashboard_posts'		=> (isset($o['dashboard_posts'])) ? $o['dashboard_posts'] : 20,
	'dashboard_last_posts'	=> (isset($o['dashboard_last_posts'])) ? $o['dashboard_last_posts'] : 20,
	'dashboard_last_days'	=> (isset($o['dashboard_last_days'])) ? $o['dashboard_last_days'] : 7,
	'show_in_lists'			=> (isset($o['show_in_lists'])) ? $o['show_in_lists'] : 1,
	'chart_days'			=> (isset($o['chart_days'])) ? $o['chart_days'] : 60,
	'chart_height'			=> (isset($o['chart_height'])) ? $o['chart_height'] : 100,
	'countries'				=> (isset($o['countries'])) ? $o['countries'] : 20,
	'startdate'				=> (isset($o['startdate'])) ? $o['startdate'] : '',
	'startcount'			=> (isset($o['startcount'])) ? $o['startcount'] : '',
	'startreads'			=> (isset($o['startreads'])) ? $o['startreads'] : '',
	'anoip'					=> (isset($o['anoip'])) ? $o['anoip'] : 0,
	'massbotlimit'			=> (isset($o['massbotlimit'])) ? $o['massbotlimit'] : 25,
	'clients'				=> (isset($o['clients'])) ? $o['clients'] : 'Firefox, MSIE, Chrome, Safari, Opera',
	'ajax'					=> (isset($o['ajax'])) ? $o['ajax'] : 0,
	'debug'					=> (isset($o['debug'])) ? $o['debug'] : 0,
	'referers'				=> (isset($o['referers'])) ? $o['referers'] : 1,
	'dashboard_referers'	=> (isset($o['dashboard_referers'])) ? $o['dashboard_referers'] : 20,
	'referers_last_days'	=> (isset($o['referers_last_days'])) ? $o['referers_last_days'] : 7,
	'chart_old'				=> (isset($o['chart_old'])) ? $o['chart_old'] : 0,
	'no_front_css'			=> (isset($o['no_front_css'])) ? $o['no_front_css'] : 0,
	'whocansee'				=> (isset($o['whocansee'])) ? $o['whocansee'] : 'publish_posts'
	);
	update_option('count_per_day', $onew);
}

/**
 * add counter column to page/post lists
 */
function cpdColumn($defaults)
{
	if ( $this->options['show_in_lists']  )
		$defaults['cpd_reads'] = '<img src="'.$this->getResource('cpd_menu.gif').'" alt="'.__('Reads', 'cpd').'" title="'.__('Reads', 'cpd').'" style="width:9px;height:12px;" />';
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
    	$res = $this->getQuery("SELECT COUNT(*) FROM ".CPD_C_TABLE." WHERE page='$id'", 'cpdColumn_'.$id.'_'.__LINE__);
    	$row = mysql_fetch_row($res);
		echo (int) $row[0];
    }
}

/**
 * gets image recource with given name
 */
function getResource( $r )
{
	return trailingslashit( $this->dir ).'img/'.$r;
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
	$this->pagehook = add_submenu_page('index.php', 'CountPerDay', $menutitle, $this->options['whocansee'], CPD_METABOX, array(&$this, 'onShowPage'));
	add_action('load-'.$this->pagehook, array(&$this, 'onLoadPage'));
}

/**
 * backlink to Plugin homepage
 */
function cpdInfo()
{
	global $cpd_version;
	
	$t = '<span style="white-space:nowrap">'.date_i18n('Y-m-d H:i').'</span>';
	echo '<p>Count per Day: <code>'.$cpd_version.'</code><br/>';
	printf(__('Time for Count per Day: <code>%s</code>.', 'cpd'), $t);
	echo '<br />'.__('Bug? Problem? Question? Hint? Praise?', 'cpd').' ';
	printf(__('Write a comment on the <a href="%s">plugin page</a>.', 'cpd'), 'http://www.tomsdimension.de/wp-plugins/count-per-day');
	echo '<br />'.__('License').': <a href="http://www.tomsdimension.de/postcards">Postcardware :)</a>';
	echo '<br /><a href="'.$this->dir.'/readme.txt?KeepThis=true&amp;TB_iframe=true" title="Count per Day - Readme.txt" class="thickbox"><strong>Readme.txt</strong></a></p>';
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
function getUserOnlineMeta() { $this->getUserOnline( false, true); }

/**
 * will be executed if wordpress core detects this page has to be rendered
 */
function onLoadPage()
{
	global $cpd_geoip;
	// needed javascripts
	wp_enqueue_script('common');
	wp_enqueue_script('wp-lists');
	if ( !$this->options['chart_old'] )
		wp_enqueue_script('postbox');

	// add the metaboxes
	add_meta_box('reads_at_all', __('Total visitors', 'cpd'), array(&$this, 'dashboardReadsAtAll'), $this->pagehook, 'cpdrow1', 'core');
	add_meta_box('user_online', __('Visitors online', 'cpd'), array(&$this, 'getUserOnlineMeta'), $this->pagehook, 'cpdrow1', 'default');
	add_meta_box('user_per_month', __('Visitors per month', 'cpd'), array(&$this, 'getUserPerMonth'), $this->pagehook, 'cpdrow2', 'default');
	add_meta_box('reads_per_month', __('Reads per month', 'cpd'), array(&$this, 'getReadsPerMonth'), $this->pagehook, 'cpdrow3', 'default');
	add_meta_box('reads_per_post', __('Visitors per post', 'cpd'), array(&$this, 'getUserPerPostMeta'), $this->pagehook, 'cpdrow3', 'default');
	add_meta_box('last_reads', __('Latest Counts', 'cpd'), array(&$this, 'getMostVisitedPostsMeta'), $this->pagehook, 'cpdrow4', 'default');
	add_meta_box('day_reads', __('Visitors per day', 'cpd'), array(&$this, 'getVisitedPostsOnDayMeta'), $this->pagehook, 'cpdrow4', 'default');
	add_meta_box('cpd_info', __('Plugin'), array(&$this, 'cpdInfo'), $this->pagehook, 'cpdrow1', 'low');
	if ( $this->options['referers'] )
	{
		add_meta_box('browsers', __('Browsers', 'cpd'), array(&$this, 'getClients'), $this->pagehook, 'cpdrow2', 'default');
		add_meta_box('referers', __('Referrer', 'cpd'), array(&$this, 'getReferersMeta'), $this->pagehook, 'cpdrow3', 'default');
	}
	if ( $this->options['chart_old'] )
	{
		add_meta_box('chart_visitors', __('Visitors per day', 'cpd'), array(&$this, 'dashboardChartVisitorsMeta'), $this->pagehook, 'cpdrow1', 'default');
		add_meta_box('chart_reads', __('Reads per day', 'cpd'), array(&$this, 'dashboardChartMeta'), $this->pagehook, 'cpdrow1', 'default');
	}
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
		if ( !$this->options['chart_old'] )
			$this->getFlotChart();
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
		$geoip = new GeoIPCpD();
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
				LIMIT $limit", 'getCountries'.__LINE__);
		else
			// reads
			$res = $this->getQuery("SELECT country, COUNT(*) c FROM ".CPD_C_TABLE." WHERE ip > 0 GROUP BY country ORDER BY c DESC LIMIT $limit", 'getCountries'.__LINE__);
		
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
 * gets a world map
 * @param string $what visitors|reads|online
 * @param int $width size
 * @param int $height size
 * @param int $min : 1 disable title, legend and zoombar
 */
function getMap( $what = 'visitors', $width = 500, $height = 340, $min = 0 )
{
	$divid = uniqid('cpdmap_');
	$dir = $this->dir.'/map/';
	?>
	<script type="text/javascript" src="<?php echo $dir ?>swfobject.js"></script>
	<div id="<?php echo $divid ?>" class="cpd_worldmap" style="width:<?php echo $width ?>px; height:<?php echo $height ?>px; background:#4499FF;">
		<strong>Flash content</strong>
	</div>
	<script type="text/javascript">
		// <![CDATA[
		var so = new SWFObject("<?php echo $dir ?>ammap.swf", "ammap", "100%", "100%", "8", "#4499FF");
		so.addVariable("path", "<?php echo $dir ?>");
		so.addVariable("settings_file", escape("<?php echo $dir ?>settings.xml.php?map=<?php echo $what ?>&min=<?php echo $min ?>"));
		so.addVariable("data_file", escape("<?php echo $dir ?>data.xml.php?map=<?php echo $what ?>&min=<?php echo $min ?>"));
		so.write("<?php echo $divid ?>");
		// ]]>
	</script>
	<?php
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
	add_shortcode('CPD_READS_LAST_WEEK', array( &$this, 'shortReadsLastWeek'));
	add_shortcode('CPD_READS_PER_MONTH', array( &$this, 'shortReadsPerMonth'));
	add_shortcode('CPD_READS_THIS_MONTH', array( &$this, 'shortReadsThisMonth'));
	add_shortcode('CPD_VISITORS_TOTAL', array( &$this, 'shortUserAll'));
	add_shortcode('CPD_VISITORS_ONLINE', array( &$this, 'shortUserOnline'));
	add_shortcode('CPD_VISITORS_TODAY', array( &$this, 'shortUserToday'));
	add_shortcode('CPD_VISITORS_YESTERDAY', array( &$this, 'shortUserYesterday'));
	add_shortcode('CPD_VISITORS_LAST_WEEK', array( &$this, 'shortUserLastWeek'));
	add_shortcode('CPD_VISITORS_THIS_MONTH', array( &$this, 'shortUserThisMonth'));
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
	add_shortcode('CPD_POSTS_ON_DAY', array( &$this, 'shortPostsOnDay'));
	add_shortcode('CPD_MAP', array( &$this, 'shortShowMap'));
}
function shortShow()			{ return $this->show('', '', false, false); }
function shortReadsTotal()		{ return $this->getReadsAll(true); }
function shortReadsToday()		{ return $this->getReadsToday(true); }
function shortReadsYesterday()	{ return $this->getReadsYesterday(true); }
function shortReadsThisMonth()	{ return $this->getReadsThisMonth(true); }
function shortReadsLastWeek()	{ return $this->getReadsLastWeek(true); }
function shortReadsPerMonth()	{ return $this->getReadsPerMonth(true); }
function shortUserAll()			{ return $this->getUserAll(true); }
function shortUserOnline()		{ return $this->getUserOnline(true); }
function shortUserToday()		{ return $this->getUserToday(true); }
function shortUserYesterday()	{ return $this->getUserYesterday(true); }
function shortUserLastWeek()	{ return $this->getUserLastWeek(true); }
function shortUserThisMonth()	{ return $this->getUserThisMonth(true); }
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
function shortPostsOnDay( $atts )
{
	extract( shortcode_atts( array(
		'date' => 0,
		'limit' => 0
	), $atts) );
	return $this->getVisitedPostsOnDay( $date, $limit, false, false, true );
}
function shortShowMap( $atts )
{
	extract( shortcode_atts( array(
		'width' => 500,
		'height' => 340,
		'what' => 'reads',
		'min' => 0
	), $atts) );
	return $this->getMap( $what, $width, $height, $min );
}

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
 * adds javascript to admin header
 */
function addJS()
{
	echo '<!--[if IE]><script type="text/javascript" src="'.$this->dir.'/js/excanvas.min.js"></script><![endif]-->'."\n";
}

/**
 * adds ajax script to count cached posts
 */
function addAjaxScript()
{
	$this->getPostID();
	echo <<< JSEND
<script type="text/javascript">
// Count per Day
//<![CDATA[
jQuery(document).ready( function($)
{
	jQuery.get('{$this->dir}/ajax.php?f=count&page={$this->page}', function(text)
	{
		var cpd_funcs = text.split('|');
		for(var i = 0; i < cpd_funcs.length; i++)
		{
			var cpd_daten = cpd_funcs[i].split('===');
			var cpd_fields = document.getElementsByName('cpd_number_' + cpd_daten[0].toLowerCase());
			for(var x = 0; x < cpd_fields.length; x++)
				cpd_fields[x].innerHTML = cpd_daten[1];
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
	global $cpd_path, $cpd_version;
	echo '<div style="margin:10px; padding-left:30px; border:1px red solid">
		<b>Count per Day - DEBUG: '.round($this->queries[0], 3).' s</b><ol>';
	echo '<li>'
		.'<b>Server:</b> '.$_SERVER['SERVER_SOFTWARE'].'<br/>'
		.'<b>PHP:</b> '.phpversion().'<br/>'
		.'<b>mySQL Server:</b> '.mysql_get_server_info($this->dbcon).'<br/>'
		.'<b>mySQL Client:</b> '.mysql_get_client_info().'<br/>'
		.'<b>WordPress:</b> '.get_bloginfo('version').'<br/>'
		.'<b>Count per Day:</b> '.$cpd_version.'<br/>'
		.'<b>Time for Count per Day:</b> '.date_i18n('Y-m-d H:i').'<br/>'
		.'<b>URL:</b> '.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'].'<br/>'
		.'<b>Referrer:</b> '.$_SERVER['HTTP_REFERER']
		.'</li>';
	echo '<li><b>POST:</b><br/>';
	var_dump($_POST);
	echo '<li><b>SESSION:</b><br/>';
	var_dump($_SESSION);
	echo '</li>';
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
	echo '</div>';
}

/**
 * checks installation in sub blogs 
 */
function checkVersion()
{
	global $wpdb;
	
	if ( function_exists('is_multisite') && is_multisite() )
	{
		// check if it is a network activation
		if ( isset($_GET['networkwide']) && ($_GET['networkwide'] == 1) )
		{
			$old_blog = $wpdb->blogid;
			$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));
			foreach ($blogids as $blog_id)
			{
				// create tables in all sub blogs
				switch_to_blog($blog_id);
				$this->createTables();
			}
			switch_to_blog($old_blog);
			return;
		}	
	}
	// create tables in main blog
	$this->createTables();
}

/**
 * calls widget class
 */
function register_widgets()
{
	register_widget('CountPerDay_Widget');
}

/**
 * adds charts to lists on dashboard
 * @param string $id HTML-id of the DIV
 * @param array $data data
 * @param string $html given list code to add the chart
 */
function includeChartJS( $id, $data, $html )
{
	if ( $this->options['chart_old'] )
		return $html;
	$d = array_reverse($data);
	$d = '[['.implode(',', $d).']]';
	$code = '<div id="'.$id.'" class="cpd-list-chart"></div>
		<script type="text/javascript">
		//<![CDATA[
		if (jQuery("#'.$id.'").width() > 0)
			jQuery(function(){jQuery.plot(jQuery("#'.$id.'"),'.$d.',{series:{lines:{fill:true,lineWidth:1}},colors:["red"],grid:{show:false}});});
		//]]>
		</script>
		'.$html;
	return $code;
}

} // class end



/**
  widget class
 */
class CountPerDay_Widget extends WP_Widget
{
	var $fields = array( 'title', 'order', 'show',
		'getreadsall', 'getreadstoday', 'getreadsyesterday', 'getreadslastweek', 'getreadsthismonth',
		'getuserall', 'getusertoday', 'getuseryesterday', 'getuserlastweek', 'getuserthismonth',
		'getuserperday', 'getuseronline', 'getfirstcount',
		'show_name',
		'getreadsall_name', 'getreadstoday_name', 'getreadsyesterday_name', 'getreadslastweek_name', 'getreadsthismonth_name',
		'getuserall_name', 'getusertoday_name', 'getuseryesterday_name', 'getuserlastweek_name', 'getuserthismonth_name',
		'getuserperday_name', 'getuseronline_name', 'getfirstcount_name' );
//	const CPDF = 'show,getReadsAll,getReadsToday,getReadsYesterday,getReadsLastWeek,getReadsThisMonth,getUserAll,getUserToday,getUserYesterday,getUserLastWeek,getUserThisMonth,getUserPerDay,getUserOnline,getFirstCount';
//	var $cpd_funcs;
	var $cpd_funcs = array ( 'show',
		'getReadsAll', 'getReadsToday', 'getReadsYesterday', 'getReadsLastWeek', 'getReadsThisMonth',
		'getUserAll', 'getUserToday', 'getUserYesterday', 'getUserLastWeek', 'getUserThisMonth',
		'getUserPerDay', 'getUserOnline', 'getFirstCount' );
	var $funcs;
	var $names;
	
	// export functions to ajax script
//	public static function getWidgetFuncs()
//	{
//		return explode(',', self::CPDF);
//	}
	
	// constructor
	function CountPerDay_Widget() {
//		$this->cpd_funcs = explode(',', self::CPDF);
		$this->funcs = array_slice( $this->fields, 2, 14);
		$this->names = array_slice( $this->fields, 16, 14);
		parent::WP_Widget('countperday_widget', 'Count per Day',
			array('description' => __('Statistics', 'cpd')), array('width' => 270) );	
	}
 
	// display widget
	function widget( $args, $instance )
	{
		global $count_per_day;
		
		extract($args, EXTR_SKIP);
		$title = empty($instance['title']) ? '&nbsp;' : apply_filters('widget_title', $instance['title']);
		echo $before_widget;
		if ( !empty( $title ) )
			echo $before_title.$title.$after_title;
			echo '<ul class="cpd">';
			$order = explode('|', $instance['order']);
			foreach ( $order as $k )
			{
				if ( $k && $instance[$k] == 1 )
				// checked only
				{
					if ( ($k == 'show' && is_singular()) || $k != 'show' )
					{
						$f = str_replace( $this->funcs, $this->cpd_funcs, $k );
						echo '<li class="cpd-l"><span id="cpd_number_'.$k.'" name="cpd_number_'.$k.'" class="cpd-r">';
						// parameters only for special functions
						if ( $f == 'getUserPerDay' )
							eval('echo $count_per_day->getUserPerDay('.$count_per_day->options['dashboard_last_days'].');');
						else if ( $f == 'show' )
							eval('echo $count_per_day->show("","",false,false);');
						else
							eval('echo $count_per_day->'.$f.'();');
						echo '</span>'.$instance[$k.'_name'].':</li>';
					}
				}
			}
			echo '</ul>';
		echo $after_widget;
	}
 
	// update/save function
	function update( $new_instance, $old_instance )
	{
		$instance = $old_instance;
		foreach ( $this->fields as $f )
			if ( isset($new_instance[strtolower($f)]) )
				$instance[strtolower($f)] = strip_tags($new_instance[strtolower($f)]);
			else
				$instance[strtolower($f)] = 0; // unchecked checkboxes
		return $instance;
	}
 
	// admin control form
	function form( $instance )
	{
		$default = 	array(
			'title' => 'Count per Day',
			'order' => implode('|', $this->funcs),
			'show' => 0,
			'getreadsall' => 0,
			'getreadstoday' => 0,
			'getreadsyesterday' => 0,
			'getreadslastweek' => 0,
			'getreadsthismonth' => 0,
			'getuserall' => 0,
			'getusertoday' => 0,
			'getuseryesterday' => 0,
			'getuserthismonth' => 0,
			'getuserlastweek' => 0,
			'getuserperday' => 0,
			'getuseronline' => 0,
			'getfirstcount' => 0,
			'show_name' => __('This post', 'cpd'),
			'getreadsall_name' => __('Total reads', 'cpd'),
			'getreadstoday_name' => __('Reads today', 'cpd'),
			'getreadsyesterday_name' => __('Reads yesterday', 'cpd'),
			'getreadslastweek_name' => __('Reads last week', 'cpd'),
			'getreadsthismonth_name' => __('Reads per month', 'cpd'),
			'getuserall_name' => __('Total visitors', 'cpd'),
			'getusertoday_name' => __('Visitors today', 'cpd'),
			'getuseryesterday_name' => __('Visitors yesterday', 'cpd'),
			'getuserlastweek_name' => __('Visitors last week', 'cpd'),
			'getuserthismonth_name' => __('Visitors per month', 'cpd'),
			'getuserperday_name' => __('Visitors per day', 'cpd'),
			'getuseronline_name' => __('Visitors currently online', 'cpd'),
			'getfirstcount_name' => __('Counter starts on', 'cpd')
		);
		$instance = wp_parse_args( (array) $instance, $default );
		
		// title field
		$field_id = $this->get_field_id('title');
		$field_name = $this->get_field_name('title');

		echo '
		<ul id="cpdwidgetlist'.$field_id.'">
		<li class="cpd_widget_item cpd_widget_title">
		<label for="'.$field_id.'">'.__('Title').':<label>
		<input type="text" class="widefat" id="'.$field_id.'" name="'.$field_name.'" value="'.esc_attr( $instance['title'] ).'" />
		</li>';
		
		$order = explode('|', $instance['order']);
		foreach ( $order as $f )
		{
			if ( $f )
			{
				$check_id = $this->get_field_id( $f );
				$check_name = $this->get_field_name( $f );
				$check_status = ( !empty($instance[$f]) ) ? 'checked="checked"' : '';
				
				$fl = $f.'_name';
				$label_id = $this->get_field_id( $fl );
				$label_name = $this->get_field_name( $fl );
				$label_value = esc_attr( $instance[$fl] );
	
				echo '
				<li itemid="'.$f.'" class="cpd_widget_item">
				<input type="checkbox" class="checkbox" id="'.$check_id.'" name="'.$check_name.'" value="1" '.$check_status.' />
				<label for="'.$check_id.'"> '.$default[$fl].'</label>
				<input type="text" class="widefat" id="'.$label_id.'" name="'.$label_name.'" value="'.$label_value.'" />
				</li>';
			}
		}
		echo "</ul>\n";

		// order
		$of_id = $this->get_field_id('order');
		$of_name = $this->get_field_name('order');
		echo '<input type="hidden" id="'.$of_id.'" name="'.$of_name.'" value="'.esc_attr( $instance['order'] ).'" />';
		?>
		<script type="text/javascript">
		//<![CDATA[
		jQuery.noConflict();
		jQuery(document).ready(function(){
  			jQuery('#cpdwidgetlist<?php echo $field_id ?>').sortable({
  				items: 'li:not(.cpd_widget_title)',
  				update: function (event, ui) {
					var ul = window.document.getElementById('cpdwidgetlist<?php echo $field_id ?>');
					var items = ul.getElementsByTagName('li');
					var array = new Array();
					for (var i = 1, n = items.length; i < n; i++) {
						var identifier = items[i].getAttribute('itemid');
						array.push(identifier);
					}
					window.document.getElementById('<?php echo $of_id ?>').value = array.join('|');
				}
  			});
		});
		//]]>
		</script>
		<?php
	}
	
} // widget class



/**
 * uninstall function, deletes tables and options
 */
function count_per_day_uninstall()
{
	global $wpdb;
//	$wpdb->query('DROP TABLE IF EXISTS '.CPD_C_TABLE);
//	$wpdb->query('DROP TABLE IF EXISTS '.CPD_CO_TABLE);
//	$wpdb->query('DROP TABLE IF EXISTS '.CPD_N_TABLE);
//	delete_option('count_per_day');
	$wpdb->query("DELETE FROM ".$wpdb->usermeta." WHERE meta_key like '%_cpd_metaboxes%';");
}


$count_per_day = new CountPerDay();
