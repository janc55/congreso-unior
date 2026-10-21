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
 $codigo=codigoAbono(true);$res=resumenCobro($r);$tipo=$_POST['tipo_cobro']??'completo';
 if(!in_array($tipo,['completo','adelanto'],true))throw new RuntimeException('Seleccione pago completo o adelanto.');
 if($tipo==='adelanto'&&user()['role']!=='admin')throw new RuntimeException('Solo el administrador puede registrar adelantos.');
 $importe=$tipo==='adelanto'?importeAbono():$res['total'];
 if($tipo==='adelanto'&&$importe>=$res['total'])throw new RuntimeException('El adelanto debe ser menor al total de inscripción.');
 insertarAbono($r,$importe,$codigo,null,$r['payment_method']??'Efectivo',true);aplicarAbono($r,$importe);
}
function bloquearCobro(array $r): array{
 // Orden estable: se bloquean las personas antes que los abonos.
 integrantesGrupo($r,true);$r=registration($r['token']);
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
 global $db;$u=auth(true);$r=registration(textValue('token',48));$db->beginTransaction();$r=bloquearCobro($r);
 $pago=one("SELECT * FROM pagos_inscripcion WHERE codigo_conjunto=? AND estado_abono='En revisión' ORDER BY id LIMIT 1 FOR UPDATE",[claveCobro($r)]);
 if(!$pago){$db->rollBack();return false;}
 $estado=$_POST['status']??'';$nota=mb_substr(trim($_POST['review_note']??''),0,2000);
 if(!in_array($estado,['Pagado','Rechazado','Anulado'],true)||($estado!=='Pagado'&&$nota===''))throw new RuntimeException('Seleccione la decisión e indique el motivo del rechazo.');
 if($estado==='Anulado'&&resumenCobro($r)['cobrado']>0)throw new RuntimeException('No puede anular una inscripción con cobros confirmados.');
 if($estado==='Pagado')aplicarAbono($r,(int)round($pago['importe_abono']*100));
 else foreach(integrantesGrupo($r) as $persona)q("UPDATE registrations SET status=IF(monto_cobrado>0,'Pagado',?),review_note=? WHERE id=?",[$estado,$nota,$persona['id']]);
 q('UPDATE pagos_inscripcion SET estado_abono=?,nota_abono=?,fecha_verificacion=NOW(),usuario_verifica=? WHERE id=?',[$estado==='Pagado'?'Confirmado':'Rechazado',$nota,$u['id'],$pago['id']]);
 audit('Revisión de abono','Pago '.$pago['id'].' · '.$estado);$db->commit();flash('Revisión registrada. El saldo se actualizó con el importe verificado.');go('p=participant&t='.$r['token']);
}
function cobrarAbono(): never{
 global $db;$u=auth(true);$r=registration(textValue('token',48));$db->beginTransaction();$r=bloquearCobro($r);
 if(one("SELECT id FROM pagos_inscripcion WHERE codigo_conjunto=? AND estado_abono='En revisión'",[claveCobro($r)]))throw new RuntimeException('Primero debe revisarse el comprobante pendiente.');
 $importe=($_POST['completar_pago']??'')==='1'?resumenCobro($r)['saldo']:importeAbono();if($importe<=0)throw new RuntimeException('La inscripción no tiene saldo pendiente.');if($importe>resumenCobro($r)['saldo'])throw new RuntimeException('El cobro supera el saldo pendiente.');$codigo=codigoAbono();$metodo=$_POST['payment_method']??'';
 if(!in_array($metodo,['Efectivo','QR/Transferencia','Tarjeta'],true))throw new RuntimeException('Forma de pago inválida.');
 $id=insertarAbono($r,$importe,$codigo,null,$metodo,true);aplicarAbono($r,$importe);audit('Cobro de saldo','Pago '.$id.' · inscripción '.$r['id']);$db->commit();go('p=receipt&t='.$r['token'].'&pago='.$id.'&imprimir=1');
}
function panelAbonos(array $r,bool $panel=false): void{
 if(!$panel||publicPortal()||!user()||user()['role']!=='admin')return;
 $res=resumenCobro($r);$pendiente=one("SELECT * FROM pagos_inscripcion WHERE codigo_conjunto=? AND estado_abono='En revisión'",[claveCobro($r)]);
 ?> <section class="card p-4 no-print"><h3>Pagos y saldo <?=$r['codigo_grupo']?'del grupo':'de la inscripción'?></h3><p><b>Total: <?=money($res['total']/100)?> · Verificado: <?=money($res['cobrado']/100)?> · Debe: <?=money($res['saldo']/100)?></b></p><?php if($pendiente):?><div class="alert alert-warning">Abono por verificar: <?=money($pendiente['importe_abono'])?> · Transacción <?=e($pendiente['codigo_transaccion'])?></div><?php endif?><div class="table-responsive"><table class="table"><tr><th>Fecha</th><th>Abono</th><th>Transacción</th><th>Estado</th><th>Documento</th></tr><?php foreach(all('SELECT * FROM pagos_inscripcion WHERE codigo_conjunto=? ORDER BY id DESC',[claveCobro($r)]) as $p):?><tr><td><?=e($p['fecha_abono'])?></td><td><?=money($p['importe_abono'])?></td><td><?=e($p['codigo_transaccion'])?></td><td><?=e($p['estado_abono'])?></td><td><?php if($p['archivo_abono']):?><a href="<?=fileUrl($p['archivo_abono'],$r['token'])?>" target="_blank" rel="noopener">Comprobante</a> <?php endif?><?php if($p['estado_abono']==='Confirmado'):?><a href="<?=url('p=receipt&t='.$r['token'].'&pago='.$p['id'])?>" target="_blank" rel="noopener">Recibo</a><?php endif?></td></tr><?php endforeach?></table></div>
 <?php if($panel&&$res['saldo']>0&&!$pendiente&&$r['status']!=='Anulado'):?><h4>Cobrar adelanto o completar saldo</h4><form method="post"><?=csrf()?><input type="hidden" name="action" value="cobrar_abono"><input type="hidden" name="token" value="<?=e($r['token'])?>"><label>Importe recibido (Bs)</label><input class="form-control mb-3" name="importe_abono" value="<?=e($_POST['importe_abono']??'')?>" type="number" min="0.01" step="0.01" max="<?=$res['saldo']/100?>" required><?php selectField('payment_method','Forma de pago',['Efectivo'=>'Efectivo','QR/Transferencia'=>'QR / Transferencia','Tarjeta'=>'Tarjeta']);field('reference','Código único de transacción / operación de caja');?><button class="btn btn-primary">Registrar abono e imprimir recibo</button> <button class="btn btn-success" name="completar_pago" value="1" formnovalidate>Completar pago · <?=money($res['saldo']/100)?> e imprimir recibo</button></form><?php endif?></section><?php
}
