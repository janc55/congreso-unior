<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
function registrarVerificacion(array $r,array $usuario,string $estado,string $motivo,?int $pago=null): void {
 if($r['created_by']!==null)return;
 foreach(integrantesGrupo($r) as $persona)q('INSERT INTO verificaciones_inscripcion(id_inscripcion,id_pago,usuario_revision,decision_revision,motivo_revision,fecha_revision) VALUES(?,?,?,?,?,NOW())',[$persona['id'],$pago,$usuario['id'],$estado==='Pagado'?'Confirmado':'Rechazado',$motivo]);
}
function ultimaVerificacion(array $r): ?array {
 return one('SELECT v.*,u.name responsable_revision FROM verificaciones_inscripcion v JOIN users u ON u.id=v.usuario_revision WHERE v.id_inscripcion=? ORDER BY v.fecha_revision DESC,v.id DESC LIMIT 1',[$r['id']])?:null;
}
function historialVerificaciones(array $r): void {
 if(publicPortal()||!user()||$r['created_by']!==null)return;
 $filas=all('SELECT v.*,u.name responsable_revision,pa.codigo_transaccion,pa.importe_abono FROM verificaciones_inscripcion v JOIN users u ON u.id=v.usuario_revision LEFT JOIN pagos_inscripcion pa ON pa.id=v.id_pago WHERE v.id_inscripcion=? ORDER BY v.fecha_revision DESC,v.id DESC',[$r['id']]); ?>
<section class="card p-4 no-print"><h3>Quién confirmó o rechazó la inscripción en línea</h3><p>Se conserva cada revisión, aunque después se envíe un nuevo comprobante.</p><div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Fecha de revisión</th><th>Resultado</th><th>Confirmado / rechazado por</th><th>Transacción</th><th>Motivo / observación</th></tr></thead><tbody><?php foreach($filas as $v):?><tr><td><?=e($v['fecha_revision']??'Fecha anterior no disponible')?></td><td><strong><?=e($v['decision_revision'])?></strong></td><td><?=e($v['responsable_revision'])?></td><td><?=e($v['codigo_transaccion']??'Revisión anterior')?></td><td><?=e($v['motivo_revision']?:'—')?></td></tr><?php endforeach?><?php if(!$filas):?><tr><td colspan="5">Todavía no se ha confirmado ni rechazado un comprobante.</td></tr><?php endif?></tbody></table></div></section><?php
}
