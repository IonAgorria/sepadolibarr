<?php
/* fourn/facture/impayees.php Copyright:
 * Copyright (C) 2002-2005	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004		Eric Seigne				<eric.seigne@ryxeo.com>
 * Copyright (C) 2004-2012	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012	Regis Houssin			<regis.houssin@capnetworks.com>
 * Copyright (C) 2012		Vinicius Nogueira       <viniciusvgn@gmail.com>
 * Copyright (C) 2012		Juanjo Menent			<jmenent@2byte.es>
 * 
 * compta/bank/index.php Copyright:
 * Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copytight (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 * 
 * credit_transfer.php Copyright:
 * Copyright (C) 2014		Ion Agorria				<ion@agorria.com>
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
 *		\file       credit_transfer.php
 *		\ingroup    sepadolibarr
 *		\brief      Containts specific code for credit transfers
 */

//Include dolibarr main.inc.php
require_once "common.php";
require get_dol_root("main.inc.php");

//Required includes
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once "config.php";
require_once 'sepa/SepaDolibarInterface.php';
require_once 'sepa/SepaFunctions.php';

if (! $user->rights->facture->lire) accessforbidden();

$langs->load("companies");
$langs->load("bills");

// GET/POST data
$stage = GETPOST('stage','alpha');
if (empty($stage)) $stage = "bank";
$disable_menu = GETPOST('disable_menu', 'int');

$bank_selected = GETPOST('bank_selected', 'int');
$facture_list_count = GETPOST('facture_list_count', 'int');
$facture_list_selected = GETPOST('facture_list_selected', '');
$template_selected = GETPOST('template_selected', '');

// Used for getting data 
$facturestatic=new FactureFournisseur($db);
$companystatic=new Societe($db);
$sepadolibarr_interface=new SepaDolibarInterface($db);

//Stage chain
$stage_chain = array(
	"bank" => "list",
	"list" => "template",
	"template" => "generate",
);

//Any ocurred generation error
$gen_error = "";

/***************************************************************************
*                                                                          *
*                         Generate page                                    *
*                                                                          *
***************************************************************************/

if ($stage == "generate") {
	//Check if all required parameters are ok
	if (empty($bank_selected) || empty($facture_list_count) || empty($facture_list_selected) || empty($template_selected))
	{
		$stage = previus_stage($stage_chain, $stage);
		setEventMessage($langs->trans("MissingTemplate"), "errors");
		$disable_menu = 0;
	} 
	else 
	{
		include SEPADOLIBARR_ABSOLUTE_URL . SEPADOLIBARR_TEMPLATE_CT_DIR . '/' . $template_selected; //Try to import the file

		$class = null;
		$class_clean = 	replace_nonalnum(get_clean_template_name($template_selected, SEPADOLIBARR_TEMPLATE_PREFIX)); 	//"clean" name (without the prefix and .php)
		$class_raw = 	replace_nonalnum(str_replace(".php", "", $template_selected));									//original name (without .php)
		if (class_exists($class_clean)) 	$class = $class_clean;
		elseif (class_exists($class_raw)) 	$class = $class_raw;
		else { //No any suitable class, go back to selector
			$stage = previus_stage($stage_chain, $stage);
			setEventMessage($langs->trans("ClassNotFoundTemplate"), "errors");
			$disable_menu = 0;
		}
		if ($class != null) {
			$template = new $class($sepadolibarr_interface);
			$a = array(
				//File parameters
				$class_clean, 	//Name
				//Bank parameters
				$bank_selected,
				//Facture parameters
				$facture_list_count,
				$facture_list_selected,
				//Template parameters
				$template_selected,
			);
			$template->LoadParameters($a);
			$gen_error = $template->GenerateAndDownload();
			if (!empty($gen_error)) {
				$stage = previus_stage($stage_chain, $stage);
				setEventMessage($gen_error, "errors");
				$disable_menu = 0;
			}
		}
	}
}

/***************************************************************************
*                                                                          *
*                         Dolibarr header page                             *
*                                                                          *
***************************************************************************/

// View
if (empty($disable_menu))
{
	$title=$langs->trans('MenuCreditTransfer');
	llxHeader('',$title);
	print_fiche_titre($title);
}

/***************************************************************************
*                                                                          *
*                         Template page                                    *
*                                                                          *
***************************************************************************/

