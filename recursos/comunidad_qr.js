'use strict';
// Generación independiente para que otro QR del comprobante no la interrumpa.
document.querySelectorAll('[data-grupo-qr]').forEach(contenedor=>{
 try{
  const enlace=contenedor.dataset.grupoQr;
  if(!enlace||typeof QRCode==='undefined')throw new Error('QR no disponible');
  contenedor.replaceChildren();
  new QRCode(contenedor,{text:enlace,width:180,height:180,colorDark:'#000000',colorLight:'#ffffff',correctLevel:QRCode.CorrectLevel.L});
 }catch(error){
  contenedor.textContent='No se pudo generar el QR. Utiliza el botón Unirme al grupo.';
  console.error('QR de Comunidad UNIOR:',error);
 }
});
