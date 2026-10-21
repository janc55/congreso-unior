<?php
define('UNIOR_INICIADO',true);
if(PHP_VERSION_ID<80100){http_response_code(503);exit('Seleccione PHP 8.1 o superior en su hosting.');}
foreach(['pdo','pdo_mysql','mbstring','fileinfo'] as $extension){if(!extension_loaded($extension)){http_response_code(503);exit('Active la extensión PHP '.$extension.' en su hosting y vuelva a abrir instalar.php.');}}
require __DIR__.'/aplicacion/base_espanol.php';
require __DIR__.'/aplicacion/migrar_base.php';
ini_set('session.use_strict_mode','1');
session_name('UNIOR_'.substr(hash('sha256',realpath(__DIR__)),0,12));
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);session_start();
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: SAMEORIGIN');
$_SESSION['instalar_csrf']??=bin2hex(random_bytes(32));
function escapar(mixed $s): string{return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function valor(string $key,string $default=''): string{return trim((string)($_POST[$key]??$default));}
$configOld=is_file(__DIR__.'/config.php')?(require __DIR__.'/config.php'):[];
$configArchivo=$configOld;
if (($configOld['instalado'] ?? true) === false) $configOld=[];
$locked=is_file(__DIR__.'/almacenamiento/instalado.bloqueo');
$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
$autoUrl=$scheme.'://'.($_SERVER['HTTP_HOST']??'localhost').rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])),'/.');
$error='';$done=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 $db=null;
 try{
  if(!hash_equals($_SESSION['instalar_csrf'],(string)($_POST['csrf']??'')))throw new RuntimeException('Recargue la página y vuelva a intentar.');
  $mode=valor('modo','nuevo');if(!in_array($mode,['nuevo','actualizar','recuperar'],true))throw new RuntimeException('Seleccione el tipo de instalación.');
  $host=valor('servidor','127.0.0.1');$port=filter_var(valor('puerto','3306'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);$database=valor('base_datos','unior_eventos');$username=valor('usuario_bd');$password=(string)($_POST['clave_bd']??'');$baseUrl=rtrim(valor('direccion_sitio',$autoUrl),'/');
  if(!preg_match('/^[a-zA-Z0-9_]{1,64}$/',$database)||!preg_match('/^[a-zA-Z0-9._:-]{1,253}$/',$host)||!$port||$username==='')throw new RuntimeException('Revise servidor, puerto, nombre de base y usuario de MariaDB.');
  $u=parse_url($baseUrl);if(!filter_var($baseUrl,FILTER_VALIDATE_URL)||!in_array($u['scheme']??'',['http','https'],true)||isset($u['query'])||isset($u['fragment'])||isset($u['user']))throw new RuntimeException('Escriba la dirección completa del sitio (http o https), sin /public.');
  $opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
  try{$db=new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",$username,$password,$opts);}
  catch(PDOException $e){
   if((int)($e->errorInfo[1]??0)!==1049)throw $e;
   $db=new PDO("mysql:host=$host;port=$port;charset=utf8mb4",$username,$password,$opts);$db->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$db->exec("USE `$database`");
  }
  $adminUser=valor('administrador_usuario');$adminPassword=(string)($_POST['administrador_clave']??'');$adminName=valor('administrador_nombre');
  if($adminUser===''||strlen($adminUser)>80)throw new RuntimeException('Escriba el usuario administrador.');
  if($mode==='nuevo'&&$configOld){
   $actual=new PDO("mysql:host={$configOld['host']};port={$configOld['port']};dbname={$configOld['database']};charset=utf8mb4",$configOld['username'],$configOld['password'],$opts);
   foreach(['usuarios','users'] as $tabla)if(existeTabla($actual,$tabla)&&(int)$actual->query("SELECT COUNT(*) FROM `$tabla`")->fetchColumn()>0)throw new RuntimeException('Este sistema ya está instalado. Use actualizar con su administrador o Recuperar administrador si no conoce su acceso.');
  }
  if($mode==='recuperar'){
   $claveLocal=(string)($configArchivo['clave_recuperacion']??'');
   if(strlen($claveLocal)<16||!hash_equals($claveLocal,(string)($_POST['clave_recuperacion']??'')))throw new RuntimeException('Para recuperar el acceso, agregue clave_recuperacion en su config.php con una clave temporal de al menos 16 caracteres y escríbala aquí.');
   if($configOld&&($host!==(string)$configOld['host']||(int)$port!==(int)$configOld['port']||$database!==$configOld['database']||$username!==$configOld['username']))throw new RuntimeException('La recuperación debe utilizar la conexión de su config.php actual.');
   if($adminName===''||mb_strlen($adminName)>150||strlen($adminPassword)<8)throw new RuntimeException('Escriba el nombre y la nueva contraseña de al menos 8 caracteres.');
  }elseif($mode==='actualizar'){
   if($configOld){
    $currentDb=new PDO("mysql:host={$configOld['host']};port={$configOld['port']};dbname={$configOld['database']};charset=utf8mb4",$configOld['username'],$configOld['password'],$opts);
    if(!verificarAdministrador($currentDb,$adminUser,$adminPassword))throw new RuntimeException('Confirme la cuenta administradora del sistema actualmente instalado.');
   }elseif($locked)throw new RuntimeException('Conserve el config.php de la instalación anterior antes de actualizar.');
   if(!verificarAdministrador($db,$adminUser,$adminPassword))throw new RuntimeException('Confirme el usuario y la contraseña del administrador de la instalación anterior.');
  }else{
   if(existeTabla($db,'users')&&(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()>0)throw new RuntimeException('La base contiene una instalación anterior. Seleccione actualizar para conservar sus datos.');
   foreach(array_values(tablasAnteriores()) as $table)if(existeTabla($db,$table)&&(int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn()>0)throw new RuntimeException('La base ya contiene datos. Seleccione actualizar para conservarlos.');
   if($adminName===''||mb_strlen($adminName)>150||strlen($adminPassword)<8)throw new RuntimeException('Escriba el nombre del administrador y una contraseña de al menos 8 caracteres.');
  }
  foreach(['almacenamiento','almacenamiento/archivos_subidos'] as $folder){if(!is_dir(__DIR__.'/'.$folder)&&!mkdir(__DIR__.'/'.$folder,0750,true))throw new RuntimeException('No se pudo crear la carpeta '.$folder.'.');if(!is_writable(__DIR__.'/'.$folder))throw new RuntimeException('Permita escribir en '.$folder.' al usuario de PHP.');}
  if(!is_writable(__DIR__)|| (is_file(__DIR__.'/config.php')&&!is_writable(__DIR__.'/config.php')))throw new RuntimeException('Permita escribir config.php durante la instalación.');
  if($mode!=='nuevo'||existeTabla($db,'users')){migrarBase($db);protegerArchivosAnteriores();}else crearEsquema($db);
  $db->beginTransaction();
  if($mode==='nuevo'){$s=$db->prepare("INSERT INTO usuarios(nombre,usuario,clave,rol) VALUES(?,?,?,'admin')");$s->execute([$adminName,$adminUser,password_hash($adminPassword,PASSWORD_DEFAULT)]);}
  if($mode==='recuperar'){
   $consulta=$db->prepare('SELECT id FROM usuarios WHERE usuario=? FOR UPDATE');$consulta->execute([$adminUser]);$id=$consulta->fetchColumn();
   if($id){$consulta=$db->prepare("UPDATE usuarios SET nombre=?,clave=?,rol='admin',activo=1 WHERE id=?");$consulta->execute([$adminName,password_hash($adminPassword,PASSWORD_DEFAULT),$id]);}
   else{$consulta=$db->prepare("INSERT INTO usuarios(nombre,usuario,clave,rol) VALUES(?,?,?,'admin')");$consulta->execute([$adminName,$adminUser,password_hash($adminPassword,PASSWORD_DEFAULT)]);}
   $consulta=$db->prepare('INSERT INTO historial(id_usuario,accion,detalle) VALUES(?, ?, ?)');$consulta->execute([$id?:$db->lastInsertId(),'Recuperación de administrador','Recuperación autorizada mediante clave temporal en config.php']);
  }
  $config=['instalado'=>true,'host'=>$host,'port'=>$port,'database'=>$database,'username'=>$username,'password'=>$password,'base_url'=>$baseUrl,'timezone'=>'America/La_Paz'];
  $payload="<?php\nif(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}\nreturn ".var_export($config,true).";\n";
  $tmp=__DIR__.'/.config_instalacion_'.bin2hex(random_bytes(8)).'.php';
  if(file_put_contents($tmp,$payload,LOCK_EX)===false||!rename($tmp,__DIR__.'/config.php')){if(is_file($tmp))unlink($tmp);throw new RuntimeException('No se pudo guardar config.php. Revise permisos.');}
  $db->commit();file_put_contents(__DIR__.'/almacenamiento/instalado.bloqueo',date('c'),LOCK_EX);$done=true;
 }catch(Throwable $e){if($db&&$db->inTransaction())$db->rollBack();error_log((string)$e);$error=$e instanceof PDOException?'No se pudo conectar o crear la base. Revise las credenciales y los permisos del usuario de MariaDB. Si su hosting no permite crear bases desde PHP, créela en su panel y vuelva a intentar.':$e->getMessage();}
}
function entrada(string $name,string $label,string $default='',string $type='text',bool $required=true): void{echo '<div class="form-group"><label>'.escapar($label).'</label><input class="form-control" name="'.escapar($name).'" type="'.escapar($type).'" value="'.escapar($type==='password'?'':valor($name,$default)).'" '.($required?'required':'').'></div>';}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalar · UNIOR</title><link rel="stylesheet" href="recursos/adminlte.min.css"><link rel="stylesheet" href="recursos/estilos.css?v=selector8"><link rel="stylesheet" href="recursos/identidad.css?v=13"></head><body class="public-portal"><header class="public-header"><div class="unior-wordmark"><img class="logo-portal" src="recursos/logo-unior.jpg" alt="UNIOR · Universidad Privada de Oruro"></div><span class="portal-label">INSTALACIÓN SIMPLE</span></header><main class="public-main">
<?php if($done):?><div class="card p-4"><h1><?=$mode==='nuevo'?'Sistema instalado':($mode==='recuperar'?'Administrador recuperado':'Sistema actualizado')?></h1><p>La base está en español y la configuración se guardó automáticamente.</p><a class="btn btn-primary" href="<?=escapar($baseUrl)?>/inicio.php?p=login">Ingresar al sistema</a></div><?php else:?><div class="card p-4"><h1>Instalar o actualizar UNIOR</h1><p>Complete la conexión y su cuenta de administrador. El sistema creará las tablas y guardará la configuración automáticamente.</p><?php if($error):?><div class="alert alert-danger"><?=escapar($error)?></div><?php endif?><form method="post"><input type="hidden" name="csrf" value="<?=escapar($_SESSION['instalar_csrf'])?>"><div class="form-group"><label>Operación</label><select class="form-control" name="modo"><option value="nuevo" <?=valor('modo',$configOld?'actualizar':'nuevo')==='nuevo'?'selected':''?>>Instalar un sistema nuevo</option><option value="actualizar" <?=valor('modo',$configOld?'actualizar':'nuevo')==='actualizar'?'selected':''?>>Actualizar el anterior y conservar datos</option><option value="recuperar" <?=valor('modo')==='recuperar'?'selected':''?>>Recuperar / crear administrador</option></select></div><div class="row"><div class="col-lg-6"><h3>Conexión a MariaDB</h3><?php entrada('servidor','Servidor',$configOld['host']??'127.0.0.1');entrada('puerto','Puerto',(string)($configOld['port']??3306),'number');entrada('base_datos','Nombre de la base',$configOld['database']??'unior_eventos');entrada('usuario_bd','Usuario de MariaDB',$configOld['username']??'root');entrada('clave_bd','Contraseña de MariaDB','','password',false);?></div><div class="col-lg-6"><h3>Cuenta del administrador</h3><?php entrada('administrador_nombre','Nombre del administrador (nuevo o recuperación)','','text',false);entrada('administrador_usuario','Usuario del administrador');entrada('administrador_clave','Contraseña del administrador','','password');entrada('direccion_sitio','Dirección del sitio',$autoUrl);entrada('clave_recuperacion','Clave temporal de recuperación (solo recuperación)','','password',false);?><p>Para recuperar el administrador, agregue en config.php: <code>'clave_recuperacion' =&gt; 'SU_CLAVE_TEMPORAL_DE_16_O_MAS_CARACTERES',</code> dentro del arreglo return. Ingrese esa clave y el nuevo usuario y contraseña. Se conservarán los datos y la clave temporal se quitará automáticamente al terminar.</p><p>Al actualizar, use el usuario y la contraseña actuales. Sus participantes, pagos, certificados y gastos se conservarán.</p></div></div><button class="btn btn-primary btn-lg">Instalar / actualizar</button></form></div><?php endif?></main></body></html>
