<?php
/**
 * Filename: counter-options.php
 * Count Per Day - Options and Uninstall
 */

// Form auswerten 
if(!empty($_POST['do']))
{
	switch($_POST['do'])
	{
		// update options
		case 'cpd_update' :
			update_option( 'cpd_onlinetime', $_POST['cpd_onlinetime'] );
			$u = empty( $_POST['cpd_user'] ) ? 0 : 1 ;
			update_option( 'cpd_user', $u );
			$a = empty( $_POST['cpd_autocount'] ) ? 0 : 1 ;
			update_option( 'cpd_autocount', $a );
			update_option( 'cpd_bots', $_POST['cpd_bots'] );
			echo '<div id="message" class="updated fade"><p>'.__('Options updated', 'cpd').'</p></div>';
			break;
		// clean database
		case 'cpd_clean' :
			$rows = cpdCleanDB();
			echo '<div id="message" class="updated fade"><p>'.sprintf(__('Database cleaned. %s rows deleted.', 'cpd'), $rows).'</p></div>';
			break;
		//  uninstall plugin
		case __('UNINSTALL Count per Day', 'cpd') :
			if(trim($_POST['uninstall_cpd_yes']) == 'yes')
			{
				$wpdb->query("DROP TABLE IF EXISTS ".CPD_C_TABLE.";");
				$wpdb->query("DROP TABLE IF EXISTS ".CPD_CO_TABLE.";");
				delete_option('cpd_cdb_version');
				delete_option('cpd_codb_version');
				delete_option('cpd_onlinetime');
				delete_option('cpd_user');
				delete_option('cpd_autocount');
				delete_option('cpd_bots');
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
	// Deaktivierung
	case 'end-UNINSTALL':
		$deactivate_url = 'plugins.php?action=deactivate&amp;plugin='.dirname(plugin_basename(__FILE__)).'/counter.php';
		if ( function_exists('wp_nonce_url') ) 
			$deactivate_url = wp_nonce_url($deactivate_url, 'deactivate-plugin_'.dirname(plugin_basename(__FILE__)).'/counter.php');
		echo '<div class="wrap">';
		echo '<h2>'.__('Uninstall', 'cpd').' "Count per Day"</h2>';
		echo '<p><strong><a href="'.$deactivate_url.'">'.__('Click here', 'cpd').'</a> '.__('to finish the uninstall and to deactivate "Count per Day".', 'cpd').'</strong></p>';
		echo '</div>';
		break;
	// Seite anzeigen
	default:
	?>
	<div class="wrap">
		<h2>Count per Day - <?php _e('Options', 'cpd') ?></h2>
		
		<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
		<table class="form-table">
		<tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Online time', 'cpd') ?>:</th>
			<td><input class="code" type="text" name="cpd_onlinetime" size="3" value="<?php echo get_option('cpd_onlinetime'); ?>" /> <?php _e('Seconds for online counter', 'cpd') ?></td>
		</tr><tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Loged on Users', 'cpd') ?>:</th>
			<td><input type="checkbox" name="cpd_user" id="cpd_user" <?php if(get_option('cpd_user')==1) echo 'checked="checked"'; ?> /> <label for="cpd_user"><?php _e('count too', 'cpd') ?></label></td>
		</tr><tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Auto counter', 'cpd') ?>:</th>
			<td><input type="checkbox" name="cpd_autocount" id="cpd_autocount" <?php if(get_option('cpd_autocount')==1) echo 'checked="checked"'; ?> /> <label for="cpd_autocount"><?php _e('Counts automatically single-posts and pages, no changes on template needed.', 'cpd') ?></label></td>
		</tr><tr>
			<th nowrap="nowrap" scope="row" style="vertical-align:middle;"><?php _e('Bots to ignore', 'cpd') ?>:</th>
			<td><textarea name="cpd_bots" cols="50" rows="10"><?php echo get_option('cpd_bots'); ?></textarea></td>
		</tr>
		</table>
		<p>
			<input type="hidden" name="do" value="cpd_update" />
			<input type="submit" name="update" value="<?php _e('Update options', 'cpd') ?>" class="button" />
		</p>
		</form>
	</div>


	<!-- Cleaner -->
	<div class="wrap" style="margin-top: 50px;">
		<h2>Count per Day - <?php _e('Clean the database', 'cpd') ?></h2>
		
		<p>
			<?php _e('You can clean the counter table by delete the "spam data".<br />If you add new bots above the old "spam data" keeps in the database.<br />Here you can run the bot filter again and delete the visits of the bots.', 'cpd') ?>
		</p>
		
		<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
		<p>
			<input type="hidden" name="do" value="cpd_clean" />
			<input type="submit" name="clean" value="<?php _e('Clean the database', 'cpd') ?>" class="button" />
		</p>
		</form>
	</div>


	<!-- Uninstall -->
	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>"> 
	<div class="wrap" style="margin-top: 100px;"> 
		<h2>Count per Day - <?php _e('Uninstall', 'cpd') ?></h2>
		<p>
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
		<p style="text-align: center;">
			<input type="checkbox" name="uninstall_cpd_yes" value="yes" />&nbsp;<?php _e('Yes', 'cpd'); ?><br /><br />
			<input type="submit" name="do" value="<?php _e('UNINSTALL Count per Day', 'cpd') ?>" class="button" onclick="return confirm('<?php _e('You are sure to disable Count per Day and delete all data?', 'cpd') ?>')" />
		</p>
	</div> 
	</form>

<?php
} // End switch($mode)
?>