if ($stage == "template") {
	//Check if all required parameters are ok
	if (empty($bank_selected) || empty($facture_list_count) || empty($facture_list_selected)) 
	{
		$stage = previus_stage($stage_chain, $stage);
		setEventMessage($langs->trans("MissingFacture"), "errors");
	} else {
		//Sub title
		print $langs->trans("SelectTemplate");
		print '<br><br>';
		
		//Create a selector
		$templates = list_templates(SEPADOLIBARR_ABSOLUTE_URL . SEPADOLIBARR_TEMPLATE_CT_DIR, SEPADOLIBARR_TEMPLATE_PREFIX);
		print '<form id="template_selection_form" method="post" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="disable_menu" value="1">';
		print '<input type="hidden" name="stage" value="'.$stage_chain[$stage].'">';
		print '<input type="hidden" id="bank_selected" name="bank_selected" value="'.$bank_selected.'">';
		print '<input type="hidden" id="facture_list_count" name="facture_list_count" value="'.$facture_list_count.'">';
		print '<input type="hidden" id="facture_list_selected" name="facture_list_selected" value="'.$facture_list_selected.'">';
		print '<select name="template_selected">';
		foreach ($templates as $name) {
			$clean_name = get_clean_template_name($name, SEPADOLIBARR_TEMPLATE_PREFIX);
			$clean_name = str_replace("_", " ", $clean_name);
			print '<option value="'.$name.'">'.$clean_name.'</option>';
		}
		print '</select>';
		print '</form>';
	
		//Buttons
		print '<div class="tabsAction">'."\n";
		print '<a id="next_button" class="butAction" href="">'.$langs->trans("ButtonGenerateSepa").'</a>';
		echo '
				<script type="text/javascript">
				jQuery(document).ready(run);
	
				function run() {
					jQuery("#next_button").click(on_click_next_button);
				}
	
				function on_click_next_button() {
					document.forms["template_selection_form"].submit();
					return false;
				}
				</script>
				';
		print '</div>';
	}
}

/***************************************************************************
*                                                                          *
*                         Facture list                                     *
*                                                                          *
***************************************************************************/

