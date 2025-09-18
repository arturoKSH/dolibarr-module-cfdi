<?php
/* Copyright (C) ---Put here your own copyright and developer email---
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    htdocs/modulebuilder/template/class/actions_mymodule.class.php
 * \ingroup mymodule
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

/**
 * Class ActionsMyModule
 */
class ActionsCfdi
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;


	/**
	 * Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 * Execute action
	 *
	 * @param	array			$parameters		Array of parameters
	 * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action      	'add', 'update', 'view'
	 * @return	int         					<0 if KO,
	 *                           				=0 if OK but we want to process standard actions too,
	 *                            				>0 if OK and we want to replace standard actions.
	 */
	public function getNomUrl($parameters, &$object, &$action)
	{
		global $db, $langs, $conf, $user;
		$this->resprints = '';
		return 0;
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('invoicecard'))) {	    // do something only for the context 'somecontext1' or 'somecontext2'
			
			
			if($action == 'confirm_valid2'){
				// do build xml
				$this->buildCFDI($object);
			}else if($action == 'confirm_timbre'){
				// do timbre in SAT
			
				$result = $this->stampCfdi($object);
				if($result == 1){ //condicion de que si se cumple el timbrado
					require_once DOL_DOCUMENT_ROOT."/custom/createevents/events.class.php";
					$actioncomm = new events($db);
					$actioncomm->createActionBillTimbre($user, $object);
				}
				
			}else if($action == 'confirm_CancelSat' && GETPOST('confirm', 'alpha') == 'yes' ){
				require_once DOL_DOCUMENT_ROOT."/custom/createevents/events.class.php";
				$actioncomm = new events($db);
				$actioncomm->createActionBillCancel($user, $object);
				$this->buildCFDICancel($object,$action);
			}
			
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}

		
	}

	/**
     * Build field XML
     * @param  InvoiceObject $object        Data from Invoice
     */
    public function stampCfdi($object){
		global $conf, $user, $langs;
		$table = 'facture_extrafields';
		$field = 'fk_object';
		$id = $object->id;
		$db = $this->db;
		
		$timbre=0;
		include(DOL_DOCUMENT_ROOT.'/elcInv/stamp/ateb.php');
		// echo 'aaaaaaa';
		if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)){
			$outputlangs = $langs;
			$newlang = '';
			if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id', 'aZ09')) $newlang = GETPOST('lang_id', 'aZ09');
			if ($conf->global->MAIN_MULTILANGS && empty($newlang))	$newlang = $object->thirdparty->default_lang;
			if (! empty($newlang)) {
				$outputlangs = new Translate("", $conf);
				$outputlangs->setDefaultLang($newlang);
				$outputlangs->load('products');
			}
			$model=$object->modelpdf;
			$ret = $object->fetch($id); // Reload to get new records

			$result = $object->generateDocument($model, $outputlangs, $hidedetails, $hidedesc, $hideref);
			if ($result < 0) setEventMessages($object->error, $object->errors, 'errors');
				

			return $timbre;
		}
	}

	function StockCfdi($user,$langs,$idwarehouse,$lines){
		
						$this->db->begin();
						dol_syslog(get_class($this)."::returnStockCfdi", LOG_DEBUG);
						// If we decrement stock on invoice validation, we increment
						// if ($this->type != self::TYPE_DEPOSIT && $result >= 0 && ! empty($conf->stock->enabled) && ! empty($conf->global->STOCK_CALCULATE_ON_BILL) && $idwarehouse!=-1)
						// {
							require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
							$langs->load("agenda");			
							$num=count($lines);
							for ($i = 0; $i < $num; $i++)
							{
								if ($lines[$i]->fk_product > 0)
								{
									$mouvP = new MouvementStock($this->db);
									$mouvP->origin = &$this;
									// We decrease stock for product
									if ($this->type == Facture::TYPE_CREDIT_NOTE) $result=$mouvP->livraison($user, $lines[$i]->fk_product, $idwarehouse, $lines[$i]->qty, $lines[$i]->subprice,"Factura ".$this->ref." Cancelada en SAT");
									else $result=$mouvP->reception($user, $lines[$i]->fk_product, $idwarehouse, $lines[$i]->qty, 0, "Factura ".$this->ref." Cancelada en SAT" );	// we use 0 for price, to not change the weighted average value					
								}
							}
							$this->db->commit();
							
						// }
					return 0;
					}
			 

	/**
     * Build field XML
     * @param  InvoiceObject $object        Data from Invoice
     */
    public function buildCFDI($object){
        global $conf;
        // set variables need field crtInvXml.php
        $id = $object->id;
        $db = $this->db;
        include(DOL_DOCUMENT_ROOT.'/elcInv/xmlCrt/crtInvXml.php');


    }
	/**
     * Build field XML To Cancel
     * @param  InvoiceObject $object        Data from Invoice
     */
    public function buildCFDICancel($object,$action){
        global $conf,$user,$langs;
        // set variables need field crtInvXml.php
        $id = $object->id;
        $db = $this->db;

		$confirm =  GETPOST('confirm', 'alpha');
		require DOL_DOCUMENT_ROOT.'/elcInv/CancelSat/composer/vendor/autoload.php';
        include(DOL_DOCUMENT_ROOT.'/elcInv/stamp/stampAteb.php');
		$result = $this->StockCfdi($user,$langs,$object->array_options['options_warehouse'],$object->lines);

    }
	
	/**
	 * Overloading the formConfirm function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function formConfirm($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs,$db;
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
		$form = new Form($db);
		$error = 0; // Error counter

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('invoicecard'))) {	    // do something only for the context 'somecontext1' or 'somecontext2'
			
			// add button to build xml when doc is status validated 
			
			if($action == 'Timbrar'){
				// do timbre in SAT
				
				$formconfirm = "";
				$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?facid=' . $object->id, "Timbrar", "¿Desea Timbrar esta factura?", 'confirm_timbre', '', "yes", 2);
				$this->resprints = $formconfirm;
			}else if($action == 'CancelSat'){
				// do timbre in SAT
				$form_question = $this->questionCancelSAT($object);
				$formconfirm = "";
				$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?facid=' . $object->id, "Cancelar", "¿Desea cancelar esta factura en el SAT?", 'confirm_CancelSat', $form_question, 0, 1,0);
				$this->resprints = $formconfirm;
			}
		}

		// if (!$error) {
		// 	$this->results = array('myreturn' => 999);
		// 	$this->resprints = 'A text to show';
		// 	return 0; // or return 1 to replace standard code
		// } else {
		// 	$this->errors[] = 'Error message';
		// 	return -1;
		// }
		return 0;
	}
	/**
	 * Build question to ask reason of Cancel in sat 
	 */
	public function questionCancelSAT($object){
		$db = $this->db;
		$form_question = array();
        $reason = array(
                "01" => "01 Comprobantes emitidos con errores con relación",
                "02" => "02 Comprobantes emitidos con errores sin relación",
                "03" => "03 No se llevó a cabo la operación",
                "04" => "04 Operación nominativa relacionada en una factura global");                


        $form_question['reason'] = array(
        'name' => 'reason',
        'type' => 'select',
        'label' => 'Motivo',
        'values' => $reason,
        'default' => '200',
		'size'=>'500',
		'morecss'=>'minwidth300'
        );


        $sql= " select a.ref, b.uuid ";
        $sql.= " from ".MAIN_DB_PREFIX."facture a inner join ".MAIN_DB_PREFIX."facture_extrafields b ";
        $sql.= " on a.rowid = b.fk_object inner join ".MAIN_DB_PREFIX."societe c ";
        $sql.= " on a.fk_soc = c.rowid ";
        $sql.= " where a.rowid != ".$object->id." ";
        $sql.= " and 	c.rowid = (select fk_soc from ".MAIN_DB_PREFIX."facture where rowid = ".$object->id.") ";
        $sql.= " and a.type = 0 ";
        $sql.= " and b.uuid is not null ";
        $sql.= " order by a.rowid desc ";

        $uuid=$db->query($sql);
        $objUuid=$uuid->fetch_all(MYSQLI_ASSOC);		                
        $uuidArray = array();

        foreach ($objUuid as $key => $value) {                    
                $uuidArray[$value['uuid']] = "|".$value['ref']."|".$value['uuid'];
        }
        $form_question['uuid'] = array(
        'name' => 'UUID',
        'type' => 'select',
        'label' => 'Sustituye a UUID',
        'values' => $uuidArray,
        'default' => '',
		'morecss'=>'minwidth300'
        );

		return   $form_question;

	}

	/**
	 * Overloading the addMoreActionsButtons function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('invoicecard'))) {	    // do something only for the context 'somecontext1' or 'somecontext2'
			
			// add button to build xml when doc is status validated
			if ($object->statut == Facture::STATUS_VALIDATED) {
				if (empty($object->array_options['options_uuid'])) {
					if (($object->statut == Facture::STATUS_VALIDATED 
							|| $object->statut == Facture::STATUS_CLOSED) 
							|| ! empty($conf->global->FACTURE_SENDBYEMAIL_FOR_ALL_STATUS)
					   ) {
						print '<div class="inline-block divButAction"><a class="butAction'.($conf->use_javascript_ajax?' reposition':'').'" href="' . $_SERVER["PHP_SELF"] . '?facid=' . $object->id . '&amp;action=confirm_valid2">' . $langs->trans('Generar CFDI ') . '</a></div>';    
						print '<div class="inline-block divButAction"><a class="butAction'.($conf->use_javascript_ajax?' reposition':'').'" href="' . $_SERVER["PHP_SELF"] . '?facid=' . $object->id . '&amp;action=Timbrar">' . $langs->trans('Timbrar') . '</a></div>';
							
					}
				}else if(!empty($object->array_options['options_uuid'])){
					/** Evitar que se pueda modificar o Eliminar una Factura Inicio */
					$modificar = 'Modificar';
					$eliminar = 'Eliminar';
					print '<script> 
					$(document).ready(function() {
						// Desactivar el enlace al cargar la página
						$("a.butAction.classfortooltip:contains('.$modificar.')").click(function(e) {
							e.preventDefault(); // Prevenir la acción predeterminada del enlace
							// También puedes agregar una clase de estilo para indicar que está desactivado
							$(this).addClass("disabled-link");
						});
						$("a.butActionDelete.classfortooltip:contains('.$eliminar.')").click(function(e) {
							e.preventDefault(); // Prevenir la acción predeterminada del enlace
							// También puedes agregar una clase de estilo para indicar que está desactivado
							$(this).addClass("disabled-link");
						});
					});
					</script>';
					/** Evitar que se pueda modificar o Eliminar una Factura Inicio */

					print '<div class="inline-block divButAction"><a class="butAction'.($conf->use_javascript_ajax?' reposition':'').'" href="' . $_SERVER["PHP_SELF"] . '?facid=' . $object->id . '&amp;action=CancelSat">' . $langs->trans('Cancelar CFDI') . '</a></div>';
				}
			}
			
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	

	/**
	 * Overloading the doMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
			foreach ($parameters['toselect'] as $objectid) {
				// Do action on each object id
			}
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}


	/**
	 * Overloading the addMoreMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter
		$disabled = 1;

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
			$this->resprints = '<option value="0"'.($disabled ? ' disabled="disabled"' : '').'>'.$langs->trans("MyModuleMassAction").'</option>';
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}



	/**
	 * Execute action
	 *
	 * @param	array	$parameters     Array of parameters
	 * @param   Object	$object		   	Object output on PDF
	 * @param   string	$action     	'add', 'update', 'view'
	 * @return  int 		        	<0 if KO,
	 *                          		=0 if OK but we want to process standard actions too,
	 *  	                            >0 if OK and we want to replace standard actions.
	 */
	public function beforePDFCreation($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;

		$outputlangs = $langs;

		$ret = 0; $deltemp = array();
		dol_syslog(get_class($this).'::executeHooks action='.$action);

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
		}

		return $ret;
	}

	/**
	 * Execute action
	 *
	 * @param	array	$parameters     Array of parameters
	 * @param   Object	$pdfhandler     PDF builder handler
	 * @param   string	$action         'add', 'update', 'view'
	 * @return  int 		            <0 if KO,
	 *                                  =0 if OK but we want to process standard actions too,
	 *                                  >0 if OK and we want to replace standard actions.
	 */
	public function afterPDFCreation($parameters, &$pdfhandler, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;

		$outputlangs = $langs;

		$ret = 0; $deltemp = array();
		dol_syslog(get_class($this).'::executeHooks action='.$action);

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {
			// do something only for the context 'somecontext1' or 'somecontext2'
		}

		return $ret;
	}



	/**
	 * Overloading the loadDataForCustomReports function : returns data to complete the customreport tool
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function loadDataForCustomReports($parameters, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$langs->load("mymodule@mymodule");

		$this->results = array();

		$head = array();
		$h = 0;

		if ($parameters['tabfamily'] == 'mymodule') {
			$head[$h][0] = dol_buildpath('/module/index.php', 1);
			$head[$h][1] = $langs->trans("Home");
			$head[$h][2] = 'home';
			$h++;

			$this->results['title'] = $langs->trans("MyModule");
			$this->results['picto'] = 'mymodule@mymodule';
		}

		$head[$h][0] = 'customreports.php?objecttype='.$parameters['objecttype'].(empty($parameters['tabfamily']) ? '' : '&tabfamily='.$parameters['tabfamily']);
		$head[$h][1] = $langs->trans("CustomReports");
		$head[$h][2] = 'customreports';

		$this->results['head'] = $head;

		return 1;
	}



	/**
	 * Overloading the restrictedArea function : check permission on an object
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int 		      			  	<0 if KO,
	 *                          				=0 if OK but we want to process standard actions too,
	 *  	                            		>0 if OK and we want to replace standard actions.
	 */
	public function restrictedArea($parameters, &$action, $hookmanager)
	{
		global $user;

		if ($parameters['features'] == 'myobject') {
			if ($user->rights->mymodule->myobject->read) {
				$this->results['result'] = 1;
				return 1;
			} else {
				$this->results['result'] = 0;
				return 1;
			}
		}

		return 0;
	}

	/**
	 * Execute action completeTabsHead
	 *
	 * @param   array           $parameters     Array of parameters
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         'add', 'update', 'view'
	 * @param   Hookmanager     $hookmanager    hookmanager
	 * @return  int                             <0 if KO,
	 *                                          =0 if OK but we want to process standard actions too,
	 *                                          >0 if OK and we want to replace standard actions.
	 */
	public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user;

		if (!isset($parameters['object']->element)) {
			return 0;
		}
		if ($parameters['mode'] == 'remove') {
			// utilisé si on veut faire disparaitre des onglets.
			return 0;
		} elseif ($parameters['mode'] == 'add') {
			$langs->load('mymodule@mymodule');
			// utilisé si on veut ajouter des onglets.
			$counter = count($parameters['head']);
			$element = $parameters['object']->element;
			$id = $parameters['object']->id;
			// verifier le type d'onglet comme member_stats où ça ne doit pas apparaitre
			// if (in_array($element, ['societe', 'member', 'contrat', 'fichinter', 'project', 'propal', 'commande', 'facture', 'order_supplier', 'invoice_supplier'])) {
			if (in_array($element, ['context1', 'context2'])) {
				$datacount = 0;

				$parameters['head'][$counter][0] = dol_buildpath('/mymodule/mymodule_tab.php', 1) . '?id=' . $id . '&amp;module='.$element;
				$parameters['head'][$counter][1] = $langs->trans('MyModuleTab');
				if ($datacount > 0) {
					$parameters['head'][$counter][1] .= '<span class="badge marginleftonlyshort">' . $datacount . '</span>';
				}
				$parameters['head'][$counter][2] = 'mymoduleemails';
				$counter++;
			}
			if ($counter > 0 && (int) DOL_VERSION < 14) {
				$this->results = $parameters['head'];
				// return 1 to replace standard code
				return 1;
			} else {
				// en V14 et + $parameters['head'] est modifiable par référence
				return 0;
			}
		}
	}

	/* Add here any other hooked methods... */
}
