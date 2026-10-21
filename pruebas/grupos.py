import requests,re,pymysql
B='http://127.0.0.1:8080';a=requests.Session();p=requests.Session();n=0
c=pymysql.connect(host='127.0.0.1',port=3309,user='root',database='legacy_unior_es_v21',autocommit=True)
def check(v,m):
 global n
 assert v,m
 n+=1;print('OK grupos',m)
def post(s,page,data,files=None):
 r=s.get(B+'/'+page);token=re.search('name="csrf" value="([a-f0-9]+)"',r.text).group(1)
 return s.post(B+'/'+page,data={'csrf':token,**data},files=files)
post(a,'inicio.php?p=login',{'action':'login','username':'testadmin','password':'TestAdmin!2026'})
check('data-activar-grupo' in p.get(B+'/inscripcion.php?p=register&id=1').text,'Formulario permite agregar integrantes')
def payload(count,prefix,categories=None):
 people=[]
 for i in range(count):people.append({'first_name':'Persona'+str(i),'last_name':'Grupo','ci':prefix+str(i),'city':'Oruro','phone':'70000000','email':'grupo@example.com','category':(categories or ['Estudiante']*count)[i],'tratamiento':'Lic.'})
 data={'action':'register','event_id':1,'grupo_activo':'1',**people[0]}
 for i,person in enumerate(people[1:]):
  for k,v in person.items():data[f'integrantes[{i}][{k}]']=v
 return data
r=post(p,'inscripcion.php?p=register&id=1',payload(5,'G5-'))
check('TOTAL A PAGAR POR EL GRUPO: Bs 200.00' in r.text,'Cinco estudiantes: 20% y total 200')
token=re.search('t=([a-f0-9]{48})',r.url).group(1)
with c.cursor() as cur:
 cur.execute("SELECT precio,precio_base,descuento_grupo,codigo_privado,codigo_certificado,codigo_grupo FROM inscripciones WHERE ci LIKE 'G5-%'");rows=cur.fetchall()
check(len(rows)==5 and all(float(x[0])==40 and float(x[1])==50 and float(x[2])==10 for x in rows),'Guarda tarifa original, descuento e importe individual')
check(len({x[3] for x in rows})==5 and len({x[4] for x in rows})==5 and len({x[5] for x in rows})==1,'QR privados y certificados únicos; grupo común')
r=post(p,'inscripcion.php?p=register&id=1',payload(4,'G4-'));check('exactamente 5 personas' in r.text,'Rechaza grupos de cuatro aunque manipulen el formulario')
with c.cursor() as cur:cur.execute("SELECT COUNT(*) FROM inscripciones WHERE ci LIKE 'G4-%'");check(cur.fetchone()[0]==0,'Grupo incompleto no registra ninguna persona')
# Mixed tariffs: 50 + 100 + 30 + 50 + 50, discounted total 224.
with c.cursor() as cur:
 for cat,price in [('Profesional',100),('UNIOR',30)]:cur.execute("INSERT INTO tarifas(id_evento,categoria,desde,hasta,precio) VALUES(1,%s,'2026-10-01','2026-10-31',%s)",(cat,price))
r=post(p,'inscripcion.php?p=register&id=1',payload(5,'GM-',['Estudiante','Profesional','UNIOR','Estudiante','Estudiante']));check('TOTAL A PAGAR POR EL GRUPO: Bs 224.00' in r.text,'Categorías mixtas aplican descuento a su propia tarifa')
bad=payload(5,'DUP-');bad['integrantes[0][ci]']='DUP-0';r=post(p,'inscripcion.php?p=register&id=1',bad)
check('marcados en rojo' in r.text,'Rechaza documentos repetidos')
with c.cursor() as cur:cur.execute("SELECT COUNT(*) FROM inscripciones WHERE ci LIKE 'DUP-%'");check(cur.fetchone()[0]==0,'Revierte todo el grupo ante error')
with c.cursor() as cur:
 cur.execute('SELECT COUNT(*) FROM inscripciones WHERE id_evento=1');used=cur.fetchone()[0];cur.execute('UPDATE eventos SET cupos=%s WHERE id=1',(used+4,))
