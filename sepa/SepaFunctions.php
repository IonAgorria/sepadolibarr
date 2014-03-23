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
 * \file       SepaFunction.php
 * \ingroup    sepadolibarr
 * \brief      Containts some functions for SEPA
 */

//Ported from https://github.com/QuimFerrer/sepa/blob/master/sepamisc.prg
//PHP date documentation http://php.net/manual/en/function.date.php
//Helpfull info: https://wiki.xmldation.com/Support/ISO20022/General_Rules

require_once 'common.php';

/* 
 * PHP doc
 * Y 	A full numeric representation of a year, 4 digits 		Examples: 1999 or 2003
 * m 	Numeric representation of a month, with leading zeros	01 through 12
 * d 	Day of the month, 2 digits with leading zeros 			01 to 31
 * H 	24-hour format of an hour with leading zeros 	00 through 23
 * i 	Minutes with leading zeros 						00 to 59
 * s 	Seconds, with leading zeros 					00 through 59
 */

/**
 * Returns the hh:mm:ss time in hhmmss format
 * @param	int		$timestamp			timestamp, if not specified will use time()
 * @return	string						hhmmss
 */
function cTime($timestamp = null)
{
	if (empty($timestamp)) $timestamp = time();
	return date("His", $timestamp);
}

/**
 * Returns the YYYY/MM/DD date in YYYYMMDD format
 * @param	int		$timestamp			timestamp, if not specified will use time()
 * @return	string						YYYYMMDD
 */
function fDate($timestamp = null)
{
	if (empty($timestamp)) $timestamp = time();
	return date("Ymd", $timestamp);
}

/**
 * Returns the YYYY/MM/DD date in YYYY-MM-DD format
 * @param	int		$timestamp			timestamp, if not specified will use time()
 * @return	string						YYYY-MM-DD
 */
function sDate($timestamp = null)
{
	if (empty($timestamp)) $timestamp = time();
	return date("Y-m-d", $timestamp);
}

/**
 * Returns the YYYY/MM/DD hh:mm:ss date in YYYY-MM-DDThh:mm:ss format
 * @param	int		$timestamp			timestamp, if not specified will use time()
 * @return	string						YYYY-MM-DDThh:mm:ss
 */
function IsoDateTime($timestamp = null)
{
	if (empty($timestamp)) $timestamp = time();
	return date("Y-m-d\TH:i:s", $timestamp); // the \T is for using literally the T character
}

/* 
from: https://github.com/QuimFerrer/sepa/blob/master/doc/BBVA-SEPA.pdf
El código de identificación del acreedor o “creditor ID”, en España tiene el formato
ESZZXXXAAAAAAAAA, siendo:
	ZZ: dígitos de control
	XXX: sufijo
	AAAAAAAAA: NIF
Los dígitos de control se calculan en base al NIF, aplicando el modelo 97-10.
En caso de otro pais se sustituye "ES" por la representacion ISO country code (2 caracteres) de dicho pais

Cálculo dígitos de control
NIF: A12345678
Sufijo: 000 
País: España -> ES
1.	 Tomamos posiciones de la 8 a la 15:
2.	 Añadimos ES y 00: A12345678ES00
3.	 Convertimos números a letras (según tabla
cuaderno): 1012345678142800
4.	 Aplicamos modelo 97-10 (dado un no, lo
dividimos entre 97 y restamos a 98 el resto de
la operación. Si se obtinene un único dígito, se
completa con un cero por delante): 53

----------------------------------------------------------------------------------------------
Other info sources:
https://github.com/QuimFerrer/sepa/blob/master/sepamisc.prg
http://www.europeanpaymentscouncil.eu/article.cfm?articles_uuid=0A12B924-9CE2-06DC-D53E41A37079D396
*/
/**
 * Calculates the identification that is used in SEPA creditor identifier
 * @param	str		$iso_country_code			2 char ISO country code
 * @param	int		$creditor_business_code	    Creditor own id which can use to identify diferent bussiness lines
 * @param	str		$country_specific_id		Country specific national identifier
 * @return	string								The calculated creditor id
 */
function calculate_creditor_identifier($iso_country_code, $creditor_business_code, $country_specific_id)
{
	$check_digit_raw = $country_specific_id . $iso_country_code . "00";
	$check_digit_converted = str_replace(range('A', 'Z'), range(10, 35), $check_digit_raw);
	$check_digit = $check_digit_converted ; 							//Convert to integer
	$check_digit = 98 - bcmod($check_digit, 97);  							//Modulo by 97 and substract 98
	if (strlen($check_digit) == 1) $check_digit = "0" . $check_digit; 	//a 0 must be added in front if its only 1 digit lenght
	$creditor_identifier = $iso_country_code . $check_digit . $creditor_business_code . $country_specific_id;
	
	//Safety measures
	if (strlen($check_digit) != 2) throw new Exception("The check digit is not 2 chr len: " . $check_digit);
	if (strlen($creditor_identifier) > 35) throw new Exception("Total lenght of creditor identifier exceedes 35 max: " . $creditor_identifier);
	
	return $creditor_identifier;
}

