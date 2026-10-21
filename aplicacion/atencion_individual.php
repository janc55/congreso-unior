<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
function tablaAtencionGrupo(array $seleccionado): void {
 if(!$seleccionado['codigo_grupo'])return;
 auth();$personas=integrantesGrupo($seleccionado);
 ?>
<section class="card p-4 atencion-grupo"><span class="rotulo-institucional">ATENCIÓN INDIVIDUAL</span><h3>Integrantes del grupo</h3><p>Selecciona a la persona que está presente. La asistencia y los materiales se registran solamente en su ficha.</p><div class="table-responsive"><table class="table table-sm tabla-personas"><thead><tr><th>Participante</th><th>Asistencia</th><th>Materiales de esta persona</th><th>Acciones individuales</th></tr></thead><tbody>
<?php foreach($personas as $persona):$actual=$persona['id']===$seleccionado['id']; ?>
<tr class="<?=$actual?'persona-seleccionada':''?>"><td><strong><?=e(nombreParticipante($persona))?></strong><br><small>CI <?=e($persona['ci'])?> · Registro #<?=e($persona['id'])?></small><?php if($actual):?><br><span class="badge badge-primary">Persona seleccionada</span><?php endif?></td><td><span class="badge <?=$persona['attended_at']?'badge-success':'badge-light'?>"><?=$persona['attended_at']?'Registrada':'Pendiente'?></span><?php if($persona['attended_at']):?><br><small><?=e($persona['attended_at'])?></small><?php endif?></td><td><?php
 $materiales=all('SELECT m.name,m.allowance,(SELECT COALESCE(SUM(d.quantity),0) FROM deliveries d WHERE d.registration_id=? AND d.material_id=m.id) picked FROM materials m WHERE m.event_id=? AND m.active=1',[$persona['id'],$persona['event_id']]);
 if(!$materiales)echo '<small>Sin materiales asignados</small>';
 foreach($materiales as $material)echo '<div class="material-persona '.($material['picked']>=$material['allowance']?'material-completo':'').'">'.e($material['name']).': <b>'.e($material['picked']).' / '.e($material['allowance']).'</b></div>';
?></td><td><div class="acciones-persona"><a class="btn btn-sm <?=$actual?'btn-dark':'btn-primary'?>" href="<?=url('p=participant&t='.$persona['token'])?>#atencion-personal">Atender a esta persona</a><button type="button" class="btn btn-sm btn-outline-primary" data-open-dialog="qr-persona-<?=e($persona['id'])?>">Ver QR individual</button><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?=url('p=receipt&t='.$persona['token'].'&individual=1&imprimir=1')?>">Imprimir recibo individual</a></div><dialog id="qr-persona-<?=e($persona['id'])?>" class="unior-dialog"><button type="button" class="dialog-close" data-close-dialog aria-label="Cerrar">×</button><h3>QR individual</h3><p><?=e(nombreParticipante($persona))?> · CI <?=e($persona['ci'])?></p><div class="qr" data-qr="<?=url('p=participant&t='.$persona['token'])?>"></div><p class="small mt-3">Este QR abre únicamente la ficha de esta persona para registrar su asistencia y entregar sus materiales.</p></dialog></td></tr>
<?php endforeach?></tbody></table></div></section>
<?php }
