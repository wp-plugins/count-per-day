<?php
// windows junction patch
$dir = dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME']);
for ( $x = 1; $x <= 5; $x++ )
{
	$dir = dirname($dir.'x');
	if ( is_file($dir.'/wp-load.php') )
		require_once($dir.'/wp-load.php');
}

$cpd_funcs = array ( 'show',
	'getReadsAll', 'getReadsToday', 'getReadsYesterday', 'getReadsLastWeek', 'getReadsThisMonth',
	'getUserAll', 'getUserToday', 'getUserYesterday', 'getUserLastWeek', 'getUserThisMonth',
	'getUserPerDay', 'getUserOnline', 'getFirstCount' );

if ( $_GET['f'] == 'count' )
{
	$page = intval($_GET['page']);
	if ( is_numeric($page) )
	{
		$count_per_day->count( '', $page );
		foreach ( $cpd_funcs as $f )
		{
			if ( ($f == 'show' && $page) || $f != 'show' )
			{
				echo $f.'===';
				if ( $f == 'getUserPerDay' )
					eval('echo $count_per_day->getUserPerDay('.$count_per_day->options['dashboard_last_days'].');');
				else if ( $f == 'show' )
					eval('echo $count_per_day->show("", "", false, false, '.$page.');');
				else
					eval('echo $count_per_day->'.$f.'();');
				echo '|';
			}
		}
	}
}
?>
