<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
function tortaReporte(array $filas,string $campo,string $titulo,bool $moneda=false): void {
 $colores=['#ba1320','#252525','#d56a23','#9b4b69','#47756c','#a18127','#657897','#703c36','#8e849a','#397790'];
 $datos=[];$total=0;
 foreach($filas as $fila){$valor=(float)($fila[$campo]??0);if($valor>0){$datos[]=['nombre'=>$fila['carrera_resumen'],'valor'=>$valor];$total+=$valor;}}
 ?>
 <section class="torta-reporte"><h4><?=e($titulo)?></h4><?php if($total<=0):?><p>No hay datos para este gráfico.</p><?php else:?><div class="torta-contenido"><svg viewBox="0 0 240 240" role="img" aria-label="<?=e($titulo)?>"><title><?=e($titulo)?></title><?php $angulo=-90;foreach($datos as $i=>$dato):$parte=$dato['valor']/$total;$fin=$angulo+$parte*360;$color=$colores[$i%count($colores)];$descripcion=$dato['nombre'].': '.($moneda?money($dato['valor']):$dato['valor']).' ('.number_format($parte*100,1,',','.').' %)';if($parte>=.999999):?><circle cx="120" cy="120" r="105" fill="<?=$color?>"><title><?=e($descripcion)?></title></circle><?php else:$x1=120+105*cos(deg2rad($angulo));$y1=120+105*sin(deg2rad($angulo));$x2=120+105*cos(deg2rad($fin));$y2=120+105*sin(deg2rad($fin));$d=sprintf('M120 120 L%.4F %.4F A105 105 0 %d 1 %.4F %.4F Z',$x1,$y1,$parte>.5?1:0,$x2,$y2);?><path d="<?=$d?>" fill="<?=$color?>" stroke="#fff" stroke-width="1"><title><?=e($descripcion)?></title></path><?php endif;$angulo=$fin;endforeach?></svg><ul class="leyenda-torta"><?php foreach($datos as $i=>$dato):?><li><span class="color-torta" style="background:<?=$colores[$i%count($colores)]?>"></span><span><?=e($dato['nombre'])?> <b><?=$moneda?money($dato['valor']):e($dato['valor'])?></b> · <?=number_format($dato['valor']/$total*100,1,',','.')?> %</span></li><?php endforeach?></ul></div><?php endif?></section>
 <?php
}
