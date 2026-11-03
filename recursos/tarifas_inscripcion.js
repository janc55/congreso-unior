'use strict';
(()=>{
 const principal=document.querySelector('[data-tarifa-admin]');if(!principal)return;
 const form=principal.closest('form');
 function sincronizar(){
  let cambio=false;
  form.querySelectorAll('[data-tarifa-admin]').forEach(selector=>{
   const bloque=selector.closest('.grupo-integrante');
   const categoria=bloque?bloque.querySelector('[data-campo="category"]').value:form.elements.category.value;
   const opciones=[...selector.options];
   opciones.forEach(o=>{const disponible=o.dataset.categoria===categoria;o.disabled=!disponible;o.hidden=!disponible;});
   const validas=opciones.filter(o=>!o.disabled);
   if(!validas.some(o=>o.value===selector.value)){
    const preferida=validas.find(o=>o.dataset.vigente==='1')||validas[0];
    const valor=preferida?preferida.value:'';if(selector.value!==valor){selector.value=valor;cambio=true;}
   }
  });
  return cambio;
 }
 form.addEventListener('change',sincronizar,true);
 const lista=form.querySelector('[data-lista-integrantes]');
 if(lista)new MutationObserver(()=>{if(sincronizar())form.dispatchEvent(new Event('input',{bubbles:true}));}).observe(lista,{childList:true,subtree:true});
 sincronizar();
})();
