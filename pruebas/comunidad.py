import requests,re,pymysql
B='http://127.0.0.1:8080';s=requests.Session();n=0
c=pymysql.connect(host='127.0.0.1',port=3309,user='root',database='legacy_unior_es_v12',autocommit=True)
def check(ok,msg):
 global n
 assert ok,msg
 n+=1;print('OK comunidad',msg)
r=s.get(B+'/inicio.php?p=login');csrf=re.search('name="csrf" value="([a-f0-9]+)"',r.text).group(1)
s.post(B+'/inicio.php?p=login',data={'csrf':csrf,'action':'login','username':'testadmin','password':'TestAdmin!2026'})
grupo='https://chat.whatsapp.com/UniorGrupoPrueba2026'
data={'action':'event','id':1,'name':'Congreso anterior','type':'Congreso','venue':'UNIOR','description':'Prueba comunidad','starts_at':'2026-10-01T09:00','ends_at':'2026-10-31T18:00','registration_start':'2026-10-01','registration_end':'2026-10-31','capacity':0,'active':1,'cert_x':50,'cert_y':50,'cert_size':32,'cert_color':'#ba1320','enlace_grupo_whatsapp':grupo}
def post(datos):
 r=s.get(B+'/inicio.php?p=event&id=1');token=re.search('name="csrf" value="([a-f0-9]+)"',r.text).group(1)
 return s.post(B+'/inicio.php?p=event&id=1',data={**datos,'csrf':token})
check('name="enlace_grupo_whatsapp"' in s.get(B+'/inicio.php?p=event&id=1').text,'Campo de WhatsApp editable por evento')
check('Evento guardado' in post(data).text,'Guardar grupo válido')
with c.cursor() as cur:
 cur.execute('SELECT enlace_grupo_whatsapp FROM eventos WHERE id=1');check(cur.fetchone()[0]==grupo,'Enlace persistido en BD')
r=requests.get(B+'/inscripcion.php?p=register&id=1')
check(grupo in r.text and 'Únete a nuestro grupo' in r.text,'Botón del grupo en inscripción independiente')
check('data-cuenta-inscripcion' in r.text and 'data-cierre=' in r.text and '23:59' in r.text and 'Hora de Bolivia' in r.text,'Cuenta regresiva ligada al cierre de inscripción')
check('data-unidad="días"' in r.text and 'data-unidad="segundos"' in r.text,'Reloj completo de días a segundos')
r=requests.get(B+'/inscripcion.php?p=receipt&t='+'a'*48)
check(grupo in r.text and 'Únete a nuestro grupo' in r.text,'Botón del grupo en comprobante')
check(r.text.index('class="comunidad-unior no-print"')>r.text.index('</article>'),'Comunidad fuera del documento imprimible')
check('data-grupo-qr="'+grupo+'"' in r.text and 'Únete a nuestro grupo de WhatsApp' in r.text,'QR apunta al grupo y muestra invitación')
check('https://www.facebook.com/UniversidadPrivadaDeOruro/?locale=es_LA' in r.text and 'https://www.tiktok.com/@unior_oruro' in r.text,'Redes oficiales en comprobante')
check('rel="noopener noreferrer"' in r.text and 'target="_blank"' in r.text,'Enlaces externos abren nueva ventana')
check('enlace web válido' in post({**data,'enlace_grupo_whatsapp':'javascript:alert(1)'}).text,'Esquema ejecutable rechazado')
with c.cursor() as cur:
 cur.execute('SELECT enlace_grupo_whatsapp FROM eventos WHERE id=1');check(cur.fetchone()[0]==grupo,'URL inválida no cambia el enlace anterior')
check('Evento guardado' in post({**data,'enlace_grupo_whatsapp':'https://ejemplo.org/grupo?invitacion=123'}).text,'Acepta enlace web de cualquier dominio')
r=requests.get(B+'/inscripcion.php?p=receipt&t='+'a'*48)
check('data-grupo-qr="https://ejemplo.org/grupo?invitacion=123"' in r.text,'QR utiliza exactamente el enlace genérico guardado')
check('Evento guardado' in post({**data,'enlace_grupo_whatsapp':''}).text,'Permite quitar grupo')
r=requests.get(B+'/inscripcion.php?p=receipt&t='+'a'*48)
check('Únete a nuestro grupo' not in r.text and 'TikTok' in r.text,'Sin grupo oculta botón y conserva redes')
print('PASS comunidad',n,'checks')
