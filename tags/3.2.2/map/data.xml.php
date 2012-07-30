<?php
if (!session_id()) session_start();
$cpd_wp = (!empty($_SESSION['cpd_wp'])) ? $_SESSION['cpd_wp'] : '../../../../';
require_once($cpd_wp.'wp-load.php');
require_once($cpd_path.'/geoip/geoip.php');
$geoip = new GeoIPCpD();
$data = array();

$what = (empty($_GET['map'])) ? 'reads' : strip_tags($_GET['map']);

if ( $what == 'online' )
{
	$gi = cpd_geoip_open($cpd_path.'geoip/GeoIP.dat', GEOIP_STANDARD);
	$oc = get_option('count_per_day_online', array());
	$vo = array();
	foreach ($oc as $ip => $x)
	{
		$country = cpd_geoip_country_code_by_addr($gi, $ip);
		$id = $geoip->GEOIP_COUNTRY_CODE_TO_NUMBER[$country];
		if ( !empty($id) )
		{
			$name = $geoip->GEOIP_COUNTRY_NAMES[$id];
			$count = (isset($vo[$country])) ? $vo[$country][1] + 1 : 1;
			$vo[$country] = array($name, $count);
		}
	}
	foreach ( $vo as $k => $v )
		$data[] = array($v[0], $k ,$v[1]);
}
else
{
	$temp = $count_per_day->addCollectionToCountries( ($what == 'visitors') );

	foreach ($temp as $country => $value)
	{
		if ($country != '-')
		{
			$country = strtoupper($country);
			$name = $geoip->GEOIP_COUNTRY_NAMES[ $geoip->GEOIP_COUNTRY_CODE_TO_NUMBER[$country] ];
			if ( !empty($name) )
				$data[] = array($name, $country ,$value);
		}
	}
}

header("content-type: text/xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<map map_file="world.swf" tl_long="-168.49" tl_lat="83.63" br_long="190.3" br_lat="-55.58" zoom_x="10%" zoom_y="6%" zoom="85%">
<areas>
<?php
foreach ( $data as $d )
	echo '<area title="'.$d[0].'" mc_name="'.$d[1].'" value="'.$d[2].'"></area>
	';
?>
<area title="borders" mc_name="borders" color="#AAAAAA" balloon="false"></area>
</areas>
<?php if (empty($_GET['min'])) { ?>
<labels>
	<label x="0" y="0" width="100%" align="center" text_size="16" color="#000000">
		<text><![CDATA[<b>Your Visitors all over the World</b>]]></text>
	</label>
</labels>
<movies>
	<movie long="13" lat="53.4" file="target" width="10" height="10" color="#000000" fixed_size="true" title="Home of your best friend:      Tom, the plugin author ;)"></movie>
</movies>
<?php } ?>
</map>
