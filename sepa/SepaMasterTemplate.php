<?php
/* Copyright (C) 2014		Ion Agorria				<ion@agorria.com>
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
 *		\file       SepaMasterTemplate.php
 *		\ingroup    sepadolibarr
 *		\brief      The master template which all templates subclasses
 */

require_once 'common.php';

abstract class SepaMasterTemplate
{
	//Passed dolibarr interface
	protected $dol_interface;
	
	//User selected parameters
	protected $file_name;
	protected $file_name_ext;
	protected $file_content;
	protected $file_line_end;
	protected $bank_selected;
	protected $facture_list_count;
	protected $facture_list_selected;
	protected $template_selected;
	
	//Template specific parameters
	protected $ignore_header_preparation;
	
	/**
	 * Constructor
	 * @param	SepaDolibarInterface	$dol_interface	SepaDolibarInterface
	 */
	public function SepaMasterTemplate($dol_interface)
	{
		$this->dol_interface = $dol_interface;
		return 1;
	}
	
	/**
	 * 	Loads data to variables from a provided array
	 * 	@param	array					$a				Array which contains data
	 */
	public function LoadParameters($a)
	{
		//File parameters
		$this->file_name				= $a[0];
		//Bank parameters
		$this->bank_selected 			= $a[1];
		//Facture parameters
		$this->facture_list_count 		= $a[2];
		$this->facture_list_selected 	= $a[3];
		//Template parameters
		$this->template_selected 		= $a[4];
		return 1;
	}

	/**
	 * This function will be called when user presses "Generate"
	 * this will call the "Generate" function which sould provide a array with a string per each ine
	 * the template implementation sould override the "Generate" function
	 * @param	string					$file_name		The name of the generated file for download
	 */
	public function GenerateAndDownload()
	{
		//Default values, template implementation can change these
		$this->header_preparation = true;
		$this->file_content = "";
		$this->file_name_ext = ".txt";
		$this->file_line_end = "\n";
		$gen_error = $this->Generate();
		if (empty($gen_error)) 
		{
			if ($this->header_preparation) prepare_download_header($this->file_name . $this->file_name_ext);
			foreach ($this->file_content as $line) print $line . $this->file_line_end;
		}
		return $gen_error;
	}
	
	/**
	 * This function will be called when user presses "Generate"
	 * this will call the "Generate" function which sould provide a array with a string per each ine
	 * the template implementation sould override the "Generate" function
	 * @param	string					$file_name		The name of the generated file for download
	 */
	abstract protected function Generate();
}
?>