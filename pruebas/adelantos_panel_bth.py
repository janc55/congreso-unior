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
for p in ['events','users','dashboard','reports','finance','online','audit']:
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
# Simulate a previous installed enum and run the real updater twice.
with conn.cursor() as c:
 for table in ['tarifas','inscripciones']:c.execute("ALTER TABLE "+table+" MODIFY categoria ENUM('Estudiante','Profesional','UNIOR') NOT NULL")
code="define('UNIOR_INICIADO',true);require 'unior-espanol/aplicacion/base_espanol.php';require 'unior-espanol/aplicacion/migrar_base.php';$db=new PDO('mysql:host=127.0.0.1;port=3309;dbname=unior_nueva_v4;charset=utf8mb4','root','');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);migrarBase($db);migrarBase($db);"
subprocess.run(['runtime-php/usr/bin/php8.3','-n','-d','extension_dir='+os.path.abspath('runtime-php/usr/lib/php/20230831'),'-d','extension=pdo','-d','extension=mysqlnd','-d','extension=pdo_mysql','-r',code],check=True)
check('Estudiante BTH' in row("SHOW COLUMNS FROM inscripciones LIKE 'categoria'")['Type'],'Updater adds BTH without recreating registrations')
post(admin,'event',{'action':'tariff','event_id':eid,'category':'Estudiante BTH','starts_on':start,'ends_on':end,'price':'80'},id=eid)
r=get(admin,'register',id=eid)
check(r.text.index('value="Profesional"')<r.text.index('value="Estudiante BTH"')<r.text.index('value="UNIOR"'),'BTH appears after professional')
check('data-cobro-panel' in r.text and 'name="tipo_cobro"' in r.text,'Administrator registration has payment choice')
def panel_register(ci,kind='adelanto',amount='30',code='PANEL-01',staff=admin,extra=None):
 d={'action':'register','event_id':eid,'first_name':'Ana','last_name':'BTH','ci':ci,'city':'Oruro','phone':'70000000','email':'bth@example.com','category':'Estudiante BTH','paid_now':'1','payment_method':'Efectivo','reference':code,'tipo_cobro':kind,'importe_abono':amount}
 if extra:d.update(extra)
 return post(staff,'register',d,id=eid)
r=panel_register('BTH-001');check('RECIBO DE PAGO' in r.text and '30.00' in r.text,'Admin advance opens receipt for amount received')
x=row("SELECT * FROM inscripciones WHERE ci='BTH-001'");token=x['codigo_privado'];check(float(x['precio'])==80 and float(x['monto_cobrado'])==30,'BTH tariff80 with advance30 leaves50 balance')
r=get(admin,'participant',t=token);check('Completar pago' in r.text and '50.00' in r.text,'Participant panel offers exact balance completion')
r=post(admin,'participant',{'action':'cobrar_abono','token':token,'completar_pago':'1','importe_abono':'0.01','reference':'COMPLETE-01','payment_method':'Efectivo'},t=token)
check('RECIBO DE PAGO' in r.text and '50.00' in r.text,'Complete payment ignores tampered amount and receipts exact balance')
check(float(row("SELECT monto_cobrado FROM inscripciones WHERE ci='BTH-001'")['monto_cobrado'])==80,'Completion settles actual tariff')
r=panel_register('BTH-002',amount='80',code='PANEL-EQUAL');check('debe ser menor' in r.text and row("SELECT id FROM inscripciones WHERE ci='BTH-002'") is None,'Invalid advance rolls back registration')
r=panel_register('BTH-003',code='PANEL-01');check('ya está registrado' in r.text and row("SELECT id FROM inscripciones WHERE ci='BTH-003'") is None,'Duplicate code rolls back registration')
r=panel_register('BTH-004',kind='completo',amount='1',code='PANEL-FULL');check(float(row("SELECT monto_cobrado FROM inscripciones WHERE ci='BTH-004'")['monto_cobrado'])==80,'Full payment uses tariff instead of manipulated amount')
r=panel_register('BTH-SELLER',code='SELLER-ADV',staff=seller);check(row("SELECT id FROM inscripciones WHERE ci='BTH-SELLER'") is None,'Seller cannot submit unauthorized initial advance')
with conn.cursor() as c:c.execute('UPDATE eventos SET cupos=50 WHERE id=%s',(eid,))
g={'grupo_activo':'1'}
for i in range(4):
 for k,v in {'first_name':'Persona','last_name':str(i),'ci':'BTH-G-'+str(i),'city':'Oruro','phone':'70000000','email':'g@example.com','category':'Estudiante BTH'}.items():g[f'integrantes[{i}][{k}]']=v
r=panel_register('BTH-PRINCIPAL',amount='100',code='PANEL-GROUP',extra=g)
x=row("SELECT codigo_grupo FROM inscripciones WHERE ci='BTH-PRINCIPAL'")
gp=row('SELECT COUNT(*) n,SUM(precio) total,SUM(monto_cobrado) cobrado FROM inscripciones WHERE codigo_grupo=%s',(x['codigo_grupo'],))
check(gp['n']==5 and float(gp['total'])==320 and float(gp['cobrado'])==100,'Five BTH participants get discount and shared advance without duplicate income')
r=get(public,'register',id=eid);check('Estudiante BTH — Bs 80.00' in r.text and 'data-cobro-panel' not in r.text,'Public BTH registration present without administration payment controls')
check('Estudiante BTH' in get(admin,'reports').text,'Reports include BTH category')
print('PANEL ADVANCE AND BTH CHECKS',len(checks))
