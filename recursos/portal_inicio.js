'use strict';
(()=>{
 const campo=document.getElementById('buscar-evento');if(!campo)return;
 const tarjetas=[...document.querySelectorAll('[data-evento-tipo]')],botones=[...document.querySelectorAll('[data-filtro-tipo]')];let tipo='Todos';
 const normalizar=s=>s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
 function filtrar(){let total=0;const termino=normalizar(campo.value.trim());tarjetas.forEach(t=>{const visible=(tipo==='Todos'||t.dataset.eventoTipo===tipo)&&normalizar(t.dataset.eventoBusqueda).includes(termino);t.hidden=!visible;if(visible)total++;});document.querySelector('[data-sin-resultados]').hidden=total>0;const mensaje=`${total} ${total===1?'evento encontrado':'eventos encontrados'}`;document.querySelector('[data-total-eventos]').textContent=mensaje;document.querySelector('[data-resultados-vivos]').textContent=mensaje;}
 botones.forEach(b=>b.addEventListener('click',()=>{tipo=b.dataset.filtroTipo;botones.forEach(x=>x.setAttribute('aria-pressed',String(x===b)));filtrar();}));campo.addEventListener('input',filtrar);
 if('IntersectionObserver' in window&&!window.matchMedia('(prefers-reduced-motion: reduce)').matches){document.documentElement.classList.add('revelar-eventos');const observador=new IntersectionObserver(entradas=>entradas.forEach(e=>{if(e.isIntersecting){e.target.classList.remove('pendiente-revelar');observador.unobserve(e.target);}}),{threshold:.05});tarjetas.forEach(t=>{t.classList.add('pendiente-revelar');observador.observe(t);});}
})();

(()=>{const portada=document.querySelector('.portada-institucional');if(!portada||window.matchMedia('(prefers-reduced-motion: reduce)').matches||!window.matchMedia('(pointer: fine)').matches)return;portada.addEventListener('pointermove',ev=>{const rect=portada.getBoundingClientRect();portada.style.setProperty('--luz-x',((ev.clientX-rect.left)/rect.width*100)+'%');portada.style.setProperty('--luz-y',((ev.clientY-rect.top)/rect.height*100)+'%');});})();
