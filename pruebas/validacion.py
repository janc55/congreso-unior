import requests,re,json
B='http://127.0.0.1:8080';s=requests.Session();n=0
base={'action':'register','event_id':1,'first_name':'María','last_name':'Persistente','ci':'G5-0','city':'Oruro','phone':'70012345','email':'mantener@example.com','category':'Estudiante','tratamiento':'Dra.','institution':'Mi institución'}
def check(ok,msg):
 global n
 assert ok,msg
 n+=1;print('OK validación',msg)
def send(data,ajax=True):
 r=s.get(B+'/inscripcion.php?p=register&id=1');csrf=re.search('name="csrf" value="([a-f0-9]+)"',r.text).group(1)
 return s.post(B+'/inscripcion.php?p=register&id=1',data={'csrf':csrf,**data},headers={'Accept':'application/json'} if ajax else {},allow_redirects=False)
r=send(base);check(r.status_code==422 and 'Location' not in r.headers,'Duplicado AJAX no redirige')
check(list(r.json()['errors'])==['ci'],'Identifica CI individual exacto')
r=send(base,False);check(r.status_code==200 and 'is-invalid' in r.text and 'value="Persistente"' in r.text and 'value="mantener@example.com"' in r.text and 'value="G5-0"' in r.text,'Sin AJAX conserva datos y campo rojo')
d={**base,'grupo_activo':'1','ci':'NUEVO-LIDER'}
for i in range(4):
 for k,v in {**base,'first_name':'Integrante'+str(i),'ci':'G5-1' if i==3 else 'CORREGIR-'+str(i)}.items():
  if k not in ['action','event_id']:d[f'integrantes[{i}][{k}]']=v
r=send(d);check('integrantes[3][ci]' in r.json()['errors'],'Señala documento inscrito del quinto participante')
d['integrantes[3][ci]']='NUEVO-LIDER';r=send(d);check('ci' in r.json()['errors'] and 'integrantes[3][ci]' in r.json()['errors'],'Señala ambos documentos repetidos dentro del grupo')
r=send(d,False);match=re.search('<script type="application/json" id="registro-conservado">(.*?)</script>',r.text,re.S);saved=json.loads(match.group(1));check(saved['datos']['integrantes'][3]['first_name']=='Integrante3' and saved['datos']['email']=='mantener@example.com','Conserva los cinco participantes para rehidratar el formulario')
d['integrantes[3][ci]']='CORREGIDO-4';r=send(d);check(r.status_code==200 and r.json()['ok'] and 'redirect' in r.json(),'Corregir los documentos permite guardar y continuar')
receipt=s.get(r.json()['redirect']);check('comprobante_media_carta.css' in receipt.text and 'TOTAL A PAGAR POR EL GRUPO' in receipt.text,'Comprobante grupal conserva detalle con estilo media carta')
css=s.get(B+'/recursos/comprobante_media_carta.css');check('size:139.7mm 215.9mm' in css.text and 'display:none!important' in css.text,'Página media carta y comunidad excluida de impresión')
r=s.get(B+'/inscripcion.php?p=register&id=1');template=re.search('<template id="plantilla-integrante">(.*?)</template>',r.text,re.S).group(1)
orden=re.findall('data-campo="([a-z_]+)"',template)
check(orden==['tratamiento','first_name','last_name','ci','city','phone','email','institution','category'],'Grupo mantiene el mismo orden de campos que participante principal')
check('<label>Tratamiento</label>' not in template and 'nombre-con-tratamiento' in template,'Prefijo junto a Nombres sin etiqueta Tratamiento')
check('size:139.7mm 215.9mm' in css.text,'Comprobante media carta vertical')
r=send({**base,'_respuesta':'json'},False);check(r.status_code==422 and r.headers.get('Content-Type','').startswith('application/json') and 'ci' in r.json()['errors'],'Marcador explícito devuelve JSON sin depender de Accept')
r=send({**base,'ci':'MARCADOR-JSON-UNICO','_respuesta':'json'},False);check(r.status_code==200 and r.json()['ok'],'Registro correcto con marcador JSON explícito')
r=send({**base,'ci':'POST-NATIVO-CORRECTO'},False);check(r.status_code==303 and 'p=receipt' in r.headers.get('Location',''),'POST nativo correcto redirige 303 al comprobante')
r=send({**base,'ci':'POST-NATIVO-CORRECTO'},False);check(r.status_code==200 and 'is-invalid' in r.text and 'value="Persistente"' in r.text,'POST nativo duplicado vuelve al formulario completo con error')
js=s.get(B+'/recursos/inscripcion_formulario.js');check('fetch(' not in js.text and 'interpretarRespuestaInscripcion' not in js.text,'Interfaz activa no depende de fetch ni JSON')
print('PASS validación',n,'checks')
