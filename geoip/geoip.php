<?php
/**
 * Filename: geoip.php
 * Count Per Day - GeoIP Addon
 */

/**
 */
include_once('geoip.inc');

class CpdGeoIp
{

/**
 * gets country of ip adress
 * @param $ip IP
 * @return array e.g. ( 'de', image link to easywhois.com , 'Germany' )
 */
function getCountry( $ip )
{
	global $cpd_path;
	
	$gi = geoip_open($cpd_path.'/geoip/GeoIP.dat', GEOIP_STANDARD);
	$c = strtolower(geoip_country_code_by_addr($gi, $ip));
	$country = array(
		$c,
		'<img src="http://www.easywhois.com/images/flags/'.$c.'.gif" alt="'.$c.'" />',
		geoip_country_name_by_addr($gi, $ip)
		);
	geoip_close($gi);
	
	return $country;
}



/**
 * updates CountPerDay table
 */
function updateDB()
{
	global $count_per_day;
	global $cpd_path;
	global $wpdb;
	
	@mysql_query("SELECT country FROM `".CPD_C_TABLE."`", $count_per_day->dbcon);
	if ((int) mysql_errno() == 1054)
		// add row "country" to table
		mysql_query("ALTER TABLE `".CPD_C_TABLE."` ADD `country` CHAR( 2 ) NOT NULL", $count_per_day->dbcon);
	
	$limit = 100;
	$res = @mysql_query("SELECT ip FROM ".CPD_C_TABLE." WHERE country like '' GROUP BY ip ORDER BY count(*) desc LIMIT $limit;", $count_per_day->dbcon);
	$gi = geoip_open($cpd_path.'/geoip/GeoIP.dat', GEOIP_STANDARD);
	while ( $r = mysql_fetch_array($res) )
	{
		$c = strtolower(geoip_country_code_by_addr($gi, $r['ip']));
		mysql_query("UPDATE ".CPD_C_TABLE." SET country = '".$c."' WHERE ip = '".$r['ip']."'", $count_per_day->dbcon);
	}
	geoip_close($gi);
	
	$res = mysql_query("SELECT count(*) FROM ".CPD_C_TABLE." WHERE country like ''", $count_per_day->dbcon);
	$row = mysql_fetch_array($res);
	$rest = (!empty($row[0])) ? $row[0] : 0;

	return $rest;
}



/**
 * updates the GeoIP database file
 * works only if directory geoip has rights 777, set it in ftp client
 */
function updateGeoIpFile()
{
	global $cpd_path;
	
	// function checks
	if ( !ini_get('allow_url_fopen') )
		return 'Sorry, <code>allow_url_open</code> is disabled!';
		
	if ( !function_exists('gzopen') )
		return __('Sorry, necessary functions (zlib) not installed or enabled in php.ini.', 'cpd');
	
	$gzfile = 'http://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz';
	$file = $cpd_path.'/geoip/GeoIP.dat';

	// get remote file
	$h = gzopen($gzfile, 'rb');
	$content = gzread($h, 1500000);
	fclose($h);

	// delete local file
	if (is_file($file))
		unlink($file);

	// write new locale file
	$h = fopen($file, 'wb');
	fwrite($h, $content);
	fclose($h);
	
	@chmod($file, 0777);
	if (is_file($file))
		return __('New GeoIP database installed.', 'cpd');
	else
		return __('Sorry, an error occurred. Try again or check the access rights of directory "geoip" is 777.', 'cpd');
}


}
?>