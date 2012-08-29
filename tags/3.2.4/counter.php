<?php
/*
Plugin Name: Count Per Day
Plugin URI: http://www.tomsdimension.de/wp-plugins/count-per-day
Description: Counter, shows reads and visitors per page; today, yesterday, last week, last months ... on dashboard, per shortcode or in widget.
Version: 3.2.4
License: Postcardware
Author: Tom Braider
Author URI: http://www.tomsdimension.de
*/

$cpd_dir_name = 'count-per-day';
$cpd_version = '3.2.4';

$cpd_path = str_replace('/', DIRECTORY_SEPARATOR, ABSPATH.PLUGINDIR.'/'.$cpd_dir_name.'/');
include_once($cpd_path.'counter-core.php');

/**
 * Count per Day
 */
class CountPerDay extends CountPerDayCore
{

/**
 * constructor
 */
function CountPerDay()
{
	$this->init();
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
	else
		$page = (int) $page;
	
	// get count from collection
	$c = $this->getCollectedPostReads($page);
	// add current data
	$c += $this->mysqlQuery('var', $wpdb->prepare("SELECT COUNT(*) FROM $wpdb->cpd_counter WHERE page='$page'"), 'show '.__LINE__);
	if ($show)
		echo $before.$c.$after;
	else
		return $c;
}

/**
 * counts visits (without show)
 * @param $x some wp data (ignore it)
 * @param string/int $page PostID to count
 */
function count( $x, $page = 'x' )
{
	global $wpdb, $wp_query, $cpd_path, $cpd_geoip, $userdata;
	
	if ($this->options['debug'])
		$this->queries[] = 'called Function: <b style="color:blue">count</b> page: <code>'.$page.'</code>';
	
	if ($page == 'x')
		// normal counter
		$page = $this->getPostID();
	else
		// ajax counter on cached pages
		$page = (int) $page;
	
	// get userlevel from role
	if (current_user_can('administrator'))		$userlevel = 10;
	else if (current_user_can('editor'))		$userlevel = 7;
	else if (current_user_can('author'))		$userlevel = 2;
	else if (current_user_can('contributor'))	$userlevel = 1;
	else if (current_user_can('subscriber'))	$userlevel = 0;
	else										$userlevel = -1;
	
	$date = date_i18n('Y-m-d');
	
	// count visitor?
	$countUser = 1;
	if (!$this->options['user'] && is_user_logged_in() ) $countUser = 0; // don't count loged user
	if ( $this->options['user'] && isset($userdata) && $this->options['user_level'] < $userlevel ) $countUser = 0; // loged user, but higher user level

	$isBot = $this->isBot();
	
	if ($this->options['debug'])
		$this->queries[] = 'called Function: <b style="color:blue">count (variables)</b> '
			.'isBot: <code>'.(int) $isBot.'</code> '
			.'countUser: <code>'.$countUser.'</code> '
			.'page: <code>'.$page.'</code> '
			.'userlevel: <code>'.$userlevel.'</code>';
	
	// only count if: non bot, Logon is ok
	if ( !$isBot && $countUser && isset($page) )
	{
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
		{
			// get real IP, not local IP
			$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			$real_ip = $ips[0];
		}
		else
			$real_ip = $_SERVER['REMOTE_ADDR'];
		
		$userip = $this->anonymize_ip($real_ip);
		$client = ($this->options['referers']) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$referer = ($this->options['referers'] && isset($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : '';
		if ($this->options['referers_cut'])
			$referer = substr( $referer, 0, strpos($referer,'?') );
		
		// new visitor on page?
		$count = $this->mysqlQuery('var', $wpdb->prepare("SELECT COUNT(*) FROM $wpdb->cpd_counter WHERE ip=$this->aton(%s) AND date=%s AND page=%d", $userip, $date, $page), 'count check '.__LINE__);
		if ( !$count )
		{
			// save count
			if ($cpd_geoip)
			{
				// with GeoIP addon save country
				$gi = cpd_geoip_open($cpd_path.'geoip/GeoIP.dat', GEOIP_STANDARD);
				$country = strtolower(cpd_geoip_country_code_by_addr($gi, $userip));
				$this->mysqlQuery('', $wpdb->prepare("INSERT INTO $wpdb->cpd_counter (page, ip, client, date, country, referer)
				VALUES (%s, $this->aton(%s), %s, %s, %s, %s)", $page, $userip, $client, $date, $country, $referer), 'count insert '.__LINE__);
			}
			else
				// without country
				$this->mysqlQuery('', $wpdb->prepare("INSERT INTO $wpdb->cpd_counter (page, ip, client, date, referer)
				VALUES (%s, $this->aton(%s), %s, %s, %s)", $page, $userip, $client, $date, $referer), 'count insert '.__LINE__);
		}
		// online counter
		$oc = (array) get_option('count_per_day_online', array());
		$oc[$userip] = array( time(), $page );
		update_option('count_per_day_online', $oc);
	}
	
	// save searchstring if exists
	$s = $this->getSearchString();
	if ($s)
	{
		$search = get_option('count_per_day_search');
		// reset if array is corrupt
		if (!is_array($search))
			$search = array($date=>'');
		if (isset($search[$date]) && is_array($search[$date]))
		{
			if (!in_array($s, $search[$date]))
				$search[$date][] = $s;
		}
		else
			$search[$date] = array($s);
		update_option('count_per_day_search', $search);
		unset($search);
	}
}

/**
 * deletes old online user
 */
function deleteOnlineCounter()
{
	$oc = (array) get_option('count_per_day_online', array());
	foreach ($oc as $k => $v)
		if ($v[0] < time() - $this->options['onlinetime'])
			unset($oc[$k]);
	update_option('count_per_day_online', $oc);
}

/**
 * creates dashboard summary metabox content
 */
function dashboardReadsAtAll()
{
	$thisMonth = date_i18n('F');
	?>
	<ul>
		<li><?php _e('Total reads', 'cpd') ?>: <b><span><?php $this->getReadsAll() ?></span></b></li>
		<li><?php _e('Reads today', 'cpd') ?>: <b><?php $this->getReadsToday() ?></b></li>
		<li><?php _e('Reads yesterday', 'cpd') ?>: <b><?php $this->getReadsYesterday() ?></b></li>
		<li><?php _e('Reads last week', 'cpd') ?>: <b><?php $this->getReadsLastWeek() ?></b></li>
		<li><?php _e('Reads', 'cpd') ?> <?php echo $thisMonth ?>:<b><?php $this->getReadsThisMonth() ?></b></li>
		<li><?php _e('Total visitors', 'cpd') ?>: <b><span><?php $this->getUserAll() ?></span></b></li>
		<li><?php _e('Visitors currently online', 'cpd') ?>:<b><span><?php $this->getUserOnline() ?></span></b></li>
		<li><?php _e('Visitors today', 'cpd') ?>: <b><?php $this->getUserToday() ?></b></li>
		<li><?php _e('Visitors yesterday', 'cpd') ?>: <b><?php $this->getUserYesterday() ?></b></li>
		<li><?php _e('Visitors last week', 'cpd') ?>: <b><?php $this->getUserLastWeek() ?></b></li>
		<li><?php _e('Visitors', 'cpd') ?> <?php echo $thisMonth ?>: <b><?php $this->getUserThisMonth() ?></b></li>
		<li>&Oslash; <?php _e('Visitors per day', 'cpd') ?>: <b><?php $this->getUserPerDay($this->options['dashboard_last_days']) ?></b></li>
		<li><?php _e('Counter starts on', 'cpd') ?>: <b><?php $this->getFirstCount() ?></b></li>
		<li><?php _e('Most visited day', 'cpd') ?>: <b class="cpd-r"><?php $this->getDayWithMostReads(1) ?></b></li>
		<li><?php _e('Most visited day', 'cpd') ?>: <b class="cpd-r"><?php $this->getDayWithMostUsers(1) ?></b></li>
	</ul>
	<?php
}

/**
 * creates the big chart with reads and visotors
 * @param int $limit last x days
 */
function getFlotChart( $limit = 0 )
{
	global $wpdb;
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
	$sql = $wpdb->prepare("
		SELECT	COUNT(*) count, c.date
		FROM	$wpdb->cpd_counter c
		WHERE	c.date BETWEEN %s AND %s
		GROUP	BY c.date",
		$start_sql, $end_sql );
	$res = $this->mysqlQuery('rows', $sql, 'ChartReads '.__LINE__);
	if ($res)
		foreach ($res as $row)
			$data[strtotime($row->date)][0] = $row->count;
	
	// visitors
	$sql = $wpdb->prepare("
		SELECT COUNT(*) count, t.date
		FROM (	SELECT	COUNT(*) count, date
				FROM	$wpdb->cpd_counter
				GROUP	BY date, ip
				) AS t
		WHERE	t.date BETWEEN %s AND %s
		GROUP	BY t.date",
		$start_sql, $end_sql );
	$res = $this->mysqlQuery('rows', $sql, 'ChartVisitors '.__LINE__);
	if ($res)
		foreach ($res as $row)
			$data[strtotime($row->date)][1] = $row->count;
	
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
 * shows current visitors
 */
function getUserOnline( $frontend = false, $country = false, $return = false )
{
	global $wpdb, $cpd_geoip, $cpd_path;
	$c = '';
	
	$oc = get_option('count_per_day_online');
	
	if ( $oc && $cpd_geoip && $country )
	{
		// map link
		if ( !$frontend && file_exists($cpd_path.'map/map.php') )
			$c .= '<div style="margin: 5px 0 10px 0;"><a href="'.$this->dir.'/map/map.php?map=online'
				 .'&amp;KeepThis=true&amp;TB_iframe=true" title="Count per Day - '.__('Map', 'cpd').'" class="thickbox button">'.__('Map', 'cpd').'</a></div>';
		
		// countries list
		$geoip = new GeoIPCpd();
		$gi = cpd_geoip_open($cpd_path.'geoip/GeoIP.dat', GEOIP_STANDARD);

		$vo = array();
		foreach ( $oc as $ip=>$x )
		{
			$country = strtolower(cpd_geoip_country_code_by_addr($gi, $ip));
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
			$c .= '<li><div class="cpd-flag cpd-flag-'.$k.'"></div> '.$v[0].'&nbsp;<b>'.$v[1].'</b></li>'."\n";
		$c .= "</ul>\n";
	}
	else if ( $oc ) 
		$c = count($oc); // number only
	else
		$c = 0;
	if ($return) return $c; else echo $c;
}

/**
 * shows all visitors
 */
function getUserAll( $return = false )
{
	global $wpdb;
	$res = $this->mysqlQuery('count', "SELECT 1 FROM $wpdb->cpd_counter GROUP BY date, ip", 'getUserAll '.__LINE__);
	$c = $res + (int) $this->options['startcount'] + $this->getCollectedUsers();
	if ($return) return $c; else echo $c;
}

/**
 * shows all reads
 */
function getReadsAll( $return = false )
{
	global $wpdb;
	$res = $this->mysqlQuery('var', "SELECT COUNT(*) FROM $wpdb->cpd_counter", 'getReadsAll '.__LINE__);
	$c = (int) $res + (int) $this->options['startreads'] + $this->getCollectedReads();
	if ($return) return $c; else echo $c;
}

/**
 * shows today visitors
 */
function getUserToday( $return = false )
{
	global $wpdb;
	$date = date_i18n('Y-m-d');
	$c = $this->mysqlQuery('count', "SELECT 1 FROM $wpdb->cpd_counter WHERE date = '$date' GROUP BY ip", 'getUserToday '.__LINE__);
	if ($return) return $c; else echo $c;
}

/**
 * shows today reads
 */
function getReadsToday( $return = false )
{
	global $wpdb;
	$date = date_i18n('Y-m-d');
	$c = $this->mysqlQuery('var', "SELECT COUNT(*) FROM $wpdb->cpd_counter WHERE date = '$date'", 'getReadsToday '.__LINE__);
	if ($return) return $c; else echo $c;
}

/**
 * shows yesterday visitors
 */
function getUserYesterday( $return = false )
{
	global $wpdb;
	$c = $this->mysqlQuery('count', "SELECT 1 FROM $wpdb->cpd_counter WHERE date = DATE_SUB('".date_i18n('Y-m-d')."', INTERVAL 1 DAY) GROUP BY ip", 'getUserYesterday '.__LINE__);
	if ($return) return $c; else echo $c;
}

/**
 * shows yesterday reads
 */
function getReadsYesterday( $return = false )
{
	global $wpdb;
	$c = $this->mysqlQuery('var', "SELECT COUNT(*) FROM $wpdb->cpd_counter WHERE date = DATE_SUB('".date_i18n('Y-m-d')."', INTERVAL 1 DAY)", 'getReadsYesterday '.__LINE__);
	if ($return) return $c; else echo $c;
}

/**
 * shows last week visitors (last 7 days)
 */
function getUserLastWeek( $return = false )
{
	global $wpdb;
	$c = $this->mysqlQuery('count', "SELECT 1 FROM $wpdb->cpd_counter WHERE date >= DATE_SUB('".date_i18n('Y-m-d')."', INTERVAL 7 DAY) GROUP BY date, ip;", 'getUserLastWeek '.__LINE__);
	if ($return) return $c; else echo $c;
}

/**
 * shows last week reads (last 7 days)
 */
function getReadsLastWeek( $return = false )
{
	global $wpdb;
	$c = $this->mysqlQuery('var', "SELECT COUNT(*) FROM $wpdb->cpd_counter WHERE date >= DATE_SUB('".date_i18n('Y-m-d')."', INTERVAL 7 DAY)", 'getReadsLastWeek '.__LINE__);
	if ($return) return $c; else echo $c;
}

/**
 * shows this month visitors
 */
function getUserThisMonth( $return = false )
{
	global $wpdb;
	$first = date_i18n('Y-m-', current_time('timestamp')).'01';
	$c = $this->mysqlQuery('count', "SELECT 1 FROM $wpdb->cpd_counter WHERE date >= '$first' GROUP BY date, ip;", 'getUserThisMonth '.__LINE__);
	if ($return) return $c; else echo $c;
}

/**
 * shows this month reads
 */
function getReadsThisMonth( $return = false )
{
	global $wpdb;
	$first = date_i18n('Y-m-', current_time('timestamp')).'01';
	$c = $this->mysqlQuery('var', "SELECT COUNT(*) FROM $wpdb->cpd_counter WHERE date >= '$first';", 'getReadsThisMonth '.__LINE__);
	if ($return) return $c; else echo $c;
}

/**
 * shows visitors per month
 */
function getUserPerMonth( $frontend = false, $return = false )
{
	global $wpdb;
	$m = $this->mysqlQuery('rows', "SELECT LEFT(date,7) month FROM $wpdb->cpd_counter GROUP BY year(date), month(date) ORDER BY date DESC", 'getUserPerMonths '.__LINE__);
	if (!$m)
		return;
	$r = '<ul class="cpd_front_list">';
	$d = array();
	$i = 1;
	foreach ( $m as $row )
	{
		$c = $this->mysqlQuery('count', "SELECT 1 FROM $wpdb->cpd_counter WHERE LEFT(date,7) = '$row->month' GROUP BY date, ip", 'getUserPerMonth '.__LINE__);
		$r .= '<li>'.$row->month.' <b>'.$c.'</b></li>'."\n";
		$d[] = '[-'.$i++.','.$c.']';
	}
	// add collection
	$coll = get_option('count_per_day_collected');
	if ($coll)
	{
		$coll = array_reverse($coll, true);
		foreach ($coll as $m => $c)
		{
			$m2 = str_split($m, 4);
			$r .= '<li>'.$m2[0].'-'.$m2[1].' <b>'.$c['users'].'</b></li>'."\n";
			$d[] = '[-'.$i++.','.$c['users'].']';
		}
	}
	$r .= '</ul>';
	if (!$frontend)
		$r = $this->includeChartJS( 'cpd-flot-userpermonth', $d, $r );
	if ($return) return $r; else echo $r;
}

/**
 * shows reads per month
 */
function getReadsPerMonth( $frontend = false, $return = false )
{
	global $wpdb;
	$res = $this->mysqlQuery('rows', "SELECT COUNT(*) count, LEFT(date,7) month FROM $wpdb->cpd_counter GROUP BY year(date), month(date) ORDER BY date DESC", 'getReadsPerMonths '.__LINE__);
	if (!$res)
		return;
	$r = '<ul class="cpd_front_list">';
	$d = array();
	$i = 1;
	foreach ( $res as $row )
	{
		$r .= '<li>'.$row->month.' <b>'.$row->count.'</b></li>'."\n";
		$d[] = '[-'.$i++.','.$row->count.']';
	}
	// add collection
	$coll = get_option('count_per_day_collected');
	if ($coll)
	{
		$coll = array_reverse($coll, true);
		foreach ($coll as $m => $c)
		{
			$m2 = str_split($m, 4);
			$r .= '<li>'.$m2[0].'-'.$m2[1].' <b>'.$c['reads'].'</b></li>'."\n";
			$d[] = '[-'.$i++.','.$c['reads'].']';
		}
	}
	$r .= '</ul>';
	if (!$frontend)
		$r = $this->includeChartJS( 'cpd-flot-readspermonth', $d, $r );
	if ($return) return $r; else echo $r;
}

/**
 * shows visitors per post
 * @param integer $limit number of posts, -1 = all, 0 = get option from db, x = number
 * @param boolean $frontend limit function on frontend
 */
function getUserPerPost( $limit = 0, $frontend = false, $return = false )
{
	global $wpdb;
	if ( $limit == 0 )
		$limit = $this->options['dashboard_posts'];
	$sql = "
	SELECT	COUNT(*) count, page
	FROM 	$wpdb->cpd_counter
	WHERE	page
	GROUP	BY page";
	$r = $this->getUserPer_SQL( $sql, 'getUserPerPost '.__LINE__, $frontend, $limit );
	if ($return) return $r; else echo $r;
}

/**
 * shows counter start, first day or given value
 */
function getFirstCount( $return = false )
{
	global $wp_locale;
	if (!empty($this->options['startdate']))
		$c = $this->options['startdate'];
	else
		$c = $this->updateFirstCount();
	$c = mysql2date(get_option('date_format'), $c );
	if ($return) return $c; else echo $c;
}

/**
 * shows averaged visitors per day
 * @param integer $days days to calc
 */
function getUserPerDay( $days = 0, $return = false )
{
	global $wpdb;
	$datemax = date_i18n('Y-m-d');
	if ( $days > 0 )
		// last $days days without today
		$datemin = date_i18n('Y-m-d', current_time('timestamp') - ($days + 1) * 86400);
	else
	{ 
		$res = $this->mysqlQuery('rows', 'SELECT MIN(date) min, MAX(date) max FROM '.$wpdb->cpd_counter, 'getUserPerDay '.__LINE__);
		foreach ($res as $row)
			$days =  ((strtotime($row->max) - strtotime($row->min)) / 86400 + 1);
		$datemin = 0;
	}
	$c = $this->mysqlQuery('count', "SELECT 1 FROM $wpdb->cpd_counter WHERE date > '$datemin' AND date < '$datemax' GROUP BY ip, date", 'getUserPerDay '.__LINE__);
	$count = $c / $days;
	
	$s = '<abbr title="last '.$days.' days without today">';
	if ( $count < 5 )
		$s .=  number_format($count, 2);
	else
		$s .=  number_format($count, 0);
	$s .=  '</abbr>';
	if ($return) return $s; else echo $s;
}

/**
 * shows most visited pages in last days
 * @param integer $days days to calc (last days)
 * @param integer $limit count of posts (last posts)
 * @param boolean $postsonly don't show categories and taxonomies
 */
function getMostVisitedPosts( $days = 0, $limit = 0, $frontend = false, $postsonly = false, $return = false )
{
	global $wpdb;
	if ( $days == 0 )
		$days = $this->options['dashboard_last_days'];
	if ( $limit == 0 )
		$limit = $this->options['dashboard_last_posts'];
	$date = date_i18n('Y-m-d', current_time('timestamp') - 86400 * $days);
	
	if ($postsonly)
		$sql = $wpdb->prepare("
		SELECT	COUNT(c.id) count,
				c.page post_id,
				p.post_title post
		FROM	$wpdb->cpd_counter c
		LEFT	JOIN $wpdb->posts p
				ON p.id = c.page
		WHERE	c.date >= %s
		AND		c.page > 0
		GROUP	BY c.page
		ORDER	BY count DESC
		LIMIT	%d",
		$date, $limit);
	else
		$sql = $wpdb->prepare("
		SELECT	COUNT(c.id) count,
				c.page post_id,
				p.post_title post,
				t.name tag_cat_name,
				t.slug tag_cat_slug,
				x.taxonomy tax
		FROM	$wpdb->cpd_counter c
		LEFT	JOIN $wpdb->posts p
				ON p.id = c.page
		LEFT	JOIN $wpdb->terms t
				ON t.term_id = 0 - c.page
		LEFT	JOIN $wpdb->term_taxonomy x
				ON x.term_id = t.term_id
		WHERE	c.date >= %s
		GROUP	BY c.page
		ORDER	BY count DESC
		LIMIT	%d",
		$date, $limit);
	$r =  '<small>'.sprintf(__('The %s most visited posts in last %s days:', 'cpd'), $limit, $days).'</small>';
	$r .= $this->getUserPer_SQL( $sql, 'getMostVisitedPosts', $frontend );
	if ($return) return $r; else echo $r;
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
	FROM	$wpdb->cpd_counter c
	$q2
	LEFT	JOIN $wpdb->term_relationships r 
			ON r.object_id = c.page
	LEFT	JOIN $wpdb->term_taxonomy x
			ON x.term_taxonomy_id = r.term_taxonomy_id
	WHERE	c.date >= '$date'
	$cat_filter
	GROUP	BY c.page
	ORDER	BY count DESC
	LIMIT	$limit";
	$res = $this->mysqlQuery('rows', $sql, 'getMostVisitedPostIDs '.__LINE__);

	$ids = array();
	if ($res)
		foreach ( $res as $row )
		{
			if ($return_array)
				$ids[] = array('id' => $row->post_id, 'title' => $row->post_title, 'count' => $row->count);
			else
				$ids[] = $row->post_id;
		}
	if ($return_array)
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
function getVisitedPostsOnDay( $date = 0, $limit = 0, $show_form = true, $show_notes = true, $frontend = false, $return = false )
{
	global $wpdb, $cpd_path, $userdata;
	if (!empty($_POST['daytoshow']))
		$date = $_POST['daytoshow'];
	else if (!empty($_GET['daytoshow']))
		$date = $_GET['daytoshow'];
	else if ( $date == 0 )
		$date = date_i18n('Y-m-d');
	if ( $limit == 0 )
		$limit = $this->options['dashboard_last_posts'];

	// get note
	$notes = get_option('count_per_day_notes');
	if ( isset($notes) && is_array($notes) )
		foreach ( $notes as $n )
			if ( $n[0] == $date )
				$note[] = $n[1];

	$sql = $wpdb->prepare("
		SELECT	COUNT(c.id) count,
				c.page post_id,
				p.post_title post,
				t.name tag_cat_name,
				t.slug tag_cat_slug,
				x.taxonomy tax
		FROM	$wpdb->cpd_counter c
		LEFT	JOIN $wpdb->posts p
				ON p.id = c.page
		LEFT	JOIN $wpdb->terms t
				ON t.term_id = 0 - c.page
		LEFT	JOIN $wpdb->term_taxonomy x
				ON x.term_id = t.term_id
		WHERE	c.date = %s
		GROUP	BY c.page
		ORDER	BY count DESC
		LIMIT	%d",
		$date, $limit );
	
	if ($show_form)
	{
		echo '<form action="" method="post">
			  <input name="daytoshow" value="'.$date.'" size="11" />
			  <input type="submit" name="showday" value="'.__('Show').'" class="button" />';
		if ( $show_notes )	
			echo ' <a href="'.$this->dir.'/notes.php?KeepThis=true&amp;TB_iframe=true" title="Count per Day - '.__('Notes', 'cpd').'" class="button thickbox">'.__('Notes', 'cpd').'</a> ';
		echo '</form>';
	}
	if (isset($note))
		echo '<p style="background:#eee; padding:2px;">'.implode(', ', $note).'</p>';
	$r = $this->getUserPer_SQL( $sql, 'getVisitedPostsOnDay', $frontend );
	if ($return) return $r; else echo $r;
}

/**
 * shows little browser statistics
 */
function getClients( $return = false )
{
	global $wpdb;
	$c_string = $this->options['clients'];
	$clients = explode(',', $c_string);
	
	$res = $this->mysqlQuery('var', "SELECT COUNT(*) FROM ".$wpdb->cpd_counter, 'getClients_all '.__LINE__);
	if (!$res)
		return;
	$all = max(1, (int) $res);
	$rest = 100;
	foreach ($clients as $c)
	{
		$c = trim($c);
		$sql = "SELECT COUNT(*) FROM $wpdb->cpd_counter WHERE client LIKE '%%".$c."%%'";
		if ( strtolower($c) == 'safari' ) // don't count chrome too while counting safari
			$sql .= " AND client NOT LIKE '%%chrome%%'";
		$count = $this->mysqlQuery('var', $sql, 'getClients_'.$c.'_ '.__LINE__);
		$percent = number_format(100 * $count / $all, 0);
		$rest -= $percent;
		$cl_percent[] = $percent;
		$cl_name[] = $c;
	}
	if ( $rest > 0 )
	{
		$cl_percent[] = $rest;
		$cl_name[] = __('Other', 'cpd');
	}
	array_multisort($cl_percent, SORT_NUMERIC, SORT_DESC, $cl_name);
	$r = '<ul id="cpd_clients" class="cpd_front_list">';
	for ($i = 0; $i < count($cl_percent); $i++)
	{
		$r .= '<li class="cpd-client-logo cpd-client-'.strtolower($cl_name[$i]).'">'.$cl_name[$i].' <b>'.$cl_percent[$i].' %</b></li>';		
	}
	$r .= '</ul>';

	$res = $this->mysqlQuery('var', "SELECT MIN(date) FROM ".$wpdb->cpd_counter, 'getClients_date '.__LINE__);
	$r .= '<small>'.__('Counter starts on', 'cpd').': '.mysql2date(get_option('date_format'), $res ).'</small>';
	if ($return) return $r; else echo $r;
}

/**
 * shows top referrers
 */
function getReferers( $limit = 0, $return = false, $days = 0 )
{
	global $wpdb;
	if ( $limit == 0 )
		$limit = $this->options['dashboard_referers'];
	if ( $days == 0 )
		$days = $this->options['referers_last_days'];
	
	// local url filter 
	$dayfiltre = "AND date > DATE_SUB('".date_i18n('Y-m-d')."', INTERVAL $days DAY)";
		
	$localref = ($this->options['localref']) ? '' : " AND referer NOT LIKE '".get_bloginfo('url')."%%' ";
	$res = $this->mysqlQuery('rows', "SELECT COUNT(*) count, referer FROM $wpdb->cpd_counter WHERE referer > '' $dayfiltre $localref GROUP BY referer ORDER BY count DESC LIMIT $limit", 'getReferers '.__LINE__);
	$r =  '<small>'.sprintf(__('The %s referrers in last %s days:', 'cpd'), $limit, $days).'</small>';
	$r .= '<ul id="cpd_referrers" class="cpd_front_list">';
	if ($res)
		foreach ( $res as $row )
		{
			$ref =  str_replace('&', '&amp;', $row->referer);
			$ref2 = str_replace(array('http://', 'https://'), '', $ref);
			$r .= '<li><a href="'.$ref.'">'.$ref2.'</a> <b>'.$row->count.'</b></li>';
		}
	$r .= '</ul>';
	if ($return) return $r; else echo $r;
}

/**
 * shows day with most reads
 */
function getDayWithMostReads( $formated = false, $return = false )
{
	global $wpdb;
	$sql = "
	SELECT	date, COUNT(id) count
	FROM	$wpdb->cpd_counter
	GROUP	BY date
	ORDER	BY count DESC
	LIMIT	1";
	$res = $this->mysqlQuery('rows', $sql, 'getDayWithMostReads '.__LINE__ );
	if (!$res)
		return;
	$r = $this->updateCollectedDayMostReads( $res[0] );
	if ($formated)
		$r = mysql2date(get_option('date_format'), $r[0]).'<br/>'.$r[1].' '.__('Reads', 'cpd');
	if ($return) return $r; else echo $r;
}

/**
 * shows day with most visitors
 */
function getDayWithMostUsers( $formated = false, $return = false )
{
	global $wpdb;
	$sql = "
	SELECT	t.date, count(*) count
	FROM (	SELECT	count(*) count, date, page
			FROM	$wpdb->cpd_counter
			GROUP	BY date, ip
			) AS t
	GROUP	BY t.date
	ORDER	BY count DESC
	LIMIT	1";
	$res = $this->mysqlQuery('rows', $sql, 'getDayWithMostVisitors '.__LINE__ );
	if (!$res)
		return;
	$r = $this->updateCollectedDayMostUsers( $res[0] );
	if ($formated)
		$r = mysql2date(get_option('date_format'), $r[0]).'<br/>'.$r[1].' '.__('Visitors', 'cpd');
	if ($return) return $r; else echo $r;
}

/**
 * creates counter lists
 * @param string $sql SQL Statement
 * @param string $name function name for debug
 * @param boolean $frontend limit function on frontend
 */
function getUserPer_SQL( $sql, $name = '', $frontend = false, $limit = 0 )
{
	global $wpdb, $userdata;
	$m = $this->mysqlQuery('rows', $sql, $name.__LINE__);
	if (!$m)
		return;

	if ( strpos($name, 'getUserPerPost') !== false )
	{
		// get collection
		$p = get_option('count_per_day_posts');
		if (empty($p))
			$p = array();
		// add normal data
		foreach ( $m as $r )
		{
			$pid = 'p'.$r->page;
			if ( isset($p[$pid]) )
				$p[$pid] += (int) $r->count;
			else if ( $r->count )
				$p[$pid] = (int) $r->count;
		}
		// max $limit
		$keys = array_keys($p);
		array_multisort($p, SORT_NUMERIC, SORT_DESC, $keys);
		$p = array_slice($p, 0, $limit);
		
		// new sql query
		$keys = array_keys($p);
		$list = '';
		foreach ($keys as $k)
			$list .= str_replace('p', '', $k).',';
		$list = substr($list, 0, -1);
		
		$if = '';
		foreach ($p as $id=>$count)
			$if .= " WHEN ".str_replace('p', '', $id)." THEN $count";

		$sql = "
        SELECT temp_outer.* FROM (
	 		SELECT	CASE p.id $if ELSE 0 END count,	
	 				p.id post_id,
	 				p.post_title post,
					'' tag_cat_name,
					'' tag_cat_slug,
					'' tax
			FROM 	$wpdb->posts p
			WHERE	p.id IN ($list)
			GROUP	BY p.id
	        UNION
	        SELECT	CASE -t.term_id $if ELSE 0 END count,
					t.term_id post_id,
					'' post,
	 				t.name tag_cat_name,
	 				t.slug tag_cat_slug,
	 				x.taxonomy tax
			FROM 	$wpdb->terms t
	 		LEFT	JOIN $wpdb->term_taxonomy x
	 				ON x.term_id = t.term_id
			WHERE	-t.term_id IN ($list)
			GROUP	BY t.term_id
			) temp_outer
 		ORDER	BY count DESC";
		$m = $this->mysqlQuery('rows', $sql, $name.' '.__LINE__);
		if (!$m)
		return;
	}	
		
		
	$r = '<ul class="cpd_front_list">';
	foreach ( $m as $row )
	{
		$r .= '<li>';
		// link only for editors in backend
		if ( current_user_can('manage_links') && !$frontend )
		{
			if ( $row->post_id > 0 )
				$r .= '<a href="post.php?action=edit&amp;post='.$row->post_id.'"><img src="'.$this->img('cpd_pen.png').'" alt="[e]" title="'.__('Edit Post').'" style="width:9px;height:12px;" /></a> '
					.'<a href="'.$this->dir.'/userperspan.php?page='.$row->post_id.'&amp;KeepThis=true&amp;TB_iframe=true" class="thickbox" title="Count per Day"><img src="'.$this->img('cpd_calendar.png').'" alt="[v]" style="width:12px;height:12px;" /></a> ';
			else
				$r .= '<img src="'.$this->img('cpd_trans.png').'" alt="" style="width:25px;height:12px;" />';
		}
		
		$r .= '<a href="'.get_bloginfo('url');
		if ( $row->tax == 'category' )
			// category
			$r .= '?cat='.abs($row->post_id).'">- '.$row->tag_cat_name.' ('.__('Category').') -';
		else if ( $row->tax )
			// tag
			$r .= '?tag='.$row->tag_cat_slug.'">- '.$row->tag_cat_name.' ('.__('Tag').') -';
		else if ( $row->post_id == 0 )
			// homepage
			$r .= '">- '.__('Front page displays').' -';
		else
			// post/page
			$r .= '?p='.$row->post_id.'">'.($row->post ? $row->post : '---');
		$r .= '</a>';

		$r .= ' <b>'.$row->count.'</b></li>'."\n";
	}
	$r .= '</ul>';
	return $r;
}

/**
* shows searchstrings
*/
function getSearches( $limit = 0, $days = 0, $return = false )
{
	$search = (array) get_option('count_per_day_search');
	if (empty($search))
		return;
	
	if ( $limit == 0 )
	$limit = $this->options['dashboard_referers'];
	if ( $days == 0 )
	$days = $this->options['referers_last_days'];
	
	// most searched
	$c = array();
	foreach ( $search as $day => $strings )
	{
		if (is_array($strings))
			foreach ( $strings as $s )
			{
				if (isset($c[$s]))
					$c[$s]++;
				else
					$c[$s] = 1;
			} 
	}
	arsort($c);
	$c = array_slice($c, 0, $limit);
	$r = '<small>'.sprintf(__('The %s most searched strings:', 'cpd'), $limit).'</small>';
	$r .= '<ul class="cpd_front_list">';
	foreach ( $c as $string => $count )
		$r .= '<li>'.$string.' <b>'.$count.'</b></li>'."\n";
	$r .= '</ul>';
	
	// last days
	krsort($search);
	$search = array_slice($search, 0, $days);
	$r .= '<small>'.sprintf(__('The search strings of the last %s days:', 'cpd'), $days).'</small>';
	$r .= '<ul class="cpd_front_list">';
	foreach ( $search as $day => $s )
		if (is_array($s))
			$r .= '<li><div style="font-weight:bold">'.$day.'</div> '.implode(', ', $s).'</li>'."\n";
	$r .= '</ul>';
	if ($return) return $r; else echo $r;
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
 * gets country flags and page views
 * @param integer $limit count of countries
 * @param boolean $frontend limit function on frontend
 * @param boolean $visitors show visitors insteed of reads
 */
function getCountries( $limit = 0, $frontend = false, $visitors = false, $return = false )
{
	global $wpdb, $cpd_path, $cpd_geoip;
	$c = '';

	// with GeoIP addon only
	if ( $cpd_geoip )
	{
		$geoip = new GeoIPCpD();
		if ( $limit == 0 )
			$limit = max( 0, $this->options['countries'] );

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

		$temp = $this->addCollectionToCountries( $visitors, $limit );
		
		// make list				
		$c .= '<ul class="cpd_front_list">';
		foreach ($temp as $country => $value)
		{
			// get country names
			if ($country != '-')
				$id = $geoip->GEOIP_COUNTRY_CODE_TO_NUMBER[strtoupper($country)];
			if ( empty($id) )
			{
				$name = '???';
				$country = 'unknown';
			}
			else
				$name = $geoip->GEOIP_COUNTRY_NAMES[$id];
			$c .= '<li><div class="cpd-flag cpd-flag-'.$country.'"></div> '.$name.' <b>'.$value.'</b></li>'."\n";
		}
		$c .= '</ul>';
	}
	if ($return) return $c; else echo $c;
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
		<strong>Flash World Map</strong>
	</div>
	<script type="text/javascript">
		//<![CDATA[
		var so = new SWFObject("<?php echo $dir ?>ammap.swf", "ammap", "100%", "100%", "8", "#4499FF");
		so.addVariable("path", "<?php echo $dir ?>");
		so.addVariable("settings_file", escape("<?php echo $dir ?>settings.xml.php?map=<?php echo $what ?>&min=<?php echo $min ?>"));
		so.addVariable("data_file", escape("<?php echo $dir ?>data.xml.php?map=<?php echo $what ?>&min=<?php echo $min ?>"));
		so.write("<?php echo $divid ?>");
		//]]>
	</script>
	<?php
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
	var $cpd_funcs = array ( 'show',
		'getReadsAll', 'getReadsToday', 'getReadsYesterday', 'getReadsLastWeek', 'getReadsThisMonth',
		'getUserAll', 'getUserToday', 'getUserYesterday', 'getUserLastWeek', 'getUserThisMonth',
		'getUserPerDay', 'getUserOnline', 'getFirstCount' );
	var $funcs;
	var $names;
	
	// constructor
	function CountPerDay_Widget() {
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
						echo '<li class="cpd-l">';
						echo '<span id="cpd_number_'.$k.'" class="cpd-r">';
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
		<label for="'.$field_id.'">'.__('Title').':</label>
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
				<li itemid="'.$f.'" class="cpd_widget_item" title="'.__('drag and drop to sort', 'cpd').'">
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
	$wpdb->query('DROP TABLE IF EXISTS '.$wpdb->cpd_counter);
	$wpdb->query('DROP TABLE IF EXISTS '.$wpdb->cpd_counter_useronline);
	$wpdb->query('DROP TABLE IF EXISTS '.$wpdb->cpd_notes);
	delete_option('count_per_day');
	delete_option('count_per_day_summary');
	delete_option('count_per_day_collected');
	delete_option('count_per_day_online');
	delete_option('count_per_day_notes');
	delete_option('count_per_day_posts');
	delete_option('count_per_day_search');
	$wpdb->query("DELETE FROM $wpdb->usermeta WHERE meta_key LIKE '%_cpd_metaboxes%';");
}

$count_per_day = new CountPerDay();
