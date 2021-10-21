<?php
/*! \file cliente/cliente.php
Este software fué realizado por el Ing. Ricardo Naranjo
Faccini, M.Sc.  para Skina IT Solutions E.U. fábrica de
software colombiana radicada en Bogotá.

Skina IT Solutions E.U.
https://www.skinait.com
soporte@skinait.com

Copyright 2021

Este archivo es parte de la librería SimpleSecREST.
Contiene el ejemplo de uso de la librería desde el punto de
vista del cliente REST.
----------------------------------------
This file is part of Simple-SecREST.

Simple-SecREST is free software: you can redistribute it
and/or modify it under the terms of the GNU Lesser General
Public License as published by the Free Software Foundation,
either version 3 of the License, or (at your option) any
later version.

Simple-SecREST is distributed in the hope that it will be
useful, but WITHOUT ANY WARRANTY; without even the implied
warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
PURPOSE. See the GNU Lesser General Public License for more
details.

You should have received a copy of the GNU Lesser General
Public License along with Simple-SecREST.  If not, see
<https://www.gnu.org/licenses/>.
***********************************************************/
// https://190.146.247.164/~gramo/desarrollos/SOAP_tutorial/REST/
// Bibliografia:
// https://reqbin.com/req/php/v0crmky0/rest-api-post-example
// https://code.tutsplus.com/es/tutorials/how-to-build-a-simple-rest-api-in-php--cms-37000
error_reporting(E_ALL);
ini_set('display_errors', 1);
setlocale(LC_ALL, 'es_ES.UTF-8', 'es_CO', 'es', 'es_ES@euro', 'es_ES');
date_default_timezone_set('America/Bogota');
$depurando = true;

require_once("lib/mi_cliente.php");
require_once("lib/herramientas.php");

// print Mostrar($_SERVER, "_SERVER");
$url = "http://"
     . $_SERVER['SERVER_NAME']
     . preg_split("#/cliente#", $_SERVER['REQUEST_URI'])[0]
     . "/servidor/servicio.php";

$login = "skinait";
$clave = hash('sha512', '3uv5nqc!"');
$fecha = date("Y-m-d H:i:s");
// $cliente = new mi_cliente($url, $login, $clave, $fecha, __DIR__, 10);
$cliente = new mi_cliente($url, $login, $clave, $fecha, __DIR__, $depurando);

$parametros['id_deudor'] = "123456789";
$respuesta = $cliente->solicitar("existen_procesos_asociados", $parametros);
print Mostrar($parametros, "SOLICITUD existen_procesos_asociados");
print Mostrar($respuesta);

$parametros['email_deudor'] = "soporte@skinait.com";
$respuesta = $cliente->solicitar("existen_procesos_asociados", $parametros);
print Mostrar($parametros, "SOLICITUD existen_procesos_asociados");
print Mostrar($respuesta);

$parametros['email_deudor'] = "soporteskinait.com";
$respuesta = $cliente->solicitar("existen_procesos_asociados", $parametros);
print Mostrar($parametros, "SOLICITUD existen_procesos_asociados");
print Mostrar($respuesta);

?>
