'use strict';
(()=>{
 const seccion=document.querySelector('[data-inscripcion-grupo]');if(!seccion)return;
 const lista=seccion.querySelector('[data-lista-integrantes]'),form=seccion.closest('form'),activar=seccion.querySelector('[data-activar-grupo]'),desactivar=seccion.querySelector('[data-desactivar-grupo]'),plantilla=document.getElementById('plantilla-integrante'),modo=form.elements.grupo_activo,registrar=form.querySelector('.boton-inscripcion');
 const tarifas=JSON.parse(seccion.dataset.tarifas);let activo=false,enviando=false;
 const lleno=c=>!c.required||(c.value.trim()!==''&&c.validity.valid);
 function actualizar(){
  const bloques=[...lista.querySelectorAll('.grupo-integrante')],catPrincipal=form.querySelector('[name="category"]').value,categorias=[catPrincipal,...(activo?bloques.map(e=>e.querySelector('[data-campo="category"]').value):[])];let base=0,total=0;
  categorias.forEach(cat=>{const n=Math.round(Number(tarifas[cat])*100);base+=n;total+=n-(activo?Math.floor((n*2000+5000)/10000):0);});
  const dinero=n=>'Bs '+(n/100).toFixed(2);seccion.querySelector('[data-grupo-estimacion]').textContent=`${activo?'Grupo de 5 personas · 20 % de descuento':'Inscripción individual · Sin descuento'} · Tarifa: ${dinero(base)} · Ahorro: ${dinero(base-total)} · Total: ${dinero(total)}`;
  const principales=[...form.querySelectorAll('[required]')].filter(c=>!lista.contains(c)),completos=(principales.every(lleno)?1:0)+bloques.filter(b=>[...b.querySelectorAll('[required]')].every(lleno)).length;
  const valido=[...form.querySelectorAll('input,select,textarea')].filter(c=>!c.disabled).every(c=>c.validity.valid&&lleno(c));
  const cerrado=!!document.querySelector('[data-cuenta-inscripcion].cuenta-cerrada');
  registrar.disabled=enviando||cerrado||!valido||(activo&&(bloques.length!==4||completos!==5));
  seccion.querySelector('[data-grupo-progreso]').textContent=cerrado?'Inscripciones no disponibles.':activo?`${completos} de 5 participantes completos. ${registrar.disabled?'Completa los datos obligatorios de las cinco personas para registrar.':'Datos completos. Ya puedes registrar al grupo.'}`:registrar.disabled?'Completa tus datos obligatorios para registrar tu inscripción individual.':'Datos completos. Ya puedes registrar tu inscripción individual.';
 }
 activar.addEventListener('click',()=>{
  activo=true;modo.value='1';lista.replaceChildren();for(let i=0;i<4;i++){const fragmento=plantilla.content.cloneNode(true),bloque=fragmento.querySelector('.grupo-integrante');bloque.querySelector('[data-numero-integrante]').textContent=i+2;bloque.querySelectorAll('[data-campo]').forEach(c=>{c.name=`integrantes[${i}][${c.dataset.campo}]`;c.id=`integrante-${i}-${c.dataset.campo}`;const label=c.closest('.form-group').querySelector('label');if(label)label.htmlFor=c.id;});lista.appendChild(fragmento);}
  lista.hidden=false;activar.hidden=true;activar.setAttribute('aria-expanded','true');desactivar.hidden=false;if(window.decorarIconosUnior)window.decorarIconosUnior(lista);actualizar();lista.querySelector('[data-campo="first_name"]').focus();
 });
 desactivar.addEventListener('click',()=>{activo=false;modo.value='0';lista.replaceChildren();lista.hidden=true;activar.hidden=false;activar.setAttribute('aria-expanded','false');desactivar.hidden=true;actualizar();activar.focus();});
 const aviso=document.createElement('div');aviso.className='aviso-registro';aviso.setAttribute('role','alert');aviso.hidden=true;form.prepend(aviso);
 function limpiarCampo(c){c.classList.remove('is-invalid');c.removeAttribute('aria-invalid');c.removeAttribute('aria-describedby');c.parentElement.querySelector('.mensaje-error-campo')?.remove();}
 function erroresCampos(errores,mensaje){aviso.textContent=mensaje;aviso.hidden=false;let primero=null;Object.entries(errores).forEach(([nombre,texto])=>{const c=form.elements.namedItem(nombre);if(!c||!c.classList)return;limpiarCampo(c);c.classList.add('is-invalid');c.setAttribute('aria-invalid','true');const detalle=document.createElement('div');detalle.className='mensaje-error-campo';detalle.textContent=texto;detalle.id='error-registro-'+nombre.replace(/[^a-z0-9]/gi,'-');c.setAttribute('aria-describedby',detalle.id);c.after(detalle);if(!primero)primero=c;});(primero||aviso).focus();aviso.scrollIntoView({block:'nearest',behavior:'auto'});}
 form.addEventListener('input',ev=>{if(ev.target.classList?.contains('is-invalid'))limpiarCampo(ev.target);actualizar();});form.addEventListener('change',actualizar);
 form.addEventListener('submit',ev=>{
  if(enviando){ev.preventDefault();return;}actualizar();if(registrar.disabled){ev.preventDefault();form.reportValidity();return;}
  aviso.hidden=true;form.querySelectorAll('.is-invalid').forEach(limpiarCampo);
  const documentos=[...form.querySelectorAll('input[name="ci"],input[data-campo="ci"]')],vistos=new Map(),errores={};documentos.forEach(c=>{const valor=c.value.trim().toLocaleUpperCase();if(!valor)return;if(vistos.has(valor)){errores[c.name]='Documento repetido dentro del grupo.';errores[vistos.get(valor).name]='Documento repetido dentro del grupo.';}else vistos.set(valor,c);});
  if(Object.keys(errores).length){ev.preventDefault();erroresCampos(errores,'Corrige los documentos repetidos.');return;}
  enviando=true;actualizar();registrar.setAttribute('aria-busy','true');
  // Se permite el POST normal. PHP devuelve el formulario con sus errores
  // o redirige al comprobante al completar el registro.

 });
 window.addEventListener('pageshow',()=>{enviando=false;registrar.removeAttribute('aria-busy');actualizar();});
 const conservado=document.getElementById('registro-conservado');if(conservado){const estado=JSON.parse(conservado.textContent),datos=estado.datos||{};if(datos.grupo_activo==='1')activar.click();Object.entries(datos).forEach(([nombre,valor])=>{if(nombre==='integrantes'){Object.values(valor).forEach((persona,i)=>Object.entries(persona).forEach(([campo,contenido])=>{const c=form.elements.namedItem(`integrantes[${i}][${campo}]`);if(c)c.value=contenido;}));}else{const c=form.elements.namedItem(nombre);if(c){if(c.type==='checkbox')c.checked=!!valor;else c.value=valor;}}});if(Object.keys(estado.errores||{}).length)erroresCampos(estado.errores,'Corrige los documentos marcados.');}

 const reloj=document.querySelector('[data-cuenta-inscripcion]');if(reloj)new MutationObserver(actualizar).observe(reloj,{attributes:true,attributeFilter:['class']});actualizar();
})();
