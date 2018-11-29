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
 *		\file       Template_aeb3414.php
 *		\ingroup    sepadolibarr
 *		\brief      Template for AEB3414, based on https://github.com/QuimFerrer/sepa/blob/master/aeb3414/SEPA3414.prg
 */

require_once 'common.php';
require_once 'sepa/SepaFunctions.php';
require_once 'sepa/SepaMasterTemplate.php';
require_once 'sepa_xml_for_php/SepaXmlFile.php';

class Template_pain_001_001_03 extends SepaMasterTemplate
{	
	protected function Generate()
	{
		$this->file_content = array();
		$this->file_name_ext = ".xml";
		
		/**
		//Remove these in production
		$this->header_preparation = false;
		**/
		
		//Interfaces
		$company_i = $this->dol_interface->company_interface;
		$society_i = $this->dol_interface->society_interface;
		$facture_i = $this->dol_interface->facture_interface;
		
		$company_i->fetch($this->bank_selected); //Load the selected bank data
		
		//Type (2) +  Prefix (10) + Unique id based on time (23) = 35
		$prefix = filtered_cut_len(filtered_siren($company_i->get_Siren()), 10);
		$uid = filtered_cut_len(md5(uniqid("", true)), 23); 

		// generate a SepaCreditTranfer object
		$creditTransferFile = new SepaXmlFile(
				filtered_cut_len($company_i->get_Name(), 70), //Initiator name
				filtered_cut_len("MI" . $prefix . $uid, 35), //Message ID
				'CT'
		);
		
		// at least one in every SepaXmlFile (of type CT). No limit.
		$creditTransferCollection = $creditTransferFile->addCreditTransferCollection(array(
				// needed information about the payer
				'pmtInfId' => filtered_cut_len("PI" . $prefix . $uid, 35), 		// ID of the paymentcollection
				'dbtr' => filtered_cut_len($company_i->get_Owner(), 70),		// Debtor (max 70 characters)
				'iban' => $company_i->get_IBAN(), 								// IBAN of the Debtor
				'bic' => $company_i->get_BIC(),  								// BIC of the Debtor
				// optional
				'ccy' => 'EUR',													// Currency. Default is 'EUR'
				'btchBookg' => 'true', 											// BatchBooking, only 'true' or 'false'
				//'ctgyPurp' => , 												// Do not use this if you not know how. For further information read the SEPA documentation
				'reqdExctnDt' => sDate(date_sum_days(time(), SEPADOLIBARR_DELAY_DAYS)), 										// When to execute the pay, Date: YYYY-MM-DD
				'ultmtDebtr' => filtered_cut_len($company_i->get_Name(), 70)	// Ultimate Debtor Name just an information, this do not affect the payment (max 70 characters)
		));
		
		//Iterate over each facture in the list
		$facture_list = explode(",", $this->facture_list_selected);
		
		//Check the list consistency
		if (count($facture_list) != $this->facture_list_count)
		{
			throw new Exception("Facture list count ".count($facture_list)." doesn't match previously count ".$this->facture_list_count);
		}
		
		foreach ($facture_list as $fac_item)
		{	
            //Extract data
            $item_data = explode(":", $fac_item);
            $fac_id = $item_data[0];
            $fac_amt = $item_data[1];
			//Fetch the facture data and asociated society data
			$facture_i->fetch($fac_id);
			$society_i->fetch($facture_i->get_Society());
			
			// at least one in every CreditTransferCollection. No limit.
			$creditTransferCollection->addPayment(array(
					// needed information about the one who gets payed
					'pmtId' => filtered_str($facture_i->get_RefSupplier()), 	// ID of the payment (EndToEndId)
					'instdAmt' => $fac_amt, 					// amount to pay,
					'iban' => $society_i->get_IBAN(),							// IBAN of the Creditor
					'bic' => $society_i->get_BIC(),								// BIC of the Creditor
					'cdtr' => filtered_cut_len($society_i->get_Owner(), 70),	// name (max 70 characters)
					// optional
					'ultmtCdrt' => filtered_cut_len($society_i->get_Name(), 70),// Ultimate Creditor Name, just an information, this do not affect the payment (max 70 characters)
					//'purp' => , 												// Do not use this if you not know how. For further information read the SEPA documentation
					'rmtInf' => filtered_str($facture_i->get_RefSupplier()), 	// Remittance Information, unstructured information about the remittance (max 140 characters)
			));
		}
		
		$xml = $creditTransferFile->generateXml();
		$xml = correct_xml($xml);
		$this->file_content[0] = $xml;
		return null; //Finished without error
	}
}
?>