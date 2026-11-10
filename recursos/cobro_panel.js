"use strict";
(()=>{
 const bloque=document.querySelector('[data-cobro-panel]');if(!bloque)return;
 const form=bloque.closest('form'),cobrar=form.elements.paid_now,tipo=form.elements.tipo_cobro,monto=form.elements.importe_abono,referencia=form.elements.reference;
 function actualizar(){
  const activo=cobrar.checked,planilla=form.elements.payment_method.value==='Descuento por planilla',adelanto=activo&&!planilla&&tipo?.value==='adelanto';
  if(planilla&&tipo)tipo.value='completo';
  const bancario=!planilla&&form.elements.payment_method.value!=='Efectivo';
  referencia.required=activo&&bancario;referencia.disabled=planilla;
  const archivo=form.elements.comprobante_presencial;if(archivo){archivo.disabled=!activo||planilla;archivo.required=activo&&bancario;}
  const ayuda=bloque.querySelector('[data-ayuda-comprobante]');if(ayuda)ayuda.textContent=planilla?'(no corresponde por planilla)':(bancario?'(obligatorio para este pago)':'(opcional en efectivo)');
  if(tipo){tipo.disabled=!activo||planilla;tipo.required=activo&&!planilla;}
  if(monto){
   monto.disabled=!adelanto;monto.required=adelanto;
   const grupo=form.querySelector('[data-inscripcion-grupo]'),tarifas=JSON.parse(grupo.dataset.tarifas),grupal=form.elements.grupo_activo.value==='1';
   const participantes=[form,...(grupal?[...form.querySelectorAll('.grupo-integrante')]:[])];
   const estudiantes=grupal&&participantes.length===5&&participantes.every(b=>(b===form?form.elements.category.value:b.querySelector('[data-campo="category"]').value)==='Estudiante');let total=0;participantes.forEach(bloque=>{const categoria=bloque===form?form.elements.category.value:bloque.querySelector('[data-campo="category"]').value,selector=bloque.querySelector('[data-tarifa-admin]'),importe=selector?.selectedOptions?.[0]?.dataset?.importe;const base=Math.round(Number(importe??tarifas[categoria]??0)*100);const previo=estudiantes?32500:base-(grupal?Math.floor((base*2000+5000)/10000):0),rebaja=bloque===form?form.querySelector('[name="descuento_administrador"]'):bloque.querySelector('[data-descuento-admin]'),motivo=bloque===form?form.querySelector('[name="motivo_descuento"]'):bloque.querySelector('[data-motivo-descuento]');const d=Math.round(Number(rebaja?.value||0)*100);if(rebaja){rebaja.max=(previo/100).toFixed(2);rebaja.setCustomValidity(d>previo?'El descuento supera el importe del participante.':'');}if(motivo){motivo.required=d>0;motivo.minLength=d>0?3:0;}total+=Math.max(0,previo-d);});
   monto.max=(Math.max(0,total-1)/100).toFixed(2);
   bloque.querySelector('[data-total-cobro]').textContent=planilla?'Inscripción por descuento de planilla: no registra ingreso ni saldo pendiente.':activo?'Total de inscripción: Bs '+(total/100).toFixed(2)+(adelanto?' · El resto quedará como saldo pendiente.':' · Se cobrará el total automáticamente.'):'Puede inscribir sin cobrar y registrar el pago después desde la ficha.';
  }
 }
 form.addEventListener('change',actualizar,true);form.addEventListener('input',actualizar,true);
 actualizar();form.dispatchEvent(new Event('input',{bubbles:true}));
})();

(()=>{
 document.querySelectorAll('[data-cobro-saldo]').forEach(form=>{
  function ajustar(){const requerido=form.elements.payment_method.value!=='Efectivo';form.elements.reference.required=requerido;form.elements.comprobante_presencial.required=requerido;}
  form.elements.payment_method.addEventListener('change',ajustar);ajustar();
 });
})();
