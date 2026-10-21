'use strict';
document.getElementById('menu-toggle')?.addEventListener('click',()=>document.body.classList.toggle('menu-open'));
document.querySelectorAll('[data-qr]').forEach(el=>new QRCode(el,{text:el.dataset.qr,width:180,height:180,correctLevel:QRCode.CorrectLevel.M}));
document.querySelectorAll('.copy').forEach(el=>el.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(el.dataset.copy);el.textContent='Enlace copiado';}catch{window.prompt('Copie este enlace:',el.dataset.copy);}}));
document.querySelectorAll('form[data-confirm]').forEach(f=>f.addEventListener('submit',ev=>{if(!confirm(f.dataset.confirm))ev.preventDefault();}));
const preview=document.getElementById('cert-preview');
if(preview){const name=document.getElementById('preview-name');function update(){const form=preview.closest('form');name.style.left=form.elements.cert_x.value+'%';name.style.top=form.elements.cert_y.value+'%';name.style.color=form.elements.cert_color.value;name.style.fontSize=(parseFloat(form.elements.cert_size.value)*preview.clientWidth/1122)+'px';}preview.closest('form').addEventListener('input',update);window.addEventListener('resize',update);update();const file=preview.closest('form').elements.certificate_bg;file.addEventListener('change',()=>{if(file.files[0]){const reader=new FileReader();reader.onload=()=>preview.style.backgroundImage='url("'+reader.result+'")';reader.readAsDataURL(file.files[0]);}});}
document.querySelectorAll('[data-open-dialog]').forEach(button=>button.addEventListener('click',()=>{const dialog=document.getElementById(button.dataset.openDialog);if(dialog){dialog.showModal();dialog.querySelector('[data-close-dialog]')?.focus();}}));
document.querySelectorAll('[data-close-dialog]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog').close()));
document.querySelectorAll('dialog').forEach(dialog=>dialog.addEventListener('click',event=>{if(event.target===dialog){const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)dialog.close();}}));

function textoCuentaInscripcion(segundos){
 if(segundos<=0)return 'Inscripciones cerradas';
 const d=Math.floor(segundos/86400),h=Math.floor(segundos%86400/3600),m=Math.floor(segundos%3600/60);
 if(d)return (d===1?'Falta 1 día':`Faltan ${d} días`)+(h?` y ${h} ${h===1?'hora':'horas'}`:'');
 if(h)return (h===1?'Falta 1 hora':`Faltan ${h} horas`)+(m?` y ${m} ${m===1?'minuto':'minutos'}`:'');
 if(m)return m===1?'Falta 1 minuto':`Faltan ${m} minutos`;
 return segundos===1?'Falta 1 segundo':`Faltan ${segundos} segundos`;
}
document.querySelectorAll('[data-cuenta-inscripcion]').forEach(caja=>{
 const origen=performance.now(),ahoraServidor=Number(caja.dataset.ahora),cierre=Number(caja.dataset.cierre),inicio=Number(caja.dataset.inicio);
 function actualizar(){
  const ahora=ahoraServidor+performance.now()-origen,segundos=Math.max(0,Math.ceil((cierre-ahora)/1000));
  const abierta=caja.dataset.activo==='1'&&ahora>=inicio&&segundos>0;
  caja.querySelector('[data-tiempo-texto]').textContent=caja.dataset.activo!=='1'?'Inscripciones no disponibles':ahora<inicio?'Las inscripciones aún no comenzaron':textoCuentaInscripcion(segundos);
  caja.classList.toggle('cuenta-urgente',abierta&&segundos<86400);caja.classList.toggle('cuenta-cerrada',!abierta);
  const valores={'días':Math.floor(segundos/86400),'horas':Math.floor(segundos%86400/3600),'minutos':Math.floor(segundos%3600/60),'segundos':segundos%60};
  caja.querySelectorAll('[data-unidad]').forEach(el=>el.textContent=String(valores[el.dataset.unidad]).padStart(2,'0'));
  if(!abierta)document.querySelectorAll('form').forEach(f=>{if(f.querySelector('input[name="action"][value="register"]'))f.querySelectorAll('button[type="submit"],button:not([type])').forEach(b=>b.disabled=true);});
 }
 actualizar();setInterval(actualizar,1000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)actualizar();});
});
