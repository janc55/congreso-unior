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

r,token=register(public,'PRIV-001')
check(bool(token),'Individual online registration works')
def receipt_article(html):return re.search(r'<article.*?</article>',html,re.S).group(0)
def neutral(html):
 text=receipt_article(html)
 return not re.search(r'Pagado|CONFIRMADA|Confirmada|SUJETO A VERIFICACIÓN|PAGO VERIFICADO|ESTADO DEL REGISTRO',text)
check(neutral(r.text),'Pending receipt does not print payment/confirmation states')
check('Pagos y saldo' not in r.text,'Public receipt does not expose payment ledger')
r=get(admin,'participant',t=token);check('Pagos y saldo' in r.text,'Administrator sees payment ledger in panel')
r=get(seller,'participant',t=token);check('Pagos y saldo' not in r.text and 'Cobrar adelanto' not in r.text,'Seller cannot view administrator payment module')
r=get(admin,'receipt',t=token);check('Pagos y saldo' not in r.text,'Receipt has no ledger even for administrator')
pdf=b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF'
post(public,'receipt',{'action':'proof','token':token,'tipo_pago':'adelanto','importe_abono':'20','reference':'PRIV-TR-01'},files={'proof':('pago.pdf',pdf,'application/pdf')},t=token)
post(admin,'participant',{'action':'review','token':token,'status':'Pagado'},t=token)
r=get(public,'receipt',t=token);check(neutral(r.text),'Confirmed advance receipt remains neutral')
r=post(admin,'participant',{'action':'cobrar_abono','token':token,'importe_abono':'30','reference':'PRIV-CAJA-02','payment_method':'Efectivo'},t=token)
check('RECIBO DE PAGO' in r.text and neutral(r.text),'Payment receipt contains amount but no confirmation badge')
check('Saldo pendiente actual' not in receipt_article(r.text),'Public payment receipt omits private balance summary')
r=get(public,'register',id=eid);check('Estudiante — Bs 50.00' in r.text and 'Profesional — Bs 100.00' in r.text,'Student and professional tariffs shown together')
import pymysql
conn=pymysql.connect(host='127.0.0.1',port=3309,user='root',database='unior_nueva_v4',autocommit=True)
with conn.cursor() as c:c.execute("UPDATE tarifas SET hasta=DATE_SUB(CURDATE(),INTERVAL 1 DAY) WHERE categoria='Profesional'")
r=get(public,'register',id=eid)
check(re.search(r'<option value="Profesional"[^>]*disabled[^>]*>Profesional — Sin tarifa vigente',r.text) is not None,'Missing tariff is visible and disabled rather than hidden')
check('Estudiante — Bs 50.00' in r.text,'Student remains available after professional tariff expires')
check('data-campo="category"' in r.text and 'Profesional — Sin tarifa vigente' in r.text,'Group form uses same student and professional categories')
page=get(seller,'participant',t=token);csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',page.text).group(1)
r=seller.post(B+'/inicio.php',params={'p':'participant','t':token},data={'csrf':csrf,'action':'cobrar_abono','token':token,'importe_abono':'1','reference':'SELLER-X','payment_method':'Efectivo'})
check(r.status_code==403,'Direct seller POST cannot bypass administrator restriction')
print('PRIVACY AND CATEGORY TESTS',len(checks))
