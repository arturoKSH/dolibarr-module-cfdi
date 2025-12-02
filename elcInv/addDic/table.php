
<?php
$ad=GETPOST('Add');
	// $sql="	SELECT label, FROM ".MAIN_DB_PREFIX."KSHtblhead";
	// $sql.=" WHERE table_element='".$object->table_element."'";
	// $sql.=  " AND post='1'";
	// $consult=$db->query($sql);


    // if($object->table_element == "projet")
	// {
	// 	$sql=" SELECT value FROM ".MAIN_DB_PREFIX."KSHtbldetail";
	// 	$sql.=" WHERE  fk_ref=".$defaultref;
	// 	if(!empty($object->id))
	// 	{
	// 	   $sql.="	AND fk_id=".$object->id; 
		  
	// 	}
	// }
/// consulta para armar la tabla
	$sql="	SELECT rowid,fieldid,label,type FROM ".MAIN_DB_PREFIX."KSHtblbox";
	$sql.=" WHERE fk_tbl=1";
	$sql.=  " AND post='1'";
	$consult=$db->query($sql);
// consulta para pintar los detalles
	$sql="	SELECT rowid,fk_ref,value,1 FROM ".MAIN_DB_PREFIX."KSHtbldetail";
	$sql.=" WHERE fk_ref='".$defaultref."'";
	$detail=$db->query($sql);
	$dt= $detail->fetch_all(MYSQLI_ASSOC);
	
    $co=count($dt);
		
	print '<br>';
	print '<br>';
	print '<form action="'.$_SERVER["PHP_SELF"].'?action=create&id=2" method="POST">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	print 	'<div class="div-table-responsive-no-min">';
	// print 		'<select class="flat" name="select">';
				
	// print 			'<option value="'.$product->label.'" >'.$langs->trans($product->label).'</option>';
				
	// print		'</select>';
	print 		'<table class="noborder centpercent">';
	print 			'<thead>';
	print 				'<tr class="liste_titre">';
							
							foreach ($file=$consult->fetch_all(MYSQLI_ASSOC) as $key => $value)
							{
									
								print '<th scope="col"><center>'.$value['label'].'</center></th>';
								
							
							}
	print 					'<th scope="col"><center></center></th>';	
	print 				'</tr>';
	print 			'</thead>';
	print 			'<tbody>';
	print				'<tr>';
							foreach ($file as $ke => $type)
							{
								print	'<td class="liste_titre center">';
								print 	'<input type="'.$type['type'].'" id="'.$type['fielid'].'" name="'.$type['fielid'].'">';
								print	'</td>';
							}
	print					'<td class="liste_titre center">';
	print 						'<input type="submit" class="button" name="Add" value="'.dol_escape_htmltag($langs->trans("Add")).'">'; 
	print					'</td>';
	print				'</tr>';
	
							foreach ($dt as $det => $detalle)
							{
		
	print						'<td class="liste_titre center>';
	print						$detalle['value'];
	print						'</td>';
							}
	print					'<td class="liste_titre center>';
	print						'<a href="'.$_SERVER["PHP_SELF"].'?id='.$valor.'&action=create"> <span class="fas fa-pencil-alt marginleftonly" style=" color: #444;" title="Modificar"></span></a>';
	print						'<a href="'.$_SERVER["PHP_SELF"].'?id='.$valor.'&action=create"> <span class="fas fa-trash marginleftonly pictodelete" style=" color: #444;" title="Eliminar"></span></a>';
	print					'</td>';
	print				'</tr>';
	print			'</tbody>';									
	print 		'</table>';
	print 	'</div>';
	
	print '</form>';

if(!empty($ad))
{
	
	$i=0;
	foreach ($file as $in => $insert)
	{
		$i++;
		$sql="INSERT INTO ".MAIN_DB_PREFIX."KSHtbldetail (fk_box,fk_ref,value) VALUES";
		$sql.="('".$insert['rowid']."','".$defaultref."','".${"array" . $i}=GETPOST($insert['fielid'])."')";
		$consult=$db->query($sql);
		$ad='';
	}


}

