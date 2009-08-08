<?php
/**
 * Filename: counter-options.php
 * Count Per Day - Options and Administration
 */

// Form auswerten 
if(!empty($_POST['do']))
{
	//global $wpdb;
	
	switch($_POST['do'])
	{
		// update options
		case 'cpd_update' :
			$count_per_day->options['onlinetime'] = $_POST['cpd_onlinetime'];
			$count_per_day->options['user'] = empty( $_POST['cpd_user'] ) ? 0 : 1 ;
			$count_per_day->options['autocount'] = empty( $_POST['cpd_autocount'] ) ? 0 : 1 ;
			$count_per_day->options['bots'] = $_POST['cpd_bots'];
			$count_per_day->options['dashboard_posts'] = $_POST['cpd_dashboard_posts'];
			$count_per_day->options['dashboard_last_posts'] = $_POST['cpd_dashboard_last_posts'];
			$count_per_day->options['dashboard_last_days'] = $_POST['cpd_dashboard_last_days'];
			$count_per_day->options['show_in_lists'] = empty( $_POST['cpd_show_in_lists'] ) ? 0 : 1 ;
			$count_per_day->options['chart_days'] = $_POST['cpd_chart_days'];
			$count_per_day->options['chart_height'] = $_POST['cpd_chart_height'];
			
			update_option('count_per_day', $count_per_day->options);
			
			echo '<div id="message" class="updated fade"><p>'.__('Options updated', 'cpd').'</p></div>';
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
				$wpdb->query("DROP TABLE IF EXISTS ".CPD_C_TABLE.";");
				$wpdb->query("DROP TABLE IF EXISTS ".CPD_CO_TABLE.";");
				delete_option('count_per_day');
				echo '<div id="message" class="updated fade"><p>';
				printf(__('Table %s deleted', 'cpd'), CPD_C_TABLE);
				echo '<br/>';
				printf(__('Table %s deleted', 'cpd'), CPD_CO_TABLE);
				echo '<br/>';
				echo __('Options deleted', 'cpd').'</p></div>';
				$mode = 'end-UNINSTALL';
			}
			break;
			
		default:
			break;
	}
}

switch($mode) {
	// deaktivation
	case 'end-UNINSTALL':
		$deactivate_url = 'plugins.php?action=deactivate&amp;plugin='.dirname(plugin_basename(__FILE__)).'/counter.php';
		if ( function_exists('wp_nonce_url') ) 
			$deactivate_url = wp_nonce_url($deactivate_url, 'deactivate-plugin_'.dirname(plugin_basename(__FILE__)).'/counter.php');
		echo '<div class="wrap">';
		echo '<h2>'.__('Uninstall', 'cpd').' "Count per Day"</h2>';
		echo '<p><strong><a href="'.$deactivate_url.'">'.__('Click here', 'cpd').'</a> '.__('to finish the uninstall and to deactivate "Count per Day".', 'cpd').'</strong></p>';
		echo '</div>';
		break;
		
	default:
	// show options page

	$o = $count_per_day->options;
	?>
	<div id="poststuff" class="wrap">
	
	<h2><img src="<?php echo $count_per_day->getResource('cpd_menu.gif') ?>" alt="" style="width:24px;height:24px" /> Count per Day</h2>

	<div class="postbox">
	<h3><?php _e('Options', 'cpd') ?></h3>
	<div class="inside">
		<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
		<table class="form-table">
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Online time', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_onlinetime" size="3" value="<?php echo $o['onlinetime']; ?>" /> <?php _e('Seconds for online counter. Used for "Visitors online" on dashboard page.', 'cpd') ?></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Loged on Users', 'cpd') ?>:</th>
			<td><label for="cpd_user"><input type="checkbox" name="cpd_user" id="cpd_user" <?php if($o['user']==1) echo 'checked="checked"'; ?> /> <?php _e('count too', 'cpd') ?></label></td>
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
			<th colspan="2"><h3><?php _e('Dashboard') ?></h3></th>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Reads per post', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_dashboard_posts" size="3" value="<?php echo $o['dashboard_posts']; ?>" /> <?php _e('How many posts do you want to see on dashboard page?', 'cpd') ?></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Last Reads - Posts', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_dashboard_last_posts" size="3" value="<?php echo $o['dashboard_last_posts']; ?>" /> <?php _e('How many posts do you want to see on dashboard page?', 'cpd') ?></td>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Last Reads - Days', 'cpd') ?>:</th>
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
			<th colspan="2"><h3><?php _e('Edit Posts') ?> / <?php _e('Edit Pages') ?></h3></th>
		</tr>
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Show in lists', 'cpd') ?>:</th>
			<td><label for="cpd_show_in_lists"><input type="checkbox" name="cpd_show_in_lists" id="cpd_show_in_lists" <?php if($o['show_in_lists']==1) echo 'checked="checked"'; ?> /> <?php _e('Show "Reads per Post" in a new column in post management views.', 'cpd') ?></label></td>
		</tr>
		</table>
		<p class="submit">
			<input type="hidden" name="do" value="cpd_update" />
			<input type="submit" name="update" value="<?php _e('Update options', 'cpd') ?>" class="button-primary" />
		</p>
		</form>
	</div>
	</div>

	<!-- Cleaner -->
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
			<b><?php _e('Since WP 2.7 you can delete the plugin directly after deactivation on the plugins page.', 'cpd') ?></b><br />
			<?php _e('If "Count per Day" only disabled the tables in the database will be preserved.', 'cpd') ?><br/>
			<?php _e('Here you can delete the tables and disable "Count per Day".', 'cpd') ?>
		</p>
		<p style="text-align: left; color: red">
			<strong><?php _e('WARNING', 'cpd') ?>:</strong><br />
			<?php _e('These tables (with ALL counter data) will be deleted.', 'cpd') ?><br />
			<b><?php echo CPD_C_TABLE.', '.CPD_CO_TABLE; ?></b><br />
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
	
	</div><!-- wrap -->

<?php } // End switch($mode) ?>