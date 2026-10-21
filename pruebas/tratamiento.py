import requests,re,pymysql,datetime
B='http://127.0.0.1:8080';s=requests.Session();checks=0
c=pymysql.connect(host='127.0.0.1',port=3309,user='root',database='legacy_unior_es_v27',autocommit=True)
def check(ok,msg):
 global checks
 assert ok,msg
 checks+=1;print('OK tratamiento',msg)
r=s.get(B+'/inscripcion.php?p=register&id=1')
check('nombre-con-tratamiento' in r.text and 'name="tratamiento"' in r.text and all('>'+x+'<' in r.text for x in ['Lic.','Ing.','Univ.','Sr.','Sra.','Est.']),'Selector en inscripción en línea')
check(re.search(r'<option value="" selected></option>',r.text) is not None,'Opción inicial vacía')
def register(t,ci):
 r=s.get(B+'/inscripcion.php?p=register&id=1');csrf=re.search('name="csrf" value="([a-f0-9]+)"',r.text).group(1)
 return s.post(B+'/inscripcion.php?p=register&id=1',data={'csrf':csrf,'action':'register','event_id':1,'tratamiento':t,'first_name':'Laura','last_name':'Perez','ci':ci,'city':'Oruro','phone':'70000000','email':'laura@example.com','category':'Estudiante'})
r=register('Ing.','TITULO-1');check('Ing. Laura Perez' in r.text,'Tratamiento impreso en comprobante')
with c.cursor() as cur:
 cur.execute("SELECT tratamiento,codigo_privado FROM inscripciones WHERE ci='TITULO-1'");t,token=cur.fetchone();check(t=='Ing.','Guardado en columna española')
 cur.execute("SELECT tratamiento FROM inscripciones WHERE ci='LEGACY42'");check(cur.fetchone()[0]=='','Migración conserva participante anterior sin tratamiento')
r=register('<script>','TITULO-INVALIDO');check('tratamiento válido' in r.text,'Tratamiento manipulado rechazado')
r=s.get(B+'/inicio.php?p=login');csrf=re.search('name="csrf" value="([a-f0-9]+)"',r.text).group(1)
s.post(B+'/inicio.php?p=login',data={'csrf':csrf,'action':'login','username':'testadmin','password':'TestAdmin!2026'})
check('name="tratamiento"' in s.get(B+'/inicio.php?p=register&id=1').text,'Selector también en inscripción presencial')
with c.cursor() as cur:cur.execute("UPDATE inscripciones SET estado='Pagado',fecha_asistencia=NOW(),fecha_certificado=NOW() WHERE ci='TITULO-1'")
check('Ing. Laura Perez' in s.get(B+'/certificados.php?p=certificate&t='+token).text,'Tratamiento impreso en certificado')
r=register('Univ.','TITULO-UNIV');check('Univ. Laura Perez' in r.text,'Univ. se registra y aparece en comprobante')
print('PASS tratamiento',checks,'checks')
