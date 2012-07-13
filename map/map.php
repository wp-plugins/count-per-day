<?php
$what = (empty($_GET['map'])) ? 'reads' : strip_tags($_GET['map']);
if ( !in_array($what, array('visitors','reads','online')) )
	die();
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>CountPerDay</title>
<link rel="stylesheet" type="text/css" href="../counter.css" />
<script type="text/javascript" src="swfobject.js"></script>
</head>
<body style="overflow:hidden; padding:0; margin:0; background:#4499FF;">
	<div id="flashcontent">
		<strong>You need to upgrade your Flash Player</strong>
	</div>
	<script type="text/javascript">
		// <![CDATA[
		var so = new SWFObject("ammap.swf", "ammap", "630", "412", "8", "#4499FF");
		so.addVariable("path", "");
		so.addVariable("settings_file", escape("settings.xml.php?map=<?php echo $what ?>"));
		so.addVariable("data_file", escape("data.xml.php?map=<?php echo $what ?>"));
		so.write("flashcontent");
		// ]]>
	</script>
</body>
</html>