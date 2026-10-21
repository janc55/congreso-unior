import requests,pymysql
B='http://127.0.0.1:8080';n=0
r=requests.get(B+'/inscripcion.php')
def check(ok,msg):
 global n
 assert ok,msg
 n+=1;print('OK portada',msg)
check(r.status_code==200 and 'portada-institucional' in r.text and 'impulsa tu' in r.text,'Portada institucional en inicio público')
check('main-sidebar' not in r.text,'Sin menú administrativo')
check('id="como-inscribirse"' in r.text and 'id="oferta-academica"' in r.text,'Guía y navegación de portada')
check('id="buscar-evento"' in r.text and 'data-filtro-tipo="Taller"' in r.text,'Buscador y filtros')
check('Congreso anterior' in r.text and 'data-evento-tipo="Congreso"' in r.text,'Tarjetas cargan eventos reales')
check('inscripcion.php?p=register&amp;id=1' in r.text or 'inscripcion.php?p=register&id=1' in r.text,'Enlace a formulario independiente')
check(requests.get(B+'/recursos/logo-unior.jpg').status_code==200,'Logo local disponible')
c=pymysql.connect(host='127.0.0.1',port=3309,user='root',database='legacy_unior_es_v14',autocommit=True)
with c.cursor() as cur:
 cur.execute("INSERT INTO eventos(nombre,tipo,fecha_inicio,fecha_fin,inicio_inscripcion,fin_inscripcion,activo) VALUES('EVENTO_PRIVADO_PRUEBA','Curso','2026-10-01','2026-10-31','2026-10-01','2026-10-31',0)")
check('EVENTO_PRIVADO_PRUEBA' not in requests.get(B+'/inscripcion.php').text,'Oculta eventos no publicados')
print('PASS portada',n,'checks')
