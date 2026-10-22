<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
function permisoVerificarInscripcion(array $r,array $u): void {
 if($u['role']!=='admin'&&$r['created_by']!==null){http_response_code(403);exit('El vendedor solo puede verificar inscripciones en línea.');}
 if(!in_array($_POST['status']??'',['Pagado','Rechazado'],true))throw new RuntimeException('Seleccione confirmar o rechazar el comprobante. La anulación se realiza desde el apartado exclusivo del administrador.');
}
function revisarPagoAutorizado(): never {
 global $db;$u=auth();$r=registration(textValue('token',48));permisoVerificarInscripcion($r,$u);
 if(revisarAbono())exit;
 if($r['codigo_grupo'])revisarGrupo($r,$u);
 $nota=mb_substr(trim($_POST['review_note']??''),0,2000);$estado=$_POST['status'];
 $db->beginTransaction();$r=one('SELECT * FROM registrations WHERE id=? FOR UPDATE',[$r['id']]);
 if(in_array($r['status'],['Pagado','Anulado'],true))throw new RuntimeException('La inscripción ya está confirmada o anulada.');
 if($estado==='Pagado'&&$r['created_by']===null&&!$r['proof'])throw new RuntimeException('Debe adjuntarse y verificarse un comprobante.');
 if($estado==='Rechazado'&&$nota==='')throw new RuntimeException('Indique el motivo del rechazo.');
 q('UPDATE registrations SET status=?,review_note=?,reviewed_by=?,paid_at=?,monto_cobrado=IF(?,price,monto_cobrado) WHERE id=?',[$estado,$nota,$u['id'],$estado==='Pagado'?date('Y-m-d H:i:s'):null,$estado==='Pagado'?1:0,$r['id']]);
 registrarVerificacion($r,$u,$estado,$nota);
 audit('Pago anterior revisado','Registro '.$r['id'].' · '.$estado);$db->commit();flash('Revisión registrada.');go('p=participant&t='.$r['token']);
}
function cambiarEstadoInscripcion(): never {
 global $db;$u=auth(true);$r=registration(textValue('token',48));$operacion=$_POST['operacion_estado']??'';$motivo=trim((string)($_POST['motivo_estado']??''));
 if(!in_array($operacion,['anular','reactivar'],true)||mb_strlen($motivo)<3||mb_strlen($motivo)>2000)throw new RuntimeException('Seleccione la operación y escriba el motivo (entre 3 y 2000 caracteres).');
 $db->beginTransaction();$evento=one('SELECT * FROM events WHERE id=? FOR UPDATE',[$r['event_id']]);$personas=integrantesGrupo($r,true);$cantidad=0;
 foreach($personas as $persona)if(($operacion==='anular'&&$persona['status']!=='Anulado')||($operacion==='reactivar'&&$persona['status']==='Anulado'))$cantidad++;
 if(!$cantidad)throw new RuntimeException($operacion==='anular'?'La inscripción ya está anulada.':'La inscripción ya está activa.');
 if($operacion==='reactivar'&&$evento['capacity']>0){$ocupados=(int)q("SELECT COUNT(*) FROM registrations WHERE event_id=? AND status<>'Anulado'",[$r['event_id']])->fetchColumn();if($ocupados+$cantidad>$evento['capacity'])throw new RuntimeException('No hay cupos suficientes para reactivar esta inscripción.');}
 foreach($personas as $persona){
  if($operacion==='anular'&&$persona['status']==='Anulado'||$operacion==='reactivar'&&$persona['status']!=='Anulado')continue;
  $anterior=$persona['status'];
  if($operacion==='anular'){$nuevo='Anulado';q('UPDATE registrations SET estado_previo_anulacion=?,status=? WHERE id=?',[$anterior,$nuevo,$persona['id']]);}
  else{$nuevo=$persona['estado_previo_anulacion']??null;if(!in_array($nuevo,['Pendiente','En revisión','Pagado','Rechazado'],true))$nuevo=$persona['monto_cobrado']>0?'Pagado':'Pendiente';q('UPDATE registrations SET status=?,estado_previo_anulacion=NULL WHERE id=?',[$nuevo,$persona['id']]);}
  q('INSERT INTO cambios_inscripcion(id_inscripcion,autor_cambio,estado_anterior,estado_nuevo,motivo_cambio) VALUES(?,?,?,?,?)',[$persona['id'],$u['id'],$anterior,$nuevo,$motivo]);
 }
 audit($operacion==='anular'?'Inscripción anulada':'Inscripción reactivada','Registro '.$r['id'].' · '.$cantidad.' persona(s) · '.$motivo);$db->commit();flash('Operación registrada con su motivo e historial.');go('p=participant&t='.$r['token']);
}
function panelEstadoInscripcion(array $r): void {
 if(!user()||user()['role']!=='admin'||publicPortal())return;
 $anulado=$r['status']==='Anulado'; ?>
<section class="card p-4 no-print"><h3><?=$anulado?'Reactivar inscripción':'Anular inscripción'?></h3><p><?=e(nombreParticipante($r))?> · CI <?=e($r['ci'])?><?php if($r['codigo_grupo']):?> · Esta operación afecta a los <?=count(integrantesGrupo($r))?> integrantes del grupo.<?php endif?></p><p>Se conservan los cobros, comprobantes, asistencia y entregas. La anulación no registra una devolución de dinero. Al reactivar se recupera el estado anterior y se comprueban los cupos.</p><form method="post" data-confirm="¿Desea registrar este cambio de inscripción con el motivo indicado?"><?=csrf()?><input type="hidden" name="action" value="estado_inscripcion"><input type="hidden" name="token" value="<?=e($r['token'])?>"><input type="hidden" name="operacion_estado" value="<?=$anulado?'reactivar':'anular'?>"><label>Motivo obligatorio</label><textarea class="form-control mb-3" name="motivo_estado" minlength="3" maxlength="2000" required></textarea><button class="btn <?=$anulado?'btn-success':'btn-danger'?>"><?=$anulado?'Reactivar inscripción':'Anular inscripción'?></button></form><h4 class="mt-3">Historial de anulación y reactivación</h4><div class="table-responsive"><table class="table"><tr><th>Fecha</th><th>Responsable</th><th>Cambio</th><th>Motivo</th></tr><?php foreach(all('SELECT c.*,u.name autor_nombre FROM cambios_inscripcion c JOIN users u ON u.id=c.autor_cambio WHERE c.id_inscripcion=? ORDER BY c.id DESC',[$r['id']]) as $c):?><tr><td><?=e($c['fecha_cambio'])?></td><td><?=e($c['autor_nombre'])?></td><td><?=e($c['estado_anterior'])?> → <?=e($c['estado_nuevo'])?></td><td><?=e($c['motivo_cambio'])?></td></tr><?php endforeach?></table></div></section><?php
}
