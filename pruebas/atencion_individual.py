import requests,re,pymysql
from pathlib import Path
B='http://127.0.0.1:8080';a=requests.Session();s=requests.Session();c=pymysql.connect(host='127.0.0.1',port=3309,user='root',database='legacy_unior_es_v29',autocommit=True);n=0

def check(v,m):
 global n
 assert v,m
 n+=1;print('OK individual',m)
def post(session,page,data,redirect=True):
 r=session.get(B+'/'+page);csrf=re.search('name="csrf" value="([a-f0-9]+)"',r.text).group(1)
 return session.post(B+'/'+page,data={'csrf':csrf,**data},allow_redirects=redirect)
post(a,'inicio.php?p=login',{'action':'login','username':'testadmin','password':'TestAdmin!2026'})
post(a,'inicio.php?p=users',{'action':'user','name':'Vendedor atención','username':'atencion','password':'Atencion!2026','role':'vendedor','active':1})
post(s,'inicio.php?p=login',{'action':'login','username':'atencion','password':'Atencion!2026'})
with c.cursor() as cur:
 cur.execute("SELECT id,codigo_privado,nombres FROM inscripciones WHERE ci LIKE 'G5-%' ORDER BY id");personas=cur.fetchall()
primero=personas[0][1];segundo=personas[1][1]
r=s.get(B+'/inicio.php?p=participant&t='+primero)
check('Integrantes del grupo' in r.text and 'Atender a esta persona' in r.text,'Ficha permite seleccionar cada integrante')
check(all(t in r.text for i,t,name in personas),'Cada integrante tiene enlaces y QR propios')
post(s,'inicio.php?p=participant&t='+primero,{'action':'attend','token':primero})
with c.cursor() as cur:
 cur.execute("SELECT COUNT(*) FROM inscripciones WHERE ci LIKE 'G5-%' AND fecha_asistencia IS NOT NULL");check(cur.fetchone()[0]==1,'Asistencia de un integrante no marca a los otros cuatro')
 cur.execute('SELECT fecha_asistencia FROM inscripciones WHERE codigo_privado=%s',(primero,));fecha=cur.fetchone()[0]
post(s,'inicio.php?p=participant&t='+primero,{'action':'attend','token':primero})
with c.cursor() as cur:cur.execute('SELECT fecha_asistencia FROM inscripciones WHERE codigo_privado=%s',(primero,));check(cur.fetchone()[0]==fecha,'Repetir asistencia mantiene fecha original')
r=post(s,'inicio.php?p=participant&t='+primero,{'action':'deliver','token':primero,'material_id':1,'quantity':1})
with c.cursor() as cur:
 cur.execute("SELECT r.ci,COALESCE(SUM(e.cantidad),0) FROM inscripciones r LEFT JOIN entregas e ON e.id_inscripcion=r.id WHERE r.ci LIKE 'G5-%' GROUP BY r.id ORDER BY r.id");rows=cur.fetchall();check(rows[0][1]==1 and all(x[1]==0 for x in rows[1:]),'Material entregado solo a la persona seleccionada')
r=post(s,'inicio.php?p=participant&t='+primero,{'action':'deliver','token':primero,'material_id':1,'quantity':1});check('Ya recogió' in r.text,'No duplica material de la misma persona')
r=post(s,'inicio.php?p=participant&t='+segundo,{'action':'deliver','token':segundo,'material_id':1,'quantity':1});check('Operación registrada' in r.text,'Otro integrante puede retirar su propio material')
with c.cursor() as cur:
 cur.execute("SELECT r.ci,COALESCE(SUM(e.cantidad),0) FROM inscripciones r LEFT JOIN entregas e ON e.id_inscripcion=r.id WHERE r.ci LIKE 'G5-%' GROUP BY r.id ORDER BY r.id");rows=cur.fetchall();check([int(x[1]) for x in rows]==[1,1,0,0,0],'Historial y cantidades independientes entre cinco integrantes')
r=post(s,'inicio.php?p=participant&t='+primero,{'action':'issue','token':primero})
with c.cursor() as cur:cur.execute("SELECT COUNT(*) FROM inscripciones WHERE ci LIKE 'G5-%' AND fecha_certificado IS NOT NULL");check(cur.fetchone()[0]==1,'Certificado solo para la persona con asistencia')
r=s.get(B+'/inicio.php?p=receipt&t='+segundo+'&individual=1&imprimir=1');articulo=re.search(r'<article.*?</article>',r.text,re.S).group(0)
check(personas[1][2] in articulo and personas[0][2] not in articulo,'Recibo individual muestra solo a su titular')
check('Bs 40.00' in articulo and 'TOTAL A PAGAR POR EL GRUPO' not in articulo,'Recibo individual muestra su importe sin total grupal')
check('p=participant&amp;t='+segundo in articulo or 'p=participant&t='+segundo in articulo,'QR del recibo corresponde a su titular')
check('data-imprimir-recibo="1"' in articulo and 'recursos/recibos.js' in r.text,'Recibo individual permite impresión automática')
# A cash registration from the admin panel must issue its receipt immediately.
datos={'action':'register','event_id':1,'first_name':'Recibo','last_name':'Panel','ci':'RECIBO-PANEL-1','city':'Oruro','phone':'70000000','email':'recibo@example.com','category':'Estudiante','paid_now':1,'payment_method':'Efectivo'}
r=post(a,'inicio.php?p=register&id=1',datos,False);check(r.status_code==303 and 'imprimir=1' in r.headers.get('Location',''),'Panel redirige al recibo para imprimir al guardar')
r=a.get(r.headers['Location']);check('RECIBO DE INSCRIPCIÓN' in r.text and 'data-imprimir-recibo="1"' in r.text,'Recibo presencial pagado se identifica y abre impresión')
# Public online inscriptions never invoke automatic printing.
p=requests.Session();datos.update(ci='RECIBO-PUBLICO-1');datos.pop('paid_now');r=post(p,'inscripcion.php?p=register&id=1',datos,False);check('imprimir=1' not in r.headers.get('Location',''),'Registro público no dispara impresión automática')
check('Registrar e imprimir recibo' in a.get(B+'/inicio.php?p=register&id=1').text,'Botón del panel informa que genera recibo')
# Preserve an exact HTML copy only for local print QA.
Path('qa-recibo-individual.html').write_text(s.get(B+'/inicio.php?p=receipt&t='+segundo+'&individual=1').text)
print('PASS atención individual',n,'checks')
