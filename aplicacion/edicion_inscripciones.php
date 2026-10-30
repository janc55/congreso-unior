<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
function guardarEdicionInscripcion(): never {
 global $db;
 auth(true);
 $id=(int)($_POST['inscripcion_id']??0);
 $anterior=one('SELECT * FROM registrations WHERE id=?',[$id]);
 if(!$anterior)throw new RuntimeException('Inscripción no encontrada.');
 $datos=[];
 foreach(['first_name'=>120,'last_name'=>150,'ci'=>40,'email'=>180] as $campo=>$max){$valor=trim((string)($_POST[$campo]??''));if($valor===''||mb_strlen($valor)>$max)throw new RuntimeException('Complete los datos sin superar el tamaño permitido.');$datos[$campo]=$valor;}
 $datos['ci']=mb_strtoupper($datos['ci']);
 if(!filter_var($datos['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Ingrese un correo electrónico válido.');
 $datos['tratamiento']=trim((string)($_POST['tratamiento']??''));
 if(!array_key_exists($datos['tratamiento'],tratamientos()))throw new RuntimeException('Seleccione un prefijo válido.');
 $datos['carrera']=validarCarrera($_POST);
 $db->beginTransaction();
 one('SELECT id FROM events WHERE id=? FOR UPDATE',[$anterior['event_id']]);
 $anterior=one('SELECT * FROM registrations WHERE id=? FOR UPDATE',[$id]);
 if(!$anterior)throw new RuntimeException('Inscripción no encontrada.');
 if(one('SELECT id FROM registrations WHERE event_id=? AND ci=? AND id<>?',[$anterior['event_id'],$datos['ci'],$id]))throw new RuntimeException('Este CI ya está inscrito en el mismo evento. Corrija el documento.');
 q('UPDATE registrations SET tratamiento=?,first_name=?,last_name=?,ci=?,carrera=?,email=? WHERE id=?',[$datos['tratamiento'],$datos['first_name'],$datos['last_name'],$datos['ci'],$datos['carrera'],$datos['email'],$id]);
 $antes=[];foreach($datos as $campo=>$valor)$antes[$campo]=$anterior[$campo]??'';
 audit('Inscripción editada','Registro '.$id.' · '.json_encode(['antes'=>$antes,'despues'=>$datos],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
 $db->commit();flash('Datos del participante actualizados.');go('p=editar_inscripciones&id='.$id);
}
function mostrarEdicionInscripciones(): void {
 auth(true);
 $id=(int)($_GET['id']??0);$buscar=trim((string)($_GET['buscar']??''));$evento=(int)($_GET['event_id']??0);
 $r=$id?one('SELECT r.*,e.name evento_edicion FROM registrations r JOIN events e ON e.id=r.event_id WHERE r.id=?',[$id]):null;
 if($id&&!$r){http_response_code(404);headerPage('Inscripción no encontrada');footerPage();return;}
 headerPage('Editar inscripciones');
 if($r){
  if(!empty($GLOBALS['edicion_fallida']))foreach(['tratamiento','first_name','last_name','ci','carrera','email'] as $campo)$r[$campo]=is_scalar($_POST[$campo]??null)?(string)$_POST[$campo]:$r[$campo];
 ?>
 <div class="card p-4"><h3>Participante #<?=e($r['id'])?> · <?=e($r['evento_edicion'])?></h3><p>Corrija los datos de esta persona. Los pagos, el grupo, la asistencia y los códigos QR se conservan.</p><form method="post"><?=csrf()?><input type="hidden" name="action" value="editar_inscripcion"><input type="hidden" name="inscripcion_id" value="<?=e($r['id'])?>"><div class="row"><div class="col-md-2"><?php selectField('tratamiento','Prefijo (opcional)',tratamientos(),$r['tratamiento']);?></div><div class="col-md-5"><?php field('first_name','Nombres',$r['first_name']);?></div><div class="col-md-5"><?php field('last_name','Apellidos',$r['last_name']);?></div><div class="col-md-4"><?php field('ci','CI / documento',$r['ci']);?></div><div class="col-md-4"><?php selectField('carrera','Carrera',carrerasInscripcion(),$r['carrera']);?></div><div class="col-md-4"><?php field('email','Correo electrónico',$r['email'],'email');?></div></div><button class="btn btn-primary">Guardar cambios</button> <a class="btn btn-outline-primary" href="<?=url('p=participant&t='.$r['token'])?>">Ver ficha</a> <a class="btn btn-outline-secondary" href="<?=url('p=editar_inscripciones')?>">Buscar otra inscripción</a></form></div>
 <?php }
 $cond=['1=1'];$args=[];
 if($evento){$cond[]='r.event_id=?';$args[]=$evento;}
 if($buscar!==''){$cond[]="(CONCAT(r.first_name,' ',r.last_name) LIKE ? OR r.ci LIKE ? OR CAST(r.id AS CHAR)=?)";array_push($args,'%'.$buscar.'%','%'.$buscar.'%',$buscar);}
 $filas=all('SELECT r.*,e.name evento_edicion FROM registrations r JOIN events e ON e.id=r.event_id WHERE '.implode(' AND ',$cond).' ORDER BY r.id DESC LIMIT 100',$args);
 ?>
 <div class="card p-4"><form method="get"><input type="hidden" name="p" value="editar_inscripciones"><div class="row"><div class="col-md-6"><?php field('buscar','Nombre, CI o número de inscripción',$buscar,'text',false);?></div><div class="col-md-6"><?php selectField('event_id','Evento',[0=>'Todos']+array_column(all('SELECT id,name FROM events ORDER BY starts_at DESC'),'name','id'),(string)$evento);?></div></div><button class="btn btn-primary">Buscar inscripción</button></form></div>
 <div class="card p-3"><p>Últimos 100 resultados. Busque por nombre, CI o registro para localizar al participante.</p><div class="table-responsive"><table class="table table-striped table-bordered"><thead><tr><th>Registro</th><th>Participante</th><th>CI</th><th>Carrera</th><th>Correo</th><th>Evento</th><th>Acción</th></tr></thead><tbody><?php foreach($filas as $fila):?><tr><td><?=e($fila['id'])?></td><td><?=e(nombreParticipante($fila))?></td><td><?=e($fila['ci'])?></td><td><?=e($fila['carrera'])?></td><td><?=e($fila['email'])?></td><td><?=e($fila['evento_edicion'])?></td><td><a class="btn btn-sm btn-primary" href="<?=url('p=editar_inscripciones&id='.$fila['id'])?>">Editar inscripción</a></td></tr><?php endforeach?><?php if(!$filas):?><tr><td colspan="7">No se encontraron inscripciones.</td></tr><?php endif?></tbody></table></div></div>
 <?php footerPage();
}
