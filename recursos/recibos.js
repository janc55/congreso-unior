'use strict';
(()=>{
 const recibo=document.querySelector('[data-imprimir-recibo="1"]');if(!recibo)return;
 function imprimir(){
  const actual=new URL(window.location.href);actual.searchParams.delete('imprimir');window.history.replaceState(null,'',actual);
  recibo.dataset.imprimirRecibo='0';
  const preparado=()=>requestAnimationFrame(()=>requestAnimationFrame(()=>window.print()));
  if(document.fonts?.ready)document.fonts.ready.then(preparado);else preparado();
 }
 if(document.readyState==='complete')imprimir();else window.addEventListener('load',imprimir,{once:true});
})();
