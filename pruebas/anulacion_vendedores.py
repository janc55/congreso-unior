"""Run against a NEW empty test database only. Requires Python requests.
BASE_URL=http://127.0.0.1:8080 SETUP_KEY=... python3 pruebas/integracion.py
Creates fixture events/users/participants. Never run on production.
"""
import os, re, datetime, requests, io
B=os.environ.get('BASE_URL','http://127.0.0.1:8080').rstrip('/')
KEY=os.environ['SETUP_KEY']; checks=[]
def check(condition, message):
    assert condition, message
    checks.append(message); print('OK', message)
def get(s,p='home',**kw):
    r=s.get(B+'/inicio.php',params={'p':p,**kw}); check(r.status_code==200,'GET '+p); return r

def post(s,p,data,files=None,**kw):
    page=get(s,p,**kw)
    csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',page.text).group(1)
    r=s.post(B+'/inicio.php',params={'p':p,**kw},data={**data,'csrf':csrf},files=files)
    check(r.status_code==200,'POST '+data['action']); return r
admin=requests.Session(); public=requests.Session(); seller=requests.Session()
r=admin.get(B+'/instalar.php');check(r.status_code==200,'Installer available')
csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',r.text).group(1)
r=admin.post(B+'/instalar.php',data={'csrf':csrf,'modo':'nuevo','servidor':'127.0.0.1','puerto':'3309','base_datos':'unior_nueva_v4','usuario_bd':'root','clave_bd':'','direccion_sitio':B,'administrador_nombre':'Administrador de prueba','administrador_usuario':'testadmin','administrador_clave':'TestAdmin!2026'})
check('Sistema instalado' in r.text,'Installation completed')
check('Actualizar el anterior' in admin.get(B+'/instalar.php').text,'Installer offers protected upgrade')
post(admin,'login',{'action':'login','username':'testadmin','password':'TestAdmin!2026'})
check('Administrador de prueba' in get(admin).text,'Admin logged in')
today=datetime.date.today(); start=today.isoformat(); end=(today+datetime.timedelta(days=10)).isoformat()
event={'action':'event','name':'Congreso de prueba','type':'Congreso','venue':'UNIOR','description':'Verificación integral','starts_at':start+'T09:00','ends_at':end+'T18:00','registration_start':start,'registration_end':end,'capacity':'5','active':'1','cert_x':'50','cert_y':'50','cert_size':'32','cert_color':'#000080'}
r=post(admin,'event',event);eid=int(re.search(r'id=(\d+)',r.url).group(1))
for cat,price in [('Estudiante','50'),('Profesional','100'),('UNIOR','30')]:
    post(admin,'event',{'action':'tariff','event_id':eid,'category':cat,'starts_on':start,'ends_on':end,'price':price},id=eid)
r=post(admin,'event',{'action':'tariff','event_id':eid,'category':'Estudiante','starts_on':start,'ends_on':end,'price':'1'},id=eid)
check('superponen' in r.text,'Overlapping tariff rejected')
post(admin,'event',{'action':'material','event_id':eid,'name':'Bolígrafo','stock':'2','allowance':'1','unit_cost':'3'},id=eid)
r=get(admin,'event',id=eid);mid=int(re.search(r'name="material_id" value="(\d+)"',r.text).group(1))
post(admin,'users',{'action':'user','name':'Vendedor de prueba','username':'seller','password':'TestSeller!2026','role':'vendedor','active':'1'})
post(seller,'login',{'action':'login','username':'seller','password':'TestSeller!2026'})
for p in ['events','users','dashboard','reports','finance','audit']:
    check(seller.get(B+'/inicio.php',params={'p':p}).status_code==403,'Seller blocked from '+p)
def register(s,ci,cat='Estudiante',paid=False,event_id=eid):
    data={'action':'register','event_id':event_id,'first_name':'Ana','last_name':'Zelaya','ci':ci,'city':'Oruro','phone':'70000000','email':'ana@example.com','category':cat,'institution':'UNIOR'}
    if paid:data.update(paid_now='1',payment_method='Efectivo')
    r=post(s,'register',data,id=event_id)
    return r,re.search(r't=([a-f0-9]{48})',r.url).group(1) if 't=' in r.url else None

import pymysql,subprocess
conn=pymysql.connect(host='127.0.0.1',port=3309,user='root',database='unior_nueva_v4',autocommit=True)
def row(sql,args=()):
 with conn.cursor(pymysql.cursors.DictCursor) as c:c.execute(sql,args);return c.fetchone()
