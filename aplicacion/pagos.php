<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
function claveCobro(array $r): string{return $r['codigo_grupo']?'grupo-'.$r['codigo_grupo']:'persona-'.$r['id'];}
function resumenCobro(array $r): array{
 $personas=integrantesGrupo($r);$total=0;$cobrado=0;
 foreach($personas as $p){$total+=(int)round($p['price']*100);$cobrado+=(int)round(($p['monto_cobrado']??0)*100);}
 return ['total'=>$total,'cobrado'=>$cobrado,'saldo'=>max(0,$total-$cobrado)];
}
function importeAbono(): int{
 $valor=trim((string)($_POST['importe_abono']??''));
 if(!preg_match('/^\d{1,9}([.,]\d{1,2})?$/D',$valor))throw new RuntimeException('Ingrese un importe válido con hasta dos decimales.');
 $centavos=(int)round((float)str_replace(',','.',$valor)*100);
 if($centavos<=0)throw new RuntimeException('El adelanto debe ser mayor a cero.');return $centavos;
}
function codigoAbono(bool $obligatorio=true): ?string{
 $codigo=mb_strtoupper(trim((string)($_POST['reference']??'')));
 if(($obligatorio&&$codigo==='')||mb_strlen($codigo)>120)throw new RuntimeException('El código de transacción es obligatorio (máximo 120 caracteres).');
 if($codigo!==''&&one('SELECT id FROM pagos_inscripcion WHERE codigo_transaccion=?',[$codigo]))throw new RuntimeException('El código de transacción ya está registrado. Revise el dato; no puede reutilizarlo.');
 return $codigo?:null;
}
function insertarAbono(array $r,int $centavos,?string $codigo,?string $archivo,string $metodo,bool $confirmado): int{
 try{q('INSERT INTO pagos_inscripcion(codigo_conjunto,id_titular,importe_abono,codigo_transaccion,archivo_abono,estado_abono,metodo_abono,fecha_verificacion,usuario_verifica) VALUES(?,?,?,?,?,?,?,?,?)',[claveCobro($r),$r['id'],number_format($centavos/100,2,'.',''),$codigo,$archivo,$confirmado?'Confirmado':'En revisión',$metodo,$confirmado?date('Y-m-d H:i:s'):null,$confirmado?user()['id']:null]);}
 catch(PDOException $e){if(($e->errorInfo[1]??0)===1062)throw new RuntimeException('El código de transacción ya está registrado. No puede reutilizarlo.');throw $e;}
 global $db;return (int)$db->lastInsertId();
}
function aplicarAbono(array $r,int $centavos): void{
 // Reparto proporcional en centavos. El último saldo absorbe el redondeo.
 $personas=integrantesGrupo($r,true);$restante=$centavos;$deuda=0;
 foreach($personas as $p)$deuda+=(int)round(($p['price']-$p['monto_cobrado'])*100);
 if($centavos>$deuda)throw new RuntimeException('El abono supera el saldo pendiente.');
 foreach($personas as $p){$saldo=(int)round(($p['price']-$p['monto_cobrado'])*100);$parte=$deuda>0?min($saldo,$restante,(int)round($restante*$saldo/$deuda)):0;$restante-=$parte;$deuda-=$saldo;
  q("UPDATE registrations SET monto_cobrado=monto_cobrado+?,status='Pagado',paid_at=COALESCE(paid_at,NOW()),reviewed_by=? WHERE id=?",[number_format($parte/100,2,'.',''),user()['id'],$p['id']]);
 }
 if($restante!==0)throw new RuntimeException('No se pudo distribuir el abono.');
}
function registrarCobroInicial(array $r): void{
 $metodo=$r['payment_method']??'Efectivo';$codigo=codigoAbono($metodo!=='Efectivo');$res=resumenCobro($r);$tipo=$_POST['tipo_cobro']??'completo';
 if(!in_array($tipo,['completo','adelanto'],true))throw new RuntimeException('Seleccione pago completo o adelanto.');
 $importe=$tipo==='adelanto'?importeAbono():$res['total'];
 if($tipo==='adelanto'&&$importe>=$res['total'])throw new RuntimeException('El adelanto debe ser menor al total de inscripción.');
 $archivo=comprobantePresencial($metodo);insertarAbono($r,$importe,$codigo,$archivo,$metodo,true);guardarComprobantePresencial($r,$codigo,$archivo);aplicarAbono($r,$importe);
}
function bloquearCobro(array $r): array{
 // Orden estable: se bloquean las personas antes que los abonos.
 foreach(integrantesGrupo($r,true) as $persona)if($persona['status']==='Anulado')throw new RuntimeException('La inscripción o su grupo están anulados. Solo el administrador puede reactivarlos.');$r=registration($r['token']);
 if($r['status']==='Anulado')throw new RuntimeException('La inscripción está anulada.');return $r;
}
function enviarAbono(): never{
 global $db;$r=registration(textValue('token',48));$db->beginTransaction();$r=bloquearCobro($r);$res=resumenCobro($r);
 if(one("SELECT id FROM pagos_inscripcion WHERE codigo_conjunto=? AND estado_abono='En revisión'",[claveCobro($r)]))throw new RuntimeException('Ya existe un abono pendiente de revisión.');
 $tipo=$_POST['tipo_pago']??'adelanto';
 if(!in_array($tipo,['completo','adelanto'],true))throw new RuntimeException('Seleccione si desea pagar completo o un adelanto.');
 $importe=$tipo==='completo'?$res['saldo']:importeAbono();
 if($importe<=0)throw new RuntimeException('La inscripción ya no tiene saldo pendiente.');
 if($tipo==='adelanto'&&isset($_POST['tipo_pago'])&&$importe>=$res['saldo'])throw new RuntimeException('El adelanto debe ser menor al saldo. Para cancelar todo seleccione Pago completo.');
 if($importe>$res['saldo'])throw new RuntimeException('El adelanto no puede superar el saldo pendiente.');$codigo=codigoAbono();
 $archivo=upload('proof',true);if(!$archivo)throw new RuntimeException('Adjunte su comprobante de pago.');
 insertarAbono($r,$importe,$codigo,$archivo,'QR/Transferencia',false);
 foreach(integrantesGrupo($r) as $p)q("UPDATE registrations SET proof=?,reference=?,payment_method='QR/Transferencia',status=IF(monto_cobrado>0,'Pagado','En revisión'),review_note=NULL WHERE id=?",[$archivo,$codigo,$p['id']]);
 audit('Adelanto enviado','Inscripción '.$r['id'].' · '.number_format($importe/100,2));$db->commit();flash('Abono enviado para verificación.');go('p=receipt&t='.$r['token']);
}
function revisarAbono(): bool{
 global $db;$u=auth();$r=registration(textValue('token',48));permisoVerificarInscripcion($r,$u);$db->beginTransaction();$r=bloquearCobro($r);
 $pago=one("SELECT * FROM pagos_inscripcion WHERE codigo_conjunto=? AND estado_abono='En revisión' ORDER BY id LIMIT 1 FOR UPDATE",[claveCobro($r)]);
 if(!$pago){$db->rollBack();return false;}
 $estado=$_POST['status']??'';$nota=mb_substr(trim($_POST['review_note']??''),0,2000);
 if(!in_array($estado,['Pagado','Rechazado'],true)||($estado!=='Pagado'&&$nota===''))throw new RuntimeException('Seleccione la decisión e indique el motivo del rechazo.');
 if($estado==='Anulado'&&resumenCobro($r)['cobrado']>0)throw new RuntimeException('No puede anular una inscripción con cobros confirmados.');
 if($estado==='Pagado')aplicarAbono($r,(int)round($pago['importe_abono']*100));
 else foreach(integrantesGrupo($r) as $persona)q("UPDATE registrations SET status=IF(monto_cobrado>0,'Pagado',?),review_note=?,reviewed_by=? WHERE id=?",[$estado,$nota,$u['id'],$persona['id']]);
 q('UPDATE pagos_inscripcion SET estado_abono=?,nota_abono=?,fecha_verificacion=NOW(),usuario_verifica=? WHERE id=?',[$estado==='Pagado'?'Confirmado':'Rechazado',$nota,$u['id'],$pago['id']]);
 registrarVerificacion($r,$u,$estado,$nota,(int)$pago['id']);
 audit('Revisión de abono','Pago '.$pago['id'].' · '.$estado);$db->commit();flash('Revisión registrada. El saldo se actualizó con el importe verificado.');go('p=participant&t='.$r['token']);
}
function cobrarAbono(): never{
 global $db;$u=auth();$r=registration(textValue('token',48));$db->beginTransaction();$r=bloquearCobro($r);
 if(one("SELECT id FROM pagos_inscripcion WHERE codigo_conjunto=? AND estado_abono='En revisión'",[claveCobro($r)]))throw new RuntimeException('Primero debe revisarse el comprobante pendiente.');
 $importe=($_POST['completar_pago']??'')==='1'?resumenCobro($r)['saldo']:importeAbono();if($importe<=0)throw new RuntimeException('La inscripción no tiene saldo pendiente.');if($importe>resumenCobro($r)['saldo'])throw new RuntimeException('El cobro supera el saldo pendiente.');$metodo=$_POST['payment_method']??'';$codigo=codigoAbono($metodo!=='Efectivo');
 if(!in_array($metodo,['Efectivo','QR/Transferencia','Tarjeta'],true))throw new RuntimeException('Forma de pago inválida.');
 $archivo=comprobantePresencial($metodo);$id=insertarAbono($r,$importe,$codigo,$archivo,$metodo,true);guardarComprobantePresencial($r,$codigo,$archivo);aplicarAbono($r,$importe);audit('Cobro de saldo','Pago '.$id.' · inscripción '.$r['id']);$db->commit();go('p=receipt&t='.$r['token'].'&pago='.$id.'&imprimir=1');
}
function panelAbonos(array $r,bool $panel=false): void{
 if(!$panel||publicPortal()||!user())return;
 $res=resumenCobro($r);$pendiente=one("SELECT * FROM pagos_inscripcion WHERE codigo_conjunto=? AND estado_abono='En revisión'",[claveCobro($r)]);
 ?> <section id="pagos-saldo" class="card p-4 no-print"><h3>Pagos y saldo <?=$r['codigo_grupo']?'del grupo':'de la inscripción'?></h3><p><b>Total: <?=money($res['total']/100)?> · A cuenta: <?=money($res['cobrado']/100)?> · Saldo pendiente: <?=money($res['saldo']/100)?></b></p><?php if($pendiente):?><div class="alert alert-warning">Abono por verificar: <?=money($pendiente['importe_abono'])?> · Transacción <?=e($pendiente['codigo_transaccion'])?>. Antes de registrar otro pago, debe revisarse este comprobante. <?php if(user()['role']==='admin'||$r['created_by']===null):?><a href="#revisar-pago">Revisar comprobante</a><?php else:?>Solicite al administrador que revise el comprobante.<?php endif?></div><?php endif?><div class="table-responsive"><table class="table"><tr><th>Fecha</th><th>Abono</th><th>Transacción</th><th>Estado</th><th>Registrado / verificado por</th><th>Documento</th></tr><?php foreach(all('SELECT pa.*,u.name responsable_cobro FROM pagos_inscripcion pa LEFT JOIN users u ON u.id=pa.usuario_verifica WHERE pa.codigo_conjunto=? ORDER BY pa.id DESC',[claveCobro($r)]) as $p):?><tr><td><?=e($p['fecha_abono'])?></td><td><?=money($p['importe_abono'])?></td><td><?=e($p['codigo_transaccion'])?></td><td><?=e($p['estado_abono'])?></td><td><?=e($p['responsable_cobro']??'Pendiente de verificación')?></td><td><?php if($p['archivo_abono']):?><a href="<?=fileUrl($p['archivo_abono'],$r['token'])?>" target="_blank" rel="noopener">Comprobante</a> <?php endif?><?php if($p['estado_abono']==='Confirmado'):?><a href="<?=url('p=receipt&t='.$r['token'].'&pago='.$p['id'])?>" target="_blank" rel="noopener">Recibo</a><?php endif?><?php opcionesComprobante($r,$p,$res);?></td></tr><?php endforeach?></table></div>
 <?php if($r['status']==='Anulado'):?><div class="alert alert-warning">La inscripción está anulada. Solo el administrador puede reactivarla antes de registrar pagos.</div><?php elseif($res['saldo']===0):?><div class="alert alert-success">La inscripción no tiene saldo pendiente.</div><?php endif?><?php if($panel&&$res['saldo']>0&&!$pendiente&&$r['status']!=='Anulado'):?><h4>Cobrar adelanto o completar saldo</h4><form method="post" enctype="multipart/form-data" data-cobro-saldo><?=csrf()?><input type="hidden" name="action" value="cobrar_abono"><input type="hidden" name="token" value="<?=e($r['token'])?>"><label>Importe recibido (Bs)</label><input class="form-control mb-3" name="importe_abono" value="<?=e($_POST['importe_abono']??'')?>" type="number" min="0.01" step="0.01" max="<?=$res['saldo']/100?>" required><?php selectField('payment_method','Forma de pago',['Efectivo'=>'Efectivo','QR/Transferencia'=>'QR / Transferencia','Tarjeta'=>'Tarjeta']);field('reference','Código de transacción (opcional en efectivo)','','text',false);?><label>Comprobante (opcional en efectivo)</label><input class="form-control mb-3" name="comprobante_presencial" type="file" accept="image/jpeg,image/png,image/webp,application/pdf"><button class="btn btn-primary">Registrar abono e imprimir recibo</button> <button class="btn btn-success" name="completar_pago" value="1" formnovalidate>Completar pago · <?=money($res['saldo']/100)?> e imprimir recibo</button></form><?php endif?></section><?php
}

function comprobantePresencial(string $metodo): ?string {
 $archivo=upload('comprobante_presencial',true);
 if($metodo!=='Efectivo'&&!$archivo)throw new RuntimeException('Adjunte el comprobante del pago por QR, transferencia o tarjeta. En efectivo es opcional.');
 return $archivo;
}
function guardarComprobantePresencial(array $r,?string $codigo,?string $archivo): void {
 foreach(integrantesGrupo($r) as $persona)q('UPDATE registrations SET reference=?,proof=COALESCE(?,proof) WHERE id=?',[$codigo,$archivo,$persona['id']]);
}
