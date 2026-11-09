<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
auth(true);
$f=[];foreach(['event_id','carrera','category','tratamiento','estado','modalidad','buscar'] as $k){if(isset($_GET[$k])&&!is_scalar($_GET[$k])){http_response_code(400);exit('Filtro inválido.');}$f[$k]=trim((string)($_GET[$k]??''));}
$where=['1=1'];$args=[];
foreach(['event_id','carrera','category','tratamiento'] as $k)if($f[$k]!==''){$where[]='r.'.$k.'=?';$args[]=$f[$k];}
$estados=['Pendiente','En revisión','Pagado','Rechazado','Anulado'];
if($f['estado']==='')$where[]="r.status<>'Anulado'";elseif(in_array($f['estado'],$estados,true)){$where[]='r.status=?';$args[]=$f['estado'];}elseif($f['estado']!=='todos'){http_response_code(400);exit('Estado inválido.');}
if($f['modalidad']==='grupo')$where[]="COALESCE(r.codigo_grupo,'')<>''";
elseif($f['modalidad']==='linea')$where[]='r.created_by IS NULL';
elseif($f['modalidad']==='presencial')$where[]='r.created_by IS NOT NULL';
elseif($f['modalidad']==='planilla')$where[]="r.payment_method='Descuento por planilla'";
elseif($f['modalidad']!==''){http_response_code(400);exit('Modalidad inválida.');}
if($f['buscar']!==''){$where[]='(r.first_name LIKE ? OR r.last_name LIKE ? OR r.ci LIKE ? OR r.phone LIKE ?)';for($i=0;$i<4;$i++)$args[]='%'.$f['buscar'].'%';}
$filas=all('SELECT r.*,e.name evento_lista FROM registrations r JOIN events e ON e.id=r.event_id WHERE '.implode(' AND ',$where).' ORDER BY r.carrera,r.last_name,r.first_name,r.id',$args);
function whatsappListado(string $celular): string {
 $n=preg_replace('/\D/','',$celular);if(str_starts_with($n,'00'))$n=substr($n,2);
 if(strlen($n)===8)$n='591'.$n;
 return preg_match('/^[1-9][0-9]{7,14}$/D',$n)?'https://wa.me/'.$n:'';
}
$encabezados=['Prefijo','Nombre completo','CI','Celular','Carrera','Tipo de participante','Evento','Modalidad','Grupo','Estado','Descuento Bs','Motivo del descuento'];
$valores=fn($r)=>[$r['tratamiento']??'',trim($r['first_name'].' '.$r['last_name']),$r['ci'],$r['phone'],$r['carrera'],$r['category'],$r['evento_lista'],$r['created_by']?'Presencial':'En línea',empty($r['codigo_grupo'])?'Individual':'En grupo',$r['status'],$r['descuento_administrador']??0,$r['motivo_descuento']??''];
if(($_GET['exportar']??'')==='csv'){
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="lista_inscritos.csv"');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
 $escribir=function($v)use($out){fputcsv($out,array_map(fn($x)=>preg_match('/^[\s]*[=+@-]/u',(string)$x)?"'".$x:$x,$v),';','"','');};$escribir($encabezados);foreach($filas as $fila)$escribir($valores($fila));fclose($out);exit;
}
headerPage('Listas de inscritos');
?>
<style>.lista-inscritos th{background:#8e1828;color:#fff;white-space:nowrap}.lista-inscritos td{vertical-align:middle}.lista-inscritos .nombre-lista{min-width:220px}@media print{@page{size:landscape;margin:10mm}.lista-inscritos{font-size:8pt}.lista-inscritos th,.lista-inscritos td{padding:3px!important}.lista-inscritos .nombre-lista{min-width:0}.lista-inscritos thead{display:table-header-group}.lista-inscritos tr{break-inside:avoid}.lista-inscritos .no-print{display:none!important}}</style>
<div class="card p-4 no-print"><form method="get"><input type="hidden" name="p" value="listado_inscritos"><div class="row">
<?php $opciones=['event_id'=>['Evento',[''=>'Todos']+array_column(all('SELECT id,name FROM events ORDER BY starts_at DESC'),'name','id')],'carrera'=>['Carrera',[''=>'Todas']+carrerasInscripcion()],'category'=>['Tipo de participante',[''=>'Todos','Estudiante'=>'Estudiante','Profesional'=>'Profesional','Estudiante BTH'=>'Estudiante BTH','UNIOR'=>'UNIOR']],'tratamiento'=>['Prefijo',[''=>'Todos']+array_filter(tratamientos(),fn($k)=>$k!=='',ARRAY_FILTER_USE_KEY)],'modalidad'=>['Modalidad',[''=>'Todas','linea'=>'En línea','presencial'=>'Presencial','grupo'=>'En grupo','planilla'=>'Por planilla']],'estado'=>['Estado',[''=>'Activos (sin anulados)','todos'=>'Todos']+array_combine($estados,$estados)]];foreach($opciones as $k=>[$etiqueta,$items]):?><div class="col-md-4"><?php selectField($k,$etiqueta,$items,$f[$k]);?></div><?php endforeach?><div class="col-md-12"><?php field('buscar','Buscar por nombre, apellido, CI o celular',$f['buscar'],'text',false);?></div></div><button class="btn btn-primary" type="submit">Generar lista</button> <button class="btn btn-success" name="exportar" value="csv">Exportar Excel (CSV)</button> <button class="btn btn-outline-primary" type="button" onclick="window.print()">Imprimir / PDF</button></form></div>
<div class="card p-4"><h3>UNIOR · Lista de inscritos</h3><p>Total: <b><?=count($filas)?></b> participantes · <?=e(date('d/m/Y H:i'))?></p><p><?php foreach($opciones as $k=>[$etiqueta,$items])echo e($etiqueta.': '.($items[$f[$k]]??$f[$k])).' · ';if($f['buscar']!=='')echo 'Búsqueda: '.e($f['buscar']);?></p><div class="table-responsive"><table class="table table-bordered table-striped lista-inscritos"><thead><tr><th>N.º</th><?php foreach($encabezados as $h):?><th><?=e($h)?></th><?php endforeach?><th class="no-print">WhatsApp</th></tr></thead><tbody><?php foreach($filas as $i=>$r):?><tr><td><?=$i+1?></td><?php foreach($valores($r) as $j=>$v):?><td <?=$j===1?'class="nombre-lista"':''?>><?=e($j===10?number_format((float)$v,2,'.',''):$v)?></td><?php endforeach?><td class="no-print"><?php $enlace=whatsappListado((string)$r['phone']);if($enlace):?><a class="btn btn-sm btn-success" href="<?=e($enlace)?>" target="_blank" rel="noopener noreferrer" aria-label="Contactar por WhatsApp a <?=e($r['first_name'])?>">WhatsApp</a><?php else:?>Sin número válido<?php endif?></td></tr><?php endforeach?><?php if(!$filas):?><tr><td colspan="14">No hay inscritos para los filtros elegidos.</td></tr><?php endif?></tbody></table></div></div>
<?php footerPage();