r=post(p,'inscripcion.php?p=register&id=1',payload(5,'CUPO-'));check('cupos suficientes' in r.text,'Comprueba cupos para todo el grupo')
with c.cursor() as cur:cur.execute('UPDATE eventos SET cupos=0 WHERE id=1')
r=post(a,'inicio.php?p=participant&t='+token,{'action':'review','token':token,'status':'Pagado'});check('comprobante de todo el grupo' in r.text,'No confirma grupo online sin voucher')
pdf=b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF'
r=post(p,'inscripcion.php?p=receipt&t='+token,{'action':'proof','token':token,'reference':'GRUPAL-200'},files={'proof':('pago.pdf',pdf,'application/pdf')})
with c.cursor() as cur:cur.execute("SELECT COUNT(DISTINCT comprobante),COUNT(*) FROM inscripciones WHERE ci LIKE 'G5-%' AND estado='En revisión'");check(cur.fetchone()==(1,5),'Un comprobante enviado para los cinco')
r=post(a,'inicio.php?p=participant&t='+token,{'action':'review','token':token,'status':'Rechazado','review_note':'Falta verificar'})
with c.cursor() as cur:cur.execute("SELECT COUNT(*) FROM inscripciones WHERE ci LIKE 'G5-%' AND estado='Rechazado'");check(cur.fetchone()[0]==5,'Rechaza pago de todo el grupo')
post(p,'inscripcion.php?p=receipt&t='+token,{'action':'proof','token':token,'reference':'GRUPAL-200-CORREGIDO'},files={'proof':('pago.pdf',pdf,'application/pdf')})
r=post(a,'inicio.php?p=participant&t='+token,{'action':'review','token':token,'status':'Pagado','review_note':'Validado 200'})
with c.cursor() as cur:cur.execute("SELECT COUNT(*),SUM(precio) FROM inscripciones WHERE ci LIKE 'G5-%' AND estado='Pagado'");q=cur.fetchone();check(q[0]==5 and float(q[1])==200,'Confirma los cinco con importe neto correcto')
check('Grupo de 5' in a.get(B+'/inicio.php?p=online&status=Pagado').text,'Cola administrativa muestra total grupal')
check('Confirmar, rechazar o anular' in r.text,'Ficha informa que decisión aplica a todo el grupo')
# Public paid_now must not bypass verification for groups.
d=payload(5,'PUBLICO-');d['paid_now']='1';post(p,'inscripcion.php?p=register&id=1',d)
with c.cursor() as cur:cur.execute("SELECT COUNT(*) FROM inscripciones WHERE ci LIKE 'PUBLICO-%' AND estado='Pendiente'");check(cur.fetchone()[0]==5,'Portal público no puede marcar grupos pagados')
# Group below minimum can be paid in person by staff without proof.
d=payload(5,'CAJA-');d.update(paid_now='1',payment_method='Efectivo');r=post(a,'inicio.php?p=register&id=1',d)
with c.cursor() as cur:cur.execute("SELECT COUNT(*),SUM(precio) FROM inscripciones WHERE ci LIKE 'CAJA-%' AND estado='Pagado'");q=cur.fetchone();check(q[0]==5 and float(q[1])==200,'Inscripción grupal presencial con cobro')
r=post(p,'inscripcion.php?p=register&id=1',payload(6,'SEIS-'));check('exactamente 5 personas' in r.text,'Rechaza grupos de seis')
d=payload(5,'INCOMPLETO-');del d['integrantes[3][email]'];r=post(p,'inscripcion.php?p=register&id=1',d);check('Complete email' in r.text,'Valida datos obligatorios de los cinco en servidor')
with c.cursor() as cur:cur.execute("SELECT COUNT(*) FROM inscripciones WHERE ci LIKE 'INCOMPLETO-%'");check(cur.fetchone()[0]==0,'Error en quinto participante revierte todo el registro')
d=payload(1,'ACTIVO-SIN-EXTRAS-');r=post(p,'inscripcion.php?p=register&id=1',d);check('exactamente 5 personas' in r.text,'Modo grupal no permite principal sin integrantes')
d=payload(1,'INDIVIDUAL-');d['grupo_activo']='0';r=post(p,'inscripcion.php?p=register&id=1',d)
with c.cursor() as cur:cur.execute("SELECT precio,codigo_grupo FROM inscripciones WHERE ci='INDIVIDUAL-0'");q=cur.fetchone();check(float(q[0])==50 and q[1] is None,'Grupo desactivado registra una persona sin descuento')
print('PASS grupos',n,'checks')
