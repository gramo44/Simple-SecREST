<?php
/*! \file Simple-SecREST.php
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
namespace skinait\REST;

require_once("lib/herramientas.php");
require_once("lib/cifrado_RSA.php");
use skinait\Codificador\codificador as RSA;

/*! Clase para la descarga del contenido de un url. Generalmente será
símplemente un file_get_contents, pero es posible que algún sitio
requiera algo más elaborado mediante el uso de CURL.

OJO:
Revisar la documentación de:
https://reqbin.com/req/php/v0crmky0/rest-api-post-example
 */
class conexionREST {
    /*------------------------------------------------------------------*/
    public static function solicitar_url($url)
    /*********************************************************************
    @brief Descarga el contenido de un URL.

    ENTRADAS:
    @param $url La ruta al recurso que se va a descargar.
    SALIDAS:
    string: contenido de la página
    *********************************************************************/
    {
        $retorno = file_get_contents($url);
        return $retorno;
    }
}

/*! Clase para el cliente de integración de componentes via REST. */
abstract class clienteREST extends conexionREST {
    public  $url;        //!< El localizador del servicio con el que se establecerá conexión.
    public  $login;      //!< El login.
    public  $hclave;     //!< El sha512 de la clave.
    public  $fecha;      //!< Fecha y hora del momento en que se estableció la conexión
    private $estado;     //!< True: autenticado; False: no autenticado.
    private $directorio; //!< Directorio con permisos de lecto-escritura donde se
                         //!< podrán almacenar las llaves de cifrado.
    private $sesion;     //!< El identificador de sesión.
    private $ll_srv;     //!< La llave pública del servicio se va a consultar.
    private $rsa;        //!< El objeto para cifrar con RSA los datos
    private $depurando;  //!< Indica si se permite mostrar letreros de depuración
    /*------------------------------------------------------------------*/
    function __construct($url, $login, $hclave, $fecha, $directorio, $depurando = false, $duracion = 300)
    /*********************************************************************
    @brief Constructora, inicializa los campos del objeto a sus valores
    por defecto.
 
    ENTRADAS:
    @param $url El localizador del servicio con el que se establecerá conexión.
    @param $login El login de acceso.
    @param $hclave La huella SHA512 de la clave de acceso.
    @param $fecha La fecha y hora de cuando se estableció la conexión.
    @param $directorio El directorio donde se pueden almacenar las llaves.
    @param $duracion La cantidad de segundos que pueden transcurrir sin
    que se deban refrescar las llaves de cifrado.
    *********************************************************************/
    {
        $error = "";
        $this->url     = null;
        $this->estado  = false;
        $this->login   = null;
        $this->hclave  = null;
        $this->ll_srv  = null;

        if ($depurando == false || $depurando == true)
            $this->depurando = $depurando;

        if (!is_dir($directorio."/prv"))
            $error = "$directorio/prv no es un directorio.\n";
        else if (!is_writable($directorio."/prv"))
            $error = "$directorio/prv no tiene permisos de escritura.\n";

        if (!is_int($duracion))
            $error = "$duracion debe ser un número entero";
        else if ($duracion < 0)
            $error = "$duracion debe ser un número positivo";

        if ($error == "") {
            $this->url     = $url;

            $this->rsa     = new RSA($duracion, $directorio."/prv", "REST_key", "sha512", $this->depurando);
            $this->directorio = $directorio;
            $this->actualizar_ll_srv($url);

            $parametros = "?metodo=autenticar";
            $this->login = $login;
            $this->hclave = $hclave;
            $this->fecha = $fecha;
            $datos = array( 'login'  => $this->login
                          , 'fecha'  => $this->fecha
                          , 'token'  => hash('sha512', $this->hclave.$fecha)
                          , 'll_cte' => $this->rsa->get_ll_publica()
                          );
            $datos = "&datos=".urlencode(RSA::cifrar(json_encode($datos), $this->ll_srv));
            $respuesta = conexionREST::solicitar_url($url.$parametros.$datos);
            // Si vienen ['pkey'] y ['firma_pkey'] hay que actualizar la llave pública del servidor.
            if ($respuesta != null) {
                list($respuesta, $refrescar) = $this->rsa->descifrar($respuesta);
                $respuesta = json_decode($respuesta);
                // Si se indica refrescar hay que mandar la clave pública actualizada al servidor
                if ($refrescar)
                    $this->actualizar_llave_publica_de_cliente($url);
            }
            if ($respuesta->autenticado) {
                $this->estado = true;
                $this->sesion = $respuesta->sesion;
            }
            // print Mostrar($url.$parametros.$datos, "URL");
            // print Mostrar($respuesta, "RESPUESTA");
            // print Mostrar($this->estado, "cliente");
            // print Mostrar($this->sesion, "cliente");
        }
    }


