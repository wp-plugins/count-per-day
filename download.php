<?php
/*
 * f = filename in tempdir
 * n = download filename
 */
if ( empty($_GET['f']) || empty($_GET['n']) )
	die('no way');
$file = sys_get_temp_dir().'/'.$_GET['f'];
if ( !in_array(substr($file, -3), array('.gz','sql','txt','tmp')) || strpos($file, '..') !== false )
	die('no way');
if (!file_exists($file))
	die('file not found');
$name = stripslashes($_GET['n']);
(substr($name, -2) == 'gz') ? header('Content-Type: application/x-gzip') : header('Content-Type: text/plain');
header("Content-Disposition: attachment; filename=\"$name\"");
readfile($file);
