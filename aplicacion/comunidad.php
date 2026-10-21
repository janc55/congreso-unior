<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
function enlaceGrupoValido(string $enlace): bool {
 if($enlace==='')return true;
 if(strlen($enlace)>2048||!filter_var($enlace,FILTER_VALIDATE_URL))return false;
 $p=parse_url($enlace);
 return in_array(strtolower($p['scheme']??''),['http','https'],true)&&!empty($p['host']);
}
function enlacesComunidad(string $grupo=''): void { ?>
<section class="comunidad-unior no-print"><div class="comunidad-cabecera"><div><span class="comunidad-etiqueta">UNIVERSIDAD PRIVADA DE ORURO</span><h3>Comunidad UNIOR</h3><p>Canales de información, actividades académicas y novedades institucionales.</p></div><img src="recursos/logo-unior.jpg" class="logo-comunidad" alt="UNIOR"></div><div class="comunidad-enlaces">
<?php if($grupo!==''&&enlaceGrupoValido($grupo)):?><div class="comunidad-whatsapp"><div class="comunidad-whatsapp-texto"><span class="comunidad-etiqueta">WHATSAPP · COMUNIDAD UNIOR</span><h4>Únete a nuestro grupo de WhatsApp</h4><p>Escanea el QR o presiona el botón para recibir información del evento.</p><a class="enlace-whatsapp" href="<?=e($grupo)?>" target="_blank" rel="noopener noreferrer"><strong>Unirme al grupo ↗</strong></a></div><div class="grupo-whatsapp-qr"><div class="qr qr-grupo-unior" data-grupo-qr="<?=e($grupo)?>" aria-label="QR para unirse al grupo de WhatsApp"></div><small>Escanea para unirte</small></div></div><?php endif?>
<a href="https://www.facebook.com/UniversidadPrivadaDeOruro/?locale=es_LA" target="_blank" rel="noopener noreferrer"><span class="red-identificador" aria-hidden="true">f</span><strong>Facebook</strong><span>Universidad Privada de Oruro ↗</span></a>
<a href="https://www.tiktok.com/@unior_oruro" target="_blank" rel="noopener noreferrer"><span class="red-identificador" aria-hidden="true">♪</span><strong>TikTok</strong><span>@unior_oruro ↗</span></a></div></section>
<?php }
function textoTiempoRestante(int $segundos): string {
 if($segundos<=0)return 'Inscripciones cerradas';
 $dias=intdiv($segundos,86400);$horas=intdiv($segundos%86400,3600);$minutos=intdiv($segundos%3600,60);
 if($dias>0)return ($dias===1?'Falta 1 día':'Faltan '.$dias.' días').($horas?' y '.$horas.($horas===1?' hora':' horas'):'');
 if($horas>0)return ($horas===1?'Falta 1 hora':'Faltan '.$horas.' horas').($minutos?' y '.$minutos.($minutos===1?' minuto':' minutos'):'');
 if($minutos>0)return $minutos===1?'Falta 1 minuto':'Faltan '.$minutos.' minutos';
 return $segundos===1?'Falta 1 segundo':'Faltan '.$segundos.' segundos';
}
function mostrarCuentaRegresiva(array $evento): void {
 $zona=new DateTimeZone('America/La_Paz');$ahora=time();
 $inicio=(new DateTimeImmutable($evento['registration_start'].' 00:00:00',$zona))->getTimestamp();
 $cierre=(new DateTimeImmutable($evento['registration_end'].' 00:00:00',$zona))->modify('+1 day')->getTimestamp();
 $activo=(bool)$evento['active'];$segundos=max(0,$cierre-$ahora);
 ?>
<section class="cuenta-inscripcion" data-cuenta-inscripcion data-inicio="<?=$inicio*1000?>" data-cierre="<?=$cierre*1000?>" data-ahora="<?=$ahora*1000?>" data-activo="<?=$activo?'1':'0'?>"><div><span class="cuenta-etiqueta">INSCRIPCIONES · TIEMPO RESTANTE</span><h2 data-tiempo-texto><?=e(!$activo?'Inscripciones no disponibles':($ahora<$inicio?'Las inscripciones aún no comenzaron':textoTiempoRestante($segundos)))?></h2><p>Inscríbete antes del <?=e(date('d/m/Y',$cierre-1))?> a las 23:59 · Hora de Bolivia</p></div><div class="cuenta-reloj" aria-label="Tiempo hasta el cierre"><?php foreach(['días'=>intdiv($segundos,86400),'horas'=>intdiv($segundos%86400,3600),'minutos'=>intdiv($segundos%3600,60),'segundos'=>$segundos%60] as $unidad=>$valor):?><div><strong data-unidad="<?=e($unidad)?>"><?=e(str_pad((string)$valor,2,'0',STR_PAD_LEFT))?></strong><span><?=e($unidad)?></span></div><?php endforeach?></div></section>
<?php }
