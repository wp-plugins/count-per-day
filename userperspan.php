<?php 
// windows junction patch
$dir = dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME']);
$dir = dirname($dir.'x');
$dir = dirname($dir.'x');
$dir = dirname($dir.'x');

require_once($dir.'/wp-load.php');

$datemin = ( !empty($_REQUEST['datemin']) ) ? $_REQUEST['datemin'] : date_i18n('Y-m-d', time() - 86400 * 14); // 14 days
$datemax = ( !empty($_REQUEST['datemax']) ) ? $_REQUEST['datemax'] : date_i18n('Y-m-d');
$page = ( isset($_REQUEST['page']) ) ? $_REQUEST['page'] : 0;

$sql = "SELECT	p.post_title,
				COUNT(*) as count,
				c.page,
				c.date
		FROM	".CPD_C_TABLE." c
		LEFT	JOIN ".$wpdb->posts." p
				ON p.ID = c.page
		WHERE	c.page = '$page'
		AND		c.date >= '$datemin'
		AND		c.date <= '$datemax'
		GROUP	BY c.date
		ORDER	BY c.date desc";
$visits = $count_per_day->getQuery($sql, 'getUserPerPostSpan');
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de-DE">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Count per Day</title>
<link rel="stylesheet" type="text/css" href="counter.css" />
</head>
<body class="cpd-thickbox">

<h2><?php _e('Visitors per day', 'cpd') ?></h2>

<form action="" method="post">
<p style="background:#ddd; padding:3px;">
	<?php _e('Start', 'cpd'); ?>:
	<input type="text" name="datemin" value="<?php echo $datemin; ?>" size="10" />
	<?php _e('End', 'cpd'); ?>:
	<input type="text" name="datemax" value="<?php echo $datemax; ?>" size="10" />
	<?php _e('PostID', 'cpd'); ?>:
	<input type="text" name="page" value="<?php echo $page; ?>" size="5" />
	<input type="submit" value="<?php _e('show', 'cpd') ?>" />  
</p>
</form>

<?php

if ( mysql_num_rows($visits) == 0 )
	_e('keine passenden Daten gefunden', 'cpd');
else
{
	$maxcount = 0;
	while ( $r = mysql_fetch_array($visits) )
		$maxcount = max(array($maxcount, $r['count']));
	mysql_data_seek($visits, 0);
	$faktor = 300 / $maxcount; 
	
	while ( $r = mysql_fetch_array($visits) )
	{
		if ( !isset($new) )
		{
			if ( $page == 0 )
				echo  '<h2>'.__('Front page displays').'</h2';
			else
				echo '<h2>'.$r['post_title'].'</h2>';
			echo '<ol class="cpd-dashboard" style="padding: 0;">';
		}
		else
		{
			if ( $new < $r['count'] )
				$style = 'style="color:#b00;"';
			else if ( $new > $r['count'] )
				$style = 'style="color:#0a0;"';
			else
				$style = '';
		
			$bar = $new * $faktor;
			$trans = 300 - $bar;
			$imgbar = '<img src="'.$count_per_day->getResource('cpd_rot.png').'" alt="" style="width:'.$bar.'px;height:23px;padding-left:10px;" />';
			$imgtrans = '<img src="'.$count_per_day->getResource('cpd_trans.png').'" alt="" style="width:'.$trans.'px;height:10px;padding-right:10px;" />';
			
			echo '<li>';
			echo '<b>'.$imgbar.$imgtrans.'</b>';
			echo '<b '.$style.'>'.$new.'</b>';
			echo $date_str.'</li>';
		}
		$date_str = mysql2date(get_option('date_format'), $r['date']);
		$new = $r['count'];
	}

	$bar = $new * $faktor;
	$trans = 300 - $bar;
	$imgbar = '<img src="'.$count_per_day->getResource('cpd_rot.png').'" alt="" style="width:'.$bar.'px;height:23px;padding-left:10px;" />';
	$imgtrans = '<img src="'.$count_per_day->getResource('cpd_trans.png').'" alt="" style="width:'.$trans.'px;height:10px;padding-right:10px;" />';

	echo '<li>';
	echo '<b>'.$imgbar.$imgtrans.'</b>';
	echo '<b>'.$new.'</b>';
	echo $date_str.'</li>';
}
?>
</ol>
</body>
</html>