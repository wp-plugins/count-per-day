<?php
$what = (empty($_GET['map'])) ? 'Reads' : ucfirst(strip_tags($_GET['map']));
$disable = (empty($_GET['min'])) ? '' : '<enabled>false</enabled>';

header("content-type: text/xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>

<settings>
<projection>mercator</projection>
<always_hand>true</always_hand>  

<small_map>
	<enabled>false</enabled>
</small_map>

<area>
	<balloon_text><![CDATA[{title}<br/><b>{value}</b> <?php echo $what ?>]]></balloon_text>
	<color_solid>#CC0000</color_solid>
	<color_light>#FFFFFF</color_light>
	<color_hover>#FFFF00</color_hover>
	<color_unlisted>#3388EE</color_unlisted>
	<disable_when_clicked>true</disable_when_clicked>
</area>

<movie>
	<balloon_text><![CDATA[{title}]]></balloon_text> 
	<color_hover>#0000ff</color_hover>
</movie>

<balloon>
	<color>#FFFFFF</color>
	<alpha>85</alpha>
	<text_color>#000000</text_color>
	<border_color>#CC0000</border_color>
	<border_alpha>90</border_alpha>
	<border_width>2</border_width>
	<corner_radius>7</corner_radius>
</balloon>

<zoom>
	<?php echo $disable ?>
    <x>5</x>
    <y>27</y>
	<min>85</min>
</zoom>

<legend>
	<?php echo $disable ?>
	<x>5</x>
	<y>!32</y>
	<margins>5</margins>
	<key>
		<border_color>#AAAAAA</border_color>
	</key>
	<entries>
		<entry color="#3388EE">no <?php echo $what ?></entry>
		<entry color="#FFFFFF">least <?php echo $what ?></entry>
		<entry color="#CC0000">most <?php echo $what ?></entry>
	</entries>
</legend>

</settings>
