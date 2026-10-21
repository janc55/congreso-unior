<?php
// Portal independiente del panel administrativo.
define('PUBLIC_PORTAL', true);
$p=(string)($_GET['p']??'home');
if(!in_array($p,['home','register','receipt','verify','file'],true)){http_response_code(404);exit('Página no disponible en el portal de inscripción.');}
require __DIR__.'/inicio.php';
