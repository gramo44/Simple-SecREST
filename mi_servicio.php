<?php
/*! \file servidor/lib/mi_servicio.php
Este software fué realizado por el Ing. Ricardo Naranjo
Faccini, M.Sc.  para Skina IT Solutions E.U. fábrica de
software colombiana radicada en Bogotá.

Skina IT Solutions E.U.
https://www.skinait.com
soporte@skinait.com

Copyright 2021

Este archivo es parte de la librería SimpleSecREST.
Contiene el ejemplo de uso de la librería desde el punto de
vista del servidor REST. Propone una implementación básica
de las extensiones requeridas al servidorREST en la cual
siempre será necesario que se implementen los métodos
abstractos:
function cargar_hash_de_clave($login) : string
function guardar_llave_publica_sesion($sesion, $pkey)
function cargar_llave_publica_sesion($sesion)
Con los cuales se indicará al cliente cómo hacer el
almacenamiento persistente de las llaves públicas y
los identificadores de sesión y cómo recuperar los hash
md5 de las claves de los usuarios autorizados a conectarse
mediante un clienteREST.
En ésta extensión deberán también implementarse los
diversos servicios que provee el servidor REST manteniendo
el nombre del método con el prefijo M_REST_
Cada método recibirá un arreglo $request con los parámetros
que solicita el cliente y deberá retornar un arreglo con
los campos:
    $retorno = array( 'dato1' => 0
                    , 'dato2' => 0
                    , 'dato3' => 0
                    , 'id_error' => 0
                    , 'error' => ""
                    );

----------------------------------------
This file is part of Simple-SecREST.

Simple-SecREST is free software: you can redistribute it
and/or modify it under the terms of the GNU Lesser General
Public License as published by the Free Software Foundation,
either version 3 of the License, or (at your option) any
later version.

Foobar is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty
of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See
the GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General
Public License along with Simple-SecREST.  If not, see
<https://www.gnu.org/licenses/>.
***********************************************************/
require_once("lib/Simple-SecREST.php");
use skinait\REST\servidorREST as SR_REST;

class mi_servicio extends SR_REST {
    //====================================================================
    //====================================================================
    // Métodos propios del servicio que se está implementando.
    //====================================================================
    //====================================================================

    /*------------------------------------------------------------------*/
    function M_REST_servicio_1($request)
    /*********************************************************************
    @brief Explicación servicio_1.
    
    ENTRADAS:
    @param $request['xxxx'] Explicación parámetro xxxx
    @param $request['yyyy'] Explicación parámetro yyyy
    SALIDAS:
    Arreglo con los siguientes campos:
        $retorno['xxxx'] Explicacion salida xxxx
        $retorno['yyyy'] Explicacion salida xxxx
        $retorno['id_error'] Código del error generado si lo hubiera.
        $retorno['error'] Descripción del error generado si lo hubiera.
    *********************************************************************/
    {
        $retorno = array( 'xxxx' => 0
                        , 'id_error' => 0
                        , 'error' => ""
                        );
    
        return $retorno;
    }


    /*------------------------------------------------------------------*/
    function M_REST_existen_procesos_asociados($request)
    /*********************************************************************
    @brief Verifica en la base de datos si existen procesos de cobro
    activos asociados con un deudor.

    NOTA PARA ÉSTE EJEMPLO
    Todos los metodos REST del servicio deben seguir el siguiente formato:
    El nombre del método debe tener el prefimo M_REST_
    La función debe retornar un arreglo asociativo en el cual se deben incluir
    los campos id_error y error como se describen en SALIDAS.

    ENTRADAS:
    @param $request['id_deudor'] Documento de identidad del deudor [opcional].
    @param $request['email_deudor'] Correo electrónico del deudor [opcional].
    SALIDAS:
    Arreglo con los siguientes campos:
        $retorno['Qprocesos'] Cantidad de procesos de cobro asociados con la identidad. 
        $retorno['id_error'] Código del error generado si lo hubiera.
        $retorno['error'] Descripción del error generado si lo hubiera.
    *********************************************************************/
    {
        $retorno = array( 'Qprocesos' => 0
                        , 'id_error' => 0
                        , 'error' => ""
                        );

        if (isset($request['id_deudor'])) {
            if ($request['id_deudor'] == "123456789")
                $retorno['Qprocesos'] = 5;
        }
         
        if (isset($request['email_deudor'])) {
            if ($request['email_deudor'] == "soporte@skinait.com")
                $retorno['Qprocesos'] = 0;
        }
         
        if (isset($request['email_deudor'])) {
            if ($request['email_deudor'] == "soporteskinait.com") {
                $retorno['Qprocesos'] = 0;
                $retorno['id_error'] = -901;
                $retorno['error'] = "El correo electrónico del deudor no tiene formato de email.";
            }
        }
    
        return $retorno;
    }


