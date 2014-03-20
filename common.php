<?php
/* Copyright (C) 2014		Ion Agorria				<cubexed@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *		\file       common.php
 *		\ingroup    sepadolibarr
 *		\brief      Containts common code for pages
 */

/**
 * Prints to js console
 * @param	string	$data	Data to be printed
 * 
 */
function debug_print($data) {
	print '<script>console.log("debug: '.$data.'");</script>';
}

/**
 * Returns the main.inc.php
 * @param	bool	$throw	if set to false, will return NULL instead of throwing a exception if no file is found
 * @return	string			The relative path
 * 
 */
function get_dol_root($file_name, $throw = true) {
	$root_main   = "../" . $file_name;
	$custom_main = "../../" . $file_name;
	
	if(is_readable($root_main)) { 		   	//Exist in root?
		return $root_main;
	} elseif (is_readable($custom_main)) { 	//Probably we are in a subdirectory (custom)
		return $custom_main;
	} elseif (!$throw) {					//Return null instead of throwing exception
		return null;	
	} else {							   	//Throw the exception and stop the script
		throw new Exception("Error locating file: " . $file_name);
	}
}

/**
 * Unicode compatible str_split, source: http://us.php.net/str_split#107658
 *
 */
function str_split_unicode($str, $l = 0) {
	if ($l > 0) {
		$ret = array();
		$len = mb_strlen($str, "UTF-8");
		for ($i = 0; $i < $len; $i += $l) {
			$ret[] = mb_substr($str, $i, $l, "UTF-8");
		}
		return $ret;
	}
	return preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);
}

/**
 * Lists template files, only shows files with starting "template"
 * @param	string		$directory		Directory where to search templates
 * @return	array						Sorted array of matching templates
 */
function list_templates($directory, $file_start)
{
	//Open the templates dir
	$template_dir = opendir($directory);
	
	$file_array = array();
	//Get each entry
	while ($file_name = readdir($template_dir)) 
	{
		if (starts_with($file_name, $file_start, true))
		{ //Only show files with certain start, ignore case
			array_push($file_array, $file_name);
		}
	}
	
	//Close directory
	closedir($template_dir);
	
	//Sort the list
	sort($file_array);
	
	return $file_array;
}

/**
 * Checks if string starts with substring
 * @param	string	$input		the string to be checked
 * @param	string	$start		the string to find
 * @param 	bool	$insenstive if is case insensitive, false by default
 * @return	bool				true if found, false if not
 */
function starts_with($input, $start, $insenstive)
{
	$extra = "";
	if ($insenstive) $extra .= "i"; //Insensitive
	return preg_match('#^'.$start.'#'.$extra, $input);
}

/**
 * Goes to previus stage in the chain
 * @param	array	$chain		the array which contains the chain
 * @param	string	$stage		the current stage name
 * @return	string				returns the previus stage
 */
function previus_stage($chain, $stage)
{
	foreach ($chain as $key => $value)
	{
		if ($value == $stage) return $key;
	}
    return null;
}

/**
 * Removes the $start from $input, sustitutes _ with spaces and removes .php from end
 * @param	string	$input		the string to be checked
 * @param	string	$start		the string to remove from start
 * @return	string				cleaned string
 */
function get_clean_template_name($input, $start)
{
	$input = str_ireplace($start, "", $input);
	$input = str_ireplace(".php", "", $input);
	$input = trim($input);
    return $input;
}

/**
 * Prepares the header for downloading a file, everything printed after this header change will be added to file!
 * @param	string	$file_name	the name of the file to show
 */
function prepare_download_header($file_name)
{
	$extension = substr($file_name, strpos($file_name,'.')+1);
	
	header('Content-disposition: attachment; filename='.$file_name);
	
	if(strtolower($extension) == "txt")
	{
		header('Content-type: text/plain'); 		// Works for txt only
	}
	else
	{
		header('Content-type: application/'.$extension); 	// Works for all extensions except txt
	}
}

/**
 * str_pad variant which cuts string if excedess length
 * @param string $input		Input string
 * @param int	 $len		The length
 * @param string $fill		The fill if input is smaller than length
 */
function pad_len($input, $len, $pad_string = " ", $pad_type = STR_PAD_RIGHT)
{
	$input = str_pad($input, $len, $pad_string, $pad_type); //Fill if smaller
	$input = substr($input, 0, $len); //Get only inside $len
	return $input;
}

?>