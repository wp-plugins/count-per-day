<?php
/**
 * Filename: counter-options.php
 * Count Per Day - Options and Administration
 */

// check form 
if(!empty($_POST['do']))
{
	switch($_POST['do'])
	{
		// update options
		case 'cpd_update' :
			$count_per_day->options['onlinetime'] = $_POST['cpd_onlinetime'];
			$count_per_day->options['user'] = empty( $_POST['cpd_user'] ) ? 0 : 1 ;
			$count_per_day->options['user_level'] = $_POST['cpd_user_level'];
			$count_per_day->options['autocount'] = empty( $_POST['cpd_autocount'] ) ? 0 : 1 ;
			$count_per_day->options['bots'] = $_POST['cpd_bots'];
			$count_per_day->options['dashboard_posts'] = $_POST['cpd_dashboard_posts'];
			$count_per_day->options['dashboard_last_posts'] = $_POST['cpd_dashboard_last_posts'];
			$count_per_day->options['dashboard_last_days'] = $_POST['cpd_dashboard_last_days'];
			$count_per_day->options['show_in_lists'] = empty( $_POST['cpd_show_in_lists'] ) ? 0 : 1 ;
			$count_per_day->options['chart_days'] = $_POST['cpd_chart_days'];
			$count_per_day->options['chart_height'] = $_POST['cpd_chart_height'];
			$count_per_day->options['startdate'] = $_POST['cpd_startdate'];
			$count_per_day->options['startcount'] = $_POST['cpd_startcount'];
			$count_per_day->options['startreads'] = $_POST['cpd_startreads'];
			$count_per_day->options['anoip'] = empty( $_POST['cpd_anoip'] ) ? 0 : 1 ;
			$count_per_day->options['clients'] = $_POST['cpd_clients'];
			$count_per_day->options['ajax'] = empty( $_POST['cpd_ajax'] ) ? 0 : 1 ;
			$count_per_day->options['debug'] = empty( $_POST['cpd_debug'] ) ? 0 : 1 ;
			$count_per_day->options['localref'] = empty( $_POST['cpd_localref'] ) ? 0 : 1 ;
			$count_per_day->options['referers'] = empty( $_POST['cpd_referers'] ) ? 0 : 1 ;
			$count_per_day->options['dashboard_referers'] = $_POST['cpd_dashboard_referers'];
			$count_per_day->options['referers_last_days'] = $_POST['cpd_referers_last_days'];
			$count_per_day->options['chart_old'] = empty( $_POST['cpd_chart_old'] ) ? 0 : 1 ;
			$count_per_day->options['no_front_css'] = empty( $_POST['cpd_no_front_css'] ) ? 0 : 1 ;
			$count_per_day->options['whocansee'] = ($_POST['cpd_whocansee'] == 'custom') ? $_POST['cpd_whocansee_custom'] : $_POST['cpd_whocansee'];
			
			if (empty($count_per_day->options['clients']))
				$count_per_day->options['clients'] = 'Firefox, MSIE, Chrome, Safari, Opera';
			
			if ( isset($_POST['cpd_countries']) )
				$count_per_day->options['countries'] = $_POST['cpd_countries'];
			
			update_option('count_per_day', $count_per_day->options);
			
			echo '<div id="message" class="updated fade"><p>'.__('Options updated', 'cpd').'</p></div>';
			break;

		// update countries
		case 'cpd_countries' :
			if ( class_exists('CpdGeoIp') )
			{
				$count_per_day->queries[] = 'cpd_countries - class "CpdGeoIp" exists'; 
				$rest = CpdGeoIp::updateDB();
				echo '<div id="message" class="updated fade">
					<form name="cpdcountries" method="post" action="'.$_SERVER['REQUEST_URI'].'">
					<p>'.sprintf(__('Countries updated. <b>%s</b> entries in %s without country left', 'cpd'), $rest, CPD_C_TABLE);
				if ( $rest > 100 )
				{
					// reload page per javascript until less than 100 entries without country
					echo '<input type="hidden" name="do" value="cpd_countries" />
						<input type="submit" name="updcon" value="'.__('update next', 'cpd').'" class="button" />';
					if ( !$count_per_day->options['debug'] )
						echo '<script type="text/javascript">document.cpdcountries.submit();</script>';
				}
				echo '</p>
					</form>
					</div>';
				if ( $rest > 100 )
					while (@ob_end_flush());
			}
			else
				$count_per_day->queries[] = '<span style="color:red">cpd_countries - class "CpdGeoIp" NOT exists</span>';
			break;
			
		// download new GeoIP database
		case 'cpd_countrydb' :
			if ( class_exists('CpdGeoIp') )
			{
				$count_per_day->getQuery("SELECT country FROM ".CPD_C_TABLE, 'geoip_select');
				if ((int) mysql_errno() == 1054)
					// add row 'country' to counter db
					$count_per_day->getQuery("ALTER TABLE `".CPD_C_TABLE."` ADD `country` CHAR(2) NOT NULL", 'geoip_alter');
						
				$result = CpdGeoIp::updateGeoIpFile();
				echo '<div id="message" class="updated fade"><p>'.$result.'</p></div>';
				if ( file_exists($cpd_path.'geoip/GeoIP.dat') )
					$cpd_geoip = 1;
			}
			break;
		
		// delete massbots
		case 'cpd_delete_massbots' :
			if ( isset($_POST['limit']) )
			{
				$bots = $count_per_day->getMassBots($_POST['limit']);
				$sum = 0;
				while ( $row = mysql_fetch_array($bots) )
				{
					$count_per_day->getQuery("DELETE FROM ".CPD_C_TABLE." WHERE ip = INET_ATON('".$row['ip']."') AND date = '".$row['date']."'", 'deleteMassbots');
					$sum += $row['posts'];
				}
				if ( $sum )
					echo '<div id="message" class="updated fade"><p>'.sprintf(__('Mass Bots cleaned. %s counts deleted.', 'cpd'), $sum).'</p></div>';
			}	
			break;
			
		// clean database
		case 'cpd_clean' :
			$rows = $count_per_day->cleanDB();
			echo '<div id="message" class="updated fade"><p>'.sprintf(__('Database cleaned. %s rows deleted.', 'cpd'), $rows).'</p></div>';
			break;

		// reset counter
		case 'cpd_reset' :
			$wpdb->query('TRUNCATE TABLE '.CPD_C_TABLE);
			echo '<div id="message" class="updated fade"><p>'.sprintf(__('Counter reseted.', 'cpd'), $rows).'</p></div>';
			break;
			
		//  uninstall plugin
		case __('UNINSTALL Count per Day', 'cpd') :
			if(trim($_POST['uninstall_cpd_yes']) == 'yes')
			{
				count_per_day_uninstall();
				echo '<div id="message" class="updated fade"><p>';
				printf(__('Table %s deleted', 'cpd'), CPD_C_TABLE);
				echo '<br/>';
				printf(__('Table %s deleted', 'cpd'), CPD_CO_TABLE);
				echo '<br/>';
				printf(__('Table %s deleted', 'cpd'), CPD_N_TABLE);
				echo '<br/>';
				echo __('Options deleted', 'cpd').'</p></div>';
				$mode = 'end-UNINSTALL';
			}
			break;
			
		default:
			break;
	}
}