    /*------------------------------------------------------------------*/
    abstract protected function cargar_sesion_servicio($url);
    /*********************************************************************
    @brief Esta función debe entregar el último id de sesión establecido
    con el servidor si se tiene disponible.

    Ésta función existe para minimizar el tráfico asociado con el envío
    de llaves públicas entre cliente y servidor.

    Debe ser implementada de acuerdo con la lógica particular de cada
    sistema de información.

    ENTRADAS
    @param $url El localizador del servicio con el que se está
    estableciendo conexión.
    SALIDA
    El último id de sesión o null si no se tiene.
    La llave pública con la que se están cifrando los mensajes.
    *********************************************************************/


    /*------------------------------------------------------------------*/
    public abstract function guardar_sesion_servicio($url, $sesion, $pkey);
    /*********************************************************************
    @brief Esta función debe almacenar el id de sesión indicado asociado
    con el servidor.

    Ésta función existe para minimizar el tráfico asociado con el envío
    de llaves públicas entre cliente y servidor.

    Debe ser implementada de acuerdo con la lógica particular de cada
    sistema de información.

    ENTRADAS
    @param $url El localizador del servicio con el que se estableció conexión.
    @param $sesion El identificador de sesión que se va a asociar.
    @param $pkey La llave pública del servidor con la cual se cifrarán los mensajes.
    *********************************************************************/

    /*------------------------------------------------------------------*/
    protected function actualizar_llave_publica_de_cliente($url)
    /*********************************************************************
    @brief Se envía al servidor la llave pública actualizada firmada con
    la antigua llave privada.
 
    ENTRADAS:
    @param $url El localizador del servicio con el que se establecerá conexión.

    OJO -- OJO -- OJO
    HACER LA CONTRAPARTE EN EL SERVIDOR
    *********************************************************************/
    {
        $datos = "?metodo=actualizar_llave_publica_de_cliente";
        list($firma, $ll_publica, $tipo_huella) = RSA::firmar($this->rsa->get_ll_publica(), false, true);
        $datos = array( 'sesion'       => $this->sesion
                      , 'll_cte'       => $this->rsa->get_ll_publica()
                      , 'firma_ll_cte' => $firma
                      );
        $datos = "&datos=".urlencode(RSA::cifrar(json_encode($datos), $this->ll_srv));
        $respuesta = conexionREST::solicitar_url($url.$datos);
        // Si vienen ['pkey'] y ['firma_pkey'] hay que actualizar la llave pública del servidor.
    }

    /*------------------------------------------------------------------*/
    protected function actualizar_ll_srv($url)
    /*********************************************************************
    @brief Solicita al servidor la llave pública de cifrado que actualmente
    está utilizando.
 
    ENTRADAS:
    @param $url El localizador del servicio con el que se establecerá conexión.
    *********************************************************************/
    {
        list($sesion, $pubkey) = $this->cargar_sesion_servicio($url);

        //----------------------------------------
        // Si nunca se ha establecido la conexión
        // (la sesión es nula) se saluda al
        // servidor con la propía llave pública
        // para que nos indique su llave pública.
        //----------------------------------------
        if ($this->sesion == null) {
            $datos = "?metodo=saludo&ll_publica_cliente=".urlencode($this->rsa->get_ll_publica());
            $respuesta = conexionREST::solicitar_url($url.$datos);
        
            if ($respuesta != null) {
                $respuesta = $this->rsa->descifrar($respuesta);
                if ($respuesta != null) {
                    $llave = json_decode($respuesta[0]);
                    if (isset($llave->pkey) && isset($llave->sesion)) {
                        $this->ll_srv = $llave->pkey;
                        $this->sesion = $llave->sesion;
                        $this->guardar_sesion_servicio($url, $this->sesion, $this->ll_srv);
                    }
                }
            }
        } else {
            // Si vienen ['pkey'] y ['firma_pkey'] hay que actualizar la llave pública del servidor.
            print Mostrar(__FUNCTION__, "Hay que cargar llaves de sesión preexistentes");
        }
    }


