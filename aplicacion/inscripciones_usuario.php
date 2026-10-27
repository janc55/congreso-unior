<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
$usuario=auth();$administrador=$usuario['role']==='admin';
$seleccion=$administrador?(int)($_GET['usuario_id']??$usuario['id']):(int)$usuario['id'];
$evento=(int)($_GET['event_id']??0);$desde=trim((string)($_GET['desde']??''));$hasta=trim((string)($_GET['hasta']??''));$buscar=trim((string)($_GET['buscar']??''));
$where=['r.created_by IS NOT NULL'];$params=[];
if($seleccion){$where[]='r.created_by=?';$params[]=$seleccion;}
if($evento){$where[]='r.event_id=?';$params[]=$evento;}
foreach(['desde'=>'>=','hasta'=>'<='] as $campo=>$op){$valor=$$campo;if($valor!==''){if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$valor)){http_response_code(400);exit('Fecha no válida.');}$where[]='r.created_at'.$op.'?';$params[]=$valor.($campo==='desde'?' 00:00:00':' 23:59:59');}}
if($buscar!==''){$where[]="(CONCAT(r.first_name,' ',r.last_name) LIKE ? OR r.ci LIKE ?)";array_push($params,'%'.$buscar.'%','%'.$buscar.'%');}
$base=' FROM registrations r JOIN events e ON e.id=r.event_id JOIN users u ON u.id=r.created_by WHERE '.implode(' AND ',$where);
$tot=one("SELECT COUNT(*) AS total_registros,COALESCE(SUM(CASE WHEN r.status<>'Anulado' THEN 1 ELSE 0 END),0) activas,COALESCE(SUM(CASE WHEN r.status<>'Anulado' THEN r.price ELSE 0 END),0) tarifa_total,COALESCE(SUM(CASE WHEN r.status<>'Anulado' THEN r.monto_cobrado ELSE 0 END),0) pagado_total,COALESCE(SUM(CASE WHEN r.status<>'Anulado' THEN GREATEST(0,r.price-r.monto_cobrado) ELSE 0 END),0) saldo_total".$base,$params);
$head=['Registro','Fecha','Participante','CI','Carrera','Evento','Categoría','Tarifa Bs','A cuenta Bs','Saldo Bs','Estado','Inscrito por (usuario)'];
$valores=fn($r)=>[$r['id'],$r['created_at'],nombreParticipante($r),$r['ci'],$r['carrera'],$r['evento_reporte'],$r['category'],$r['price'],$r['monto_cobrado'],max(0,$r['price']-$r['monto_cobrado']),$r['status'],$r['usuario_inscriptor']];
$sql='SELECT r.*,e.name evento_reporte,u.name usuario_inscriptor'.$base.' ORDER BY r.id DESC';
if(($_GET['exportar']??'')==='csv'){
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="inscripciones_por_usuario.csv"');$salida=fopen('php://output','w');fwrite($salida,"\xEF\xBB\xBF");fputcsv($salida,$head,';');$consulta=q($sql,$params);
 while($r=$consulta->fetch())fputcsv($salida,array_map(fn($v)=>preg_match('/^[\s]*[=+@-]/u',(string)$v)?"'".$v:$v,$valores($r)),';');fclose($salida);exit;
}
$pagina=max(1,(int)($_GET['pagina']??1));$filas=all($sql.' LIMIT 50 OFFSET '.(($pagina-1)*50),$params);$usuarios=$administrador?all('SELECT id,name FROM users ORDER BY name'):[];
$responsableFiltro=$seleccion?(one('SELECT name FROM users WHERE id=?',[$seleccion])['name']??'Usuario registrado'):'Todos los usuarios';
headerPage($administrador?'Reporte de quién realizó la inscripción':'Mis inscritos · Responsable de inscripción'); ?><style><?php readfile(__DIR__.'/../recursos/reporte_inscriptor.css');?></style>
<div class="card p-4 no-print"><form method="get"><input type="hidden" name="p" value="mis_inscripciones"><div class="row"><div class="col-md-4"><?php if($administrador)selectField('usuario_id','Usuario que inscribió',[0=>'Todos los usuarios']+array_column($usuarios,'name','id'),(string)$seleccion);else echo '<p><b>Usuario:</b> '.e($usuario['name']).'</p>';?></div><div class="col-md-4"><?php selectField('event_id','Evento',[0=>'Todos']+array_column(all('SELECT id,name FROM events ORDER BY starts_at DESC'),'name','id'),(string)$evento);?></div><div class="col-md-4"><?php field('buscar','Nombre / CI',$buscar,'text',false);?></div><div class="col-md-3"><?php field('desde','Desde',$desde,'date',false);?></div><div class="col-md-3"><?php field('hasta','Hasta',$hasta,'date',false);?></div></div><button class="btn btn-primary">Generar reporte</button> <button class="btn btn-success" name="exportar" value="csv">Exportar Excel (CSV)</button> <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="<?=url(http_build_query(array_merge($_GET,['p'=>'mis_inscripciones','imprimir'=>'1','pagina'=>1])))?>">Imprimir reporte</a></form></div>
<?php if(($_GET['imprimir']??'')==='1')$filas=all($sql,$params);?>
<div class="card p-4 reporte-inscriptor"><h3>UNIOR · Reporte de inscritos y responsable</h3><p class="responsable-reporte"><b>Inscrito por:</b> <?=e($responsableFiltro)?></p><p>Generado: <?=e(date('d/m/Y H:i'))?> · <?=e($tot['activas'])?> participantes activos · Tarifas: <?=money($tot['tarifa_total'])?> · A cuenta: <?=money($tot['pagado_total'])?> · Saldo pendiente: <?=money($tot['saldo_total'])?></p><p>La columna Inscrito por identifica al usuario que registró a cada participante. Cada integrante del grupo se cuenta individualmente. Los totales excluyen las inscripciones anuladas.</p><p class="ayuda-tabla-inscriptor no-print">En el celular, deslice la tabla hacia los lados para ver todas las columnas, incluida «Inscrito por (usuario)».</p><div class="table-responsive"><table class="table table-bordered table-sm tabla-inscriptor">
<colgroup><col class="col-registro"><col class="col-fecha"><col class="col-participante"><col class="col-ci"><col class="col-carrera"><col class="col-evento"><col class="col-categoria"><col class="col-importe"><col class="col-importe"><col class="col-importe"><col class="col-estado"><col class="col-usuario"><col class="col-acciones no-print"></colgroup>
<thead><tr>
<th scope="col" style="display:table-cell;padding:12px;border:1px solid #b66572;white-space:normal"><span>Registro</span></th>
<th scope="col" style="display:table-cell;padding:12px;border:1px solid #b66572;white-space:normal"><span>Fecha</span></th>
<th scope="col" style="display:table-cell;padding:12px;border:1px solid #b66572;white-space:normal"><span>Participante</span></th>
<th scope="col" style="display:table-cell;padding:12px;border:1px solid #b66572;white-space:normal"><span>CI</span></th>
<th scope="col" style="display:table-cell;padding:12px;border:1px solid #b66572;white-space:normal"><span>Carrera</span></th>
<th scope="col" style="display:table-cell;padding:12px;border:1px solid #b66572;white-space:normal"><span>Evento</span></th>
<th scope="col" style="display:table-cell;padding:12px;border:1px solid #b66572;white-space:normal"><span>Categoría</span></th>
<th scope="col" style="display:table-cell;padding:12px;border:1px solid #b66572;white-space:normal"><span>Tarifa Bs</span></th>
<th scope="col" style="display:table-cell;padding:12px;border:1px solid #b66572;white-space:normal"><span>A cuenta Bs</span></th>
<th scope="col" style="display:table-cell;padding:12px;border:1px solid #b66572;white-space:normal"><span>Saldo Bs</span></th>
<th scope="col" style="display:table-cell;padding:12px;border:1px solid #b66572;white-space:normal"><span>Estado</span></th>
<th scope="col" style="display:table-cell;padding:12px;border:1px solid #b66572;white-space:normal"><span>Inscrito por (usuario)</span></th>
<th scope="col" class="no-print" style="display:table-cell;padding:12px;border:1px solid #b66572;white-space:normal"><span>Acciones</span></th>
</tr></thead><tbody><?php foreach($filas as $r):?><tr><?php foreach($valores($r) as $indice=>$v)echo '<td'.($indice===11?' class="columna-inscriptor"':'').'>'.($indice===11?'<strong>':'').e($v).($indice===11?'</strong>':'').'</td>';?><td class="no-print"><?php recibosReporte($r);?></td></tr><?php endforeach?><?php if(!$filas):?><tr><td colspan="13">No hay inscripciones para estos filtros.</td></tr><?php endif?></tbody></table></div><div class="no-print"><?php if($pagina>1):?><a class="btn btn-outline-primary" href="<?=url(http_build_query(array_merge($_GET,['pagina'=>$pagina-1])))?>">Anterior</a><?php endif?> Página <?=e($pagina)?><?php if($pagina*50<$tot['total_registros']):?> <a class="btn btn-outline-primary" href="<?=url(http_build_query(array_merge($_GET,['pagina'=>$pagina+1])))?>">Siguiente</a><?php endif?></div></div>
<?php if(($_GET['imprimir']??'')==='1'):?><script>window.addEventListener('load',()=>window.print(),{once:true});</script><?php endif;footerPage();
