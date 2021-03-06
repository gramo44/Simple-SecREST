<?php
/*! \file lib/herramientas.php
Este software fué realizado por el Ing. Ricardo Naranjo
Faccini, M.Sc.  para Skina IT Solutions E.U. fábrica de
software colombiana radicada en Bogotá.

Skina IT Solutions E.U.
https://www.skinait.com
soporte@skinait.com

Copyright 2021

Este archivo es parte de la librería SimpleSecREST.
Contiene funciones utilitarias varias que se requieren
para el buen funcionamiento del sistema pero no
están necesariamente involucradas con la transmisión
REST segura.
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
------------------------------------------------------------
CONTRIBUTORS:
Dave Bolt
***********************************************************/

/*------------------------------------------------------------------*/
if ( ! function_exists( 'array_key_last' ) ) {
    function array_key_last( $arreglo ) {
/*********************************************************************
Polyrelleno para array_key_last() función que fue agregada in PHP 7.3.

Obtiene el nombre del último campo del arreglo sin afectar el puntero
interno.

ENTRADAS:
@param $arreglo El arreglo
SALIDAS:
@return mixed El nombre del último campo del arreglo si no está vacío;
    NULL de lo contrario.

----------------------------------------------------------------------
Polyfill for array_key_last() function added in PHP 7.3.

Get the last key of the given array without affecting
the internal array pointer.

@param array $arreglo An array

@return mixed The last key of array if arreglo is not empty; NULL
otherwise.
*********************************************************************/
        $retorno = NULL;
 
        if (is_array($arreglo)) {
            end($arreglo);
            $retorno = key($arreglo);
        }
 
        return $retorno;
    }
}

/*------------------------------------------------------------------*/
function Mostrar($dato, $texto = "")
/*********************************************************************
@brief Retorna el HTML para desplegar un dato con un letrero.
ENTRADAS:
@param $dato El dato a desplegar
@param $texto El letrero
SALIDAS:
ninguna

----------------------------------------------------------------------
@brief Returns an HTML in order to display a data with a sign
INPUTS:
@param $dato The data to be displayed
@param $texto The sign
OUTPUT:
none
*********************************************************************/
{
    return "$texto<pre>"
         . htmlentities(stripslashes(var_export($dato, true)))
         . "</pre>";
}


/*------------------------------------------------------------------*/
function periodo2unidades($intervalo, $tipo)
/*********************************************************************
@brief Cambia el formato de un intervalo a las unidades expresadas en tipo.

ENTRADAS:
@param $intervalo El intervalo a formatear
@param $tipo Podrá ser years, months, days, hours, minutes, seconds o miliseconds
SALIDAS:
Intervalo reformateado
*********************************************************************/
{
    switch($tipo) {
        case 'years':
            return $intervalo->format('%Y');
            break;
        case 'months':
            $years = $intervalo->format('%Y');
            $months = 0;
            if($years)
                $months += $years*12;
            $months += $intervalo->format('%m');
            return $months;
            break;
        case 'days':
            return $intervalo->format('%a');
            break;
        case 'hours':
            $days = $intervalo->format('%a');
            $hours = 0;
            if($days)
                $hours += 24 * $days;
            $hours += $intervalo->format('%H');
            return $hours;
            break;
        case 'minutes':
            $qdias = $intervalo->format('%a');
            if(!is_numeric($qdias))
                $qdias = 0;
            $qhoras = $intervalo->format('%H');
            if(!is_numeric($qhoras))
                $qhoras = 0;
            $qminutos = $intervalo->format('%i');
            if(!is_numeric($qminutos))
                $hours = 0;
            $minutos = 24 * 60 * $qdias + 60 * $qhoras + $qminutos;
            return $minutos;
            break;
        case 'seconds':
            $days = $intervalo->format('%a');
            $seconds = 0;
            if($days)
                $seconds += 24 * 60 * 60 * $days;
            $hours = $intervalo->format('%H');
            if($hours)
                $seconds += 60 * 60 * $hours;
            $minutes = $intervalo->format('%i');
            if($minutes)
                $seconds += 60 * $minutes;
            $seconds += $intervalo->format('%s');
            return $seconds;
            break;
        case 'milliseconds':
            $days = $intervalo->format('%a');
            $seconds = 0;
            if($days)
                $seconds += 24 * 60 * 60 * $days;
            $hours = $intervalo->format('%H');
            if($hours)
                $seconds += 60 * 60 * $hours;
            $minutes = $intervalo->format('%i');
            if($minutes)
                $seconds += 60 * $minutes;
            $seconds += $intervalo->format('%s');
            $milliseconds = $seconds * 1000;
            return $milliseconds;
            break;
        default:
            return NULL;
    }
}


/*------------------------------------------------------------------*/
function traza_de_funciones()
/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
@brief Para depurar presenta la jerarquia de llamado de funciones que
se invocaron para llegar al llamado a ésta función.
--------------------------------------------------------------------*/
{
    $retorno = "";

    $trace = debug_backtrace();
    array_shift($trace);
    $trace = array_reverse($trace);
    foreach ($trace as $traza) {
        $retorno .= "\n".$traza["function"]."(";
        foreach ($traza['args'] as $valor) {
            if (is_object($valor)) {
                if (isset($valor->id))
                    $retorno .= "[[".intval($valor->id)."]], ";
                else
                    $retorno .= var_export($valor, true).", ";
            } else
                $retorno .= var_export($valor, true).", ";
        }
        $retorno.= ")";
    }

    return $retorno;
}


?>
