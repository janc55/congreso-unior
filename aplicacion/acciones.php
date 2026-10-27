<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')return;
checkCsrf();$action=(string)($_POST['action']??'');
if(publicPortal() && !in_array($action,['register','proof'],true)){http_response_code(403);exit('Operación no disponible en el portal público.');}
try {
 if($action==='cambiar_comprobante') cambiarComprobante();
 if($action==='reenviar_comprobante') reenviarComprobante();
 if($action==='proof') enviarAbono();
 if($action==='cobrar_abono') cobrarAbono();
 if($action==='review') revisarPagoAutorizado();
 if($action==='estado_inscripcion') cambiarEstadoInscripcion();

 if($action==='login') {
  $now=time();$attempt=$_SESSION['login_attempt']??['count'=>0,'until'=>0];if($attempt['until']>$now)throw new RuntimeException('Espere unos minutos antes de volver a intentar.');
  $u=one('SELECT * FROM users WHERE username=? AND active=1',[trim($_POST['username']??'')]);
  if(!$u||!password_verify($_POST['password']??'',$u['password'])){$_SESSION['login_attempt']=['count'=>$attempt['count']+1,'until'=>$attempt['count']>=4?$now+300:0];throw new RuntimeException('Usuario o contraseña incorrectos.');}
  session_regenerate_id(true);$_SESSION['uid']=$u['id'];unset($_SESSION['login_attempt']);audit('Inicio de sesión');go();
 }
 if($action==='logout'){session_destroy();go('p=login');}
 if($action==='register') {
  if(($_POST['grupo_activo']??'')==='1'||!empty($_POST['integrantes']))registrarGrupo();
  $id=(int)($_POST['event_id']??0);$category=textValue('category',30);if(!in_array($category,['Estudiante','Profesional','Estudiante BTH','UNIOR'],true))throw new RuntimeException('Categoría inválida.');
  $tratamiento=trim((string)($_POST['tratamiento']??''));if(!array_key_exists($tratamiento,tratamientos()))throw new RuntimeException('Seleccione un tratamiento válido.');
  $first=textValue('first_name',120);$last=textValue('last_name',150);$ci=mb_strtoupper(textValue('ci',40));$city=textValue('city',100);$phone=textValue('phone',40);$email=textValue('email',180);
  if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Correo electrónico inválido.');
  $db->beginTransaction();$event=one('SELECT * FROM events WHERE id=? FOR UPDATE',[$id]);$today=date('Y-m-d');
  if(!$event||!$event['active']||$today<$event['registration_start']||$today>$event['registration_end'])throw new RuntimeException('Inscripciones cerradas para este evento.');
  if($event['capacity']>0&&(int)q("SELECT COUNT(*) FROM registrations WHERE event_id=? AND status<>'Anulado'",[$id])->fetchColumn()>=$event['capacity'])throw new RuntimeException('No quedan cupos.');
  validarDocumentosInscripcion($id,[$_POST]);
  $t=tariff($id,$category);if(!$t)throw new RuntimeException('No hay tarifa vigente para esa categoría. Consulte con la administración.');
  $token=bin2hex(random_bytes(24));$ct=bin2hex(random_bytes(24));$staff=publicPortal()?null:user();$cash=$staff&&isset($_POST['paid_now']);$method=($cash?($_POST['payment_method']??'Efectivo'):null);
  if($cash&&!in_array($method,['Efectivo','QR/Transferencia','Tarjeta'],true))throw new RuntimeException('Forma de pago inválida.');
  q('INSERT INTO registrations(tratamiento,event_id,token,certificate_token,first_name,last_name,ci,city,phone,email,institution,category,price,tariff_id,status,payment_method,reference,paid_at,reviewed_by,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$tratamiento,$id,$token,$ct,$first,$last,$ci,$city,$phone,$email,mb_substr(trim($_POST['institution']??''),0,200),$category,$t['price'],$t['id'],$cash?'Pagado':'Pendiente',$method,$cash?mb_substr(trim($_POST['reference']??''),0,120):null,$cash?date('Y-m-d H:i:s'):null,$cash?$staff['id']:null,$staff['id']??null]);
  q('UPDATE registrations SET carrera=? WHERE token=?',[validarCarrera($_POST),$token]);
  if($cash) registrarCobroInicial(registration($token));
  audit('Inscripción','Evento '.$id.' · Registro '.registration($token)['id'].' · Usuario '.($staff['id']??'En línea'));$db->commit();finalizarInscripcion($token);
 }
 if($action==='proof') {
  $r=registration(textValue('token',48));if($r['codigo_grupo'])comprobanteGrupo($r);$db->beginTransaction();$locked=one('SELECT status FROM registrations WHERE id=? FOR UPDATE',[$r['id']]);
  if(!in_array($locked['status'],['Pendiente','Rechazado'],true))throw new RuntimeException('El pago ya fue enviado o confirmado.');
  $proof=upload('proof',true);if(!$proof)throw new RuntimeException('Adjunte el comprobante de pago.');
  q("UPDATE registrations SET proof=?,status='En revisión',reference=?,payment_method='QR/Transferencia',review_note=NULL WHERE id=?",[$proof,mb_substr(trim($_POST['reference']??''),0,120),$r['id']]);audit('Comprobante adjuntado','Registro '.$r['id']);$db->commit();flash('Comprobante enviado. El administrador verificará el pago.');go('p=receipt&t='.$r['token']);
 }
 $u=auth();
 if($action==='password') {
  if(!password_verify($_POST['old_password']??'',$u['password']))throw new RuntimeException('La contraseña actual es incorrecta.');
  $pw=textValue('new_password',200);if(strlen($pw)<10)throw new RuntimeException('Use al menos 10 caracteres.');
  q('UPDATE users SET password=? WHERE id=?',[password_hash($pw,PASSWORD_DEFAULT),$u['id']]);audit('Cambio de contraseña');flash('Contraseña actualizada.');go('p=password');
 }
 if(in_array($action,['deliver','attend','issue'],true)) {
  $r=registration(textValue('token',48));$db->beginTransaction();$locked=one('SELECT * FROM registrations WHERE id=? FOR UPDATE',[$r['id']]);if($locked['status']!=='Pagado')throw new RuntimeException('El participante debe tener su pago confirmado.');
  if($action==='attend'){q('UPDATE registrations SET attended_at=COALESCE(attended_at,NOW()) WHERE id=?',[$r['id']]);audit('Asistencia','Registro '.$r['id']);}
  if($action==='issue') {
   if(!$locked['attended_at'])throw new RuntimeException('Registre primero la asistencia para emitir el certificado.');
   q('UPDATE registrations SET certificate_issued_at=COALESCE(certificate_issued_at,NOW()) WHERE id=?',[$r['id']]);audit('Certificado emitido','Registro '.$r['id']);
  }
  if($action==='deliver') {
   $m=one('SELECT * FROM materials WHERE id=? AND event_id=? AND active=1 FOR UPDATE',[(int)($_POST['material_id']??0),$r['event_id']]);$qty=filter_var($_POST['quantity']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
   if(!$m||!$qty)throw new RuntimeException('Material o cantidad inválidos.');
   $given=(int)q('SELECT COALESCE(SUM(quantity),0) FROM deliveries WHERE registration_id=? AND material_id=?',[$r['id'],$m['id']])->fetchColumn();
   $total=(int)q('SELECT COALESCE(SUM(quantity),0) FROM deliveries WHERE material_id=?',[$m['id']])->fetchColumn();
   if($given+$qty>$m['allowance'])throw new RuntimeException('Ya recogió este material o la cantidad supera lo permitido.');
   if($total+$qty>$m['stock'])throw new RuntimeException('Existencias insuficientes.');
   q('INSERT INTO deliveries(registration_id,material_id,quantity,delivered_by) VALUES(?,?,?,?)',[$r['id'],$m['id'],$qty,$u['id']]);audit('Entrega','Registro '.$r['id'].' · Material '.$m['id'].' · Cantidad '.$qty);
  }
  $db->commit();if($action==='issue'){header('Location: '.certificateUrl('p=certificate&t='.$r['token']));exit;}flash('Operación registrada. '.($action==='attend'?'Asistencia de ':($action==='deliver'?'Material entregado a ':'' )).nombreParticipante($r).'.');go('p=participant&t='.$r['token']);
 }
 auth(true);
 if($action==='event') {
  $id=(int)($_POST['id']??0);$name=textValue('name');$type=textValue('type',20);if(!in_array($type,['Congreso','Curso','Taller'],true))throw new RuntimeException('Tipo inválido.');
  $start=str_replace('T',' ',textValue('starts_at',16)).':00';$end=str_replace('T',' ',textValue('ends_at',16)).':00';
  foreach([$start,$end] as $dt){$d=DateTime::createFromFormat('!Y-m-d H:i:s',$dt);if(!$d||$d->format('Y-m-d H:i:s')!==$dt)throw new RuntimeException('Fecha del evento inválida.');}
  $rs=dateValue('registration_start');$re=dateValue('registration_end');if($end<$start||$re<$rs||$re>substr($end,0,10))throw new RuntimeException('Revise el orden de las fechas y el cierre de inscripción.');
  $cap=filter_var($_POST['capacity']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);if($cap===false)throw new RuntimeException('Cupo inválido.');
  $old=$id?one('SELECT * FROM events WHERE id=?',[$id]):null;if($id&&!$old)throw new RuntimeException('Evento no encontrado.');
  $x=(float)($_POST['cert_x']??50);$y=(float)($_POST['cert_y']??50);$size=(int)($_POST['cert_size']??32);$color=$_POST['cert_color']??'#ba1320';if($x<0||$x>100||$y<0||$y>100||$size<10||$size>90||!preg_match('/^#[0-9a-fA-F]{6}$/',$color))throw new RuntimeException('Posición o estilo del certificado inválido.');
  $grupo=trim((string)($_POST['enlace_grupo_whatsapp']??($old['enlace_grupo_whatsapp']??'')));if(!enlaceGrupoValido($grupo))throw new RuntimeException('Escriba un enlace web válido que comience con https:// o http://.');
  $minGrupo=5;$descGrupo='20';
  $values=[$name,$type,mb_substr(trim($_POST['description']??''),0,10000),mb_substr(trim($_POST['venue']??''),0,250),$start,$end,$rs,$re,$cap,isset($_POST['active'])?1:0,upload('payment_qr')??($old['payment_qr']??null),upload('poster')??($old['poster']??null),upload('certificate_bg')??($old['certificate_bg']??null),$x,$y,$size,$color];
  if($id){$values[]=$id;q('UPDATE events SET name=?,type=?,description=?,venue=?,starts_at=?,ends_at=?,registration_start=?,registration_end=?,capacity=?,active=?,payment_qr=?,poster=?,certificate_bg=?,cert_x=?,cert_y=?,cert_size=?,cert_color=? WHERE id=?',$values);}else{q('INSERT INTO events(name,type,description,venue,starts_at,ends_at,registration_start,registration_end,capacity,active,payment_qr,poster,certificate_bg,cert_x,cert_y,cert_size,cert_color) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$values);$id=(int)$db->lastInsertId();}
  q('UPDATE events SET enlace_grupo_whatsapp=? WHERE id=?',[$grupo,$id]);
  q('UPDATE events SET grupo_minimo=?,grupo_descuento=? WHERE id=?',[$minGrupo,$descGrupo,$id]);
  audit('Evento guardado','Evento '.$id);flash('Evento guardado. Configure tarifas y materiales.');go('p=event&id='.$id);
 }
 if($action==='tariff') {
  $id=(int)($_POST['event_id']??0);$cat=textValue('category',30);if(!in_array($cat,['Estudiante','Profesional','Estudiante BTH','UNIOR'],true))throw new RuntimeException('Categoría inválida.');$start=dateValue('starts_on');$end=dateValue('ends_on');$price=decimalValue('price');if($end<$start)throw new RuntimeException('Fechas inválidas.');
  $db->beginTransaction();if(!one('SELECT id FROM events WHERE id=? FOR UPDATE',[$id]))throw new RuntimeException('Evento inválido.');
  if(one('SELECT id FROM tariffs WHERE event_id=? AND category=? AND starts_on<=? AND ends_on>=?',[$id,$cat,$end,$start]))throw new RuntimeException('Las fechas se superponen con otra tarifa de esa categoría.');
  q('INSERT INTO tariffs(event_id,category,starts_on,ends_on,price) VALUES(?,?,?,?,?)',[$id,$cat,$start,$end,$price]);audit('Tarifa creada','Evento '.$id);$db->commit();flash('Tarifa agregada.');go('p=event&id='.$id);
 }
 if($action==='delete_tariff') {
  $id=(int)($_POST['tariff_id']??0);$t=one('SELECT * FROM tariffs WHERE id=?',[$id]);if(!$t)throw new RuntimeException('Tarifa no encontrada.');if(one('SELECT id FROM registrations WHERE tariff_id=? LIMIT 1',[$id]))throw new RuntimeException('La tarifa tiene inscripciones y debe conservarse.');q('DELETE FROM tariffs WHERE id=?',[$id]);audit('Tarifa eliminada',(string)$id);go('p=event&id='.$t['event_id']);
 }
 if($action==='material') {
  $id=(int)($_POST['event_id']??0);$mid=(int)($_POST['material_id']??0);$name=textValue('name',150);$stock=filter_var($_POST['stock']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);$allowance=filter_var($_POST['allowance']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);$cost=decimalValue('unit_cost');if($stock===false||!$allowance)throw new RuntimeException('Cantidad inválida.');
  $db->beginTransaction();if(!one('SELECT id FROM events WHERE id=? FOR UPDATE',[$id]))throw new RuntimeException('Evento inválido.');
  if($mid){if(!one('SELECT id FROM materials WHERE id=? AND event_id=? FOR UPDATE',[$mid,$id]))throw new RuntimeException('Material inválido.');$total=(int)q('SELECT COALESCE(SUM(quantity),0) FROM deliveries WHERE material_id=?',[$mid])->fetchColumn();$max=(int)q('SELECT COALESCE(MAX(n),0) FROM (SELECT SUM(quantity) n FROM deliveries WHERE material_id=? GROUP BY registration_id) x',[$mid])->fetchColumn();if($stock<$total||$allowance<$max)throw new RuntimeException('El stock o la asignación no pueden ser inferiores a lo ya entregado.');q('UPDATE materials SET name=?,stock=?,allowance=?,unit_cost=?,active=? WHERE id=?',[$name,$stock,$allowance,$cost,isset($_POST['active'])?1:0,$mid]);}
  else q('INSERT INTO materials(event_id,name,stock,allowance,unit_cost) VALUES(?,?,?,?,?)',[$id,$name,$stock,$allowance,$cost]);
  audit('Material guardado','Evento '.$id);$db->commit();go('p=event&id='.$id);
 }
 if($action==='category') {
  $cid=(int)($_POST['category_id']??0);$name=textValue('name',150);$active=isset($_POST['active'])?1:0;
  if($cid){if(!one('SELECT id FROM expense_categories WHERE id=?',[$cid]))throw new RuntimeException('Categoría no encontrada.');q('UPDATE expense_categories SET name=?,active=? WHERE id=?',[$name,$active,$cid]);}
  else q('INSERT INTO expense_categories(name,active) VALUES(?,?)',[$name,$active]);
  audit('Categoría de gasto guardada',$name);flash('Categoría guardada.');go('p=categories');
 }
 if($action==='expense') {
  $id=(int)($_POST['event_id']??0);$cid=(int)($_POST['category_id']??0);
  if($cid&&!one('SELECT id FROM expense_categories WHERE id=? AND active=1',[$cid]))throw new RuntimeException('Seleccione una categoría activa.');
  q('INSERT INTO expenses(event_id,category_id,description,amount,spent_on,created_by) VALUES(?,?,?,?,?,?)',[$id,$cid?:null,textValue('description',250),decimalValue('amount'),dateValue('spent_on'),$u['id']]);audit('Gasto registrado','Evento '.$id.' · Categoría '.$cid);go('p=dashboard&event_id='.$id);
 }
 if($action==='classify_expense') {
  $gid=(int)($_POST['expense_id']??0);$cid=(int)($_POST['category_id']??0);
  if(!one('SELECT id FROM expenses WHERE id=?',[$gid]))throw new RuntimeException('Gasto no encontrado.');
  if($cid&&!one('SELECT id FROM expense_categories WHERE id=? AND active=1',[$cid]))throw new RuntimeException('Seleccione una categoría activa.');
  q('UPDATE expenses SET category_id=? WHERE id=?',[$cid?:null,$gid]);audit('Gasto clasificado','Gasto '.$gid.' · Categoría '.$cid);go('p=expense_report&event_id='.(int)($_POST['event_id']??0));
 }
 if($action==='user') {
  $id=(int)($_POST['id']??0);$name=textValue('name',150);$username=textValue('username',80);$role=textValue('role',20);if(!in_array($role,['admin','vendedor'],true))throw new RuntimeException('Rol inválido.');$active=isset($_POST['active'])?1:0;$pw=trim($_POST['password']??'');if((!$id||$pw!=='')&&strlen($pw)<10)throw new RuntimeException('La contraseña debe tener al menos 10 caracteres.');if($id===$u['id']&&(!$active||$role!=='admin'))throw new RuntimeException('No puede desactivar o quitar su propio acceso de administrador.');
  if($id){q('UPDATE users SET name=?,username=?,role=?,active=? WHERE id=?',[$name,$username,$role,$active,$id]);if($pw!=='')q('UPDATE users SET password=? WHERE id=?',[password_hash($pw,PASSWORD_DEFAULT),$id]);}else q('INSERT INTO users(name,username,password,role,active) VALUES(?,?,?,?,?)',[$name,$username,password_hash($pw,PASSWORD_DEFAULT),$role,$active]);audit('Usuario guardado','Usuario '.$username);go('p=users');
 }
 throw new RuntimeException('Operación desconocida.');
} catch(Throwable $ex) {
 if($db->inTransaction())$db->rollBack();error_log((string)$ex);
 if(in_array($action,['proof','cobrar_abono','cambiar_comprobante','reenviar_comprobante'],true)){
 $GLOBALS['registro_fallido']=true;$GLOBALS['errores_registro']=str_contains($ex->getMessage(),'transacción')?['reference'=>$ex->getMessage()]:[];
 $_GET['p']=$action==='proof'?'receipt':'participant';$_GET['t']=(string)($_POST['token']??'');flash($ex instanceof PDOException?'No se pudo guardar el pago.':$ex->getMessage());return;
 }
 if($action==='register'){
  $errores=$ex instanceof ErrorDatosInscripcion?$ex->campos:(str_contains($ex->getMessage(),'transacción')?['reference'=>$ex->getMessage()]:[]);
  $mensaje=$ex instanceof PDOException?'No se pudo guardar la inscripción. Revise los datos e intente nuevamente.':$ex->getMessage();
  if(respuestaInscripcionJson())emitirRespuestaInscripcion(['ok'=>false,'message'=>$mensaje,'errors'=>$errores],422);
  $GLOBALS['registro_fallido']=true;$GLOBALS['errores_registro']=$errores;$_GET['p']='register';$_GET['id']=(int)($_POST['event_id']??0);flash($mensaje);return;
 }
 $msg=$ex instanceof PDOException?'No se pudo guardar: revise duplicados y datos relacionados.':$ex->getMessage();flash($msg);
 $p=(string)($_GET['p']??'home');$allowed=['home','login','register','receipt','participant','event','events','dashboard','users','password','list','categories','expense_report'];if(!in_array($p,$allowed,true))$p='home';$params=['p'=>$p];foreach(['id','event_id','t'] as $key)if(isset($_GET[$key]))$params[$key]=(string)$_GET[$key];go(http_build_query($params));
}
