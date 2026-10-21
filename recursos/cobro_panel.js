"use strict";
(()=>{
 const bloque=document.querySelector('[data-cobro-panel]');if(!bloque)return;
 const form=bloque.closest('form'),cobrar=form.elements.paid_now,tipo=form.elements.tipo_cobro,monto=form.elements.importe_abono,referencia=form.elements.reference;
 function actualizar(){
  const activo=cobrar.checked,adelanto=activo&&tipo?.value==='adelanto';
  referencia.required=activo;
  if(tipo){tipo.disabled=!activo;tipo.required=activo;}
  if(monto){
   monto.disabled=!adelanto;monto.required=adelanto;
   const grupo=form.querySelector('[data-inscripcion-grupo]'),tarifas=JSON.parse(grupo.dataset.tarifas),grupal=form.elements.grupo_activo.value==='1';
   const categorias=[form.elements.category.value,...(grupal?[...form.querySelectorAll('[data-campo="category"]')].map(c=>c.value):[])];
   let total=0;categorias.forEach(c=>{const base=Math.round(Number(tarifas[c]||0)*100);total+=base-(grupal?Math.floor((base*2000+5000)/10000):0);});
   monto.max=(Math.max(0,total-1)/100).toFixed(2);
   bloque.querySelector('[data-total-cobro]').textContent=activo?'Total de inscripción: Bs '+(total/100).toFixed(2)+(adelanto?' · El resto quedará como saldo pendiente.':' · Se cobrará el total automáticamente.'):'Puede inscribir sin cobrar y registrar el pago después desde la ficha.';
  }
 }
 form.addEventListener('change',actualizar,true);form.addEventListener('input',actualizar,true);
 actualizar();form.dispatchEvent(new Event('input',{bubbles:true}));
})();
