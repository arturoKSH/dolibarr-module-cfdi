<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

//$h = sizeof($head);


$head[$h][0] = DOL_URL_ROOT.'/compta/facture/elcInv/facsrel.php?facid='.$object->id;
$head[$h][1] = $langs->trans('Facturas relacionadas');
$head[$h][2] = 'facsrel';
$h++;

//echo $this->tabs;
