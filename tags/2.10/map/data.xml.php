<?php
require('../../../../wp-load.php');
include_once($cpd_path.'/geoip/geoip.php');
$geoip = new GeoIP();

$res = $count_per_day->getQuery("SELECT country, COUNT(*) c FROM ".CPD_C_TABLE." WHERE country > '' GROUP BY country", 'getCountriesMap');
$data = array();
while ( $r = mysql_fetch_array($res) )
{
	$country = strtoupper($r['country']);
	$name = $geoip->GEOIP_COUNTRY_NAMES[ $geoip->GEOIP_COUNTRY_CODE_TO_NUMBER[$country] ];
	if ( !empty($name) )
		$data[] = array($name, $country ,$r['c']);
}

header("content-type: text/xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>
<map map_file="world.swf" tl_long="-168.49" tl_lat="83.63" br_long="190.3" br_lat="-55.58" zoom_x="10%" zoom_y="6%" zoom="85%">
<areas>
';

foreach ( $data as $d )
	echo '	<area title="'.$d[0].'" mc_name="'.$d[1].'" value="'.$d[2].'"></area>
	';

echo '
	<area title="borders" mc_name="borders" color="#AAAAAA" balloon="false"></area>
</areas>
	
<labels>
	<label x="0" y="0" width="100%" align="center" text_size="16" color="#000000">
		<text><![CDATA[<b>Your Visitors all over the World</b>]]></text>
	</label>
</labels>

<movies>
	<movie long="13" lat="53.4" file="target" width="10" height="10" color="#000000" fixed_size="true" title="Home of your best friend:      Tom, the plugin author ;)"></movie>
</movies>

</map>
';
?>
