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

import pymysql
conn=pymysql.connect(host='127.0.0.1',port=3309,user='root',database='unior_nueva_v4',autocommit=True)
def row(sql,args=()):
 with conn.cursor(pymysql.cursors.DictCursor) as c:c.execute(sql,args);return c.fetchone()
r,token=register(public,'PAGO-SALDO-ONLINE')
pdf=b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF'
post(public,'receipt',{'action':'proof','token':token,'tipo_pago':'adelanto','importe_abono':'20','reference':'SALDO-ADV-001'},files={'proof':('recibo.pdf',pdf,'application/pdf')},t=token)
post(admin,'participant',{'action':'review','token':token,'status':'Pagado'},t=token)
r=get(seller,'participant',t=token);check('Pagos y saldo' in r.text and 'Completar pago' in r.text and '30.00' in r.text,'Seller sees debt and collection controls on online enrollment')
r=post(seller,'participant',{'action':'cobrar_abono','token':token,'importe_abono':'10','payment_method':'Efectivo'},t=token)
check('RECIBO DE PAGO' in r.text and '10.00' in r.text,'Seller collects partial outstanding balance and issues receipt')
x=row('SELECT * FROM inscripciones WHERE codigo_privado=%s',(token,));check(float(x['monto_cobrado'])==30 and x['id_creador'] is None,'Collection updates paid amount without changing enrollment origin')
r=get(seller,'participant',t=token);check('Registrado / verificado por' in r.text and 'Vendedor de prueba' in r.text,'Payment history identifies collecting seller')
x=row('SELECT p.*,u.usuario FROM pagos_inscripcion p JOIN usuarios u ON u.id=p.usuario_verifica ORDER BY p.id DESC LIMIT 1');check(x['usuario']=='seller' and float(x['importe_abono'])==10,'Collector stored per payment')
r=post(seller,'participant',{'action':'cobrar_abono','token':token,'importe_abono':'21','payment_method':'Efectivo'},t=token);check('supera el saldo' in r.text and float(row('SELECT monto_cobrado FROM inscripciones WHERE codigo_privado=%s',(token,))['monto_cobrado'])==30,'Seller cannot overcharge remaining debt')
r=post(seller,'participant',{'action':'cobrar_abono','token':token,'completar_pago':'1','importe_abono':'0.01','payment_method':'Efectivo'},t=token);check('RECIBO DE PAGO' in r.text and '20.00' in r.text,'Seller completes exact remaining20 regardless of altered input')
check(float(row('SELECT monto_cobrado FROM inscripciones WHERE codigo_privado=%s',(token,))['monto_cobrado'])==50,'Complete payment settles full50')
r=get(seller,'participant',t=token);check('name="action" value="cobrar_abono"' not in r.text,'Settled enrollment hides collection form')
check('Pagos y saldo' not in get(public,'receipt',t=token).text,'Debt controls remain hidden in public receipt')
with conn.cursor() as c:c.execute('UPDATE eventos SET cupos=50 WHERE id=%s',(eid,))
r,stafftoken=register(admin,'PAGO-PRESENCIAL-PENDING')
r=post(seller,'participant',{'action':'cobrar_abono','token':stafftoken,'importe_abono':'15','payment_method':'Efectivo'},t=stafftoken)
check('RECIBO DE PAGO' in r.text,'Seller can receive pending payment for enrollment created by another user')
check(row('SELECT u.usuario FROM inscripciones r JOIN usuarios u ON u.id=r.id_creador WHERE r.codigo_privado=%s',(stafftoken,))['usuario']=='testadmin','Original registering user is preserved')
post(admin,'participant',{'action':'estado_inscripcion','token':stafftoken,'operacion_estado':'anular','motivo_estado':'Registro equivocado'},t=stafftoken)
r=post(seller,'participant',{'action':'cobrar_abono','token':stafftoken,'importe_abono':'5','payment_method':'Efectivo'},t=stafftoken);check('anulad' in r.text and float(row('SELECT monto_cobrado FROM inscripciones WHERE codigo_privado=%s',(stafftoken,))['monto_cobrado'])==15,'Cancelled enrollment cannot be charged by seller')
check(seller.get(B+'/inicio.php',params={'p':'gestion_inscripciones'}).status_code==403,'Cancellation remains admin only')
check(seller.get(B+'/inicio.php',params={'p':'finance'}).status_code==403,'General finance remains admin only')
print('SELLER BALANCE COLLECTION CHECKS',len(checks))
