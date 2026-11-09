<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
auth(true);
$evento=filter_var($_GET['event_id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
if($evento===false){http_response_code(400);exit('Evento inválido.');}
$eventos=all('SELECT id,name FROM events ORDER BY starts_at DESC');
$filas=all("SELECT r.id,r.token,r.first_name,r.last_name,r.ci,r.carrera,r.tratamiento,r.event_id,e.name evento_qr FROM registrations r JOIN events e ON e.id=r.event_id WHERE r.status<>'Anulado'".($evento?' AND r.event_id=?':'').' ORDER BY r.event_id,r.last_name,r.first_name,r.id',$evento?[$evento]:[]);
$datos=[];foreach($filas as $r)$datos[]=['id'=>(int)$r['id'],'evento'=>(int)$r['event_id'],'nombre'=>trim(($r['tratamiento']??'').' '.$r['first_name'].' '.$r['last_name']),'ci'=>(string)$r['ci'],'carrera'=>(string)$r['carrera'],'evento_nombre'=>$r['evento_qr'],'enlace'=>url('p=participant&t='.$r['token'])];
headerPage('Descargar QR de inscritos');
?>
<div class="card p-4"><h3>QR individuales de inscritos</h3><form method="get"><input type="hidden" name="p" value="exportar_qr_inscritos"><?php selectField('event_id','Evento',[0=>'Todos los eventos']+array_column($eventos,'name','id'),(string)$evento);?><button class="btn btn-primary">Consultar</button></form><hr><p><b><?=count($datos)?></b> inscritos activos. No incluye inscripciones anuladas. Cada integrante de grupo tiene su propio QR.</p><p>Descargue el ZIP y extráigalo para obtener la carpeta con las imágenes PNG, ordenadas por evento. Cada imagen contiene únicamente el QR. El nombre y el CI se incluyen en el nombre del archivo. Estos QR sirven para asistencia y entrega de materiales.</p><button id="descargar-qr" class="btn btn-success" <?=!$datos?'disabled':''?>>Generar carpeta ZIP de QR</button><p id="progreso-qr" class="mt-3" role="status" aria-live="polite"></p><div id="qr-temporal" hidden></div></div>
<script type="application/json" id="datos-qr-inscritos"><?=json_encode($datos,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script>
<script>
'use strict';
function crcQr(bytes){let crc=0xffffffff;for(const b of bytes){crc^=b;for(let k=0;k<8;k++)crc=(crc>>>1)^((crc&1)?0xedb88320:0);}return (crc^0xffffffff)>>>0;}
function zipQr(archivos){
 const partes=[],central=[];let posicion=0,tamanoCentral=0;const encoder=new TextEncoder();
 if(archivos.length>65535)throw new Error('Seleccione un evento con menos de 65.536 inscritos.');
 for(const a of archivos){const nombre=encoder.encode(a.nombre),datos=a.datos,crc=crcQr(datos),local=new Uint8Array(30+nombre.length),v=new DataView(local.buffer);v.setUint32(0,0x04034b50,true);v.setUint16(4,20,true);v.setUint16(6,0x800,true);v.setUint32(14,crc,true);v.setUint32(18,datos.length,true);v.setUint32(22,datos.length,true);v.setUint16(26,nombre.length,true);local.set(nombre,30);
 const c=new Uint8Array(46+nombre.length),d=new DataView(c.buffer);d.setUint32(0,0x02014b50,true);d.setUint16(4,20,true);d.setUint16(6,20,true);d.setUint16(8,0x800,true);d.setUint32(16,crc,true);d.setUint32(20,datos.length,true);d.setUint32(24,datos.length,true);d.setUint16(28,nombre.length,true);d.setUint32(42,posicion,true);c.set(nombre,46);partes.push(local,datos);central.push(c);posicion+=local.length+datos.length;tamanoCentral+=c.length;
 if(posicion+tamanoCentral>0xffffffff)throw new Error('Archivo demasiado grande. Descargue por evento.');}
 const fin=new Uint8Array(22),f=new DataView(fin.buffer);f.setUint32(0,0x06054b50,true);f.setUint16(8,archivos.length,true);f.setUint16(10,archivos.length,true);f.setUint32(12,tamanoCentral,true);f.setUint32(16,posicion,true);return new Blob([...partes,...central,fin],{type:'application/zip'});
}
function nombreQr(valor){return String(valor).normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-zA-Z0-9_-]+/g,'_').slice(0,85)||'participante';}
document.getElementById('descargar-qr').addEventListener('click',async function(){
 const boton=this,aviso=document.getElementById('progreso-qr'),host=document.getElementById('qr-temporal');boton.disabled=true;
 try{if(typeof QRCode==='undefined')throw new Error('No se pudo cargar el generador QR. Recargue la página.');const personas=JSON.parse(document.getElementById('datos-qr-inscritos').textContent),archivos=[];
 for(let i=0;i<personas.length;i++){const p=personas[i];aviso.textContent=`Generando ${i+1} de ${personas.length}…`;host.replaceChildren();const qr=new QRCode(host,{text:p.enlace,width:256,height:256,correctLevel:QRCode.CorrectLevel.M}),matriz=qr._oQRCode,n=matriz.getModuleCount(),celda=Math.floor(620/(n+8)),lado=(n+8)*celda,canvas=document.createElement('canvas');canvas.width=lado;canvas.height=lado;const ctx=canvas.getContext('2d');if(!ctx)throw new Error('Su navegador no permite generar las imágenes.');ctx.fillStyle='#fff';ctx.fillRect(0,0,lado,lado);ctx.fillStyle='#000';for(let r=0;r<n;r++)for(let c=0;c<n;c++)if(matriz.isDark(r,c))ctx.fillRect((c+4)*celda,(r+4)*celda,celda,celda);const png=await new Promise((resolve,reject)=>canvas.toBlob(b=>b?resolve(b):reject(new Error('No se pudo generar una imagen.')),'image/png'));archivos.push({nombre:'QR_inscritos/evento_'+p.evento+'/'+p.id+'_'+nombreQr(p.nombre)+'_CI_'+nombreQr(p.ci)+'.png',datos:new Uint8Array(await png.arrayBuffer())});if(i%10===0)await new Promise(resolve=>setTimeout(resolve,0));}
 aviso.textContent='Preparando descarga…';const blob=zipQr(archivos),enlace=URL.createObjectURL(blob),a=document.createElement('a');a.href=enlace;a.download='UNIOR_QR_inscritos_'+new Date().toISOString().slice(0,10)+'.zip';document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(enlace),60000);aviso.textContent=`Archivo listo: ${archivos.length} QR individuales. Extraiga el ZIP para ver la carpeta.`;
 }catch(e){aviso.textContent='No se pudo completar: '+e.message;}finally{host.replaceChildren();boton.disabled=false;}
});
</script>
<?php footerPage();
