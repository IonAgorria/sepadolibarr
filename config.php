<?php

define("SEPADOLIBARR_TEMPLATE_PREFIX", "template_");
define("SEPADOLIBARR_TEMPLATE_CT_DIR", "templates_ct");
define("SEPADOLIBARR_TEMPLATE_DD_DIR", "templates_dd");
define("SEPADOLIBARR_CHAR_SET", "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 /-?:().,+");

//You sould not touch anything from this point
define("SEPADOLIBARR_REPLACE_URL", "?sepadolibarr_replacedwithhash");

//This resolves the relative URL which this module resides
$path = ".";
$max_recursion = 10;
$recursion = 0;
while ($recursion <= $max_recursion & !is_readable($path . "/main.inc.php") == 1)
{
	$path .= "/..";
	$recursion++;
}
$dir = dirname(__FILE__);
$path = "";
$max_recursion = $recursion - 1;
$recursion = 0;
while ($recursion <= $max_recursion) {
	if ($path != "") $path = "/" . $path;
	$path = basename($dir) . $path;
	$dir = dirname($dir);
	$recursion++;
}
define("SEPADOLIBARR_RELATIVE_URL", "/" . $path . "/");
define("SEPADOLIBARR_ABSOLUTE_URL", DOL_DOCUMENT_ROOT . SEPADOLIBARR_RELATIVE_URL);
?>