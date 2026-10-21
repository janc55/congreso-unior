<?php
if(!defined('UNIOR_INICIADO')){http_response_code(403);exit;}
class ErrorDatosInscripcion extends RuntimeException {
 public function __construct(public array $campos){parent::__construct('Hay documentos repetidos o ya inscritos. Revise los campos marcados en rojo.');}
}
function validarDocumentosInscripcion(int $evento,array $personas): void {
 $indices=[];$errores=[];
 foreach($personas as $indice=>$persona){$ci=mb_strtoupper(trim((string)($persona['ci']??'')));if($ci==='')continue;$campo=$indice===0?'ci':'integrantes['.($indice-1).'][ci]';$indices[$ci][]=$campo;}
 foreach($indices as $ci=>$campos){$existe=(bool)one('SELECT id FROM registrations WHERE event_id=? AND ci=?',[$evento,$ci]);if($existe||count($campos)>1)foreach($campos as $campo)$errores[$campo]=$existe?'Este CI / documento ya está inscrito en este evento.':'Este CI / documento está repetido dentro del grupo.';}
 if($errores)throw new ErrorDatosInscripcion($errores);
}
function respuestaInscripcionJson(): bool {return ($_POST['_respuesta']??'')==='json'||str_contains(strtolower($_SERVER['HTTP_ACCEPT']??''),'application/json');}
function emitirRespuestaInscripcion(array $datos,int $estado=200): never {
 if(isset($GLOBALS['buffer_registro']))while(ob_get_level()>=$GLOBALS['buffer_registro'])ob_end_clean();
 http_response_code($estado);header('Content-Type: application/json; charset=utf-8');echo json_encode($datos,JSON_INVALID_UTF8_SUBSTITUTE);exit;
}
function finalizarInscripcion(string $token): never {
 $query='p=receipt&t='.$token;
 if(!publicPortal()&&user()){
  if(isset($_POST['paid_now'])){
   $r=registration($token);$pago=one("SELECT id FROM pagos_inscripcion WHERE codigo_conjunto=? AND estado_abono='Confirmado' ORDER BY id DESC LIMIT 1",[claveCobro($r)]);
   if($pago)$query.='&pago='.$pago['id'];
  }
  $query.='&imprimir=1';
 }
 if(respuestaInscripcionJson()){emitirRespuestaInscripcion(['ok'=>true,'redirect'=>publicPortal()?publicUrl($query):url($query)]);}
 header('Location: '.(publicPortal()?publicUrl($query):url($query)),true,303);exit;
}
function valorRegistro(string $nombre,string $valor): string {return !empty($GLOBALS['registro_fallido'])&&isset($_POST[$nombre])&&is_scalar($_POST[$nombre])?(string)$_POST[$nombre]:$valor;}
function errorCampoRegistro(string $nombre): string {return $GLOBALS['errores_registro'][$nombre]??'';}