    /*------------------------------------------------------------------*/
    protected function get_directorio()
    /*********************************************************************
    @brief Entrega la ruta al directorio donde se almacenan las llaves.
    *********************************************************************/
    {
        return $this->directorio;
    }


    /*------------------------------------------------------------------*/
    protected function get_duracion()
    /*********************************************************************
    @brief Entrega la duración en segundos de las llaves.
    *********************************************************************/
    {
        return $this->rsa->get_duracion();
    }


    /*------------------------------------------------------------------*/
    public function solicitar($metodo, $parametros = array())
    /*********************************************************************
    @brief Solicita la ejecución de un método al servidor enviándo los
    parámetros indicados.
    ENTRADAS
    @param $metodo El método que se solicitará al servidor.
    @param $parametros Arreglo asociativo con los parámetros requeridos.
    *********************************************************************/
    {
        $parametros['sesion'] = $this->sesion;

        $datos = RSA::cifrar(json_encode($parametros), $this->ll_srv);
        $datos = "&datos=".urlencode($datos);
        $url = $this->url."?metodo=$metodo".$datos;
        if ($this->depurando)
            print Mostrar($url);
        $respuesta = conexionREST::solicitar_url($url);
        $respuesta = json_decode($respuesta);

        return $respuesta;
    }
}

//================================================================================
//================================================================================
//================================================================================

/*! Clase para el servidor de integración de componentes via REST . */
abstract class servidorREST {
    private $rsa;          //!< El objeto para cifrar con RSA.
    public  $directorio;   //!< Directorio con permisos de lecto-escritura donde se
                           //!< podrán almacenar las llaves de cifrado.
    private $libres;       //!< Los campos que no requieren intercambiarse cifrados.
    private $lista_blanca; //!< Arreglo con los métodos existentes en el servidor.
    private $sesion;       //!< Cadena que identifica la sesión establecida.
    private $ll_cte;       //!< La llave pública del cliente que se ha conectado.
    private $timeout;      //!< El intervalo de tiempo máximo entre la petición del cliente
                           //!< y la respuesta del servidor expresada en segundos.
    private $depurando;    //!< Indica si se permite mostrar letreros de depuración
    private $error;        //!< cadena vacía o la descripción de un error si lo hubo
    /*------------------------------------------------------------------*/
    function __construct($directorio, $sesion = null, $duracion = 300, $depurando = false)
    /*********************************************************************
    @brief Constructora, inicializa los campos del objeto a sus valores
    por defecto.
 
    ENTRADAS:
    @param $directorio Directorio con permisos de lecto-escritura donde se
                       podrán almacenar las llaves de cifrado.
    @param $sesion Cadena alfanumérica de longitud 13 que identifica una
                   sesión previamente establecida.
    @param $duracion La cantidad de segundos que pueden transcurrir sin
                     que se deban refrescar las llaves de cifrado.
    *********************************************************************/
    {
        $this->error = "";
 
        if ($depurando == false || $depurando == true)
            $this->depurando = $depurando;

        $this->sesion = null;
        if (!is_dir($directorio."/prv"))
            $this->error .= "$directorio no existe o no es un directorio.\n";
        else if (!is_writable($directorio."/prv"))
            $this->error .= "$directorio no existe o no es un directorio.\n";

        // FALTA VERIFICAR QUE ll_cte sea un identificador de sesión, una
        // llave pública o nulo.
 
        if (!is_int($duracion))
            $this->error .= "$duracion debe ser un número entero.\n";
        else if ($duracion < 0)
            $this->error .= "$duracion debe ser un número positivo.\n";

        if ($this->error == "") {
            $this->rsa = new RSA($duracion, $directorio."/prv", "REST_key", "sha512", $this->depurando);
            $this->directorio = $directorio;
            $this->libres = array('metodo', 'll_publica_cliente');
            $this->lista_blanca = null;
            $this->sesion = $sesion;
            if (preg_match("#^[[:alnum:]]{13}$#", $sesion)) {
                $ll_cte = $this->cargar_llave_publica_sesion($sesion);
                if ($ll_cte != null) {
                    $this->sesion = $sesion;
                    $this->ll_cte = $ll_cte;
                }
            } else {
                $this->sesion = null;
                $this->ll_cte = null;
            }
            $this->timeout = $duracion;
        } else if ($this->depurando)
            print $this->error;

    }


