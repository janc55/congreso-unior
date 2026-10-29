<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
function recibosReporte(array $r): void {
 static $cache=[];$clave=claveCobro($r);
 if(!isset($cache[$clave]))$cache[$clave]=all("SELECT id,importe_abono FROM pagos_inscripcion WHERE codigo_conjunto=? AND estado_abono='Confirmado' AND importe_abono>0 ORDER BY id DESC",[$clave]);
 echo '<a class="btn btn-sm btn-primary" href="'.url('p=participant&t='.$r['token'].'#pagos-saldo').'">Cobrar / editar voucher</a> ';
 if($cache[$clave]){echo '<details class="mt-1"><summary>Reimprimir recibos</summary>';foreach($cache[$clave] as $pago)echo '<a class="d-block" target="_blank" rel="noopener" href="'.url('p=receipt&t='.$r['token'].'&pago='.$pago['id'].'&imprimir=1').'">Recibo P-'.e(str_pad((string)$pago['id'],7,'0',STR_PAD_LEFT)).' · '.money($pago['importe_abono']).'</a>';echo '</details>';}
}
function opcionesComprobante(array $r,array $p,array $res): void {
 if($r['status']==='Anulado')return;
 if(in_array($p['estado_abono'],['Confirmado','En revisión'],true)):?>
 <details class="mt-2"><summary>Editar / volver a subir voucher</summary><p class="small">El archivo anterior queda en el historial. Reemplazarlo no modifica el monto ni vuelve a verificar el pago.</p><form method="post" enctype="multipart/form-data"><?=csrf()?><input type="hidden" name="action" value="cambiar_comprobante"><input type="hidden" name="token" value="<?=e($r['token'])?>"><input type="hidden" name="pago_id" value="<?=e($p['id'])?>"><input type="file" class="form-control mb-2" name="nuevo_comprobante" accept="image/jpeg,image/png,image/webp,application/pdf" required><button class="btn btn-sm btn-outline-primary">Guardar voucher</button></form></details>
 <?php $cambios=historialArchivosComprobante((int)$p['id']);if($cambios):?><details class="mt-1"><summary>Historial de vouchers</summary><?php foreach($cambios as $c):?><p class="small"><?=e($c['fecha_cambio']??'Fecha no disponible')?> · <?=e($c['nombre_operador']??'Usuario') ?> <?php if(!empty($c['archivo_anterior'])):?><a href="<?=fileUrl($c['archivo_anterior'])?>" target="_blank" rel="noopener">Archivo anterior</a><?php endif?></p><?php endforeach?></details><?php endif;
 elseif($p['estado_abono']==='Rechazado'&&!pagoAnulado($p)&&$res['saldo']>0&&!one("SELECT id FROM pagos_inscripcion WHERE codigo_conjunto=? AND estado_abono='En revisión'",[claveCobro($r)])):?>
 <details class="mt-2"><summary>Enviar nuevo voucher</summary><p class="small">El voucher rechazado se conserva. Indique un nuevo código de transacción no utilizado; el nuevo envío quedará sujeto a verificación.</p><form method="post" enctype="multipart/form-data"><?=csrf()?><input type="hidden" name="action" value="reenviar_comprobante"><input type="hidden" name="token" value="<?=e($r['token'])?>"><input type="hidden" name="pago_id" value="<?=e($p['id'])?>"><label>Importe declarado (Bs)</label><input class="form-control mb-2" type="number" name="importe_abono" min="0.01" step="0.01" max="<?=$res['saldo']/100?>" value="<?=e($p['importe_abono'])?>" required><label>Nuevo código de transacción</label><input class="form-control mb-2" name="reference" maxlength="120" required><input type="file" class="form-control mb-2" name="nuevo_comprobante" accept="image/jpeg,image/png,image/webp,application/pdf" required><button class="btn btn-sm btn-primary">Enviar para revisión</button></form></details>
 <?php endif;
}
function cambiarComprobante(): never {
 global $db;$u=auth();$r=registration(textValue('token',48));$nuevo=null;
 try{
  $db->beginTransaction();$r=bloquearCobro($r);$pago=one('SELECT * FROM pagos_inscripcion WHERE id=? AND codigo_conjunto=? FOR UPDATE',[(int)($_POST['pago_id']??0),claveCobro($r)]);
  if(!$pago||!in_array($pago['estado_abono'],['Confirmado','En revisión'],true))throw new RuntimeException('Seleccione un pago válido para reemplazar su voucher.');
  $nuevo=upload('nuevo_comprobante',true);if(!$nuevo)throw new RuntimeException('Adjunte el voucher nuevo.');
  audit('Voucher reemplazado','Pago:'.$pago['id'].'|Anterior:'.($pago['archivo_abono']?:'Ninguno').'|Nuevo:'.$nuevo.'|Inscripcion:'.$r['id']);
  q('UPDATE pagos_inscripcion SET archivo_abono=? WHERE id=?',[$nuevo,$pago['id']]);
  $ultimo=one('SELECT id FROM pagos_inscripcion WHERE codigo_conjunto=? ORDER BY id DESC LIMIT 1',[claveCobro($r)]);
  foreach(integrantesGrupo($r) as $miembro){if(($pago['archivo_abono']&&$miembro['proof']===$pago['archivo_abono'])||(!$pago['archivo_abono']&&(int)$ultimo['id']===(int)$pago['id']))q('UPDATE registrations SET proof=? WHERE id=?',[$nuevo,$miembro['id']]);}
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($nuevo){$ruta=dirname(__DIR__).'/almacenamiento/archivos_subidos/'.$nuevo.'.php';if(is_file($ruta))unlink($ruta);}throw $e;}
 flash('Voucher reemplazado; se conservó el archivo anterior. El cambio no modifica el importe ni confirma una nueva verificación.');go('p=participant&t='.$r['token'].'#pagos-saldo');
}
function reenviarComprobante(): never {
 global $db;$u=auth();$r=registration(textValue('token',48));$nuevo=null;
 try{
  $db->beginTransaction();$r=bloquearCobro($r);$rechazado=one("SELECT * FROM pagos_inscripcion WHERE id=? AND codigo_conjunto=? AND estado_abono='Rechazado' FOR UPDATE",[(int)($_POST['pago_id']??0),claveCobro($r)]);
  if(!$rechazado||pagoAnulado($rechazado))throw new RuntimeException('El voucher seleccionado no está rechazado o corresponde a un pago anulado.');
  if(one("SELECT id FROM pagos_inscripcion WHERE codigo_conjunto=? AND estado_abono='En revisión'",[claveCobro($r)]))throw new RuntimeException('Ya existe otro voucher en revisión.');
  $res=resumenCobro($r);$importe=importeAbono();if($importe>$res['saldo'])throw new RuntimeException('El importe supera el saldo pendiente.');$codigo=codigoAbono(true);
  $nuevo=upload('nuevo_comprobante',true);if(!$nuevo)throw new RuntimeException('Adjunte el voucher nuevo.');
  $id=insertarAbono($r,$importe,$codigo,$nuevo,'QR/Transferencia',false);
  foreach(integrantesGrupo($r) as $persona)q("UPDATE registrations SET proof=?,reference=?,payment_method='QR/Transferencia',status=IF(monto_cobrado>0,'Pagado','En revisión'),review_note=NULL WHERE id=?",[$nuevo,$codigo,$persona['id']]);
  audit('Nuevo voucher enviado','Pago '.$id.' · inscripción '.$r['id']);$db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($nuevo){$ruta=dirname(__DIR__).'/almacenamiento/archivos_subidos/'.$nuevo.'.php';if(is_file($ruta))unlink($ruta);}throw $e;}
 flash('Nuevo voucher enviado para revisión; se conserva el rechazo anterior.');go('p=participant&t='.$r['token'].'#pagos-saldo');
}

