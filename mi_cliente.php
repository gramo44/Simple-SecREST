<?php
/*! \file cliente/lib/mi_cliente.php
Este software fué realizado por el Ing. Ricardo Naranjo
Faccini, M.Sc.  para Skina IT Solutions E.U. fábrica de
software colombiana radicada en Bogotá.

Skina IT Solutions E.U.
https://www.skinait.com
soporte@skinait.com

Copyright 2021

Este archivo es parte de la librería SimpleSecREST.
Contiene el ejemplo de uso de la librería desde el punto de
vista del cliente REST. Propone una implementación básica
de las extensiones requeridas al clienteREST en la cual
siempre será necesario que se implementen los métodos
abstractos:
function guardar_sesion_servicio($url, $sesion, $pkey)
public function cargar_sesion_servicio($url)
Con los cuales se indicará al cliente cómo hacer el
almacenamiento persistente de las llaves públicas y
los identificadores de sesión.
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
require_once("lib/Simple-SecREST.php");
use skinait\REST\clienteREST as CL_REST;

class mi_cliente extends CL_REST {
    //====================================================================
    //====================================================================
    // Métodos obligatorios a implementar (abstractos):
    //     guardar_sesion_servicio
    //     cargar_sesion_servicio
    //====================================================================
    //====================================================================
    /*------------------------------------------------------------------*/
    function guardar_sesion_servicio($url, $sesion, $pkey)
    /*********************************************************************
    @brief Almacena en forma persistente (archivo o base de datos) el id
    de sesión indicado asociado con el servidor con una llave pública y su
    fecha de creación.

    Ésta función existe para minimizar el tráfico asociado con el envío
    de llaves públicas entre cliente y servidor.

    NOTA PARA ÉSTE EJEMPLO
    Debe ser implementada de acuerdo con la lógica particular de cada
    sistema de información.
    En éste ejemplo se almacena en un archivo en formato json con
    hashtables. Pero es ideal que se almacene en una base de datos como
    postgresql o mariadb.

    ENTRADAS
    @param $url El localizador del servicio con el que se estableció conexión.
    @param $sesion El identificador de sesión que se va a asociar.
    @param $pkey La llave pública asociada con el URL.
    *********************************************************************/
    {
        //----------------------------------------
        // Se carga la información del llavero.
        //----------------------------------------
        $directorio = $this->get_directorio();
        $llavero = array();
        if (file_exists($directorio."/prv/llavero_pubkeys.db")) {
            $llavero = file_get_contents($directorio."/prv/llavero_pubkeys.db");
            // $llavero = base64_decode($llavero);
            $llavero = json_decode($llavero);
        }

        //----------------------------------------
        // Se cambia el objeto llavero a arreglo.
        //----------------------------------------
        $arreglo = array();
        foreach ($llavero as $llave => $valor)
            foreach ($valor as $ll => $vl)
                $arreglo[$llave][$ll] = $vl;
        $llavero = $arreglo;

        //----------------------------------------
        // Se agrega la llave que se quiere
        // registrar.
        //----------------------------------------
        $hash = md5($url);
        $llavero[$hash][$sesion] = array(time(), $this->get_duracion(), $pkey);

        //----------------------------------------
        // Se limpian las llaves expiradas
        //----------------------------------------
        $sin_antiguas = array();
        foreach ($llavero[$hash] as $sesion => $valor) {
            if ($valor[0] > time() - $valor[1])
                $sin_antiguas[$sesion] = $valor;
        }
        $llavero[$hash] = $sin_antiguas;

        //----------------------------------------
        // Se almacena la información del llavero.
        //----------------------------------------
        $llavero = json_encode($llavero);
        // $llavero = base64_encode($llavero);
        file_put_contents($directorio."/prv/llavero_pubkeys.db", $llavero);
    }

    /*------------------------------------------------------------------*/
    public function cargar_sesion_servicio($url)
    /*********************************************************************
    @brief Esta función debe recuperar el último id de sesión establecido
    con el servidor si se tiene disponible.

    Debe ser implementada de acuerdo con la lógica particular de cada
    sistema de información.

    NOTA PARA ÉSTE EJEMPLO
    En éste ejemplo se almacena en un archivo en formato json con
    hashtables. Pero es ideal que se almacene en una base de datos como
    postgresql o mariadb.

    ENTRADAS
    @param $url El localizador del servicio con el que se está
    estableciendo conexión.
    SALIDA
    Arreglo con:
    El último id de sesión o null si no se tiene
    La llave pública asociada con el id de sesión.
    *********************************************************************/
    {
        //----------------------------------------
        // Se carga la información del llavero.
        //----------------------------------------
        $directorio = $this->get_directorio();
        $llavero = array();
        $sesion = null;
        $pubkey = null;
        if (file_exists($directorio."/prv/llavero_pubkeys.db")) {
            $llavero = file_get_contents($directorio."/prv/llavero_pubkeys.db");
            // $llavero = base64_decode($llavero);
            $llavero = json_decode($llavero);
            $arr = array();
            foreach ($llavero as $llave => $valor)
                foreach ($valor as $ll => $vl)
                    $arr[$llave][$ll] = $vl;
            $llavero = $arr;
        }

        $hash = md5($url);
        if (isset($llavero[$hash])) {
            $sesion = array_key_last($llavero[$hash]);
            $pubkey = array_pop($llavero[$hash]);
        }

        return array($sesion, $pubkey);
    }

}
?>
