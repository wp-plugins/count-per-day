<?php
if ( $_GET['f'] == 'count' )
{
	// answer only for 20 seconds after calling
	if ( empty($_GET['time']) || time() - $_GET['time'] > 20 )
	{
		header("HTTP/1.0 403 Forbidden");
		die('wrong request');
	}
	
	if (!session_id()) session_start();
	$cpd_wp = (!empty($_SESSION['cpd_wp'])) ? $_SESSION['cpd_wp'] : '../../../';
	require_once($cpd_wp.'wp-load.php');
	
	$cpd_funcs = array ( 'show',
	'getReadsAll', 'getReadsToday', 'getReadsYesterday', 'getReadsLastWeek', 'getReadsThisMonth',
	'getUserAll', 'getUserToday', 'getUserYesterday', 'getUserLastWeek', 'getUserThisMonth',
	'getUserPerDay', 'getUserOnline', 'getFirstCount' );
	
	$page = (int) $_GET['page'];
	if ( is_numeric($page) )
	{
		$count_per_day->count( '', $page );
		foreach ( $cpd_funcs as $f )
		{
			if ( ($f == 'show' && $page) || $f != 'show' )
			{
				echo $f.'===';
				if ( $f == 'getUserPerDay' )
					echo $count_per_day->getUserPerDay($count_per_day->options['dashboard_last_days']);
				else if ( $f == 'show' )
					echo $count_per_day->show('', '', false, false, $page);
				else
					echo $count_per_day->{$f}();
				echo '|';
			}
		}
	}
}
