<?php 
if (!session_id()) session_start();
require_once($_SESSION['cpd_wp'].'wp-load.php');

if ( isset($_GET['dmbip']) && isset($_GET['dmbdate']) )
{
	$sql = 'SELECT	c.page post_id, p.post_title post,
					t.name tag_cat_name,
					t.slug tag_cat_slug,
					x.taxonomy tax
			FROM	'.CPD_C_TABLE.' c
			LEFT	JOIN '.$wpdb->posts.' p
					ON p.ID = c.page
			LEFT	JOIN '.$wpdb->terms.' t
					ON t.term_id = 0 - c.page
			LEFT	JOIN '.$wpdb->term_taxonomy.' x
					ON x.term_id = t.term_id
			WHERE	c.ip = '.$_GET['dmbip'].'
			AND		c.date = \''.$_GET['dmbdate'].'\'
			ORDER	BY p.ID';
	$massbots = $count_per_day->getQuery($sql, 'showMassbotPosts');
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de-DE">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Count per Day</title>
<link rel="stylesheet" type="text/css" href="counter.css" />
</head>
<body class="cpd-thickbox">
<h2><?php _e('Mass Bots', 'cpd') ?></h2>
<ol>
<?php
while ( $row = mysql_fetch_array($massbots) )
{
	if ( $row['post_id'] < 0 && $row['tax'] == 'category' )
	{
		$name = '- '.$row['tag_cat_name'].' -';
		$link = get_bloginfo('url').'?cat='.abs($row['post_id']);
	}
	else if ( $row['post_id'] < 0 )
	{
		$name = '- '.$row['tag_cat_name'].' -';
		$link = get_bloginfo('url').'?tag='.$row['tag_cat_slug'];
	}
	else if ( $row['post_id'] == 0 )
	{
		$name = '- '.__('Front page displays').' -';
		$link =	get_bloginfo('url');
	}
	else
	{
		$postname = $row['post'];
		if ( empty($postname) ) 
			$postname = '---';
		$name = $postname;
		$link =	get_permalink($row['post_id']);
	}
	echo '<li><a href="'.$link.'" target="_blank">'.$name.'</a></li>';
}
?>
</ol>
<?php if ($count_per_day->options['debug']) $count_per_day->showQueries(); ?>
</body>
</html>