    /*------------------------------------------------------------------*/
    function establecer_lista_blanca($lista_blanca)
    /*********************************************************************
    @brief Verifica que lista_blanca['metodo'] tenga únicamente nombres de
    métodos válidos.

    ENTRADAS:
    @param $lista_blanca Arreglo asociativo indicando los valores válidos
                         para cada una de las variables que vangan en el
                         $_REQUEST.
                         En particular ['metodo'] que trae la llamada a
                         los diferentes servicios provistos por el servicio.
    SALIDAS:
    En $this->lista_blanca quedarán los métodos válidos.
    *********************************************************************/
    {
        $error = "";

        //----------------------------------------
        // Se agregan los métodos existentes al
        // objeto.
        //----------------------------------------
        $this->lista_blanca = array();
        foreach ($lista_blanca['metodo'] as $llave => $valor) {
            if (!method_exists($this, "M_REST_".$valor))
                $error .= "$valor: Metodo no existente en el servidor\n";
            else
                $this->lista_blanca['metodo'][] = $valor;
        }
        //----------------------------------------
        // Se agregan los métodos base a la lista
        // blanca.
        //----------------------------------------
        $this->lista_blanca['metodo'][] = "saludo";
        $this->lista_blanca['metodo'][] = "autenticar";

        //----------------------------------------
        // Si se intentó agregar algún método no
        // existente se informa del error.
        //----------------------------------------
        if ($error != "")
            print Mostrar($error);
    }


    /*------------------------------------------------------------------*/
    function atender($request)
    /*********************************************************************
    @brief Atiende la petición de un cliente, no sin antes verificar que
    los datos que provienen del cliente sean seguros y estén cifrados.

    ENTRADAS:
    @param $request arreglo asociativo con la información de la requisicion.
        en particular deberá contar con el campo "metodo" que indicará el
        método que el usuario desea que se procese.
    SALIDAS:
    string: arreglo codificado en json con los datos de salida del método
        si el cliente se autenticó y registró su llave pública la salida
        estará cifrada sólo para el cliente.
    *********************************************************************/
    {
        $retorno = "";
    
        //----------------------------------------
        // Se interpreta y valida la información
        // que viene en _REQUEST.
        //----------------------------------------
        $refrescar = array();
        if ($request != array())
            list($request, $refrescar) = $this->asegurar_requerimiento($request);

        //----------------------------------------
        // Se verifica el método solicitado y se
        // invoca con los parámetros validados
        // cargando el resultado en $retorno.
        //----------------------------------------
        $metodo = "saludo";
        if (isset($request['metodo']))
            $metodo = $request['metodo'];

        if (in_array($metodo, $this->lista_blanca['metodo'])) {
            $nom_metodo = "M_REST_".$metodo;
            $retorno = $this->$nom_metodo($request);
        }
    
        //----------------------------------------
        // Si se detecta que hay que refrescar la
        // llave pública, dado que el cliente está
        // utilizando una vencida, se agrega al
        // request la nueva llave firmada con la
        // llave privada que conoce el cliente.
        //----------------------------------------
        if (isset($refrescar['pkey']) && isset($refrescar['firma_old_pkey'])) {
            // ASEGURARSE DE ENVIAR LA LLAVE FIRMADA AL CLIENTE
            $retorno['pkey'] = $refrescar['pkey'];
            $retorno['firma_pkey'] = $refrescar['firma_old_pkey'];
        }

        //----------------------------------------
        // Se entrega la información cifrada solo
        // para que la interprete el cliente.
        //----------------------------------------
        $retorno = json_encode($retorno);
        if ($this->ll_cte != null)
            $retorno = RSA::cifrar($retorno, $this->ll_cte);

        return $retorno;
    }