/**
 * Sums $n days to a date
 * @param	str		$current_date				The current date that is going to be added
 * @param	int		$n	    					Number of days to sum
 * @return	string								The result of sum
 */
function date_sum_days($current_date, $n)
{
	return strtotime(IsoDateTime($current_date).' + '.$n.' days');
}

/**
 * Filters each character acording to SEPA charset and conversions
 * @param	str		$input						The input
 * @return	string								The filtered string
 */

function filtered_str($input)
{
	//From: http://stackoverflow.com/a/14815225
	$input = strtr(utf8_decode($input),
        utf8_decode(
        'ŠŒŽšœžŸ¥µÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýÿº'),
        'SOZsozYYuAAAAAAACEEEEIIIIDNOOOOOOUUUUYsaaaaaaaceeeeiiiionoooooouuuuyyo'
	);
	$filtered = "";
	$input_array = str_split_unicode($input);
	foreach ($input_array as $char) {
		if (!ctype_print($char))
		{
			if ($char == "\r" | $char == "\n")
			{ //Convert CR and NL chars to space
				$char = " ";
			}
			else
			{ //Ignore the rest of non printable characters
				$char = "";
			}
		}
		else if (strpos(SEPADOLIBARR_CHAR_SET, $char) === false) 
		{ //The === is not a typo, is necesary to do check type (strpos can return 0)
			$char = "?";
		}
		$filtered.= $char;
	}
	//print $input."<br>".$filtered."<br>";
	return $filtered;
}
/**
 * pad_len variant which cuts string if excedess length, also filters the input text
 * @param string 	$input		Input string
 * @param int	 	$len		The length
 * @param string 	$fill		The fill if input is smaller than length
 */
function filtered_pad_len($input, $len, $pad_string = " ", $pad_type = STR_PAD_RIGHT)
{
	$input = filtered_str($input);
	$input = pad_len($input, $len, $pad_string, $pad_type);
	return $input;
}

/**
 * Converts number to SEPA price format, return null if number is excesive
 * @param 	float	$input		The price number
 * @return 	string				The formated string
 */

function convert_float_price($input, $numbers, $decimals)
{
	
	$point = "."; //What is the point of this?
	$max_input = str_repeat("9", $numbers).$point.str_repeat("9", $decimals); //The maximum permited number
	if ($input > $max_input) return null;
	$input = number_format($input, $decimals, $point, "");
	list($number, $decimal) = explode($point, $input);
	$number = filtered_pad_len($number, $numbers, "0", STR_PAD_LEFT);
	$decimal = filtered_pad_len($decimal, $decimals, "0", STR_PAD_RIGHT);
	return $number.$decimal;
}
	
/**
 * Filters the siren
 * @param	str		$input						The input
 * @return	string								The filtered siren
 */

function filtered_siren($input)
{
	//Remove the ES- starting that some spanish siren have
	if (starts_with($input, "ES-", true))
	{
		$input = str_replace_first("ES-", "", $input);
	}
	return $input;
}

/**
 * This function tests and prints the result of testings
 * the result sould match the harbour version...
 */
function TestAll() {
	
	/* Original Harbour version:
	? "cTime"
	? cTime()
	
	? "fDate"
	? fDate()
	
	? "sDate"
	? sDate()
	
	? "IsoDateTime"
	? IsoDateTime()
	 */
	
	/* Result of executing above code:
	cTime                                                                                                                                                       
	224724
	
	fDate 
	20140308
	
	sDate   
	2014-03-08
	
	IsoDateTime
	2014-03-08T22:47:24
	*/
	print "<head></head><html>";
	print "cTime<br>";
	print cTime();
	
	print "<br>fDate<br>";
	print fDate();
	
	print "<br>sDate<br>";
	print sDate();
	
	print "<br>IsoDateTime<br>";
	print IsoDateTime();

	print "<br>calculate_creditor_identifier<br>";
	print calculate_creditor_identifier("ES", "123", "A12345678");
	print "<br>";
	print calculate_creditor_identifier("ES", "543", "A4CF05Z98");
	print "<br>";
	print calculate_creditor_identifier("NL", "ZZZ", "5FF02149533FF");
	print "</html>";
}

if (!empty($_GET["test_sepa_functions"])) TestAll(); //put ?test_sepa_functions=1 in url to test this
?>