function existeHistorialComprobantesAnterior(): bool {
 static $existe=null;
 if($existe===null)$existe=(bool)q('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',['historial_comprobantes'])->fetchColumn();
 return $existe;
}
function historialArchivosComprobante(int $pago): array {
 $filas=all('SELECT a.created_at,u.name nombre_operador,a.detail FROM audit a LEFT JOIN users u ON u.id=a.user_id WHERE a.action=? AND a.detail LIKE ? ORDER BY a.id DESC',['Voucher reemplazado','Pago:'.$pago.'|%']);$historial=[];
 foreach($filas as $f){$archivo=null;if(preg_match('/\|Anterior:([a-f0-9]{48}\.(?:jpg|png|webp|pdf))\|/',$f['detail'],$m))$archivo=$m[1];$historial[]=['fecha_cambio'=>$f['created_at'],'nombre_operador'=>$f['nombre_operador'],'archivo_anterior'=>$archivo];}
 // Compatibilidad con una instalación que hubiera usado la versión anterior.
 if(existeHistorialComprobantesAnterior())foreach(all('SELECT h.*,u.name nombre_operador FROM historial_comprobantes h LEFT JOIN users u ON u.id=h.usuario_cambio WHERE h.id_pago=? ORDER BY h.id DESC',[$pago]) as $f)$historial[]=$f;
 return $historial;
}
function archivoAnteriorAutorizado(string $archivo): bool {
 if(one('SELECT id FROM audit WHERE action=? AND detail LIKE ?',['Voucher reemplazado','%|Anterior:'.$archivo.'|%']))return true;
 return existeHistorialComprobantesAnterior()&&(bool)one('SELECT id FROM historial_comprobantes WHERE archivo_anterior=? OR archivo_nuevo=?',[$archivo,$archivo]);
}
