<?php
define('CERTIFICATE_WINDOW', true);
$_GET['p']=$_GET['p']??'list';
if(!in_array($_GET['p'],['list','certificate','participant','login'],true)){http_response_code(404);exit('Página de certificados no encontrada.');}
require __DIR__.'/inicio.php';
