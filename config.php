<?php
define("SEPADOLIBARR_RELATIVE_URL", "/custom/sepadolibarr/"); //The relative location of module regarding to DOL_DOCUMENT_ROOT
define("SEPADOLIBARR_TEMPLATE_PREFIX", "template_");
define("SEPADOLIBARR_TEMPLATE_DIR", "templates");
define("SEPADOLIBARR_CHAR_SET", "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 /-?:().,+");

//You sould not touch these
define("SEPADOLIBARR_ABSOLUTE_URL", DOL_DOCUMENT_ROOT . SEPADOLIBARR_RELATIVE_URL);
define("SEPADOLIBARR_TEMPLATE_URL", SEPADOLIBARR_ABSOLUTE_URL . SEPADOLIBARR_TEMPLATE_DIR);
define("SEPADOLIBARR_REPLACE_URL", "?sepadolibarr_replacedwithhash");
?>