with conn.cursor() as c:
 c.execute('UPDATE eventos SET cupos=50 WHERE id=%s',(eid,));c.execute('DROP TABLE cambios_inscripcion');c.execute('ALTER TABLE inscripciones DROP COLUMN estado_previo_anulacion')
code="define('UNIOR_INICIADO',true);require 'unior-espanol/aplicacion/base_espanol.php';require 'unior-espanol/aplicacion/migrar_base.php';$db=new PDO('mysql:host=127.0.0.1;port=3309;dbname=unior_nueva_v4;charset=utf8mb4','root','');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);migrarBase($db);migrarBase($db);"
subprocess.run(['runtime-php/usr/bin/php8.3','-n','-d','extension_dir='+os.path.abspath('runtime-php/usr/lib/php/20230831'),'-d','extension=pdo','-d','extension=mysqlnd','-d','extension=pdo_mysql','-r',code],check=True)
check(row("SHOW COLUMNS FROM inscripciones LIKE 'estado_previo_anulacion'") is not None,'Updater adds state restoration column and history idempotently')
r,token=register(public,'ONLINE-VERIFY')
pdf=b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF'
post(public,'receipt',{'action':'proof','token':token,'tipo_pago':'adelanto','importe_abono':'20','reference':'SELLER-VERIF-01'},files={'proof':('recibo.pdf',pdf,'application/pdf')},t=token)
r=get(seller,'online');check('SELLER-VERIF-01' in r.text,'Seller sees online review queue')
r=post(seller,'participant',{'action':'review','token':token,'status':'Pagado'},t=token)
x=row("SELECT * FROM inscripciones WHERE codigo_privado=%s",(token,));check(x['estado']=='Pagado' and float(x['monto_cobrado'])==20,'Seller confirms online advance')
check(row('SELECT usuario FROM usuarios WHERE id=%s',(x['id_revisor'],))['usuario']=='seller','Reviewer identity recorded')
page=get(seller,'participant',t=token);csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',page.text).group(1)
r=seller.post(B+'/inicio.php',params={'p':'participant','t':token},data={'csrf':csrf,'action':'estado_inscripcion','token':token,'operacion_estado':'anular','motivo_estado':'Intento vendedor'})
check(r.status_code==403 and row('SELECT estado FROM inscripciones WHERE codigo_privado=%s',(token,))['estado']=='Pagado','Seller cannot directly cancel enrollment')
check(seller.get(B+'/inicio.php',params={'p':'gestion_inscripciones'}).status_code==403,'Seller cannot access cancellation module')
r=post(seller,'participant',{'action':'review','token':token,'status':'Anulado','review_note':'No permitido'},t=token);check(row('SELECT estado FROM inscripciones WHERE codigo_privado=%s',(token,))['estado']=='Pagado','Seller cannot cancel through review action')
r=post(admin,'participant',{'action':'estado_inscripcion','token':token,'operacion_estado':'anular','motivo_estado':''},t=token);check('motivo' in r.text and row('SELECT estado FROM inscripciones WHERE codigo_privado=%s',(token,))['estado']=='Pagado','Cancellation requires reason')
r=post(admin,'participant',{'action':'estado_inscripcion','token':token,'operacion_estado':'anular','motivo_estado':'Registro equivocado'},t=token)
x=row('SELECT * FROM inscripciones WHERE codigo_privado=%s',(token,));check(x['estado']=='Anulado' and x['estado_previo_anulacion']=='Pagado' and float(x['monto_cobrado'])==20,'Cancellation preserves paid amount and prior state')
check('Registro equivocado' in r.text and 'Historial de anulación' in r.text,'Admin sees cancellation history')
check('Total generado: Bs 20.00' in get(admin,'finance').text,'Cancelled enrollment does not erase received income')
r=post(seller,'participant',{'action':'review','token':token,'status':'Pagado'},t=token);check(row('SELECT estado FROM inscripciones WHERE codigo_privado=%s',(token,))['estado']=='Anulado','Seller cannot reactivate via payment verification')
r=post(admin,'participant',{'action':'estado_inscripcion','token':token,'operacion_estado':'reactivar','motivo_estado':'Corrección del error'},t=token);check(row('SELECT estado FROM inscripciones WHERE codigo_privado=%s',(token,))['estado']=='Pagado','Admin restores prior confirmed state')
check(row('SELECT COUNT(*) n FROM cambios_inscripcion WHERE id_inscripcion=%s',(x['id'],))['n']==2,'Both changes retain reason and actor')
r,unpaid=register(public,'UNPAID-RESTORE');post(admin,'participant',{'action':'estado_inscripcion','token':unpaid,'operacion_estado':'anular','motivo_estado':'Error de datos'},t=unpaid);post(admin,'participant',{'action':'estado_inscripcion','token':unpaid,'operacion_estado':'reactivar','motivo_estado':'Datos revisados'},t=unpaid)
check(row('SELECT estado FROM inscripciones WHERE codigo_privado=%s',(unpaid,))['estado']=='Pendiente','Reactivation does not mark unpaid enrollment paid')
# Seller may reject an online voucher but cannot review a presential enrollment.
post(public,'receipt',{'action':'proof','token':unpaid,'tipo_pago':'completo','reference':'SELLER-REJECT'},files={'proof':('recibo.pdf',pdf,'application/pdf')},t=unpaid)
post(seller,'participant',{'action':'review','token':unpaid,'status':'Rechazado','review_note':'No aparece en banco'},t=unpaid);check(row('SELECT estado FROM inscripciones WHERE codigo_privado=%s',(unpaid,))['estado']=='Rechazado','Seller rejects online voucher with reason')
r,presencial=register(admin,'STAFF-PENDING')
page=get(seller,'participant',t=presencial);csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',page.text).group(1)
r=seller.post(B+'/inicio.php',params={'p':'participant','t':presencial},data={'csrf':csrf,'action':'review','token':presencial,'status':'Pagado'})
check(r.status_code==403,'Seller verification permission restricted to online registrations')
g={'action':'register','event_id':eid,'grupo_activo':'1','first_name':'Principal','last_name':'Grupo','ci':'GROUP-PRINCIPAL','city':'Oruro','phone':'70000000','email':'g@example.com','category':'Estudiante'}
for i in range(4):
 for k,v in {'first_name':'Persona','last_name':str(i),'ci':'GROUP-'+str(i),'city':'Oruro','phone':'70000000','email':'g@example.com','category':'Estudiante'}.items():g[f'integrantes[{i}][{k}]']=v
