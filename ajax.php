<?php
// windows junction patch
$dir = dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME']);
for ( $x = 1; $x <= 3; $x++)
	$dir = dirname($dir.'x');

require_once($dir.'/wp-load.php');

if ( $_GET['f'] == 'count' )
{
	$page = intval($_GET['page']);
	
	if ( is_numeric($page) )
	{
		$count_per_day->count( '', $page );
	
		foreach ( $count_per_day->options['widget_functions'] as $f )
		{
			$s = explode('|', $f);
			if ( $s[0] == 'getUserPerDay' )
				$count_per_day->getUserPerDay($count_per_day->options['dashboard_last_days']);
			else if ( $s[0] == 'show' )
				$count_per_day->show('','',true,false,$page);
			else
				eval('$count_per_day->'.$s[0].'();');
			echo '==='.$s[0]."|";
		}
	}
}
?>
