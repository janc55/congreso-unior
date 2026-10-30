<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
$usuario=auth(true);$seleccion=(int)($_GET['usuario_id']??$usuario['id']);$buscar=trim((string)($_GET['buscar']??''));
$evento=(int)($_GET['event_id']??0);$desde=trim((string)($_GET['desde']??''));$hasta=trim((string)($_GET['hasta']??''));
$where=["r.status<>'Anulado'","r.created_by IS NOT NULL"];$args=[];
if($seleccion){$where[]='r.created_by=?';$args[]=$seleccion;}
if($buscar!==''){$where[]="(CONCAT(r.first_name,' ',r.last_name) LIKE ? OR r.ci LIKE ?)";array_push($args,'%'.$buscar.'%','%'.$buscar.'%');}
if($evento){$where[]='r.event_id=?';$args[]=$evento;}
foreach(['desde'=>'>=','hasta'=>'<='] as $campo=>$op){$fecha=$$campo;if($fecha!==''){if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$fecha)){http_response_code(400);exit('Fecha no válida.');}$where[]='r.created_at'.$op.'?';$args[]=$fecha.($campo==='desde'?' 00:00:00':' 23:59:59');}}
$filas=all("SELECT COALESCE(NULLIF(TRIM(r.carrera),''),'Sin carrera') carrera, COUNT(*) total_personas FROM registrations r JOIN events e ON e.id=r.event_id JOIN users u ON u.id=r.created_by WHERE ".implode(' AND ',$where)." GROUP BY COALESCE(NULLIF(TRIM(r.carrera),''),'Sin carrera') ORDER BY total_personas DESC, carrera",$args);
$total=array_sum(array_column($filas,'total_personas'));
if(($_GET['exportar']??'')==='csv'){
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="reporte_carreras.csv"');$f=fopen('php://output','w');fwrite($f,"\xEF\xBB\xBF");fputcsv($f,['Carrera','Participantes'],';');foreach($filas as $fila){$nombre=(string)$fila['carrera'];if(preg_match('/^[\s]*[=+@-]/u',$nombre))$nombre="'".$nombre;fputcsv($f,[$nombre,$fila['total_personas']],';');}fputcsv($f,['Total',$total],';');fclose($f);exit;
}
$responsable=$seleccion?(one('SELECT name FROM users WHERE id=?',[$seleccion])['name']??'Usuario registrado'):'Todos los usuarios';
headerPage('Cantidad de participantes por carrera');
?>
<div class="card p-4 no-print"><form method="get"><input type="hidden" name="p" value="reporte_carreras"><div class="row"><div class="col-md-4"><?php selectField('usuario_id','Usuario que inscribió',[0=>'Todos los usuarios']+array_column(all('SELECT id,name FROM users ORDER BY name'),'name','id'),(string)$seleccion);?></div><div class="col-md-4"><?php field('buscar','Nombre / CI',$buscar,'text',false);?></div><div class="col-md-4"><?php selectField('event_id','Evento',[0=>'Todos']+array_column(all('SELECT id,name FROM events ORDER BY starts_at DESC'),'name','id'),(string)$evento);?></div><div class="col-md-3"><?php field('desde','Inscripción desde',$desde,'date',false);?></div><div class="col-md-3"><?php field('hasta','Inscripción hasta',$hasta,'date',false);?></div></div><button class="btn btn-primary">Generar reporte</button> <button class="btn btn-success" name="exportar" value="csv">Exportar Excel (CSV)</button> <button type="button" class="btn btn-outline-primary" onclick="print()">Imprimir / PDF</button></form></div>
<div class="card p-4"><h3>UNIOR · Participantes por carrera</h3><p><b>Inscrito por:</b> <?=e($responsable)?></p><p>Utiliza los mismos filtros de usuario, evento, fechas y búsqueda que «Mis inscripciones». Solo incluye registros presenciales activos.</p><p>Total: <b><?=e($total)?></b> participantes activos. Las inscripciones anuladas no se incluyen; los integrantes de grupos se cuentan individualmente.</p><div class="table-responsive"><table class="table table-striped table-bordered"><thead><tr><th>Carrera</th><th>Cantidad de participantes</th></tr></thead><tbody><?php foreach($filas as $fila):?><tr><td><?=e($fila['carrera'])?></td><td><?=e($fila['total_personas'])?></td></tr><?php endforeach?><?php if(!$filas):?><tr><td colspan="2">No hay participantes para los filtros seleccionados.</td></tr><?php endif?></tbody><tfoot><tr><th>Total</th><th><?=e($total)?></th></tr></tfoot></table></div></div>
<?php footerPage();
