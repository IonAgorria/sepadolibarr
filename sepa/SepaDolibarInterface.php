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
 *		\file       SepaDolibarrInterface.class.php
 *		\ingroup    sepadolibarr
 *		\brief      Containts functions for accessing dolibarr data
 */

/*
 * PHP reminder:
 * public - accesible from anywhere
 * private - accesible only from own class
 * protected - accesible only from class tree (parent, class and subclasses)
 */

require_once "common.php";
require_once DOL_DOCUMENT_ROOT.'/societe/class/companybankaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

$langs->load("bills");
//Global interface
class SepaDolibarInterface
{
	protected $db;
	public $company_interface;
	public $facture_interface;
	public $society_interface;

	/**
	 * Constructor, creates the subclass interfaces
	 * @param	dol_DB	$db				Database handler
	 */
	public function SepaDolibarInterface($db)
	{
		$this->db = $db;
		$this->company_interface = new CompanyInterface($db);
		$this->society_interface = new SocietyInterface($db);
		$this->facture_interface = new FactureInterface($db);
		return 1;
	}
}

//Bank accounts abstract interface, subclasses must provide fetch() implementation
abstract class BankAbstractInterface
{
	protected $db;
	protected $account;

	/**
	 * Constructor
	 * @param	dol_DB	$db				Database handler
	 */
	public function BankAbstractInterface($db)
	{
		$this->db = $db;
		$this->account = null;
		return 1;
	}

	/**
	 * (re)fetch the bank data, returns the result of fetch
	 * @param	int						$bankid	Bank ID
	 * @return	int						<0 if KO, >0 if OK
	 */
	abstract function fetch($id);
	
	/**
	 * returns the IBAN
	 * @return     string				IBAN
	 */
	public function get_IBAN()
	{
		return $this->account->iban_prefix;
	}

	/** 
	 * returns the BIC
	 * @return     string				BIC
	 */
	public function get_BIC()
	{
		return $this->account->bic;
	}

	/**
	 * returns the Domiciliation
	 * @return     string				Domiciliation
	 */
	public function get_Domiciliation()
	{
		return $this->account->domiciliation;
	}

	/**
	 * returns the Owner
	 * @return     string				Owner
	 */
	public function get_Owner()
	{
		return $this->account->proprio;
	}

	/**
	 * returns the OwnerAddress
	 * @return     string				Owner Address
	 */
	public function get_OwnerAddress()
	{
		return $this->account->owner_address;
	}

	/**
	 * Returns null if all data is present, or missing data names if something is missing
	 * @param      bool					$ignore_domiliciation  If true, the check ignores the domiliciation, false by default
	 * @return     multi				null or array containing strings of missing data
	 */
	public function check_EmptyBankData($ignore_domiciliation = false)
	{
		//Get the values
		$iban = $this->get_IBAN();
		$bic = $this->get_BIC();
		$domiciliation = $this->get_Domiciliation();
		$owner = $this->get_Owner();
		$owner_address = $this->get_OwnerAddress();
		//Check if they are empty
		$iban = empty($iban);
		$bic = empty($bic);
		$domiciliation = empty($domiciliation);
		$owner = empty($owner);
		$owner_address = empty($owner_address);
		if ($ignore_domiciliation) $domiciliation = false; //Set the domiciliation to false even if its empty or not
		$some_empty = $iban || $bic || $domiciliation || $owner || $owner_address;
		if (!$some_empty) { //Nothing missing
			return null;
		} else  { //There is something missing, create a array with missing data
			//We use translation strings, so its not necesary to map in case of translation need
			$missing = array();
			if ($iban) 			array_push($missing, "IBAN");
			if ($bic) 			array_push($missing, "BIC");
			if ($domiciliation) array_push($missing, "BankAccountDomiciliation");
			if ($owner) 		array_push($missing, "BankAccountOwner");
			if ($owner_address) array_push($missing, "BankAccountOwnerAddress");
			return $missing;
		}
	}


	/**
	 * Returns a formatted string from missing data list, useful for human reading
	 * @param		array	$missing	Missing data list
	 * @param		string	$sep		Separator in case of formated string (", " by default)
	 * @param		bool	$translate	Translate each missing data name (true by default)
	 * @return     string	string 		list for human readable
	 */
	public function format_EmptyBankData($missing, $sep = ", ", $translate = true)
	{
        global $langs;
		//Iterate each element and build a string
		$missing_string = "";
		foreach ($missing as $data) {
			if ($missing_string != "") $missing_string .= $sep; //Add separator if its not the first element
			if ($translate) $data = $langs->trans($data);
			$missing_string .= $data;
		}
		return $missing_string;
	}
}

//Our company interface, uses account accesses with bank interface
class CompanyInterface extends BankAbstractInterface
{
	/**
	 * Constructor
	 * @param	dol_DB	$db				Database handler
	 */
	public function CompanyInterface($db)
	{
		parent::BankAbstractInterface($db);
		global $conf;
		$this->conf = $conf;
		return 1;
	}

