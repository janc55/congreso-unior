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
r,token=register(public,'TEST-001')
check(token and 'Bs 50.00' in r.text and 'Pendiente' in r.text,'Online registration locks correct price')
check('Ana Zelaya' in r.text,'Receipt participant data')
r=post(public,'receipt',{'action':'proof','token':token},files={'proof':('fake.php',b'<?php echo 1;?>','application/x-php')},t=token)
check('Use JPG' in r.text,'Executable upload rejected')
check(public.get(B+'/inicio.php',params={'p':'participant','t':token}).url.endswith('p=login'),'Public participant QR requires staff login')
r=post(seller,'participant',{'action':'deliver','token':token,'material_id':mid,'quantity':'1'},t=token)
check('pago confirmado' in r.text,'Unpaid participant cannot collect')
r=post(admin,'participant',{'action':'review','token':token,'status':'Pagado','review_note':'Verificado'},t=token)
check('debe adjuntarse' in r.text,'Online registration without voucher cannot be confirmed')
pdf=b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF'
r=post(public,'receipt',{'action':'proof','token':token,'reference':'TX-001'},files={'proof':('voucher.pdf',pdf,'application/pdf')},t=token)
check('En revisión' in r.text,'Online voucher sent for review')
r=get(admin,'online');check('TX-001' in r.text,'Dedicated online verification queue')
r=post(admin,'participant',{'action':'review','token':token,'status':'Rechazado','review_note':'No figura en el banco'},t=token)
check('Rechazado' in r.text,'Voucher rejected')
r=post(public,'receipt',{'action':'proof','token':token,'reference':'TX-002'},files={'proof':('voucher.pdf',pdf,'application/pdf')},t=token)
check('En revisión' in r.text,'Rejected voucher can be resubmitted')
r=post(admin,'participant',{'action':'review','token':token,'status':'Pagado','review_note':'Pago verificado en el banco'},t=token)
check('Pagado' in r.text,'Admin confirms payment and registration')
r=post(seller,'participant',{'action':'issue','token':token},t=token)
check('primero la asistencia' in r.text,'Certificate requires attendance')
post(seller,'participant',{'action':'attend','token':token},t=token)
r=post(seller,'participant',{'action':'deliver','token':token,'material_id':mid,'quantity':'1'},t=token)
check('Operación registrada' in r.text,'Seller delivers material')
r=post(seller,'participant',{'action':'deliver','token':token,'material_id':mid,'quantity':'1'},t=token)
check('Ya recogió' in r.text,'Duplicate delivery rejected')
r=post(seller,'participant',{'action':'issue','token':token},t=token)
check('CERTIFICADO DE PARTICIPACIÓN' in r.text,'Certificate printed with default background')
cert=re.search(r'p=verify&c=([a-f0-9]{48})',r.text).group(1)
r=get(public,'verify',c=cert);check('AUTÉNTICO' in r.text and 'Certificados emitidos' in r.text,'Unique QR validates and lists participant certificates')
check(public.get(B+'/inicio.php',params={'p':'verify','c':'a'*48}).status_code==404,'Invented certificate rejected')
r=get(seller,'certificate',t=token,bg='0');check('CERTIFICADO DE PARTICIPACIÓN' not in r.text and 'class="name"' in r.text,'Name only print mode')
r,t2=register(seller,'TEST-002','Profesional',True);check('Bs 100.00' in r.text and 'Pagado' in r.text,'Seller collects presencial tariff')
post(seller,'participant',{'action':'deliver','token':t2,'material_id':mid,'quantity':'1'},t=t2)
r,t3=register(seller,'TEST-003','UNIOR',True);check('Bs 30.00' in r.text,'UNIOR category price')
r=post(seller,'participant',{'action':'deliver','token':t3,'material_id':mid,'quantity':'1'},t=t3);check('Existencias insuficientes' in r.text,'Stock exhaustion enforced')
r,_=register(public,'TEST-001');check('marcados en rojo' in r.text and 'is-invalid' in r.text,'Duplicate event CI rejected')
post(admin,'dashboard',{'action':'expense','event_id':eid,'description':'Alquiler','amount':'20','spent_on':start},event_id=eid)
r=get(admin,'finance',event_id=eid);check('Bs 180.00' in r.text and 'Bs 26.00' in r.text and 'Bs 154.00' in r.text,'Per event income expenses and net match')
r=get(admin,'finance',event_id=eid,export='csv');check('Gasto total Bs' in r.text,'Finance CSV export')
r=get(admin,'reports',event_id=eid,material_id=mid,delivery='none',status='Pagado',export='csv');check('TEST-003' in r.text and 'TEST-001' not in r.text,'Report of those who did not collect')
r=get(admin,'reports',event_id=eid,material_id=mid,delivery='complete',export='csv');check('TEST-001' in r.text and 'TEST-002' in r.text and 'TEST-003' not in r.text,'Report of complete deliveries')
r=seller.post(B+'/inicio.php',params={'p':'users'},data={'csrf':re.search(r'name="csrf" value="([a-f0-9]+)"',get(seller).text).group(1),'action':'user','username':'bad','name':'Bad','password':'FakePass123','role':'admin'})
check(r.status_code==403,'Seller cannot bypass role via POST')
check(admin.post(B+'/inicio.php',data={'action':'event'}).status_code==419,'CSRF rejected')
# Second event / certificate for the same person: same CI links certificates.
event['name']='Taller adicional';event['type']='Taller';r=post(admin,'event',event);eid2=int(re.search(r'id=(\d+)',r.url).group(1))
post(admin,'event',{'action':'tariff','event_id':eid2,'category':'Estudiante','starts_on':start,'ends_on':end,'price':'10'},id=eid2)
r,t4=register(seller,'TEST-001',paid=True,event_id=eid2)
post(seller,'participant',{'action':'attend','token':t4},t=t4)
post(seller,'participant',{'action':'issue','token':t4},t=t4)
r=get(public,'verify',c=cert);check('Taller adicional' in r.text and 'Congreso de prueba' in r.text,'QR shows all certificates for the same participant')
# Nonexistent event, capacity and date period boundaries.
event['name']='Curso de cupo único';event['type']='Curso';event['capacity']='1';r=post(admin,'event',event);eid3=int(re.search(r'id=(\d+)',r.url).group(1))
post(admin,'event',{'action':'tariff','event_id':eid3,'category':'Estudiante','starts_on':start,'ends_on':start,'price':'7'},id=eid3)
r,t5=register(public,'CAP-001',event_id=eid3);check('Bs 7.00' in r.text,'Inclusive tariff date endpoint')
r,t6=register(public,'CAP-002',event_id=eid3);check(t6 is None and 'No quedan cupos' in r.text,'Capacity enforced')
print('PASS',len(checks),'checks')
