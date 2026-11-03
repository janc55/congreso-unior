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
