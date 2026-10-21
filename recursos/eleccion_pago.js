'use strict';
(()=>{
 document.querySelectorAll('[data-eleccion-pago]').forEach(bloque=>{
  const selector=bloque.querySelector('[name="tipo_pago"]');
  const monto=bloque.querySelector('[name="importe_abono"]');
  const total=bloque.querySelector('[data-total-pago]');
  function actualizar(){
   const adelanto=selector.value==='adelanto';
   monto.disabled=!adelanto;monto.required=adelanto;
   total.hidden=selector.value!=='completo';
   monto.setCustomValidity('');
  }
  selector.addEventListener('change',()=>{actualizar();if(!monto.disabled)monto.focus();});
  actualizar();
 });
})();
