import requests,re
B='http://127.0.0.1:8080';s=requests.Session()
r=s.get(B+'/inicio.php?p=login');csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',r.text).group(1)
s.post(B+'/inicio.php?p=login',data={'csrf':csrf,'action':'login','username':'testadmin','password':'TestAdmin!2026'})
checks=0
def check(c,m):
 global checks
 assert c,m
 checks+=1;print('OK V2',m)
r=s.get(B+'/inscripcion.php?p=register&id=1')
check(r.status_code==200 and 'main-sidebar' not in r.text and 'Gestionar eventos' not in r.text,'Independent public inscription without admin menu')
check('name="paid_now"' not in r.text,'Public portal cannot collect staff payments even with admin session')
csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',r.text).group(1)
r=s.post(B+'/inscripcion.php?p=register&id=1',data={'csrf':csrf,'action':'register','event_id':1,'first_name':'María','last_name':'Flores','ci':'PUBLIC-ADMIN','city':'Oruro','phone':'70000000','email':'maria@example.com','category':'Estudiante','paid_now':'1','payment_method':'Efectivo'})
check('inscripcion.php?p=receipt' in r.url and 'Pendiente' in r.text,'Registration stays public and cannot bypass payment review')
check('COMPROBANTE DE INSCRIPCIÓN' in r.text and 'receipt-letterhead' in r.text and 'comprobante_media_carta.css' in r.text,'Formal half letter receipt')
check('main-sidebar' not in r.text,'Receipt has no admin menu')
check(s.get(B+'/inscripcion.php?p=users').status_code==404,'Public portal excludes admin routes')
check(s.post(B+'/inscripcion.php?p=register&id=1',data={'csrf':csrf,'action':'user'}).status_code==403,'Public portal blocks admin actions')
r=s.get(B+'/inicio.php?p=event&id=1')
check('/inscripcion.php?p=register' in r.text,'Shared links target independent portal')
check('share-event' in r.text and 'data-open-dialog' in r.text,'Share QR modal available')
r=s.get(B+'/certificados.php?p=list')
check(r.status_code==200 and 'Gestión y emisión de certificados' in r.text and 'main-sidebar' not in r.text,'Independent certificate window')
check('target="_blank"' in r.text,'Certificate emission form opens new window')
check(requests.get(B+'/certificados.php?p=list',allow_redirects=False).status_code==302,'Certificate window requires staff login')
r=s.get(B+'/inicio.php?p=participant&t='+re.search(r'name="token" value="([a-f0-9]+)"',r.text).group(1))
check('target="_blank" class="mb-2"' in r.text and '/certificados.php?' in r.text,'Participant certificate form opens independent window')
css=s.get(B+'/recursos/estilos.css').text
check('--unior-red:#ba1320' in css and '--unior-black:#181818' in css,'Red black UNIOR theme')
print('PASS V2',checks,'checks')
