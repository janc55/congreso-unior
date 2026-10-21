import requests,re,pymysql
B='http://127.0.0.1:8080';s=requests.Session()
r=s.get(B+'/inicio.php?p=login');csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',r.text).group(1);s.post(B+'/inicio.php?p=login',data={'csrf':csrf,'action':'login','username':'testadmin','password':'TestAdmin!2026'})
checks=0
def check(c,m):
 global checks
 assert c,m
 checks+=1;print('OK Category',m)
def post(page,data):
 r=s.get(B+'/inicio.php?p='+page);csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',r.text).group(1)
 return s.post(B+'/inicio.php?p='+page,data={'csrf':csrf,**data})
for name in ['Transporte','Publicidad']:
 r=post('categories',{'action':'category','name':name,'active':'1'});check(name in r.text,'Create '+name)
r=post('categories',{'action':'category','name':'transporte','active':'1'});check('duplicados' in r.text,'Duplicate category rejected')
c=pymysql.connect(host='127.0.0.1',port=3309,user='root',database='unior_nueva_v4',autocommit=True)
with c.cursor() as cur:
 cur.execute('SELECT id,nombre FROM categorias_gastos');ids={name:i for i,name in cur.fetchall()}
for name,amount,date in [('Transporte','200','2026-10-01'),('Transporte','50','2026-10-02'),('Publicidad','100','2026-10-02')]:
 r=post('dashboard',{'action':'expense','event_id':1,'category_id':ids[name],'description':'Gasto de prueba '+name,'amount':amount,'spent_on':date});check(name in r.text,'Register categorized expense')
r=s.get(B+'/inicio.php?p=expense_report&event_id=1');open('category-response.html','w').write(r.text);check('Bs 370.00' in r.text and 'Transporte · Bs 250.00' in re.sub('<[^>]+>','',r.text),'Totals and largest category correct, legacy expense preserved')
r=s.get(B+'/inicio.php?p=expense_report&event_id=1&export=csv');check(r.text.index('Transporte')<r.text.index('Publicidad') and 'Sin categoría' in r.text,'CSV sorted and uncategorized included')
r=s.get(B+'/inicio.php?p=expense_report&event_id=1&from=2026-10-01&to=2026-10-01');check('Bs 200.00' in r.text and 'Publicidad' not in r.text.split('Distribución de otros gastos')[1].split('Detalle y clasificación')[0],'Date filter')
r=s.get(B+'/inicio.php?p=expense_report&event_id=1&category_id='+str(ids['Publicidad']));check('Bs 100.00' in r.text,'Category filter')
r=post('categories',{'action':'category','category_id':ids['Publicidad'],'name':'Publicidad'});check('Inactiva' in r.text,'Deactivate category')
r=post('dashboard',{'action':'expense','event_id':1,'category_id':ids['Publicidad'],'description':'Inválido','amount':'1','spent_on':'2026-10-02'});check('categoría activa' in r.text,'Inactive category cannot receive new expenses')
with c.cursor() as cur:cur.execute('SELECT MIN(id) FROM gastos');gid=cur.fetchone()[0]
r=post('expense_report',{'action':'classify_expense','expense_id':gid,'category_id':ids['Transporte'],'event_id':1});check('Transporte · Bs 270.00' in re.sub('<[^>]+>','',r.text),'Classify old expense')
seller=requests.Session()
r=seller.get(B+'/inicio.php?p=login');token=re.search(r'name="csrf" value="([a-f0-9]+)"',r.text).group(1)
seller.post(B+'/inicio.php?p=login',data={'csrf':token,'action':'login','username':'seller','password':'TestSeller!2026'})
for page in ['categories','expense_report']:
 check(seller.get(B+'/inicio.php?p='+page).status_code==403,'Seller blocked from '+page)
check(seller.post(B+'/inicio.php?p=categories',data={'csrf':token,'action':'category','name':'Bypass','active':'1'}).status_code==403,'Seller cannot create categories via POST')
r=s.get(B+'/inicio.php?p=expense_report&event_id=2');check('Total gastado: Bs 0.00' in r.text,'Event filter excludes other events')
r=s.get(B+'/inicio.php?p=expense_report&event_id=1&category_id='+str(ids['Publicidad']));check('Bs 100.00' in r.text,'Inactive category preserves historical report')
print('PASS categories',checks,'checks')
