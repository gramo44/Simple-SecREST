<?php
/*! \file servidor/servicio.php
Este software fué realizado por el Ing. Ricardo Naranjo
Faccini, M.Sc.  para Skina IT Solutions E.U. fábrica de
software colombiana radicada en Bogotá.

Skina IT Solutions E.U.
https://www.skinait.com
soporte@skinait.com

Copyright 2021

Este archivo es parte de la librería SimpleSecREST.
Contiene el ejemplo de uso de la librería desde el punto de
vista del servidor REST.
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
error_reporting(E_ALL);
ini_set('display_errors', 1);
// setlocale(LC_ALL, 'es_ES.UTF-8', 'es_CO', 'es', 'es_ES@euro', 'es_ES');
// date_default_timezone_set('America/Bogota');
setlocale(LC_ALL, 'en_GB.UTF-8');
date_default_timezone_set('Europe/London');

require_once("lib/mi_servicio.php");
require_once("lib/herramientas.php");

$depurando = true;
// $servidor = new mi_servicio(__DIR__, null, 300, $depurando);
$servidor = new mi_servicio($_SERVER['DOCUMENT_ROOT'], null, 300, $depurando);

$lista_blanca['metodo'] = array( "existen_procesos_asociados"
                               // , "servicio_2"
                               // , "servicio_3"
                               // , "servicio_4"
                               // , "servicio_5"
                               );
$servidor->establecer_lista_blanca($lista_blanca);

$respuesta = $servidor->atender($_REQUEST);

print $respuesta;
?>