// delete one massbots per click on X
if ( isset($_GET['dmbip']) && isset($_GET['dmbdate']) )
	$count_per_day->getQuery("DELETE FROM ".CPD_C_TABLE." WHERE ip = '".$_GET['dmbip']."' AND date = '".$_GET['dmbdate']."'", 'deleteMassbot');

if ( empty($mode) )
	$mode = '';
	
switch($mode) {
	// deactivation
	case 'end-UNINSTALL':
		$deactivate_url = 'plugins.php?action=deactivate&amp;plugin='.$cpd_dir_name.'/counter.php';
		if ( function_exists('wp_nonce_url') ) 
			$deactivate_url = wp_nonce_url($deactivate_url, 'deactivate-plugin_'.$cpd_dir_name.'/counter.php');
		echo '<div class="wrap">';
		echo '<h2>'.__('Uninstall', 'cpd').' "Count per Day"</h2>';
		echo '<p><strong><a href="'.$deactivate_url.'">'.__('Click here', 'cpd').'</a> '.__('to finish the uninstall and to deactivate "Count per Day".', 'cpd').'</strong></p>';
		echo '</div>';
		break;
		
	default:
	// show options page

	$o = $count_per_day->options;
	
	// save massbot limit
	if(isset($_POST['limit']))
	{
		$o['massbotlimit'] = $_POST['limit'];
		update_option('count_per_day', $o);
	}
	?>
	<div class="wrap">
	<h2><img src="<?php echo $count_per_day->getResource('cpd_menu.gif') ?>" alt="" style="width:24px;height:24px" /> Count per Day</h2>
 	
 	<div id="poststuff">
 	
	<div class="postbox cpd_settings">
	<h3><?php _e('Options', 'cpd') ?></h3>
	<div class="inside">
		<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
			
		<!-- counter -->
		
		<fieldset>
		<legend><?php _e('Counter', 'cpd') ?></legend>
		
		<table class="form-table">
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Online time', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_onlinetime" size="3" value="<?php echo $o['onlinetime']; ?>" /> <?php _e('Seconds for online counter. Used for "Visitors online" on dashboard page.', 'cpd') ?></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Logged on Users', 'cpd') ?>:</th>
			<td>
				<label for="cpd_user"><input type="checkbox" name="cpd_user" id="cpd_user" <?php if($o['user']==1) echo 'checked="checked"'; ?> /> <?php _e('count too', 'cpd') ?></label>
				- <?php _e('until User Level', 'cpd') ?>
				<select name="cpd_user_level">
					<option value="10" <?php if ($o['user_level'] == 10) echo 'selected="selected"' ?>><?php echo translate_user_role('Administrator') ?> (10)</option>
					<option value="7" <?php if ($o['user_level'] == 7) echo 'selected="selected"' ?>><?php echo translate_user_role('Editor') ?> (7)</option>
					<option value="2" <?php if ($o['user_level'] == 2) echo 'selected="selected"' ?>><?php echo translate_user_role('Author') ?> (2)</option>
					<option value="1" <?php if ($o['user_level'] == 1) echo 'selected="selected"' ?>><?php echo translate_user_role('Contributor') ?> (1)</option>
					<option value="0" <?php if ($o['user_level'] == 0) echo 'selected="selected"' ?>><?php echo translate_user_role('Subscriber') ?> (0)</option>
				</select>
			</td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Auto counter', 'cpd') ?>:</th>
			<td><label for="cpd_autocount"><input type="checkbox" name="cpd_autocount" id="cpd_autocount" <?php if($o['autocount']==1) echo 'checked="checked"'; ?> /> <?php _e('Counts automatically single-posts and pages, no changes on template needed.', 'cpd') ?></label></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Bots to ignore', 'cpd') ?>:</th>
			<td><textarea name="cpd_bots" cols="50" rows="10"><?php echo $o['bots']; ?></textarea></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Anonymous IP', 'cpd') ?>:</th>
			<td><label for="cpd_anoip"><input type="checkbox" name="cpd_anoip" id="cpd_anoip" <?php if($o['anoip']==1) echo 'checked="checked"'; ?> /> a.b.c.d &gt; a.b.c.x</label></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Cache', 'cpd') ?> (beta):</th>
			<td><label for="cpd_ajax"><input type="checkbox" name="cpd_ajax" id="cpd_ajax" <?php if($o['ajax']==1) echo 'checked="checked"'; ?> /> <?php _e('I use a cache plugin. Count these visits with ajax.', 'cpd') ?></label></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Clients and referrers', 'cpd') ?>:</th>
			<td><label for="cpd_referers"><input type="checkbox" name="cpd_referers" id="cpd_referers" <?php if($o['referers']==1) echo 'checked="checked"'; ?> /> <?php _e('Save and show clients and referrers.<br />Needs a lot of space in the database but gives you more detailed informations of your visitors.', 'cpd') ?> (1000000 <?php _e('Reads', 'cpd') ?> ~ 130 MB)</label></td>
		</tr>
		</table>
		</fieldset>
		
		<!-- dashboard -->
		
		<fieldset>
		<legend><?php _e('Dashboard') ?></legend>
		
		<script>
		function checkcustom()
		{
			var b = document.getElementById('cpd_whocansee');
			var i = document.getElementById('cpd_whocansee_custom_div');
			if ( b.value == 'custom' )
				i.style.display = 'block';
			else
				i.style.display = 'none';
		}
		</script>
		
		<table class="form-table">
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Who can see it', 'cpd') ?>:</th>
			<td>
				<?php $cus = (in_array($o['whocansee'], array('manage_options','manage_links','publish_posts','edit_posts','read'))) ? 0 : 1 ?> 
				<select id="cpd_whocansee" name="cpd_whocansee" onchange="checkcustom()">
					<option value="manage_options" <?php if ($o['whocansee'] == 'manage_options') echo 'selected="selected"' ?>><?php echo translate_user_role('Administrator') ?> </option>
					<option value="manage_links" <?php if ($o['whocansee'] == 'manage_links') echo 'selected="selected"' ?>><?php echo translate_user_role('Editor') ?></option>
					<option value="publish_posts" <?php if ($o['whocansee'] == 'publish_posts') echo 'selected="selected"' ?>><?php echo translate_user_role('Author') ?></option>
					<option value="edit_posts" <?php if ($o['whocansee'] == 'edit_posts') echo 'selected="selected"' ?>><?php echo translate_user_role('Contributor') ?></option>
					<option value="read" <?php if ($o['whocansee'] == 'read') echo 'selected="selected"' ?>><?php echo translate_user_role('Subscriber') ?></option>
					<option value="custom" <?php if ($cus) echo 'selected="selected"' ?>>- <?php echo _e('custom', 'cpd') ?> -</option>
				</select>
				<?php _e('and higher are allowed to see the statistics page.', 'cpd') ?>
				<div id="cpd_whocansee_custom_div" <?php if (!$cus) echo 'style="display:none"' ?>>
				<?php printf(__('Set the %s capability %s a user need:', 'cpd'), '<a href="https://codex.wordpress.org/Roles_and_Capabilities">', '</a>'); ?>
					<input type="text" name="cpd_whocansee_custom" value="<?php echo $o['whocansee'] ?>" />
				</div>
			</td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Visitors per post', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_dashboard_posts" size="3" value="<?php echo $o['dashboard_posts']; ?>" /> <?php _e('How many posts do you want to see on dashboard page?', 'cpd') ?></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Latest Counts - Posts', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_dashboard_last_posts" size="3" value="<?php echo $o['dashboard_last_posts']; ?>" /> <?php _e('How many posts do you want to see on dashboard page?', 'cpd') ?></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Latest Counts - Days', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_dashboard_last_days" size="3" value="<?php echo $o['dashboard_last_days']; ?>" /> <?php _e('How many days do you want look back?', 'cpd') ?></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Chart - Days', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_chart_days" size="3" value="<?php echo $o['chart_days']; ?>" /> <?php _e('How many days do you want look back?', 'cpd') ?></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Chart - Height', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_chart_height" size="3" value="<?php echo $o['chart_height']; ?>" /> px - <?php _e('Height of the biggest bar', 'cpd') ?></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Old Charts', 'cpd') ?>:</th>
			<td><label for="cpd_chart_old"><input type="checkbox" name="cpd_chart_old" id="cpd_chart_old" <?php if($o['chart_old']==1) echo 'checked="checked"'; ?> />  <?php _e('Show old bar charts.', 'cpd') ?></label></td>
		</tr>
		<?php if ($cpd_geoip) { ?>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Countries', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_countries" size="3" value="<?php echo $o['countries']; ?>" /> <?php _e('How many countries do you want to see on dashboard page?', 'cpd') ?></td>
		</tr>
		<?php } ?>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Browsers', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_clients" size="50" value="<?php echo $o['clients']; ?>" /> <?php _e('Substring of the user agent, separated by comma', 'cpd') ?></td>
		</tr>		
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Referrers - Entries', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_dashboard_referers" size="3" value="<?php echo $o['dashboard_referers']; ?>" /> <?php _e('How many referrers do you want to see on dashboard page?', 'cpd') ?></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Referrers - Days', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_referers_last_days" size="3" value="<?php echo $o['referers_last_days']; ?>" /> <?php _e('How many days do you want look back?', 'cpd') ?></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Local URLs', 'cpd') ?>:</th>
			<td><label for="cpd_localref"><input type="checkbox" name="cpd_localref" id="cpd_localref" <?php if($o['localref']==1) echo 'checked="checked"'; ?> />  <?php _e('Show local referrers too.', 'cpd') ?> (<?php echo bloginfo('url') ?>/...)</label></td>
		</tr>
		</table>
		</fieldset>
		
		<!-- lists -->
		
		<fieldset>
		<legend><?php _e('Posts') ?> / <?php _e('Pages') ?></legend>
		<table class="form-table">
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Show in lists', 'cpd') ?>:</th>
			<td><label for="cpd_show_in_lists"><input type="checkbox" name="cpd_show_in_lists" id="cpd_show_in_lists" <?php if($o['show_in_lists']==1) echo 'checked="checked"'; ?> /> <?php _e('Show "Reads per Post" in a new column in post management views.', 'cpd') ?></label></td>
		</tr>
		</table>
		</fieldset>
		
		<!-- start values -->
		
		<fieldset>
		<legend><?php _e('Start Values', 'cpd') ?></legend>
		<table class="form-table">
		<tr>
			<th colspan="2">
				<?php _e('Here you can change the date of first count and add a start count.', 'cpd')?>
			</th>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Start date', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_startdate" size="10" value="<?php echo $o['startdate']; ?>" /> <?php _e('Your old Counter starts at?', 'cpd') ?> [yyyy-mm-dd]</td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Start count', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_startcount" size="10" value="<?php echo $o['startcount']; ?>" /> <?php _e('Add this value to "Total visitors".', 'cpd') ?></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Start count', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_startreads" size="10" value="<?php echo $o['startreads']; ?>" /> <?php _e('Add this value to "Total reads".', 'cpd') ?></td>
		</tr>
		</table>
		</fieldset>
		
		<!-- Stylesheet -->
		
		<fieldset>
		<legend><?php _e('Stylesheet') ?></legend>
		<table class="form-table">
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('NO Stylesheet in Frontend', 'cpd') ?>:</th>
			<td><label for="cpd_no_front_css"><input type="checkbox" name="cpd_no_front_css" id="cpd_no_front_css" <?php if($o['no_front_css']==1) echo 'checked="checked"'; ?> /> <?php _e('Do not load the stylesheet "counter.css" in frontend.', 'cpd') ?></label></td>
		</tr>
		</table>
		</fieldset>
		
		<!-- debug -->
		
		<fieldset>
			<legend style="color:red"><?php _e('Debug mode', 'cpd') ?></legend>
			<p style="margin-top:15px;">
				<label for="cpd_debug"><input type="checkbox" name="cpd_debug" id="cpd_debug" <?php if($o['debug']==1) echo 'checked="checked"'; ?> /> <?php _e('Show debug informations at the bottom of all pages.', 'cpd') ?></label>
			</p>
		</fieldset>
		
		<input type="hidden" name="do" value="cpd_update" />
		<input type="submit" name="update" value="<?php _e('Update options', 'cpd') ?>" class="button-primary" style="margin-left: 5px;" />
		
		</form>

	</div>
	</div>

	<!-- Countries -->
	<div class="postbox">
	<h3><?php _e('GeoIP - Countries', 'cpd') ?></h3>
	<div class="inside">

		<table class="form-table">
		<?php if ( $cpd_geoip ) { ?>
			<tr>
				<td>
					<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
					<input type="hidden" name="do" value="cpd_countries" />
					<input type="submit" name="updcon" value="<?php _e('Update old counter data', 'cpd') ?>" class="button" />
					</form>
				</td>
				<td><?php _e('You can get the country data for all entries in database by checking the IP adress against the GeoIP database. This can take a while!', 'cpd') ?></td>
			</tr>
		<?php } ?>
		
		<?php if ( class_exists('CpdGeoIp') && ini_get('allow_url_fopen') && function_exists('gzopen') ) {
			// install or update database ?>
			<tr>
				<td width="10">
					<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
					<input type="hidden" name="do" value="cpd_countrydb" />
					<input type="submit" name="updcondb" value="<?php _e('Update GeoIP database', 'cpd') ?>" class="button" />
					</form>
				</td>
				<td><?php _e('Download a new version of GeoIP.dat file.', 'cpd') ?></td>
			</tr>
		<?php }	?>
		</table>
	
		<p>
			<span class="cpd-r"><?php _e('More informations about GeoIP', 'cpd') ?>:
			<a href="http://www.maxmind.com/app/geoip_country">www.maxmind.com</a></span>&nbsp;
		</p>

	</div>
	</div>

	<!-- Mass Bots -->
	<div class="postbox">
	<?php
	$limit = (isset($o['massbotlimit'])) ? $o['massbotlimit'] : 25;
	$limit = (isset($_POST['limit'])) ? $_POST['limit'] : $limit;
	$limit_input = '<input type="text" size="3" name="limit" value="'.$limit.'" />';
	$bots = $count_per_day->getMassBots($limit);
	?>
	<h3><?php _e('Mass Bots', 'cpd') ?></h3>
	<div class="inside">
		<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
		<p>
			<?php printf(__('Show all IPs with more than %s page views per day', 'cpd'), $limit_input) ?>
			<input type="submit" name="showmassbots" value="<?php _e('show', 'cpd') ?>" class="button" />
		</p>
		</form>
		
		<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
		<table class="widefat post">
		<thead>
		<tr>
			<th><?php _e('IP', 'cpd') ?></th>
			<th><?php _e('Date', 'cpd') ?></th>
			<th><?php _e('Client', 'cpd') ?></th>
			<th><?php _e('Views', 'cpd') ?></th>
		</tr>
		</thead>
		<?php
		$sum = 0;
		if ( !mysql_errno() ) : 
			while ( $row = mysql_fetch_assoc($bots) )
			{
				$ip = $row['ip'];
				echo '<tr><td style="white-space:nowrap">';
				if ( $cpd_geoip )
				{
					$c = CpdGeoIp::getCountry($ip);
					echo $c[1].' &nbsp;';
				}
				echo '<a href="?page=count-per-day/counter-options.php&amp;dmbip='.$row['longip'].'&amp;dmbdate='.$row['date'].'"
					title="'.sprintf(__('Delete these %s counts', 'cpd'), $row['posts']).'"
					style="color:red; font-weight: bold;">X</a> &nbsp;';
				echo '<a href="http://www.utrace.de/?query='.$ip.'">'.$ip.'</a></td>'
					.'<td style="white-space:nowrap;">'.mysql2date(get_option('date_format'), $row['date'] ).'</td>'
					.'<td>'.$row['client'].'</td>'
					.'<td style="text-align:right;"><a href="'.$count_per_day->dir.'/massbots.php?dmbip='.$row['longip'].'&amp;dmbdate='.$row['date'].'&amp;KeepThis=true&amp;TB_iframe=true" title="Count per Day" class="thickbox">'
						.$row['posts'].'</a></td>'
					.'</tr>';
				$sum += $row['posts'];
			}
		endif;
		?>	
		</table>
		<?php if ( $sum ) { ?>
			<p class="submit">
				<input type="hidden" name="do" value="cpd_delete_massbots" />
				<input type="hidden" name="limit" value="<?php echo $limit ?>" />
				<input type="submit" name="clean" value="<?php printf(__('Delete these %s counts', 'cpd'), $sum) ?>" class="button" />
			</p>
		<?php } ?>
		</form>
	</div>
	</div>

	<!-- Cleaner -->
	<?php if ( $count_per_day->options['referers'] ) : ?>
		<div class="postbox">
		<h3><?php _e('Clean the database', 'cpd') ?></h3>
		<div class="inside">
			<p>
				<?php _e('You can clean the counter table by delete the "spam data".<br />If you add new bots above the old "spam data" keeps in the database.<br />Here you can run the bot filter again and delete the visits of the bots.', 'cpd') ?>
			</p>
			
			<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
			<p class="submit">
				<input type="hidden" name="do" value="cpd_clean" />
				<input type="submit" name="clean" value="<?php _e('Clean the database', 'cpd') ?>" class="button" />
			</p>
			</form>
		</div>
		</div>
	<?php endif; ?>

	<!-- Reset DBs -->
	<div class="postbox">
	<h3><?php _e('Reset the counter', 'cpd') ?></h3>
	<div class="inside">
		<p style="color: red">
			<?php _e('You can reset the counter by empty the table. ALL TO 0!<br />Make a backup if you need the current data!', 'cpd') ?>
		</p>
		
		<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
		<p class="submit">
			<input type="hidden" name="do" value="cpd_reset" />
			<input type="submit" name="clean" value="<?php _e('Reset the counter', 'cpd') ?>" class="button" />
		</p>
		</form>
	</div>
	</div>

	<!-- Uninstall -->
	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>"> 
	<div class="postbox">
	<h3><?php _e('Uninstall', 'cpd') ?></h3>
	<div class="inside"> 
		<p>
			<?php _e('If "Count per Day" only disabled the tables in the database will be preserved.', 'cpd') ?><br/>
			<?php _e('Here you can delete the tables and disable "Count per Day".', 'cpd') ?>
		</p>
		<p style="color: red">
			<strong><?php _e('WARNING', 'cpd') ?>:</strong><br />
			<?php _e('These tables (with ALL counter data) will be deleted.', 'cpd') ?><br />
			<b><?php echo CPD_C_TABLE.', '.CPD_CO_TABLE.', '.CPD_N_TABLE; ?></b><br />
			<?php _e('If "Count per Day" re-installed, the counter starts at 0.', 'cpd') ?>
		</p>
		<p>&nbsp;</p>
		<p class="submit">
			<input type="checkbox" name="uninstall_cpd_yes" value="yes" />&nbsp;<?php _e('Yes', 'cpd'); ?><br /><br />
			<input type="submit" name="do" value="<?php _e('UNINSTALL Count per Day', 'cpd') ?>" class="button" onclick="return confirm('<?php _e('You are sure to disable Count per Day and delete all data?', 'cpd') ?>')" />
		</p>
	</div>
	</div>
	</form>
	
	<!-- Plugin page -->
	<div class="postbox">
	<h3><?php _e('Support', 'cpd') ?></h3>
	<div class="inside">
		<?php $count_per_day->cpdInfo() ?>
	</div>
	</div>
	
	
	</div><!-- poststuff -->
	</div><!-- wrap -->

<?php } // End switch($mode)