    /*------------------------------------------------------------------*/
    public function asegurar_requerimiento($arreglo)
    /*********************************************************************
    DESCRIPCION:
    Verifica que los elementos del arreglo sean seguros.
    Coteja contra la lista blanca de valores los campos libres.
    Todo dato que no sea libre lo descifra con la llave privada para
    garantizar que llegó de forma segura.
    SALIDAS
    El arreglo asegurado y, en caso que el cliente esté utilizando la
    antigua llave pública para cifrar sus datos, un arreglo $refrescar
    con la llave que hay que refrescar, firmada con la antigua llave
    privada.
    *********************************************************************/
    {
        $retorno = array();
        $refrescar = array();
    
        foreach ($arreglo as $llave => $valor) {
            $agregar = false;
            if (in_array($llave, $this->libres)) {
                switch ($llave) {
                case 'metodo':
                    if (in_array($valor, $this->lista_blanca[$llave]))
                        $retorno[$llave] = $valor;
                    break;
                case 'll_publica_cliente':
                    $retorno[$llave] = $valor;
                    break;
                }
            } else {
                list($dato, $refrescar) = $this->rsa->descifrar($valor);
                //----------------------------------------
                // Si se indica refrescar hay que mandar
                // la clave pública actualizada al cliente
                // firmada con la antigua llave privada.
                //----------------------------------------
                if ($refrescar) {
                    $retorno['pkey'] = $this->rsa->get_ll_publica();
                    list($retorno['firma_old_pkey'], $pkey, $tipo) =
                        $this->rsa->firmar($retorno['pkey'], false, true);
                }
                if ($llave == "datos") {
                    $los_datos = json_decode($dato);
                    if ($los_datos != null)
                        foreach($los_datos as $ll => $vl)
                            $retorno[$ll] = $vl;
                } else if ($dato != '')
                    $retorno[$llave] = $dato;
            }
        }
    
        return array($retorno, $refrescar);
    }


    /*------------------------------------------------------------------*/
    function get_duracion()
    /*********************************************************************
    @brief Entregar la duración establecida de las llaves en segundos.
    *********************************************************************/
    {
        return $this->rsa->get_duracion();
    }


    /*------------------------------------------------------------------*/
    function M_REST_saludo($request)
    /*********************************************************************
    @brief Entrega la llave pública de cifrado del servidor REST al cliente.
    
    ENTRADAS:
    @param $request arreglo asociativo con la información de la requisicion.
        campo 'll_publica_cliente': la llave pública del cliente con la cual
        se va a cifrar la información que se le vaya a enviar en adelante.
    SALIDAS:
    Arreglo con la llave pública del servicio y el código de sesión.
        Si no se provee llave pública retorna ambos valores en vacío.

    ----------------------------------------------------------------------
    @brief Deliver REST server's public key to client.
    
    INPUTS:
    @param $request associative array with the request information
        field 'll_publica_cliente': Client's public key used for encrypt
        the information that will be forwardly sended to the client.
    OUTPUTS:
    Array with the public key and the session code
        If there is no client's public key both values will return null.
    *********************************************************************/
    {
        $retorno = "";
    
        //----------------------------------------
        // Genera un identificador de sesión y
        //----------------------------------------
        if (isset($request['ll_publica_cliente'])) {
            $sesion = uniqid();
            $this->guardar_llave_publica_sesion($sesion, $request['ll_publica_cliente']);
            $this->sesion = $sesion;
            $this->ll_cte = $request['ll_publica_cliente'];
            $retorno = array( "pkey"   => $this->rsa->get_ll_publica()
                            , "sesion" => $this->sesion
                            );
        } else
            $retorno = array( "pkey"   => null
                            , "sesion" => null
                            );
    
        return $retorno;
    }