if ($stage == "list" && $user->rights->fournisseur->facture->lire)
{
	//Check if all required parameters are ok
	if (empty($bank_selected)) 
	{
		$stage = previus_stage($stage_chain, $stage);
		setEventMessage($langs->trans("MissingBank"), "errors");
	} else {
		$sortfield="f.date_lim_reglement"; //Pending
		$sortorder="ASC";				   //Ascendent (closest ones top)
		
		$sql = "SELECT s.rowid as socid, s.nom,";
		$sql.= " f.rowid, f.ref, f.ref_supplier, f.total_ht, f.total_ttc,";
		$sql.= " f.datef as df, f.date_lim_reglement as datelimite, ";
		$sql.= " f.paye as paye, f.rowid as facid, f.fk_statut";
		$sql.= " ,sum(pf.amount) as am";
		if (! $user->rights->societe->client->voir) $sql .= ", sc.fk_soc, sc.fk_user ";
		$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
		if (! $user->rights->societe->client->voir) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
		$sql.= ",".MAIN_DB_PREFIX."facture_fourn as f";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."paiementfourn_facturefourn as pf ON f.rowid=pf.fk_facturefourn ";
		$sql.= " WHERE f.entity = ".$conf->entity;
		$sql.= " AND f.fk_soc = s.rowid";
		$sql.= " AND f.paye = 0 AND f.fk_statut = 1";
        $sql.= " AND f.fk_mode_reglement = 2";
        $sql.= " AND (f.fk_account = ".$bank_selected." OR f.fk_account IS NULL)";
		if (! $user->rights->societe->client->voir) $sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
		
		$sql.= " GROUP BY s.rowid, s.nom, f.rowid, f.ref, f.ref_supplier, f.total_ht, f.total_ttc, f.datef, f.date_lim_reglement, f.paye, f.fk_statut, s.rowid, s.nom";
		if (! $user->rights->societe->client->voir) $sql .= ", sc.fk_soc, sc.fk_user ";
		$sql.= " ORDER BY ";
		$listfield=explode(',',$sortfield);
		foreach ($listfield as $key => $value) $sql.=$listfield[$key]." ".$sortorder.",";
		$sql.= " f.ref_supplier DESC";

		$found = 0;
		$resql = $db->query($sql);
		if ($resql)
		{
			$num = $db->num_rows($resql);
			
			//Sub title
			print $langs->trans("BillsSuppliersUnpaid");
			print '<br><br>';
			
			//List column names
			print '<table class="liste" width="100%">';
			print '<tr class="liste_titre" style="display: table-row">';
			print '<td style="width: 20px; max-width: 20px">'.'&nbsp&nbspX&nbsp&nbsp'.'</td>';
			print_liste_field_titre($langs->trans("Company"),		'',	'',	'',	'',	'align="left"',		$sortfield,	$sortorder);
			print_liste_field_titre($langs->trans("Ref"),			'',	'',	'',	'',	'align="left"',		$sortfield,	$sortorder);
			print_liste_field_titre($langs->trans("RefSupplier"),	'',	'',	'',	'',	'align="left"',		$sortfield,	$sortorder);
			print_liste_field_titre($langs->trans("IBAN"),			'',	'',	'',	'',	'align="center"',	$sortfield,	$sortorder);
			print_liste_field_titre($langs->trans("BIC"),			'',	'',	'',	'',	'align="center"',	$sortfield,	$sortorder);
			print_liste_field_titre($langs->trans("DateDue"),		'',	'',	'',	'',	'align="center"',	$sortfield,	$sortorder);
			print_liste_field_titre($langs->trans("Price"),			'',	'',	'',	'', 'align="right"',	$sortfield,	$sortorder);
			print_liste_field_titre($langs->trans("AlreadyPaid"),	'',	'',	'',	'', 'align="right"',	$sortfield,	$sortorder);
			print_liste_field_titre($langs->trans("PendingAmount"),	'',	'',	'',	'', 'align="right"',	$sortfield,	$sortorder);
            print_liste_field_titre($langs->trans("SelectAmount"),	'',	'',	'',	'', 'align="right"',	$sortfield,	$sortorder);
			print "</tr>\n";
	
			//Iterate each SQL line
			$i = 0;
			$society_i = $sepadolibarr_interface->society_interface;
			if ($num > 0)
			{
				$found++;
				$var=True;
				$total_pending=0;
				$total_paid=0;
	
				while ($i < $num)
				{
					$i++;
					$var=!$var;
					
					$objp = $db->fetch_object($resql);
					$pending = $objp->total_ttc - $objp->am;
					
					//Fetch data
					$result = $society_i->fetch($objp->socid);
					if ($result < 1) {
						print "ERROR in society interface fetching: ".$result." socid: ".$objp->socid;
					}
					$missing = $society_i->check_EmptyBankData();
					
					//Disabled state
					$disabled_line = false;
					if ($missing) $disabled_line = true;
					if ($excesive_price) $disabled_line = true;
	
					//background color changing
					print "<tr ".$bc[$var].">";
					
					//Checkbox slot
					$extra = '';
					if ($disabled_line) $extra.= 'disabled'; 
					$checkbox = '<input type="checkbox" id="facture_list_box_'.$i.'" value="'.$objp->facid.'" '.$extra.'/><br />';
					print '<td class="nowrap" align="center">'.$checkbox."</td>\n";
					
					//Company name
					print '<td>';
					$companystatic->id=$objp->socid;
					$companystatic->nom=$objp->nom;
					print $companystatic->getNomUrl(1,'supplier',32);
					print '</td>';
					
					//Facture Ref
					print '<td class="nowrap">';
					$facturestatic->id=$objp->facid;
					$facturestatic->ref=$objp->ref;
					print $facturestatic->getNomUrl(1);
					print "</td>\n";
	
					//Supplier Ref
					print '<td class="nowrap">'.dol_trunc($objp->ref_supplier,12)."</td>\n";
					
					//IBAN and BIC
					if ($missing == null) { //Nothing missing
						print '<td class="nowrap" align="center">'.$society_i->get_IBAN()."</td>\n"; //IBAN
						print '<td class="nowrap" align="center">'.$society_i->get_BIC()."</td>\n"; //BIC
					} else { //Print the missing data in IBAN/BIC cols
						print '<td align="center" colspan="2" style="color:red">';
						print $langs->trans("Missing").": ".$society_i->format_EmptyBankData($missing)."</td>\n";
					}
	
					//Date limit
					print '<td class="nowrap" align="center">'.dol_print_date($db->jdate($objp->datelimite),'day');
					if ($objp->datelimite && $db->jdate($objp->datelimite) < (dol_now() - $conf->facture->fournisseur->warning_delay) && ! $objp->paye && $objp->fk_statut == 1) print img_warning($langs->trans("Late"));
					print "</td>\n";
					
					;
					//Total price
					print '<td align="right">'.price($objp->total_ttc)."</td>\n";

                    //Paid amount
                    print '<td align="right">'.price($objp->am)."</td>\n";

					//Pending
					print '<td align="right">'.price($pending)."</td>\n";

                    //Selected amount slot
                    $extra = '';
                    if ($disabled_line) $extra.= 'disabled';
                    $selectedAmount = '<input type="number" name="" min="0" max="'.$pending.'" step=".01" 
                    id="facture_list_textbox_'.$i.'" value="'.($disabled_line ? 0 : $pending).'" '.$extra.'/><br />';
                    print '<td class="nowrap" align="center">'.$selectedAmount."</td>\n";
	
					//Finish table line
					print "</tr>\n";
					
					//Accumulating total
					$total_pending+=$pending;
					$total_paid+=$objp->am;
				}
	
				//Total
				print '<tr class="liste_total">';
				print "<td colspan=\"7\" align=\"left\">".$langs->trans("Total").": </td>\n";
				print "<td align=\"right\"><b>".price($total_pending)."</b></td>\n";
				print "<td align=\"right\"><b>".price($total_paid)."</b></td>\n";
				print "</tr>\n";
			}
			print "</table>";
			
			$db->free($resql);
	
			//The hidden form storing the important data to be send by POST
			$form_name = "facture_list_form";
			print '<form id="'.$form_name.'" method="post" action="'.$_SERVER['PHP_SELF'].'">';
			print '<input type="hidden" name="stage" value="'.$stage_chain[$stage].'">';
			print '<input type="hidden" id="bank_selected" name="bank_selected" value="'.$bank_selected.'">';
			print '<input type="hidden" id="facture_list_count" name="facture_list_count" value="'.$facture_list_count.'">';
			print '<input type="hidden" id="facture_list_selected" name="facture_list_selected" value="'.$facture_list_selected.'">';
			print '</form>';
			
			//Buttons
			print '<div class="tabsAction">'."\n";
			print '<a id="next_button" class="butAction" href="">'.$langs->trans("ButtonSelectTemplate").'</a>';
			echo '
				<script type="text/javascript">
				jQuery(document).ready(run);
						
				function run() {
					jQuery("#next_button").click(on_click_next_button);
				}
						
				function on_click_next_button() {
					var count = '.$i.';
					var checked_count = 0;
					var selected = "";
					if (count > 0) {
						for (var i = 1; i <= count; i++) {
							var box_name = "#facture_list_box_" + i;
							var amt_name = "#facture_list_textbox_" + i;
							var box = jQuery(box_name);
							var amt = jQuery(amt_name);
							if (box.prop("checked") == true) {
								if (selected != "") {
									selected = selected + ",";
								}
								selected = selected + box.val() + ":" + amt.val();
								checked_count = checked_count + 1;
							}
						}
					}
					
					jQuery("#facture_list_count").val(checked_count);
					jQuery("#facture_list_selected").val(selected);
					document.forms["'.$form_name.'"].submit();
					return false;
				}
				</script>
				';
			print '</div>';
		}
		else
		{
			dol_print_error($db);
		}
		if (! $found) print '<tr '.$bc[$var].'><td colspan="6">'.$langs->trans("None").'</td></tr>';
	}
}

