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
 *		\file       Template_aeb3414.php
 *		\ingroup    sepadolibarr
 *		\brief      Template for AEB3414, based on https://github.com/QuimFerrer/sepa/blob/master/aeb3414/SEPA3414.prg
 */

require_once 'common.php';
require_once 'sepa/SepaFunctions.php';
require_once 'sepa/SepaMasterTemplate.php';

class Template_aeb3414 extends SepaMasterTemplate
{
	protected function Generate()
	{
		$this->file_content = array();
		$this->file_name_ext = ".txt";
		
		/**
		//Remove these in production
		$this->header_preparation = false;
		$this->file_line_end = "_EOL<br>";
		**/
		
		//Interfaces
		$company_i = $this->dol_interface->company_interface;
		$society_i = $this->dol_interface->society_interface;
		$facture_i = $this->dol_interface->facture_interface;
		
		$company_i->fetch($this->bank_selected); //Load the selected bank data
		
		//Constants
		$norma = '34145';
		$sufijo = "000"; //TODO: This sould not be user chooseable?
		
		//Variables 
		$line_i = 0;
		$facture_list = explode(",", $this->facture_list_selected);
		$ben_importe_total = 0; //Total de todas las facturas
		$ben_type = "SUPP"; // (Para proveedores SUPP) Obligatorio para transferencias estatales : SALA=Nomina PENS=Pension
		$ben_purpose = "SUPP";
		
		/** REGISTRO DE CABECERA ORDENANTE */
		
		//Datos de ordenante
		$ord_nif = filtered_siren($company_i->get_Siren());
		$ord_dexec = date_sum_days(time(), SEPADOLIBARR_DELAY_DAYS); 	// Enviar a la entidad, 3 dias habiles antes de ejecucion
		$ord_id_cta = "A"; 						// Id. de la Cuenta del Ordenante : A=IBAN B=CCC
		$ord_cta = $company_i->get_IBAN();
		$ord_cargo = "1"; 						// 0=Cargo total operaciones 1=Un cargo por operacion
		$ord_nombre = $company_i->get_Name();
		$ord_direcc = $company_i->get_Address();
		$ord_ciudad = $company_i->get_ZIP()." ".$company_i->get_Town();
		$ord_provin = $company_i->get_State();
		$ord_pais = $company_i->get_CountryCode();
		
		//Line creation
		$line = "";										// N.Descipcion OB=Obligatorio OP=Opcional Tipo Len Posiciones
		$line.= filtered_pad_len("01", 2); 				// 1 Código de Registro OB Numérico 2 01-02
		$line.= filtered_pad_len("ORD", 3);				// 2 Código de Operación OB Alfanumérico 3 03-05
		$line.= filtered_pad_len($norma, 5);			// 3 Versión Cuaderno OB Numérico 5 06-10
		$line.= filtered_pad_len('001', 3);				// 4 Número de Dato OB Numérico 3 11-13
		$line.= filtered_pad_len($ord_nif, 9);			// 5 Identificación del Ordenante: NIF OB Alfanumérico 9 14-22
		$line.= filtered_pad_len($sufijo, 3);			// 6 Identificación del Ordenante: Sufijo OB Alfanumérico 3 23-25
		$line.= filtered_pad_len(fDate(), 8);			// 7 Fecha de Creación del Fichero OB Numérico 8 26-33
		$line.= filtered_pad_len(fDate($ord_dexec), 8);	// 8 Fecha de Ejecución Órdenes (AT-07)* OB Numérico 8 34-41
		$line.= filtered_pad_len($ord_id_cta, 1);		// 9 Id. de la Cuenta del Ordenante OB Alfanumérico 1 42-42
		$line.= filtered_pad_len($ord_cta, 34);			//10 Cuenta del Ordenante (AT-01) OB Alfanumérico 34 43-76
		$line.= filtered_pad_len($ord_cargo, 1);		//11 Detalle del Cargo OB Numérico 1 77-77
		$line.= filtered_pad_len($ord_nombre, 70);		//12 Nombre del Ordenante (AT-02) OB Alfanumérico 70 78-147
		$line.= filtered_pad_len($ord_direcc, 50);		//13 Dirección del Ordenante (AT-03) OP Alfanumérico 50 148-197
		$line.= filtered_pad_len($ord_ciudad, 50);		//14 Dirección del Ordenante (AT-03) OP Alfanumérico 50 198-247
		$line.= filtered_pad_len($ord_provin, 40);		//15 Dirección del Ordenante (AT-03) OP Alfanumérico 40 248-287
		$line.= filtered_pad_len($ord_pais, 2);			//16 País del Ordenante (AT-03) OP Alfanumérico 2 288-289
		$line.= filtered_pad_len('', 311);				//17 Libre OB Alfanumérico 311
		$this->file_content[$line_i] = $line;
		$line_i++;
		
		/** REGISTROS DE TRANSFERENCIAS SEPA - REGISTRO DE CABECERA */
		
		//Line creation
		$line = "";										// N.Descipcion OB=Obligatorio OP=Opcional Tipo Len Posiciones
		$line.= filtered_pad_len('02', 2); 				// 1 Código de Registro OB Numérico 2 01-02
		$line.= filtered_pad_len('SCT', 3); 			// 2 Código de Operación OB Alfanumérico 3 03-05
		$line.= filtered_pad_len($norma, 5); 			// 3 Versión Cuaderno OB Numérico 5 06-10
		$line.= filtered_pad_len($ord_nif, 9); 			// 4 Identificación del Ordenante: NIF OB Alfanumérico 9 11-19
		$line.= filtered_pad_len($sufijo, 3); 			// 5 Identificación del Ordenante: Sufijo OB Alfanumérico 3 20-22
		$line.= filtered_pad_len('', 578); 				// 6 Libre
		$this->file_content[$line_i] = $line;
		$line_i++;
		
		/** REGISTROS DE TRANSFERENCIAS SEPA - REGISTROS DE BENEFICIARIO */
		
		//Check the list consistency
		if (count($facture_list) != $this->facture_list_count)
		{
			throw new Exception("Facture list count ".count($facture_list)." doesn't match previously count ".$this->facture_list_count);
		}
		
		//Iterate over each facture in the list
		foreach ($facture_list as $fac_id)
		{
			/** REGISTRO DE BENEFICIARIO */
			
			//Fetch the facture data and asociated society data
			$facture_i->fetch($fac_id);
			$society_i->fetch($facture_i->get_Society());
			
			//Datos de beneficiario
			$ben_ref = $facture_i->get_Ref(); 			// Código identificativo para el ordenante de cada transferencia presentada
			$ben_id_cta = "A"; 							// Id. de la Cuenta del Beneficiario : A=IBAN B=CCC
			$ben_cta = $society_i->get_IBAN();
			$ben_importe = $facture_i->get_TotalTTC(); 	// Las 2 utimas posiciones, parte decimal
			$ben_gastos = "3";							// 3 = Gastos compartidos (SHA)
			$ben_bic = $society_i->get_BIC();
			$ben_nombre = $society_i->get_Owner();
			$ben_direcc = $society_i->get_Address(); 	//We use address from soc instead of bank address because bank one is concatenated (addr + zip + state...)
			$ben_ciudad = $society_i->get_ZIP()." ".$society_i->get_Town();
			$ben_provin = $society_i->get_State();
			$ben_pais = $society_i->get_CountryCode();
			$ben_concepto = $facture_i->get_RefSupplier();
			
			//Checks price
			$ben_importe_converted = convert_float_price($ben_importe, 9, 2);
			$excesive_price = $ben_importe_converted === null;
			if ($excesive_price)
			{
				return translate("ExcessivePrice") . " " .  $facture_i->get_Ref() . " " . $ben_importe;
			}
			
			//Line creation
			$line = "";
																// N.Descipcion OB=Obligatorio OP=Opcional Tipo Len Posiciones
			$line.= filtered_pad_len('03', 2);					// 1 Código de Registro OB Numérico 2 01-02
			$line.= filtered_pad_len('SCT', 3);					// 2 Código de Operación OB Alfanumérico 3 03-05
			$line.= filtered_pad_len($norma, 5);				// 3 Versión Cuaderno OB Numérico 5 06-10
			$line.= filtered_pad_len('002', 3);					// 4 Número de Dato OB Numérico 3 11-13
			$line.= filtered_pad_len($ben_ref, 35);				// 5 Referencia del Ordenante (AT-41) OP Alfanumérico 35 14-48
			$line.= filtered_pad_len($ben_id_cta, 1);			// 6 Id. de la Cuenta del Beneficiario OB Alfanumérico 1 49-49
			$line.= filtered_pad_len($ben_cta, 34);				// 7 Cuenta del Beneficiario (AT-20) OB Alfanumérico 34 50-83
			$line.= $ben_importe_converted;						// 8 Importe de Transferencia (AT-04) OB Numérico 11 84-94
			$line.= filtered_pad_len($ben_gastos, 1);			// 9 Clave de Gastos OB Numérico 1 95-95
			$line.= filtered_pad_len($ben_bic, 11);				//10 BIC Entidad del Beneficiario (AT-23) OB Alfanumérico 11 96-106
			$line.= filtered_pad_len($ben_nombre, 70);			//11 Nombre del Beneficiario (AT-21) OB Alfanumérico 70 107-176
			$line.= filtered_pad_len($ben_direcc, 50);			//12 Dirección del Beneficiario (AT-22) OP Alfanumérico 50 177-226
			$line.= filtered_pad_len($ben_ciudad, 50);			//13 Dirección del Beneficiario (AT-22) OP Alfanumérico 50 227-276
			$line.= filtered_pad_len($ben_provin, 40);			//14 Dirección del Beneficiario (AT-22) OP Alfanumérico 40 277-316
			$line.= filtered_pad_len($ben_pais, 2);				//15 País del Beneficiario (AT-22) OP Alfanumérico 2 317-318
			$line.= filtered_pad_len($ben_concepto, 140);		//16 Concepto del Ordenante al Beneficiario OP Alfanumérico 140 319-458
			$line.= filtered_pad_len('', 35);					//17 Referencia para el Beneficiario OP Alfanumérico 35 459-493
			$line.= filtered_pad_len($ben_type, 4);				//18 Tipo de Transferencia (AT-45) OP Alfanumérico 4 494-497
			$line.= filtered_pad_len($ben_purpose, 4);			//19 Propósito de la Transferencia (AT-44) OP Alfanumérico 4 498-501
			$line.= filtered_pad_len('', 99);					//20 Libre OB Alfanumérico 99 502-600
			$this->file_content[$line_i] = $line;
			$line_i++;
			
			$ben_importe_total+= $ben_importe;
		}
		 
		/** REGISTROS DE TRANSFERENCIAS SEPA - REGISTRO DE TOTALES */
		/*
			5) Total de registros : Suma 2 registros (01 02) + todos los registros 03
		*/
		
		$line = "";												// N.Descipcion OB=Obligatorio OP=Opcional Tipo Len Posiciones
		$line.= filtered_pad_len('04', 2);						// 1 Código de Registro OB Numérico 2 01-02
		$line.= filtered_pad_len('SCT', 3);						// 2 Código de Operación OB Alfanumérico 3 03-05
		$line.= convert_float_price($ben_importe_total, 15, 2);	// 3 Total de Importes OB Numérico 17 06-22
		$line.= filtered_pad_len($this->facture_list_count, 	 8, "0", STR_PAD_LEFT);	// 4 Número de Registros OB Numérico 8 23-30
		$line.= filtered_pad_len($this->facture_list_count + 2,	10, "0", STR_PAD_LEFT);	// 5 Total de Registros OB Numérico 10 31-40
		$line.= filtered_pad_len('', 560);						// 6 Libre 
		$this->file_content[$line_i] = $line;
		$line_i++;
		
		/** REGISTRO DE TOTALES GENERAL */
		/*
			2)
			ORD = Órdenes de Transferencia y de Emisión de Cheques
			SCT = Transferencias SEPA
			OTR = Otras Transferencias
			CHQ = Cheques Bancarios / Nómina
			
			5) Total de registros : Suma 4 registros (01 02 04 99) + todos los registros 03
			
			Nota para campo Importes General :
			Total de importes general = suma de los totales de importes en euros (campo 3) de los registros de
			totales (códigos de registro 04). Si no se mezclan ordenes de transferencia con emision de cheques,
			el importe Total General se corresponde al de total importes registro 04. En caso contrario, establecer
			acumulador distinto para el total general.
		*/
		
		$line = "";												// N.Descipcion OB=Obligatorio OP=Opcional Tipo Len Posiciones
		$line.= filtered_pad_len('99', 2);						// 1 Código de Registro OB Numérico 2 01-02
		$line.= filtered_pad_len('ORD', 3);						// 2 Código de Operación OB Alfanumérico 3 03-05
		$line.= convert_float_price($ben_importe_total, 15, 2);	// 3 Total de Importes General OB Numérico 17 06-22
		$line.= filtered_pad_len($this->facture_list_count, 	 8, "0", STR_PAD_LEFT);	// 4 Número de Registros OB Numérico 8 23-30
		$line.= filtered_pad_len($this->facture_list_count + 4,	10, "0", STR_PAD_LEFT);	// 5 Total de Registros OB Numérico 10 31-40
		$line.= filtered_pad_len('', 560);						// 6 Libre 
		$this->file_content[$line_i] = $line;
		$line_i++;
		return null; //Finished without error
	}
}
?>