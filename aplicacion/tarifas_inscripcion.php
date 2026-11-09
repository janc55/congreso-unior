<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
function puedeElegirTarifa(): bool {return !publicPortal()&&user()&&user()['role']==='admin';}
function tarifaParaInscripcion(int $evento,string $categoria,array $datos): array {
 $valor=$datos['tarifa_id_admin']??'';
 if(!is_scalar($valor))throw new RuntimeException('Seleccione una tarifa válida.');
 $valor=trim((string)$valor);
 if($valor!==''&&$valor!=='0'){
  if(!puedeElegirTarifa())throw new RuntimeException('Solo el administrador puede seleccionar tarifas fuera de vigencia.');
  $id=filter_var($valor,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if(!$id)throw new RuntimeException('Seleccione una tarifa válida.');
  $t=one('SELECT * FROM tariffs WHERE id=? AND event_id=? AND category=?',[$id,$evento,$categoria]);
  if(!$t)throw new RuntimeException('La tarifa seleccionada no corresponde al evento y tipo de participante.');
  audit('Tarifa elegida por administrador','Evento '.$evento.' · Categoría '.$categoria.' · Tarifa '.$t['id'].' · Bs '.$t['price'].' · '.$t['starts_on'].' a '.$t['ends_on']);
  return $t;
 }
 $t=tariff($evento,$categoria);
 if(!$t)throw new RuntimeException('No hay tarifa vigente para '.$categoria.'.');
 return $t;
}
function selectorTarifaAdmin(int $evento,string $categoria,string $seleccion='',bool $plantilla=false): void {
 if(!puedeElegirTarifa())return;
 $tarifas=all('SELECT * FROM tariffs WHERE event_id=? ORDER BY starts_on DESC,id DESC',[$evento]);
 $vigente=tariff($evento,$categoria);$preferida=$vigente['id']??null;
 if(!$preferida)foreach($tarifas as $t)if($t['category']===$categoria){$preferida=$t['id'];break;}
 $elegida=$seleccion!==''?$seleccion:(string)$preferida;
 ?><div class="form-group"><label>Tarifa a aplicar · administrador</label><select class="form-control" data-tarifa-admin <?=$plantilla?'data-campo="tarifa_id_admin"':'name="tarifa_id_admin"'?> required><?php foreach($tarifas as $t):$actual=$t['starts_on']<=date('Y-m-d')&&$t['ends_on']>=date('Y-m-d');?><option value="<?=e($t['id'])?>" data-categoria="<?=e($t['category'])?>" data-importe="<?=e($t['price'])?>" data-vigente="<?=$actual?'1':'0'?>" <?=$t['category']!==$categoria?'disabled hidden':''?> <?=(string)$t['id']===$elegida?'selected':''?>><?=e($t['category'])?> · <?=money($t['price'])?> · <?=e($t['starts_on'])?> al <?=e($t['ends_on'])?><?=$actual?' · Vigente':''?></option><?php endforeach?></select><small>Puede elegir una tarifa registrada aunque esté fuera de sus fechas de vigencia.</small></div><?php
}

// El importe se expresa en bolivianos y se aplica por persona, después de la promoción grupal.
function descuentoPresencial(array $datos,int $base): array {
 $valor=$datos['descuento_administrador']??'0';$motivo=$datos['motivo_descuento']??'';
 if(!is_scalar($valor)||!is_scalar($motivo))throw new RuntimeException('Descuento no válido.');
 $valor=trim((string)$valor);if($valor==='')$valor='0';$motivo=trim((string)$motivo);
 if(!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',$valor))throw new RuntimeException('El descuento debe ser un importe positivo con hasta dos decimales.');
 $centavos=(int)round((float)$valor*100);
 if(($centavos>0||$motivo!=='')&&!puedeElegirTarifa())throw new RuntimeException('Solo el administrador puede autorizar descuentos presenciales.');
 if($centavos>0&&($_POST['payment_method']??'')==='Descuento por planilla')throw new RuntimeException('No combine descuento monetario con descuento por planilla.');
 if($centavos>$base)throw new RuntimeException('El descuento no puede superar el importe del participante.');
 if($centavos>0&&(mb_strlen($motivo)<3||mb_strlen($motivo)>500))throw new RuntimeException('Indique el motivo del descuento (entre 3 y 500 caracteres).');
 return ['final'=>$base-$centavos,'importe'=>$centavos/100,'motivo'=>$centavos>0?$motivo:null];
}
function guardarDescuentoPresencial(string $token,array $d): void {
 if($d['importe']<=0)return;
 q('UPDATE registrations SET descuento_administrador=?,motivo_descuento=? WHERE token=?',[$d['importe'],$d['motivo'],$token]);
 audit('Descuento presencial autorizado','Registro '.registration($token)['id'].' · Bs '.$d['importe'].' · '.$d['motivo']);
}
function camposDescuentoPresencial(bool $plantilla=false): void {
 if(!puedeElegirTarifa())return;
 ?><div class="border rounded p-3 mb-3"><b>Descuento autorizado · administrador</b><small class="d-block">Importe por persona, adicional a la promoción grupal. El motivo es obligatorio si aplica descuento.</small><label>Descuento (Bs)</label><input class="form-control" type="number" min="0" step="0.01" data-descuento-admin <?=$plantilla?'data-campo="descuento_administrador"':'name="descuento_administrador"'?> value="<?=e($plantilla?'0':($_POST['descuento_administrador']??'0'))?>"><label>Motivo del descuento</label><input class="form-control" maxlength="500" data-motivo-descuento <?=$plantilla?'data-campo="motivo_descuento"':'name="motivo_descuento"'?> value="<?=e($plantilla?'':($_POST['motivo_descuento']??''))?>"></div><?php
}

function usuarioInscripcionPresencial(array $datos): ?int {
 $actual=publicPortal()?null:user();$valor=$datos['usuario_inscriptor']??'';
 if(!is_scalar($valor))throw new RuntimeException('Seleccione un usuario inscriptor válido.');
 $valor=trim((string)$valor);
 if($valor==='')return $actual?(int)$actual['id']:null;
 if(!$actual||$actual['role']!=='admin')throw new RuntimeException('Solo el administrador puede asignar la inscripción a otro usuario.');
 $id=filter_var($valor,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if(!$id||!one("SELECT id FROM users WHERE id=? AND active=1 AND role IN ('admin','vendedor')",[$id]))throw new RuntimeException('El usuario inscriptor debe ser un administrador o vendedor activo.');
 return (int)$id;
}
function selectorUsuarioInscripcion(): void {
 if(!puedeElegirTarifa())return;
 $seleccion=(string)($_POST['usuario_inscriptor']??user()['id']);
 ?><div class="form-group"><label for="usuario-inscriptor">Inscrito por (usuario)</label><select id="usuario-inscriptor" name="usuario_inscriptor" class="form-control" required><?php foreach(all("SELECT id,name,username,role FROM users WHERE active=1 AND role IN ('admin','vendedor') ORDER BY name,id") as $u):?><option value="<?=e($u['id'])?>" <?=(string)$u['id']===$seleccion?'selected':''?>><?=e($u['name'].' · '.$u['username'].' · '.($u['role']==='admin'?'Administrador':'Vendedor'))?></option><?php endforeach?></select><small>La inscripción figurará en los reportes del usuario elegido. En grupos se aplica a los cinco integrantes. El cobro y el historial conservan al usuario que realizó la operación.</small></div><?php
}