/***************************************************************************
 *                                                                          *
*                         Bank    list                                     *
*                                                                          *
***************************************************************************/

if ($stage == "bank") {
	//Load the data from SQL
	$accounts = array();

	$sql  = "SELECT rowid, courant, rappro";
	$sql.= " FROM ".MAIN_DB_PREFIX."bank_account";
	$sql.= " WHERE entity IN (".getEntity('bank_account', 1).")";
	if ($statut != 'all') $sql.= " AND clos = 0";
	$sql.= $db->order('label', 'ASC');

	$resql = $db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i = 0;
		while ($i < $num)
		{
			$objp = $db->fetch_object($resql);
			$accounts[$objp->rowid] = $objp->courant;
			$i++;
		}
		$db->free($resql);
	}


	//Sub title
	print $langs->trans("SelectBankAccount");
	print '<br><br>';

	//Print page
	print '<table class="liste" width="100%">';
	print '<tr class="liste_titre" style="display: table-row">';
	print '<td align="center" style="width: 20px; max-width: 20px">'.'&nbsp&nbspX&nbsp&nbsp'.'</td>';
	print '<td align="left">'.$langs->trans("CurrentAccounts").'</td>';
	print '<td align="left">'.$langs->trans("Bank").'</td>';
	print '<td align="left">'.$langs->trans("Numero").'</td>';
	print '<td align="center">'.$langs->trans("IBAN").'</td>';
	print '<td align="center">'.$langs->trans("BIC").'</td>';
	print '<td align="center" width="70">'.$langs->trans("Status").'</td>';
	print '<td align="right" width="100">'.$langs->trans("BankBalance").'</td>';
	print "</tr>\n";

	$found = 0;
	$var=true;
	$company_i = $sepadolibarr_interface->company_interface;
	$form_name = "bank_selection_form";
	print '<form id="'.$form_name.'" method="post" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="stage" value="'.$stage_chain[$stage].'">';
	foreach ($accounts as $key=>$type)
	{
		if ($type == 1)
		{
			$found++;

			$acc = new Account($db);
			$acc->fetch($key);

			$var = !$var;
			$solde = $acc->solde(1);

			//Fetch data
			$result = $company_i->fetch($acc->id);
			if ($result < 1) {
				print "ERROR in bank interface fetching: ".$result." accid: ".$acc->id;
			}
			$ignore_domiciliation = true;
			$missing = $company_i->check_EmptyBankData($ignore_domiciliation);
				
			//Disabled state
			$disabled_line = false;
			if ($missing) $disabled_line = true;
			
			//background color changing
			print "<tr ".$bc[$var].">";
				
			//Radio slot
			$extra = '';
			if ($disabled_line) $extra.= 'disabled';
			
			$radio = '<input type="radio" name="bank_selected" value="'.$acc->id.'" '.$extra.'/><br />';
			print '<td class="nowrap" align="center">'.$radio."</td>\n";
			
			//Account url
			print '<td>'.$acc->getNomUrl(1).'</td>';
				
			//Bank name
			print '<td>'.$acc->bank.'</td>';
				
			//Bank number
			print '<td>'.$acc->number.'</td>';

			//IBAN and BIC
			if ($missing == null) { //Nothing missing
				print '<td class="nowrap" align="center">'.$company_i->get_IBAN()."</td>\n"; //IBAN
				print '<td class="nowrap" align="center">'.$company_i->get_BIC()."</td>\n"; //BIC
			} else { //Print the missing data in IBAN/BIC cols
				print '<td align="center" colspan="2" style="color:red">';
				print $langs->trans("Missing").": ".$company_i->format_EmptyBankData($missing)."</td>\n";
			}
				
			//Status
			print '<td align="center">'.$acc->getLibStatut(2).'</td>';
				
			//Balance
			print '<td align="right">';
			print price($solde);
			print '</td>';
			print '</tr>';

			$total += $solde;
		}
	}
	if (! $found) print '<tr '.$bc[$var].'><td colspan="6">'.$langs->trans("None").'</td></tr>';

	// Total
	print '<tr class="liste_total"><td colspan="5" class="liste_total">'.$langs->trans("Total").'</td><td align="right" class="liste_total">'.price($total).'</td></tr>';
	print "</table>";

	print '</form>';
	
	//Buttons
	print '<div class="tabsAction">'."\n";
	print '<a id="next_button" class="butAction" href="">'.$langs->trans("ButtonSelectFacture").'</a>';
	echo '
			<script type="text/javascript">
			jQuery(document).ready(run);
		
			function run() {
				jQuery("#next_button").click(on_click_next_button);
			}
		
			function on_click_next_button() {
				document.forms["'.$form_name.'"].submit();
				return false;
			}
			</script>
			';
	print '</div>';

}

// End of page
if (empty($disable_menu))
{
	llxFooter();
}
$db->close();
?>