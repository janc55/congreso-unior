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

import pymysql
conn=pymysql.connect(host='127.0.0.1',port=3309,user='root',database='unior_nueva_v4',autocommit=True)
def row(sql,args=()):
 with conn.cursor(pymysql.cursors.DictCursor) as c:c.execute(sql,args);return c.fetchone()
with conn.cursor() as c:c.execute('UPDATE eventos SET cupos=50 WHERE id=%s',(eid,))
pdf=b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF'
def inscribir(staff,ci,amount='20',method='Efectivo',ref='',proof=False,extra=None):
 d={'action':'register','event_id':eid,'first_name':'Persona','last_name':ci,'ci':ci,'city':'Oruro','phone':'70000000','email':'p@example.com','category':'Estudiante','carrera':'Sistemas','paid_now':'1','tipo_cobro':'adelanto','importe_abono':amount,'payment_method':method,'reference':ref}
 if extra:d.update(extra)
 return post(staff,'register',d,files={'comprobante_presencial':('recibo.pdf',pdf,'application/pdf')} if proof else None,id=eid)
r=get(seller,'register',id=eid);check('name="tipo_cobro"' in r.text and 'name="comprobante_presencial"' in r.text,'Seller sees advance and upload controls')
r=inscribir(seller,'SELLER-CASH');check('RECIBO DE PAGO' in r.text and '20.00' in r.text,'Seller registers cash advance without code or voucher')
x=row("SELECT * FROM inscripciones WHERE ci='SELLER-CASH'");token=x['codigo_privado'];sellerid=x['id_creador'];check(float(x['monto_cobrado'])==20 and x['comprobante'] is None and x['referencia'] is None,'Cash has actual advance with optional evidence')
check(row("SELECT usuario FROM usuarios WHERE id=%s",(sellerid,))['usuario']=='seller','Registration creator comes from authenticated seller')
r=inscribir(admin,'ADMIN-CASH',amount='10');check('RECIBO DE PAGO' in r.text,'Admin registers cash advance without code or voucher')
r=inscribir(admin,'QR-NO-PROOF',method='QR/Transferencia',ref='BANK-NOFILE');check('Adjunte el comprobante' in r.text and row("SELECT id FROM inscripciones WHERE ci='QR-NO-PROOF'") is None,'Noncash missing voucher rejected atomically')
r=inscribir(seller,'SELLER-QR',method='QR/Transferencia',ref='BANK-001',proof=True);check('RECIBO DE PAGO' in r.text,'Seller registers QR advance with code and uploaded receipt')
x=row("SELECT * FROM inscripciones WHERE ci='SELLER-QR'");check(bool(x['comprobante']),'Presential voucher saved and attached to enrollment')
check(bool(row("SELECT archivo_abono FROM pagos_inscripcion WHERE codigo_transaccion='BANK-001'")['archivo_abono']),'Voucher retained in payment history')
r=inscribir(admin,'QR-DUP',method='QR/Transferencia',ref='bank-001',proof=True);check('ya está registrado' in r.text and row("SELECT id FROM inscripciones WHERE ci='QR-DUP'") is None,'Bank duplicate code still prevented')
r=get(seller,'mis_inscripciones');check('SELLER-CASH' in r.text and 'SELLER-QR' in r.text and 'ADMIN-CASH' not in r.text,'Seller report lists only own registrations')
r=get(seller,'mis_inscripciones',usuario_id='0');check('ADMIN-CASH' not in r.text,'Seller cannot bypass ownership by selecting all users')
r=get(admin,'mis_inscripciones',usuario_id='0');check('ADMIN-CASH' in r.text and 'SELLER-CASH' in r.text,'Administrator can report every creator')
r=get(admin,'mis_inscripciones',usuario_id=sellerid);check('ADMIN-CASH' not in r.text and 'SELLER-QR' in r.text,'Admin report filters selected seller')
r=seller.get(B+'/inicio.php',params={'p':'mis_inscripciones','usuario_id':'0','exportar':'csv'});check('ADMIN-CASH' not in r.text and 'SELLER-CASH' in r.text,'CSV export enforces same ownership')
r=get(admin,'participant',t=token);check('Inscrito por:' in r.text and 'Vendedor de prueba' in r.text,'Participant file identifies registering user')
r=post(admin,'participant',{'action':'cobrar_abono','token':token,'completar_pago':'1','payment_method':'Efectivo'},t=token);check('RECIBO DE PAGO' in r.text and '30.00' in r.text,'Cash balance can be completed without code and voucher')
check(float(row("SELECT monto_cobrado FROM inscripciones WHERE ci='SELLER-CASH'")['monto_cobrado'])==50,'Final cash payment settles actual debt')
g={'grupo_activo':'1'}
for i in range(4):
 for k,v in {'first_name':'Grupo','last_name':str(i),'ci':'GROUP-SELLER-'+str(i),'city':'Oruro','phone':'70000000','email':'g@example.com','category':'Estudiante','carrera':'Derecho'}.items():g[f'integrantes[{i}][{k}]']=v
r=inscribir(seller,'SELLER-GROUP',amount='50',extra=g);check('RECIBO DE PAGO' in r.text,'Seller registers group cash advance')
x=row("SELECT codigo_grupo FROM inscripciones WHERE ci='SELLER-GROUP'");gstats=row('SELECT COUNT(*) n,COUNT(DISTINCT id_creador) autores,SUM(monto_cobrado) cobrado FROM inscripciones WHERE codigo_grupo=%s',(x['codigo_grupo'],));check(gstats['n']==5 and gstats['autores']==1 and float(gstats['cobrado'])==50,'Five registrations share authenticated creator and exact advance')
check(public.get(B+'/inscripcion.php',params={'p':'mis_inscripciones'}).status_code==404,'Public portal cannot access user reports')
print('PRESENTIAL USER REPORT CHECKS',len(checks))