	/**
	 * (re)fetch the default data from company, returns the result of fetch
	 * @param	int						$bankid	Bank ID
	 * @return	int						<0 if KO, >0 if OK
	 */
	public function fetch($bankid)
	{
		$this->account = new Account($this->db);
		$result = $this->account->fetch($bankid);
		return $result;
	}
	
	/**
	 * returns the Name
	 * @return     string				Name
	 */
	public function get_Name()
	{
		return $this->conf->global->MAIN_INFO_SOCIETE_NOM;
	}

	/**
	 * returns the Address
	 * @return     string				Address
	 */
	public function get_Address()
	{
		return $this->conf->global->MAIN_INFO_SOCIETE_ADDRESS;
	}

	/**
	 * returns the ZIP
	 * @return     string				ZIP
	 */
	public function get_ZIP()
	{
		return $this->conf->global->MAIN_INFO_SOCIETE_ZIP;
	}

	/**
	 * returns the Town
	 * @return     string				Town
	 */
	public function get_Town()
	{
		return $this->conf->global->MAIN_INFO_SOCIETE_TOWN;
	}

	/**
	 * returns the company resident country code
	 * @return     string				siren
	 */
	public function get_CountryCode()
	{
		global $mysoc;
		return $mysoc->country_code;
	}

	/**
	 * returns the State
	 * @return     string				State
	 */
	public function get_State()
	{
		return getState($this->conf->global->MAIN_INFO_SOCIETE_STATE);
	}

	/**
	 * returns "siren", in Spain is called CIF/NIF
	 * @return     string				siren
	 */
	public function get_Siren()
	{
		return $this->conf->global->MAIN_INFO_SIREN;
	}
}

//Third party society interface, uses account accesses with bank interface
class SocietyInterface extends BankAbstractInterface
{
	protected $society;
	
	/**
	 * (re)fetch the data and default account data from society, returns the result of fetch
	 * @param	int						$socid	Society ID
	 * @return	array					2 fetchs results (society, account)	<0 if KO, >0 if OK
	 */
	public function fetch($socid)
	{
        $this->society = new Societe($this->db);
		$this->account = new CompanyBankAccount($this->db);
    	$result_s = $this->society->fetch($socid); //fetch society data
    	$result_a = $this->account->fetch(0, $socid); //if id is 0 and socid is provided, will fetch the default account
		return array($result_s, $result_a);
	}
	
	/**
	 * returns the Name
	 * @return     string				Name
	 */
	public function get_Name()
	{
		return $this->society->name;
	}

	/**
	 * returns the Address
	 * @return     string				Address
	 */
	public function get_Address()
	{
		return $this->society->address;
	}

	/**
	 * returns the ZIP
	 * @return     string				ZIP
	 */
	public function get_ZIP()
	{
		return $this->society->zip;
	}

	/**
	 * returns the Town
	 * @return     string				Town
	 */
	public function get_Town()
	{
		return $this->society->town;
	}

	/**
	 * returns the company resident country code
	 * @return     string				siren
	 */
	public function get_CountryCode()
	{
		global $mysoc;
		return $this->society->country_code;
	}

	/**
	 * returns the State
	 * @return     string				State
	 */
	public function get_State()
	{
		return $this->society->state;
	}

	/**
	 * returns "siren", in Spain is called CIF/NIF
	 * @return     string				siren
	 */
	public function get_Siren()
	{
		return $this->society->idprof1;
	}
}

//Facture interface
class FactureInterface
{
	protected $db;
	private $facture;

	/**
	 * Constructor
	 * @param	dol_DB	$db				Database handler
	 */
	public function FactureInterface($db)
	{
		$this->db = $db;
		return 1;
	}

	/**
	 * (re)fetch the data from society, returns the result of fetch
	 * @param	int						$socid	Society ID
	 * @return	int						<0 if KO, >0 if OK
	 */
	public function fetch($facid)
	{
		$this->facture = new FactureFournisseur($this->db);
		$result = $this->facture->fetch($facid); //if id is 0 and socid is provided, will fetch the  account
		return $result;
	}
	
	/**
	 * returns the asociated society
	 * @return     string				Society ID
	 */
	public function get_Society()
	{
		return $this->facture->socid;
	}
	
	/**
	 * returns the ref
	 * @return     string				ref ID
	 */
	public function get_Ref()
	{
		return $this->facture->ref;
	}
	
	/**
	 * returns the ref_supplier
	 * @return     string				ref_supplier
	 */
	public function get_RefSupplier()
	{
		return $this->facture->ref_supplier;
	}
	
	/**
	 * returns the total_ttc
	 * @return     string				total_ttc
	 */
	public function get_TotalTTC()
	{
		return $this->facture->total_ttc;
	}
}
?>