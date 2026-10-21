<?php
declare(strict_types=1);
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
require __DIR__.'/base_espanol.php';
$configPath = dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) { header('Location: instalar.php');exit; }
$config = require $configPath;
if (($config['instalado'] ?? true) === false) { header('Location: instalar.php');exit; }
date_default_timezone_set($config['timezone'] ?? 'America/La_Paz');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>str_starts_with($config['base_url'], 'https://')]);
session_name('UNIOR_'.substr(hash('sha256',realpath(dirname(__DIR__))),0,12));
session_start();
header('Cache-Control: no-store, private, max-age=0');header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: SAMEORIGIN');
try {
$db = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4", $config['username'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STATEMENT_CLASS=>[ConsultaUnior::class,[]]]);
$db->exec("SET time_zone = '-04:00'");
} catch (Throwable $e) { error_log((string)$e); http_response_code(503); exit('No se pudo conectar con MariaDB. Revise config.php.'); }
function q(string $sql, array $args=[]): PDOStatement { global $db; $s=$db->prepare(traducirConsulta($sql));$s->execute($args);return $s; }
function one(string $sql,array $args=[]): ?array { return q($sql,$args)->fetch() ?: null; }
function all(string $sql,array $args=[]): array { return q($sql,$args)->fetchAll(); }
function e(mixed $v): string { return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8'); }
function money(mixed $v): string { return 'Bs '.number_format((float)$v,2,'.',','); }
function url(string $query=''): string { global $config; return rtrim($config['base_url'],'/').'/inicio.php'.($query?'?'.$query:''); }
function publicUrl(string $query=''): string { global $config; return rtrim($config['base_url'],'/').'/inscripcion.php'.($query?'?'.$query:''); }
function certificateUrl(string $query=''): string { global $config; return rtrim($config['base_url'],'/').'/certificados.php'.($query?'?'.$query:''); }
function publicPortal(): bool { return defined('PUBLIC_PORTAL') && PUBLIC_PORTAL; }
function go(string $query=''): never { header('Location: '.(publicPortal()?publicUrl($query):(defined('CERTIFICATE_WINDOW') && in_array(explode('&',$query)[0],['p=list','p=participant','p=certificate'],true)?certificateUrl($query):url($query))));exit; }
function user(): ?array { static $u=false; if($u===false)$u=isset($_SESSION['uid'])?one('SELECT * FROM users WHERE id=? AND active=1',[$_SESSION['uid']]):null;return $u; }
function auth(bool $admin=false): array { $u=user(); if(!$u)go('p=login'); if($admin&&$u['role']!=='admin'){http_response_code(403);exit('Acceso exclusivo del administrador.');}return $u; }
function csrf(): string { $_SESSION['csrf']??=bin2hex(random_bytes(32)); return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function checkCsrf(): void { if(!hash_equals($_SESSION['csrf']??'',(string)($_POST['csrf']??''))){http_response_code(419);exit('La sesión expiró. Recargue la página.');} }
function audit(string $a,string $d=''): void { q('INSERT INTO audit(user_id,action,detail) VALUES(?,?,?)',[user()['id']??null,$a,$d]); }
function flash(string $s): void { $_SESSION['flash']=$s; }
function textValue(string $key,int $max=200): string { $v=trim((string)($_POST[$key]??''));if($v===''||mb_strlen($v)>$max)throw new RuntimeException('Complete correctamente el campo '.$key.'.');return $v; }
function decimalValue(string $key): string { $v=(string)($_POST[$key]??'');if(!preg_match('/^\d{1,9}(\.\d{1,2})?$/',$v))throw new RuntimeException('Monto no válido: '.$key);return $v; }
function dateValue(string $key): string { $v=textValue($key,10); $d=DateTime::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new RuntimeException('Fecha inválida.');return $v; }
function upload(string $key,bool $pdf=false): ?string {
 $f=$_FILES[$key]??null;if(!$f||$f['error']===UPLOAD_ERR_NO_FILE)return null;
 if($f['error']!==UPLOAD_ERR_OK||$f['size']>8*1024*1024)throw new RuntimeException('El archivo no debe superar 8 MB.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if($pdf)$types['application/pdf']='pdf';
 if(!isset($types[$mime]))throw new RuntimeException('Use JPG, PNG o WEBP'.($pdf?' o PDF':'').'.');
 if($mime!=='application/pdf'&&!getimagesize($f['tmp_name']))throw new RuntimeException('Imagen inválida.');
 $name=bin2hex(random_bytes(24)).'.'.$types[$mime];$dir=dirname(__DIR__).'/almacenamiento/archivos_subidos';
 if(!is_dir($dir))mkdir($dir,0750,true);if(file_put_contents($dir.'/'.$name.'.php',proteccionArchivo().file_get_contents($f['tmp_name']),LOCK_EX)===false)throw new RuntimeException('No se pudo guardar el archivo.');return $name;
}
function tariff(int $id,string $category): ?array { return one('SELECT * FROM tariffs WHERE event_id=? AND category=? AND starts_on<=? AND ends_on>=? ORDER BY starts_on DESC,id DESC LIMIT 1',[$id,$category,date('Y-m-d'),date('Y-m-d')]); }
function registration(string $token): array { $r=one('SELECT r.*, e.name event_name,e.enlace_grupo_whatsapp,e.type,e.starts_at,e.ends_at,e.venue,e.payment_qr,e.certificate_bg,e.cert_x,e.cert_y,e.cert_size,e.cert_color FROM registrations r JOIN events e ON e.id=r.event_id WHERE r.token=?',[$token]);if(!$r){http_response_code(404);exit('Registro no encontrado.');}return $r; }
function fileUrl(?string $file,string $token=''): string { return url('p=file&f='.rawurlencode($file??'').($token?'&t='.rawurlencode($token):'')); }
function field(string $name,string $label,string $value='',string $type='text',bool $required=true): void { $value=valorRegistro($name,$value);$error=errorCampoRegistro($name);echo '<div class="form-group"><label>'.e($label).'</label><input class="form-control '.($error?'is-invalid':'').'" aria-invalid="'.($error?'true':'false').'" name="'.e($name).'" type="'.e($type).'" value="'.e($value).'" '.($required?'required':'').' '.($type==='number'?'min="0" step="0.01"':'').' maxlength="250">'.($error?'<div class="invalid-feedback mensaje-error-campo">'.e($error).'</div>':'').'</div>'; }
function selectField(string $name,string $label,array $options,string $value='',array $deshabilitadas=[]): void { $value=valorRegistro($name,$value);echo '<div class="form-group"><label>'.e($label).'</label><select class="form-control" name="'.e($name).'">';foreach($options as $k=>$v)echo '<option value="'.e($k).'" '.((string)$k===$value?'selected':'').' '.(in_array($k,$deshabilitadas,true)?'disabled':'').'>'.e($v).'</option>';echo '</select></div>'; }
function headerPage(string $title): void { if(publicPortal() || (!user() && in_array($_GET['p']??'home',['register','receipt','verify'],true))){publicHeader($title);return;} $u=user(); $admin=$u&&$u['role']==='admin'; ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · UNIOR</title><link rel="stylesheet" href="recursos/adminlte.min.css"><link rel="stylesheet" href="recursos/estilos.css?v=portal14"><link rel="stylesheet" href="recursos/identidad.css?v=22"><link rel="stylesheet" href="recursos/portal_inicio.css?v=18"></head><body class="hold-transition sidebar-mini"><div class="wrapper">
<nav class="main-header navbar navbar-expand navbar-white navbar-light"><button class="btn btn-light" id="menu-toggle" aria-label="Abrir menú">☰</button><span class="ml-3">Universidad Privada de Oruro</span><span class="ml-auto badge badge-primary"><?=e($u['role']??'Inscripción en línea')?></span></nav>
<aside class="main-sidebar sidebar-dark-primary"><a class="brand-link" href="<?=url()?>"><img class="logo-menu" src="recursos/logo-unior.jpg" alt="UNIOR · Universidad Privada de Oruro"><span class="marca-eventos">Congresos · Cursos · Talleres</span></a><div class="sidebar"><div class="user-panel p-3 text-white"><?=e($u['name']??'Bienvenido a UNIOR')?></div><nav><ul class="nav nav-pills nav-sidebar flex-column"><li><a class="nav-link" href="<?=url()?>">Inscripciones presenciales</a></li><?php if($u):?><li><a class="nav-link" href="<?=url('p=registrations')?>">Participantes</a></li><li><a class="nav-link" href="<?=url('p=scan')?>">Escanear / entregar</a></li><li><a class="nav-link" target="_blank" rel="noopener" href="<?=certificateUrl('p=list')?>">Certificados ↗</a></li><?php if($admin):?><li><a class="nav-link" href="<?=url('p=recibos_pagos')?>">Recibos de pago / reimprimir</a></li><li><a class="nav-link" href="<?=url('p=dashboard')?>">Resumen y finanzas</a></li><li><a class="nav-link" href="<?=url('p=events')?>">Gestionar eventos</a></li><li><a class="nav-link" href="<?=url('p=online')?>">Verificar inscripciones en línea</a></li><li><a class="nav-link" href="<?=url('p=reports')?>">Reportes de participantes</a></li><li><a class="nav-link" href="<?=url('p=finance')?>">Reporte financiero</a></li><li><a class="nav-link" href="<?=url('p=categories')?>">Categorías de gastos</a></li><li><a class="nav-link" href="<?=url('p=expense_report')?>">Gastos por categoría</a></li><li><a class="nav-link" href="<?=url('p=users')?>">Usuarios</a></li><li><a class="nav-link" href="<?=url('p=audit')?>">Historial</a></li><?php endif?><li><a class="nav-link" href="<?=url('p=password')?>">Cambiar contraseña</a></li><li><form method="post"><?=csrf()?><input type="hidden" name="action" value="logout"><button class="btn nav-link text-left">Cerrar sesión</button></form></li><?php else:?><li><a class="nav-link" href="<?=url('p=login')?>">Acceso personal</a></li><?php endif?></ul></nav></div></aside>
<main class="content-wrapper"><div class="content-header"><div class="container-fluid"><h1><?=e($title)?></h1></div></div><section class="content"><div class="container-fluid"><?php if(isset($_SESSION['flash'])):?><div class="alert alert-info"><?=e($_SESSION['flash'])?></div><?php unset($_SESSION['flash']);endif; }
function footerPage(): void { if(!empty($GLOBALS['public_layout'])){publicFooter();return;} ?></div></section></main><footer class="main-footer">UNIOR · Gestión de congresos, cursos y talleres</footer></div><script src="recursos/qrcode.min.js"></script><script src="recursos/comunidad_qr.js?v=12"></script><script src="recursos/funciones.js?v=portal14"></script><script src="recursos/portal_inicio.js?v=18"></script><script src="recursos/iconos.js?v=18"></script><script src="recursos/inscripcion_formulario.js?v=21"></script><script src="recursos/cobro_panel.js?v=26"></script></body></html><?php }

function publicHeader(string $title): void {
 $GLOBALS['public_layout']=true; ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · UNIOR</title><link rel="stylesheet" href="recursos/adminlte.min.css"><link rel="stylesheet" href="recursos/estilos.css?v=portal14"><link rel="stylesheet" href="recursos/identidad.css?v=22"><link rel="stylesheet" href="recursos/portal_inicio.css?v=18"></head><body class="public-portal <?=!empty($GLOBALS['inicio_publico'])?'inicio-publico':''?>">
<header class="public-header no-print"><a href="<?=publicUrl()?>" class="unior-wordmark marca-institucional"><img class="logo-portal" src="recursos/logo-unior.jpg" alt="UNIOR · Universidad Privada de Oruro"></a><span class="portal-label">FORMACIÓN · CONGRESOS · TALLERES</span></header>
<main class="public-main"><div class="public-title no-print"><span class="eyebrow">UNIOR · FORMACIÓN CONTINUA</span><h1><?=e($title)?></h1></div><?php if(isset($_SESSION['flash'])):?><div class="alert alert-info no-print"><?=e($_SESSION['flash'])?></div><?php unset($_SESSION['flash']);endif;
}
function publicFooter(): void { ?>
</main><footer class="public-footer no-print"><b>UNIOR</b> · Universidad Privada de Oruro<br><small>Congresos, cursos y talleres</small></footer><script src="recursos/qrcode.min.js"></script><script src="recursos/comunidad_qr.js?v=12"></script><script src="recursos/funciones.js?v=portal14"></script><script src="recursos/portal_inicio.js?v=18"></script><script src="recursos/iconos.js?v=18"></script><script src="recursos/inscripcion_formulario.js?v=21"></script><script src="recursos/cobro_panel.js?v=26"></script></body></html><?php }

function proteccionArchivo(): string { return "<?php http_response_code(403); exit; ?>\n"; }

function tratamientos(): array {return [''=>'','Lic.'=>'Lic.','Ing.'=>'Ing.','Univ.'=>'Univ.','Sr.'=>'Sr.','Sra.'=>'Sra.','Est.'=>'Est.','Dr.'=>'Dr.','Dra.'=>'Dra.','Mgtr.'=>'Mgtr.','Prof.'=>'Prof.','Profa.'=>'Profa.','Tec.'=>'Tec.'];}
function nombreParticipante(array $r): string {return trim(($r['tratamiento']??'').' '.($r['first_name']??'').' '.($r['last_name']??''));}

require __DIR__.'/comunidad.php';

require __DIR__.'/grupos.php';

require __DIR__.'/validacion_inscripcion.php';

require __DIR__.'/atencion_individual.php';

require __DIR__.'/pagos.php';

function carrerasInscripcion(): array {
 $lista=['Sistemas','Auditoría','Gastronomía y Hotelería','Derecho','Medicina','Odontología','Prótesis','Administración de Empresas','Enfermería','Otra carrera'];
 return array_combine($lista,$lista);
}
function validarCarrera(array $datos): string {
 $carrera=trim((string)($datos['carrera']??'Otra carrera'));
 if(!isset(carrerasInscripcion()[$carrera]))throw new RuntimeException('Seleccione una carrera válida.');
 return $carrera;
}
function qrWhatsappComprobante(array $r): void {
 $enlace=$r['enlace_grupo_whatsapp']??'';
 if($enlace===''||!enlaceGrupoValido($enlace))return;
 ?><div class="receipt-qr qr-whatsapp-comprobante"><div class="qr qr-grupo-unior" data-grupo-qr="<?=e($enlace)?>" aria-label="QR del grupo de WhatsApp"></div><strong>ÚNETE A NUESTRO GRUPO</strong><small>WhatsApp · Comunidad UNIOR</small></div><?php
}
