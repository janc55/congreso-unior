import requests,re,pymysql
from pathlib import Path
B='http://127.0.0.1:8080';s=requests.Session();n=0
c=pymysql.connect(host='127.0.0.1',port=3309,user='root',database='legacy_unior_es_v6',autocommit=True)
def check(ok,msg):
 global n
 assert ok,msg
 n+=1;print('OK recuperación',msg)
def send(key=''):
 r=s.get(B+'/instalar.php');token=re.search('name="csrf" value="([a-f0-9]+)"',r.text).group(1)
 return s.post(B+'/instalar.php',data={'csrf':token,'modo':'recuperar','servidor':'127.0.0.1','puerto':'3309','base_datos':'legacy_unior_es_v6','usuario_bd':'root','clave_bd':'','direccion_sitio':B,'administrador_nombre':'Administrador recuperado','administrador_usuario':'adminrecuperado','administrador_clave':'MiNuevaClave2026!','clave_recuperacion':key})
with c.cursor() as cur:
 cur.execute('SELECT COUNT(*),SUM(precio) FROM inscripciones');antes=cur.fetchone()
check('clave temporal de al menos 16' in send().text,'Sin habilitación local se rechaza la recuperación')
p=Path('unior-espanol/config.php');p.write_text(p.read_text().replace("return array (","return array (\n'clave_recuperacion' => 'TemporalDeRecuperacion2026!',"))
check('clave temporal de al menos 16' in send('incorrecta').text,'Clave incorrecta rechazada')
check('Administrador recuperado' in send('TemporalDeRecuperacion2026!').text,'Crea administrador sin exigir usuario anterior')
check('clave_recuperacion' not in p.read_text(),'Deshabilita recuperación después de usarla')
with c.cursor() as cur:
 cur.execute('SELECT COUNT(*),SUM(precio) FROM inscripciones');check(antes==cur.fetchone(),'Conserva participantes y montos')
 cur.execute("SELECT rol,activo FROM usuarios WHERE usuario='adminrecuperado'");check(cur.fetchone()==('admin',1),'Administrador activo creado')
check('clave temporal de al menos 16' in send('TemporalDeRecuperacion2026!').text,'Clave temporal no se puede reutilizar')
r=s.get(B+'/inicio.php?p=login');token=re.search('name="csrf" value="([a-f0-9]+)"',r.text).group(1)
r=s.post(B+'/inicio.php?p=login',data={'csrf':token,'action':'login','username':'adminrecuperado','password':'MiNuevaClave2026!'})
check('Administrador recuperado' in r.text,'Acceso con la nueva cuenta funciona')
print('PASS recuperación',n,'checks')