r=post(public,'register',g,id=eid);gt=re.search(r't=([a-f0-9]{48})',r.url).group(1)
post(admin,'participant',{'action':'estado_inscripcion','token':gt,'operacion_estado':'anular','motivo_estado':'Grupo registrado por error'},t=gt)
grp=row('SELECT codigo_grupo FROM inscripciones WHERE codigo_privado=%s',(gt,))['codigo_grupo'];check(row("SELECT COUNT(*) n FROM inscripciones WHERE codigo_grupo=%s AND estado='Anulado'",(grp,))['n']==5,'Group cancellation covers all five')
with conn.cursor() as c:c.execute("UPDATE eventos SET cupos=(SELECT COUNT(*) FROM inscripciones WHERE id_evento=%s AND estado<>'Anulado') WHERE id=%s",(eid,eid))
r=post(admin,'participant',{'action':'estado_inscripcion','token':gt,'operacion_estado':'reactivar','motivo_estado':'Revisado y corregido'},t=gt);check('cupos suficientes' in r.text and row("SELECT COUNT(*) n FROM inscripciones WHERE codigo_grupo=%s AND estado='Anulado'",(grp,))['n']==5,'Reactivation checks capacity atomically')
with conn.cursor() as c:c.execute('UPDATE eventos SET cupos=50 WHERE id=%s',(eid,))
post(admin,'participant',{'action':'estado_inscripcion','token':gt,'operacion_estado':'reactivar','motivo_estado':'Cupos disponibles y datos corregidos'},t=gt);check(row("SELECT COUNT(*) n FROM inscripciones WHERE codigo_grupo=%s AND estado='Pendiente'",(grp,))['n']==5,'Group restores all five without payment changes')
check('Anular / reactivar' in get(admin,'gestion_inscripciones').text,'Dedicated cancellation module available')

post(public,'receipt',{'action':'proof','token':gt,'tipo_pago':'adelanto','importe_abono':'50','reference':'SELLER-GROUP-REVIEW'},files={'proof':('recibo.pdf',pdf,'application/pdf')},t=gt)
post(seller,'participant',{'action':'review','token':gt,'status':'Pagado'},t=gt)
x=row("SELECT COUNT(*) n,SUM(monto_cobrado) cobrado FROM inscripciones WHERE codigo_grupo=%s AND estado='Pagado'",(grp,));check(x['n']==5 and float(x['cobrado'])==50,'Seller can verify shared online group advance')
print('CANCELLATION AND SELLER REVIEW CHECKS',len(checks))