    /*------------------------------------------------------------------*/
    function M_REST_autenticar($request)
    /*********************************************************************
    @brief Realiza el proceso de autenticación del cliente que intenta
    establecer conección con el servicio.

    Requiere que se implementen los métodos abstracto llamados
    cargar_hash_de_clave, guardar_llave_publica_sesion y 
    cargar_llave_publica_sesion.

    El proceso revisa que la comunicación no exceda el tiempo de timeout,
    verifica la clave del usuario y, si todo está correcto, genera un
    identificador único de sesión y guarda la llave pública del cliente
    para futuras comunicaciones.

    ENTRADAS:
    @param $request arreglo asociativo con la información de la requisicion.
        Particularmente debe tener los siguientes campos:
        @param login el login de identificación del usuario
        @param hclave el hash SHA512 de la clave de acceso
        @param fecha la fecha en que el cliente envía los datos de conexión
        @param ll_cliente la llave pública de cifrado del cliente
    SALIDAS:
    Arreglo asociativo con los campos:
        autenticado booleano que indica el estado de autenticación.
        mensaje descripción de la finalización del proceso
        [sesion] cadena alfanumérica con el código único de sesión para
        ser utilizado en futuras sesiones.
    *********************************************************************/
    {
        $retorno = array( 'autenticado' => false
                        , 'mensaje' => "Autenticación fallida"
                        );

        $T_fechaserver = new \DateTime();
        $T_fecha_envio = new \DateTime($request['fecha']);
        $demora = periodo2unidades($T_fecha_envio->diff($T_fechaserver), "seconds");

        $hclave = null;
        if (method_exists($this, "cargar_hash_de_clave"))
            $hclave = $this->cargar_hash_de_clave($request['login']);
        else
            $retorno = "";

        $this->ll_cte = null;
        if (  $hclave != null
           && hash('sha512', $hclave.$request['fecha']) == $request['token']
           && $demora < $this->timeout) {
            $retorno = true;
            $this->ll_cte = $request['ll_cte'];
            $sesion_id = uniqid();
            // GUARDAR LA ID DE SESIÓN Y LA LLAVE PÚBLICA DEL CLIENTE
            $retorno = array( 'autenticado' => true
                            , 'mensaje' => "AUTENTICADO EXITOSAMENTE"
                            , 'sesion' => $sesion_id
                            , 'll_cte' => $this->ll_cte
                            );
        } else if ($demora >= $this->timeout)
            $retorno = array( 'autenticado' => false
                            , 'mensaje' => "Tiempo máximo de conexión caducado"
                            );

        return $retorno;
    }

    /*------------------------------------------------------------------*/
    public abstract function cargar_hash_de_clave($login);
    /*********************************************************************
    @brief Esta función debe entregar el SHA512 de la clave del usuario con
    $login para ser utilizada durante la autenticación del cliente.

    Debe ser implementada de acuerdo con la lógica particular de cada
    sistema de información.

    ENTRADAS
    @param $login el identificador del usuario.
    SALIDA
    El hash SHA512 que se tiene almacenado de la clave del usuario, null
    si no se tiene.
    *********************************************************************/

    /*------------------------------------------------------------------*/
    public abstract function guardar_llave_publica_sesion($sesion, $pkey);
    /*********************************************************************
    @brief Almacena en forma persistente (archivo o base de datos) un
    código de sesión establecido asociado con una llave pública.

    ENTRADAS
    @param $sesion un código alfanumérico de longitud 13 con el que se
    identifica la sesión.
    @param $pkey la llave pública asociada con el código de sesión.
    SALIDA
    bool: true -> almacenada exitosamente
    *********************************************************************/

    /*------------------------------------------------------------------*/
    public abstract function cargar_llave_publica_sesion($sesion);
    /*********************************************************************
    @brief Verifica si se tiene almacenada una llave pública del cliente
    asociada con el identificador de sesión.

    Ésta llave pública se utiliza para cifrar la información que se va a
    devolver al cliente.

    ENTRADAS
    @param $sesion un código alfanumérico que identifica la sesión.
    SALIDA
    string : La llave pública del cliente asociada con el código de sesión.
    *********************************************************************/

    /*------------------------------------------------------------------*/
    public function __call($nombre, $parametros)
    /*********************************************************************
    DESCRIPCION:
    ENTRADAS:
    SALIDAS:
    PRE:
    POST:
    *********************************************************************/
    {
        $retorno = array("mensaje" => "Funcionalidad no existente [$nombre]");
    
        return $retorno;
    }

    /*------------------------------------------------------------------*/
    public static function __callStatic($nombre, $parametros)
    /*********************************************************************
    DESCRIPCION:
    ENTRADAS:
    SALIDAS:
    PRE:
    POST:
    *********************************************************************/
    {
        $retorno = array("mensaje" => "Funcionalidad no existente [$nombre]");
    
        return $retorno;
    }

}

?>
