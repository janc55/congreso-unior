<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
function existeTabla(PDO $db,string $table): bool {
 $s=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);return (bool)$s->fetchColumn();
}
function existeColumna(PDO $db,string $table,string $column): bool {
 $s=$db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$table,$column]);return (bool)$s->fetchColumn();
}
function crearEsquema(PDO $db): void {
 foreach(explode(';',file_get_contents(dirname(__DIR__).'/base_datos/esquema.sql')) as $sql)if(trim($sql)!=='')$db->exec($sql);
}
function migrarBase(PDO $db): void {
 $columns=columnasAnteriores();$definitions=definicionesAnteriores();
 foreach(tablasAnteriores() as $old=>$new){
  if(existeTabla($db,$old)){
   if(existeTabla($db,$new))throw new RuntimeException('Existen las tablas '.$old.' y '.$new.' a la vez. Seleccione la base original o revise su respaldo.');
   $db->exec("RENAME TABLE `$old` TO `$new`");
  }
  if(!existeTabla($db,$new))continue;
  foreach($definitions[$old]??[] as $column=>$definition){
   $translated=$columns[$column];
   if(existeColumna($db,$new,$column)&&!existeColumna($db,$new,$translated))$db->exec("ALTER TABLE `$new` CHANGE COLUMN `$column` `$translated` $definition");
  }
 }
 crearEsquema($db);
 if(!existeColumna($db,'inscripciones','carrera'))$db->exec("ALTER TABLE inscripciones ADD COLUMN carrera VARCHAR(120) NOT NULL DEFAULT 'Otra carrera'");

 foreach(['tarifas','inscripciones'] as $tabla){
  $columna=$db->query("SHOW COLUMNS FROM `$tabla` LIKE 'categoria'")->fetch(PDO::FETCH_ASSOC);
  if(!str_contains($columna['Type'],'Estudiante BTH'))$db->exec("ALTER TABLE `$tabla` MODIFY COLUMN categoria ENUM('Estudiante','Profesional','Estudiante BTH','UNIOR') NOT NULL");
 }

 if(!existeColumna($db,'inscripciones','monto_cobrado')){
  $db->exec('ALTER TABLE inscripciones ADD COLUMN monto_cobrado DECIMAL(12,2) NOT NULL DEFAULT 0');
  $db->exec("UPDATE inscripciones SET monto_cobrado=precio WHERE estado='Pagado'");
 }
 // Reservar también las referencias históricas: una misma operación no se reutiliza.
 $db->exec("INSERT IGNORE INTO pagos_inscripcion(codigo_conjunto,id_titular,importe_abono,codigo_transaccion,estado_abono,metodo_abono,nota_abono) SELECT CONCAT('historico-',MIN(id)),MIN(id),0,UPPER(TRIM(referencia)),'Confirmado','Histórico','Referencia histórica reservada; ingreso conservado en inscripción' FROM inscripciones WHERE TRIM(COALESCE(referencia,''))<>'' GROUP BY UPPER(TRIM(referencia))");
 foreach(['eventos'=>['grupo_minimo'=>'INT UNSIGNED NOT NULL DEFAULT 5','grupo_descuento'=>'DECIMAL(5,2) NOT NULL DEFAULT 20'],'inscripciones'=>['codigo_grupo'=>'CHAR(48) NULL','precio_base'=>'DECIMAL(12,2) NOT NULL DEFAULT 0','descuento_grupo'=>'DECIMAL(12,2) NOT NULL DEFAULT 0']] as $tabla=>$campos)foreach($campos as $campo=>$definicion)if(!existeColumna($db,$tabla,$campo))$db->exec("ALTER TABLE `$tabla` ADD COLUMN `$campo` $definicion");
 $indice=$db->query("SHOW INDEX FROM inscripciones WHERE Key_name='codigo_grupo'")->fetch();if(!$indice)$db->exec('ALTER TABLE inscripciones ADD INDEX codigo_grupo(codigo_grupo)');
 if(!existeColumna($db,'eventos','enlace_grupo_whatsapp'))$db->exec("ALTER TABLE eventos ADD COLUMN enlace_grupo_whatsapp VARCHAR(2048) NOT NULL DEFAULT ''");
 if(!existeColumna($db,'inscripciones','tratamiento'))$db->exec("ALTER TABLE inscripciones ADD COLUMN tratamiento VARCHAR(20) NOT NULL DEFAULT '' AFTER id");
 if(!existeColumna($db,'gastos','id_categoria')){
  $db->exec('ALTER TABLE gastos ADD COLUMN id_categoria INT UNSIGNED NULL');
  $db->exec('ALTER TABLE gastos ADD CONSTRAINT fk_gasto_categoria FOREIGN KEY(id_categoria) REFERENCES categorias_gastos(id)');
 }
 $db->exec("ALTER TABLE eventos MODIFY COLUMN enlace_grupo_whatsapp VARCHAR(2048) NOT NULL DEFAULT ''");
 $db->exec('UPDATE eventos SET grupo_minimo=5,grupo_descuento=20');
 $db->exec("UPDATE eventos SET color_nombre='#ba1320' WHERE color_nombre='#000080'");
}
function protegerArchivosAnteriores(): void {
 $dir=dirname(__DIR__).'/almacenamiento/archivos_subidos';
 foreach(glob($dir.'/*')?:[] as $file){
  if(preg_match('/^[a-f0-9]{48}\.(jpg|png|webp|pdf)$/',basename($file))){
   $data=file_get_contents($file);$target=$file.'.php';
   if(file_put_contents($target,"<?php http_response_code(403); exit; ?>\n".$data,LOCK_EX)===false)throw new RuntimeException('No se pudo proteger un comprobante anterior. Revise los permisos de almacenamiento/archivos_subidos.');
   if(!unlink($file))throw new RuntimeException('No se pudo retirar el archivo anterior. Revise los permisos.');
  }
 }
}

function verificarAdministrador(PDO $db,string $user,string $password): bool {
 $table=existeTabla($db,'users')?'users':(existeTabla($db,'usuarios')?'usuarios':null);if(!$table)return false;
 $username=existeColumna($db,$table,'usuario')?'usuario':'username';$key=existeColumna($db,$table,'clave')?'clave':'password';$role=existeColumna($db,$table,'rol')?'rol':'role';$active=existeColumna($db,$table,'activo')?'activo':'active';
 $s=$db->prepare("SELECT `$key` clave,`$role` rol,`$active` activo FROM `$table` WHERE `$username`=?");$s->execute([$user]);$admin=$s->fetch();return $admin&&$admin['rol']==='admin'&&$admin['activo']&&password_verify($password,$admin['clave']);
}
