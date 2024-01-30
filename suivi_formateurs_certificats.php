<?php
/**
 * Action for rapport document
 *
 * @author Nevea
 * @version $Id$
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FORMATION
 */
/**
 */
include_once ("FDL/Lib.Attr.php");

include_once ("EXTERNALS/fdl.php");
include_once ("FDL/Lib.Dir.php");
include_once ("FDL/freedom_util.php");
include_once ("FDL/modcard.php");
include_once ("FDL/editutil.php");

//include_once 'FDL/Class.AllPurposeLibrary.php';
include_once ("RAPPORT/menutabIntegrated.php");

function suivi_formateurs_certificats ( &$action ) {

	editmode($action);
	/* echo "<pre>";
	var_dump(json_encode(menuTabIntegratedLoad($action)));
	echo "</pre>";
	die; */
	$action->lay->Set("MENUTAB", menuTabIntegratedLoad($action));
	// Connecteur DB
	$dbaccess = $action->GetParam("FREEDOM_DB");
	$DAL = new postgres_dal($dbaccess);
	$DAL->escape = false;

    // Liste des caces
    $lstCACES = array (
        '482' => 10,
        '483' => 5,
        '484' => 5,
        '485' => 5,
        '486' => 5,
        '487' => 5,
        '489' => 5,
        '490' => 5,
    );
	// Date du jour
	$today = time();
	$duree_mois = 30 * 24 * 60 * 60;
	
	// Liste des formateurs en activité
	$DAL->sendArgQuery("SELECT family.nve_employee.id, family.nve_employee.title FROM family.nve_employee WHERE (family.nve_employee.us_formentrep = 'OUI') ORDER BY family.nve_employee.title");
	$lstFormateurs = $DAL->getAllValues();
	
	if (count($lstFormateurs) == 0){
		print "Pas de formateur enregistr&eacute;";
		return false;	
	}
	
	$synthese = array ();
	foreach ($lstFormateurs as $formateur) {
		
		// Formateur
		$formateur_id = $formateur["id"];
		$formateur_nom = $formateur["title"];
		
		//if($formateur_id != '1218622') continue;
		//if($formateur_id != '1219213') continue;
		// TODO : test contrat de travail valide
		//$oDoc = new_Doc('', '1219213', true);
		$oDoc = new_Doc('', $formateur_id, true);
		


		foreach ($lstCACES as $rcnam => $duree) {
			// Liste des certifications
			$sSql = "
					WITH myQuery AS (
					SELECT UNNEST(STRING_TO_ARRAY(family.nve_employee.us_certif".$rcnam."_catcode, E'\n')) AS code, 
						UNNEST(STRING_TO_ARRAY(family.nve_employee.us_certif".$rcnam."_catvalid, E'\n')) AS date_fin_validite, 
						(CASE 
								WHEN family.nve_employee.us_certif".$rcnam."_fonction IS NULL 
									THEN 'Testeur' 
									ELSE (
											SELECT  UNNEST(STRING_TO_ARRAY(family.nve_employee.us_certif".$rcnam."_fonction, E'\n')) 
											FROM    family.nve_employee 
											WHERE   family.nve_employee.id::INT = '$formateur_id'::INT 
										)
							END) AS fonction 
					FROM family.nve_employee 
					WHERE family.nve_employee.id::INT = '$formateur_id'::INT) 
					
					SELECT  code, 
							date_fin_validite, 
							fonction 
					FROM    myQuery 
					WHERE   LENGTH(code) > 0";

					//var_dump($sSql);exit(0);
				

					//$DAL->sendArgQuery($sSql);
			$aUserCertifRcnam = $oDoc->getArrayRawValues("us_ar_certif".$rcnam);

			//if ($DAL->getNbreEnregistrements() > 0) {
			if(count($aUserCertifRcnam) > 0){
				//$lstCertifications = $DAL->getAllValues();

				/*var_dump($oDoc->getArrayRawValues("us_ar_certif".$rcnam));
				var_dump($lstCertifications);*/

				$aCertifTmp = [];
				$aRow = [];
				foreach($oDoc->getArrayRawValues("us_ar_certif".$rcnam) as $aRowCertif)
				{
					foreach($aRowCertif as $sAttribut => $sValeur)
					{
						switch($sAttribut)
						{
							case "us_certif".$rcnam."_catcode" : $aRow['code'] = $sValeur; break;
							case "us_certif".$rcnam."_catvalid" : $aRow['date_fin_validite'] = $sValeur; break;
							case "us_certif".$rcnam."_fonction" : $aRow['fonction'] = ($sValeur == null ? 'Testeur' : $sValeur); break;
							default : break;
						}
						//var_dump($aRow);
						if(array_key_exists('code', $aRow) == true && array_key_exists('date_fin_validite', $aRow) == true && array_key_exists('fonction', $aRow) == true)
						{
							$aCertifTmp[] = $aRow;
							$aRow = [];
						}
					}
				}

				// var_dump($aCertifTmp);
				$lstCertifications = $aCertifTmp;
				
				$arrCertifications = array ();
				$arrFonctions = array();
				// $arrDateFinValidite= array ();
				foreach ($lstCertifications as $certification) {
					
					// Lecture des catégories
					$categorie_code = $certification["code"];
                    if (mb_strlen($categorie_code) > 0){
                        $date_fin_validite = $certification["date_fin_validite"];

                        switch($certification["fonction"]){
                            case "1":
                                $fonction = "Testeur";
                                break;

                            case '2':
                                $fonction = "Formateur";
                                break;

                            default:
                                $fonction = $certification["fonction"];
                                break;
                        }
				
						
                        $message = '';
                        if (mb_strlen($date_fin_validite) > 0) {
                            //$sp_date_finvalidite = AllPurposeLibrary::convert2span($date_fin_validite);
                            $sp_date_finvalidite = DateConvert::stringDateToTimestamp($date_fin_validite);
                            for ($mois = 0; $mois <= 6; $mois++) {
                                $border_date = $today + $mois * $duree_mois;

                                if ($sp_date_finvalidite <= $border_date) {
                                    $message = $mois;
                                    break;
                                }
                            }

                        } else {
                            $message = "NR";
                        }

                        $arrCertifications[$categorie_code] = $message;
                        $arrFonctions[$categorie_code] = $fonction;
                        $oDate = new DateTime($date_fin_validite);
                        $arrDatesFinValidite[$categorie_code] = $oDate->format("d/m/Y");
						// var_dump($fonction);
                    }
				}
				
				if (count($arrCertifications) > 0) {
					$synthese[$rcnam][] = array (
					'form_id' => $formateur_id, 
					'formateur' => $formateur_nom, 
					'fonctions' => $arrFonctions,
					'datefinvalidite' => $arrDatesFinValidite,
					'categories' => $arrCertifications);
				}
			}
		}
	}
	
	// Sortie HTML
	if (count($synthese) > 0) {
		
		// Liste des CACES
		$lstCacesRestants = $lstCACES;
		
		$aDonneesDataSource = array();

		// Pour chaque recommandation
		foreach ($synthese as $rcnam => $recommandation) {

			// bandeau
			$zone = sprintf("DATAR%s", $rcnam);
			unset($lstCacesRestants[$rcnam]);
			
			// Container
			$html = new StringBuilder();
			
			// Ouvrir la table
			$html->addString("<table width=\"100%%\" id=\"report_%s\" class='reportHBlue' border='0' cellspacing='2' cellpadding='2'>", $rcnam);
			
			// En tête
			$html->addString("<tr>");
			$html->addString("<th rowspan='2' scope='col'>%s</th>", "Formateur");
			$html->addString("<th rowspan='2' scope='col'>%s</th>", "Fonction");
			$html->addString("<th rowspan='2' scope='col'>%s</th>", "Cat&eacutegorie");
			$html->addString("<th rowspan='2' scope='col'>%s</th>", "Non renseign&eacute;");
			$html->addString("<th rowspan='2' scope='col'>%s</th>", "Non valide");
			$html->addString("<th colspan='6' scope='col'>%s</th>", "Non valide dans");
			$html->addString("</tr>");
			
			$html->addString("<tr>");
			$html->addString("<th scope='col'>%s</th>", "1 mois");
			$html->addString("<th scope='col'>%s</th>", "2 mois");
			$html->addString("<th scope='col'>%s</th>", "3 mois");
			$html->addString("<th scope='col'>%s</th>", "4 mois");
			$html->addString("<th scope='col'>%s</th>", "5 mois");
			$html->addString("<th scope='col'>%s</th>", "6 mois");
			$html->addString("</tr>");
			
			// certifications catégories
			$num_line_formateur = 0;
			$num_line = 0;

			if(!array_key_exists($rcnam, $aDonneesDataSource)){
				$aDonneesDataSource[$rcnam] = array();
			}
			$aDonneesSynthese = [];

			foreach ($recommandation as $formateur) {				
				$categories = $formateur["categories"];
				$fonctions = $formateur["fonctions"];
                $datefinvalidite = $formateur["datefinvalidite"];
				
				$nbre_categories = count($categories);
				
				// Classe
				$class_formateur = ($num_line_formateur % 2 == 0) ? "even" :"odd";
				$num_line_formateur++;
				
				// Formateur
				$employee = sprintf("<a href='[CORE_STANDURL]&app=FDL&action=FDL_CARD&latest=Y&id=%s' title='Voir la fiche' target='_blank'>%s</a>", $formateur["form_id"], $formateur["formateur"]);
				// var_dump($employee);
				$html->addString("<tr>");
				$html->addString("<td rowspan='%s' scope='col' class='%s' valign='top'>%s</td>", $nbre_categories,  $class_formateur, $employee);
				
				// Catégories
				$num_categorie = 0;
				foreach ($categories as $code => $response) {
					
					if ($num_categorie > 0) {
						$html->addString("<tr>");
					}
					
					// Classe
					if ($num_line % 2 == 0) {
						$class = "even";
					} else {
						$class = "odd";
					}
					$num_line++;
					
					// Fonction
					$html->addString("<td align='left' class='%s'>%s</td>", $class, $fonctions[$code]);
										
					// Code
					$html->addString("<td class='%s'>%s</td>", $class, $code);
					
					// Validite
					// $validite = [];
					/* if($response === 'NR'){
						$validite[] = "X";
						$sNomRenseigne = "X";
						
					}
					else{
						$validite[] = "&nbsp;";	
						$sNomRenseigne = "";
					} */
					/* $validite[] = ($response === 'NR') ? "X" : "&nbsp;";
					$validite[] = ($response === 0) ? "X" : "&nbsp;";
					$validite[] = ($response === 1) ? "X" : "&nbsp;";
					$validite[] = ($response === 2) ? "X" : "&nbsp;";
					$validite[] = ($response === 3) ? "X" : "&nbsp;";
					$validite[] = ($response === 4) ? "X" : "&nbsp;";
					$validite[] = ($response === 5) ? "X" : "&nbsp;";
					$validite[] = ($response === 6) ? "X" : "&nbsp;";

                    $validite[] = ($response === 7) ? "X" : "&nbsp;";
                    $validite[] = ($response === 8) ? "X" : "&nbsp;";
                    $validite[] = ($response === 9) ? "X" : "&nbsp;"; */

					
					/* foreach ($validite as $value) {
						$html->addString("<td align='center' class='%s'>%s</td>", $class, $value);
					} */
					// Fin de la ligne
					$html->addString("</tr>");
					

					// Validite
					$date_fin_validite = $certification["date_fin_validite"];

					$aDonneesDataSource[$rcnam][] = [
                        "formateur" => $employee,
                        "fonction" => $fonctions[$code],
                        "categorie" => $code,
						"date_fin_validite" => $datefinvalidite[$code],
                        "non_renseigne" => ($response === 'NR') ? "X" : "",
                        "non_valide" => ($response === 0) ? "X" : "",
                        "non_valide_1_mois" => ($response === 1) ? "X" : "",
                        "non_valide_2_mois" => ($response === 2) ? "X" : "",
                        "non_valide_3_mois" => ($response === 3) ? "X" : "",
                        "non_valide_4_mois" => ($response === 4) ? "X" : "",
                        "non_valide_5_mois" => ($response === 5) ? "X" : "",
                        "non_valide_6_mois" => ($response === 6) ? "X" : ""
                    ];
					
					foreach ($aDonneesDataSource as $aDonneesDataSources){
						foreach($aDonneesDataSources as $aDonneesSyntheses){
							$aDonneesSynthese[] = $aDonneesSyntheses;
						}
					}
					
					$num_categorie++;
					
				}
			}
			
			// Fin de la table
			$html->addString("</table>");
			
			// Passage sur le layer
			$action->lay->Set($zone, $html->buildString("\n"));

            $action->lay->Set("GRID_".$zone, "display:block");
            $action->lay->Set("NC_".$zone, "display:none");
            // $action->lay->Set("GRID_SYNTHESE", "display:block");
			
			// var_dump($aDonneesSynthese);
			$action->lay->Set("SYNTHESE", json_encode($aDonneesSynthese));
			// var_dump($aDonneesDataSource[$rcnam]);
			$action->lay->Set($zone, json_encode($aDonneesDataSource[$rcnam]));
			
		}

		if (count($lstCacesRestants) > 0) {
			foreach ($lstCacesRestants as $rcnam => $duree) {
				
				$zone = sprintf("DATAR%s", $rcnam);
				
                $aDonneesDataSource = [];
                $aDonneesDataSource[] = [
                    "formateur" => "Pas de donnees",
                    "fonction" => "",
                    "categorie" => "",
                    "date_fin_validite" => "",
                    "non_renseigne" => "",
                    "non_valide" => "",
                    "non_valide_1_mois" => "",
                    "non_valide_2_mois" => "",
                    "non_valide_3_mois" => "",
                    "non_valide_4_mois" => "",
                    "non_valide_5_mois" => "",
                    "non_valide_6_mois" => ""
                ];
				
                $action->lay->Set("GRID_".$zone, "display:none");
                $action->lay->Set("NC_".$zone, "display:block");
                $action->lay->Set($zone, json_encode($aDonneesDataSource));
				
			}
		}
	
	} else {
		
		// Effacer les zones
		foreach ($lstCACES as $rcnam => $duree) {
			$zone = sprintf("DATAR%s", $rcnam);
            $aDonneesDataSource = [];
            $aDonneesDataSource[] = [
                "formateur" => "Pas de donnees",
                "fonction" => "",
                "categorie" => "",
                "date_fin_validite" => "",
                "non_renseigne" => "",
                "non_valide" => "",
                "non_valide_1_mois" => "",
                "non_valide_2_mois" => "",
                "non_valide_3_mois" => "",
                "non_valide_4_mois" => "",
                "non_valide_5_mois" => "",
                "non_valide_6_mois" => ""
            ];
			
            $action->lay->Set("GRID_".$zone, "display:none");
            $action->lay->Set("NC_".$zone, "display:block");
            $action->lay->Set($zone, json_encode($aDonneesDataSource));
		}
	
	}
}
?>