    //====================================================================
    //====================================================================
    // Métodos obligatorios a implementar (abstractos):
    //     cargar_hash_de_clave
    //     guardar_llave_publica_sesion
    //     cargar_llave_publica_sesion
    //====================================================================
    //====================================================================
    /*------------------------------------------------------------------*/
    function cargar_hash_de_clave($login) : string
    /*********************************************************************
    @brief Esta función debe entregar el SHA512 de la clave del usuario
    con $login para ser utilizada durante la autenticación del cliente.

    NOTA PARA ÉSTE EJEMPLO
    En éste ejemplo los hash de las llaves están "quemados" en el código
    pero idealmente deberían estar almacenados en una base de datos como
    postgresql o mariadb.

    ENTRADAS:
    @param $login el login del usuario
    SALIDAS:
    La clave del usuario
    *********************************************************************/
    {
        $retorno = null;

        switch ($login) {
        case "skinait":
            $retorno = "46df87fc5147954c9ca6185a3d029da501619568dcbc"
                     . "3bd7faa0d6375c27a7b3c69fbb6ac0973b4ffabe5c8a"
                     . "d36dfdb92e77291c76835c9abfd1e46c683d6521";
            break;
        }

        return $retorno;
    }

    /*------------------------------------------------------------------*/
    function guardar_llave_publica_sesion($sesion, $pkey)
    /*********************************************************************
    @brief Almacena en forma persistente (archivo o base de datos) un
    código de sesión establecido asociado con una llave pública y su fecha
    de creación.
    Llaves más antiguas que la duración establecida en $this->duracion
    deberán ser eliminadas del llavero.

    NOTA PARA ÉSTE EJEMPLO
    En éste ejemplo se almacena en un archivo en formato json con
    hashtables. Pero es ideal que se almacene en una base de datos como
    postgresql o mariadb.

    ENTRADAS
    @param $sesion un código alfanumérico de longitud 13 con el que se
    identifica la sesión.
    @param $pkey la llave pública asociada con el código de sesión.
    SALIDA
    bool: true -> almacenada exitosamente
    *********************************************************************/
    {
        //----------------------------------------
        // Se carga la información del llavero.
        //----------------------------------------
        $directorio = $this->directorio;
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
            $arreglo[$llave] = $valor;
        $llavero = $arreglo;

        //----------------------------------------
        // Se agrega la llave que se quiere
        // registrar.
        //----------------------------------------
        $llavero[$sesion] = array(time(), $this->get_duracion(), $pkey);

        //----------------------------------------
        // Se limpian las llaves expiradas
        //----------------------------------------
        $sin_antiguas = array();
        foreach ($llavero as $sesion => $valor)
            if ($valor[0] > time() - $valor[1])
                $sin_antiguas[$sesion] = $valor;
        $llavero = $sin_antiguas;

        //----------------------------------------
        // Se almacena la información del llavero.
        //----------------------------------------
        $llavero = json_encode($llavero);
        // $llavero = base64_encode($llavero);
        file_put_contents($directorio."/prv/llavero_pubkeys.db", $llavero);
    }

    /*------------------------------------------------------------------*/
    function cargar_llave_publica_sesion($sesion)
    /*********************************************************************
    @brief Verifica si se tiene almacenada una llave pública del cliente
    asociada con el identificador de sesión, almacenada en un momento más
    reciente que $this->duracion.

    Ésta llave pública se utiliza para cifrar la información que se va a
    devolver al cliente.

    NOTA PARA ÉSTE EJEMPLO
    En éste ejemplo se almacena en un archivo en formato json con
    hashtables. Pero es ideal que se almacene en una base de datos como
    postgresql o mariadb.

    ENTRADAS
    @param $sesion un código alfanumérico que identifica la sesión.
    SALIDA
    string : La llave pública del cliente asociada con el código de sesión.
    *********************************************************************/
    {
        //----------------------------------------
        // Se carga la información del llavero.
        //----------------------------------------
        $directorio = $this->directorio;
        $llavero = array();
        $pubkey = null;
        if (file_exists($directorio."/llavero_pubkeys.db")) {
            $llavero = file_get_contents($directorio."/llavero_pubkeys.db");
            // $llavero = base64_decode($llavero);
            $llavero = json_decode($llavero);
        }

        //----------------------------------------
        // Se busca la llave correspondiente a la
        // sesion.
        //----------------------------------------
        if (isset($llavero[$sesion]))
            if ($llavero[$hash][0] > time() + $this->get_duracion())
                $pubkey = array_pop($llavero[$hash][1]);

        return $pubkey;
    }

    
}
?>
