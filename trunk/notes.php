<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"  dir="ltr" lang="de-DE">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>CountPerDay</title>
<link rel="stylesheet" type="text/css" href="counter.css" />
</head>
<body>
<?php 
require('../../../wp-load.php');

// save changes
if ( isset($_POST['new']) )
	$sql = "INSERT INTO ".$table_prefix."cpd_notes (date, note) VALUES ('".$_POST['date']."', '".$_POST['note']."')";
else if ( isset($_POST['edit']) )
	$sql = "UPDATE ".$table_prefix."cpd_notes SET date = '".$_POST['date']."', note = '".$_POST['note']."' WHERE id = ".$_POST['id'];
else if ( isset($_POST['delete']) )
	$sql = "DELETE FROM ".$table_prefix."cpd_notes WHERE id = ".$_POST['id'];
if ( !empty($sql) )
	$wpdb->query($wpdb->prepare($sql)); 
 
// load notes
$notes = $wpdb->get_results('SELECT * FROM '.$table_prefix.'cpd_notes ORDER BY date DESC', ARRAY_A);
?>

<form name="cpd_notes_form1" action="" method="post">
<table class="cpd-notes">
<tr>
	<th style="width:15%"><?php _e('Date') ?></th>
	<th style="width:75%"><?php _e('Notes', 'cpd') ?> <?php _e('(1 per day)', 'cpd') ?></th>
	<th style="width:10%"><?php _e('Action') ?></th>
</tr>
<tr>
	<td><input name="date" /></td>
	<td><input name="note" /></td>
	<td><input type="submit" name="new" value="+" title="<?php _e('add', 'cpd') ?>" class="green" /></td>
</tr>
<?php
if ( $notes )
{
	foreach ( $notes as $row )
	{
		if ( isset($_POST['edit_'.$row['id']]) || isset($_POST['edit_'.$row['id'].'_x']) )
		{
			?>
			<tr style="background: #ccc">
				<td><input name="date" value="<?php echo $row['date'] ?>" /></td>
				<td><input name="note" value="<?php echo $row['note'] ?>" /></td>
				<td>
					<input type="hidden" name="id" value="<?php echo $row['id'] ?>" />
					<input type="submit" name="edit" value="V" title="<?php _e('save', 'cpd') ?>" class="green" style="width:45%;" />
					<input type="submit" name="delete" value="X"title="<?php _e('delete', 'cpd') ?>" class="red" style="width:45%;" />
				</td>
			</tr>
			<?php
		}
		else
		{
			?>
			<tr>
				<td><?php echo $row['date'] ?></td>
				<td><?php echo $row['note'] ?></td>
				<td><input type="image" src="cpd_pen.png" name="edit_<?php echo $row['id'] ?>" title="<?php _e('edit', 'cpd') ?>" style="width:auto;" /></td>
			</tr>
			<?php
		}
	}
}
?>
</table>
</form>
</body>
</html>