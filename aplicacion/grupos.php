<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
function registrarGrupo(): never {
 global $db;
 $extras=$_POST['integrantes']??[];
 if(($_POST['grupo_activo']??'')!=='1'||!is_array($extras)||count($extras)!==4)throw new RuntimeException('La promoción requiere exactamente 5 personas: el participante principal y 4 integrantes. Complete los cinco o desactive el grupo.');
 $personas=array_merge([$_POST],array_values($extras));$id=(int)($_POST['event_id']??0);
 $db->beginTransaction();$evento=one('SELECT * FROM events WHERE id=? FOR UPDATE',[$id]);$hoy=date('Y-m-d');
 if(!$evento||!$evento['active']||$hoy<$evento['registration_start']||$hoy>$evento['registration_end'])throw new RuntimeException('Inscripciones cerradas para este evento.');
 $usados=(int)q("SELECT COUNT(*) FROM registrations WHERE event_id=? AND status<>'Anulado'",[$id])->fetchColumn();
 if($evento['capacity']>0&&$usados+count($personas)>$evento['capacity'])throw new RuntimeException('No quedan cupos suficientes para todo el grupo.');
 validarDocumentosInscripcion($id,$personas);
 $porcentaje=2000;
 $staff=publicPortal()?null:user();$cash=$staff&&isset($_POST['paid_now']);$method=$cash?($_POST['payment_method']??'Efectivo'):null;
 if($cash&&!in_array($method,['Efectivo','QR/Transferencia','Tarjeta'],true))throw new RuntimeException('Forma de pago inválida.');
 $grupo=bin2hex(random_bytes(24));$primerToken='';$documentos=[];
 foreach($personas as $numero=>$persona){
  if(!is_array($persona))throw new RuntimeException('Integrante inválido.');
  $datos=[];foreach(['first_name'=>120,'last_name'=>150,'ci'=>40,'city'=>100,'phone'=>40,'email'=>180,'category'=>30] as $campo=>$limite){$valor=trim((string)($persona[$campo]??''));if($valor===''||mb_strlen($valor)>$limite)throw new RuntimeException('Complete '.$campo.' del participante '.($numero+1).'.');$datos[$campo]=$valor;}
  $datos['ci']=mb_strtoupper($datos['ci']);$tratamiento=trim((string)($persona['tratamiento']??''));
  if(!array_key_exists($tratamiento,tratamientos())||!in_array($datos['category'],['Estudiante','Profesional','Estudiante BTH','UNIOR'],true)||!filter_var($datos['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Revise tratamiento, categoría y correo del participante '.($numero+1).'.');
  if(isset($documentos[$datos['ci']])||one('SELECT id FROM registrations WHERE event_id=? AND ci=?',[$id,$datos['ci']]))throw new RuntimeException('El documento '.$datos['ci'].' está repetido o ya inscrito.');$documentos[$datos['ci']]=true;
  $tarifa=tariff($id,$datos['category']);if(!$tarifa)throw new RuntimeException('No hay tarifa vigente para '.$datos['category'].'.');
  $base=(int)round((float)$tarifa['price']*100);$descuento=intdiv($base*$porcentaje+5000,10000);$final=$base-$descuento;
  $token=bin2hex(random_bytes(24));if(!$primerToken)$primerToken=$token;
  q('INSERT INTO registrations(tratamiento,event_id,token,certificate_token,first_name,last_name,ci,city,phone,email,institution,category,price,tariff_id,status,payment_method,reference,paid_at,reviewed_by,created_by,codigo_grupo,precio_base,descuento_grupo) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$tratamiento,$id,$token,bin2hex(random_bytes(24)),$datos['first_name'],$datos['last_name'],$datos['ci'],$datos['city'],$datos['phone'],$datos['email'],mb_substr(trim((string)($persona['institution']??'')),0,200),$datos['category'],number_format($final/100,2,'.',''),$tarifa['id'],$cash?'Pagado':'Pendiente',$method,$cash?mb_substr(trim($_POST['reference']??''),0,120):null,$cash?date('Y-m-d H:i:s'):null,$cash?$staff['id']:null,$staff['id']??null,$grupo,number_format($base/100,2,'.',''),number_format($descuento/100,2,'.','')]);
  q('UPDATE registrations SET carrera=? WHERE token=?',[validarCarrera($persona),$token]);
 }
 if($cash)registrarCobroInicial(registration($primerToken));
 audit('Inscripción de grupo','Evento '.$id.' · '.count($personas).' integrantes · descuento '.($porcentaje/100).'%');$db->commit();finalizarInscripcion($primerToken);
}
function integrantesGrupo(array $r,bool $bloquear=false): array {return $r['codigo_grupo']?all('SELECT * FROM registrations WHERE codigo_grupo=? ORDER BY id'.($bloquear?' FOR UPDATE':''),[$r['codigo_grupo']]):[$r];}
function comprobanteGrupo(array $r): void {
 global $db;$db->beginTransaction();$integrantes=integrantesGrupo($r,true);
 foreach($integrantes as $persona)if(!in_array($persona['status'],['Pendiente','Rechazado'],true))throw new RuntimeException('El pago del grupo ya fue enviado, confirmado o anulado.');
 $archivo=upload('proof',true);if(!$archivo)throw new RuntimeException('Adjunte el comprobante de pago de todo el grupo.');
 q("UPDATE registrations SET proof=?,status='En revisión',reference=?,payment_method='QR/Transferencia',review_note=NULL WHERE codigo_grupo=?",[$archivo,mb_substr(trim($_POST['reference']??''),0,120),$r['codigo_grupo']]);audit('Comprobante de grupo adjuntado','Grupo '.$r['codigo_grupo']);$db->commit();flash('Comprobante del grupo enviado para revisión.');go('p=receipt&t='.$r['token']);
}
function revisarGrupo(array $r,array $u): never {
 global $db;auth(true);$estado=$_POST['status']??'';$nota=mb_substr(trim($_POST['review_note']??''),0,2000);
 if(!in_array($estado,['Pagado','Rechazado','Anulado'],true)||($estado!=='Pagado'&&$nota===''))throw new RuntimeException('Seleccione el estado y explique el motivo cuando corresponda.');
 $db->beginTransaction();$personas=integrantesGrupo($r,true);
 foreach($personas as $persona){if(in_array($persona['status'],['Pagado','Anulado'],true))throw new RuntimeException('El grupo ya está confirmado o anulado.');if($estado==='Pagado'&&$persona['created_by']===null&&!$persona['proof'])throw new RuntimeException('Debe adjuntarse y verificarse el comprobante de todo el grupo.');}
 if($estado==='Pagado')q('UPDATE registrations SET monto_cobrado=price WHERE codigo_grupo=?',[$r['codigo_grupo']]);
 q('UPDATE registrations SET status=?,review_note=?,reviewed_by=?,paid_at=?,payment_method=COALESCE(payment_method,?) WHERE codigo_grupo=?',[$estado,$nota,$u['id'],$estado==='Pagado'?date('Y-m-d H:i:s'):null,'QR/Transferencia',$r['codigo_grupo']]);audit('Pago de grupo revisado','Grupo '.$r['codigo_grupo'].' · '.$estado);$db->commit();flash('Estado actualizado para todos los integrantes del grupo.');go('p=participant&t='.$r['token']);
}
