<?php
/*! \file servidor/lib/cifrado_RSA.php
o bien:
    \file cliente/lib/cifrado_RSA.php
Este software fué realizado por el Ing. Ricardo Naranjo
Faccini, M.Sc.  para Skina IT Solutions E.U. fábrica de
software colombiana radicada en Bogotá.

Skina IT Solutions E.U.
https://www.skinait.com
soporte@skinait.com

Copyright 2021

Este archivo es parte de la librería SimpleSecREST.
Contiene la clase que permite cifrar, descifrar, firmar,
sellar, verificar una firma y verificar un sello mediante
el uso del algoritmo RSA.
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
namespace skinait\Codificador;

/*! Clase para cifrar, descifrar, firmar, sellar y validar mensajes con RSA.
Cifrar = codificar con una llave pública, sólo el emisor de la llave podrá
         conocer su contenido.
Descifrar = decodificar con la llave privada del codificador mensajes que
            fueron enviados confidencialmente a éste codificador.
Firmar = generar un hash de un mensaje y cifrarlo con la llave privada, para
         certificar que el emisor del mensaje fue éste codificador.
Sellar = codificar un mensaje con la llave privada para certificar que el
         emisor del mensaje fue éste codificador.
Validar = Verificar que un mensaje sellado o firmado fue emitido por quien
          generó la llave pública asociada.
METODOS
    function __construct( $duracion = 300, $directorio = null
                        , $nombre_base = "rsa_key", $tipo_huella = "sha512")
    function get_ll_publica()
    function get_ll_pub_old()
    function get_refrescar()
    function cargar_llaves_RSA()
    final protected function crear_llaves_RSA()
    function firmar($mensaje, $sellado = false, $usar_old = false)
    public static function validar_firma( $mensaje, $firma, $ll_publica
                                        , $tamanho = 2048, $tipo_huella = "sha512")
    public static function cifrar($mensaje, $ll_publica, $tamanho = 2048)
    function descifrar($mensaje)
    function borrarLlavesViejas()

 * */
class codificador {
    private $valido; /*!< \var Centinela que indica si el codificador es válido. */
                     /*!< Para ser válido debe tener un directorio escribible */
                     /*!< por php y el nombre del archivo para las llaves no */
                     /*!< debe contener caracteres extraños. */
    private $duracion; /*!< \var La duración máxima de las llaves pública y privada */
    private $directorio; /*!< \var El directorio donde se almacenarán las llaves. */
                         /*!< php debe tener permisos de lectoescritura. */
    private $nombre_base; /*!< \var El nombre que deberán tener los archivos .crt y .pem */
    private $ll_publica; /*!< \var La llave pública */
    private $ll_privada; /*!< \var La llave privada */
    private $ll_pub_old; /*!< \var La llave pública por expirar */
    private $ll_pri_old; /*!< \var La llave privada por expirar */
    private $tipo_huella; /*!< \var el mecanismo HASH que se utilizará. */
    private $tamanho = 2048; /*!< \var El tamaño en bytes de las llaves */
    private $bloques_cifrar = 245; /*!< \var $tamanho / 8 - 11 */
                                   /*!< Lo máximo que se puede cifrar con el tamaño de las llaves. */
    private $bloques_descifrar = 256; /*!< \var 1024 -> 128 */
                                      /*!< 2048 -> 256 */
                                      /*!< ...  -> ... */
                                      /*!< Lo máximo que se puede descifrar con el tamaño de las llaves. */
    private $depurando = false; /*!< Indica si se permite mostrar letreros de depuración */

    /*------------------------------------------------------------------*/
    function __construct( $duracion = 300, $directorio = null
                        , $nombre_base = "rsa_key", $tipo_huella = "sha512", $depurando = false)
    /*********************************************************************
    @brief Constructora, inicializa los campos del objeto a sus
    valores por defecto.
 
    ENTRADAS:
    @param $duracion Tiempo máximo de duración de las llaves
                     por defecto 5 minutos (300 segs = 5 min)
    @param $directorio Ruta a directorio donde se almacenarrán las llaves.
                       El directorio debería tener permisos de
                       lecto-escritura para php.
    @param $nombre_base Nombre de los archivos con las llaves, uno con
                        extensión .pem y el otro, para la llave privada,
                        con extensión .crt
    @param $tipo_huella Algoritmo hash que se utilizará para las huellas
                        digitales debería estar entre los valores
                        descritos en hash_algos().
    *********************************************************************/
    {
        if ($depurando == false || $depurando == true)
            $this->depurando = $depurando;

        if (is_numeric($duracion))
            $this->duracion = $duracion;
        else
            $this->duracion = 300;

        $this->nombre_base = null;
        if (preg_match("#^[^/?*;:{}\\\\]+$#", $nombre_base))
            $this->nombre_base = $nombre_base;
 
        $this->directorio = null;
        if (is_dir($directorio))
            if (is_writable($directorio))
                $this->directorio = realpath($directorio);

        $this->valido = false;
        if ($this->nombre_base != null && $this->directorio != null)
            $this->valido = true;
        else
            die("Fallo al crear el codificador ($duracion, $directorio, $nombre_base).");

        $this->tipo_huella = "sha512";
        if (in_array($tipo_huella, hash_algos()))
            $this->tipo_huella = $tipo_huella;

        $this->cargar_llaves_RSA();
        $this->refrescar = false;
    }

    /*------------------------------------------------------------------*/
    function get_duracion()
    /*********************************************************************
    @brief Entregar la duración establecida de las llaves en segundos.
    *********************************************************************/
    {
        return $this->duracion;
    }


    /*------------------------------------------------------------------*/
    function get_ll_publica()
    /*********************************************************************
    @brief Entregar la llave pública vigente.
    *********************************************************************/
    {
        return $this->ll_publica;
    }


    /*------------------------------------------------------------------*/
    function get_ll_pub_old()
    /*********************************************************************
    @brief Entregar la llave pública que pronto expirará.
    *********************************************************************/
    {
        return $this->ll_pub_old;
    }


    /*------------------------------------------------------------------*/
    function get_refrescar()
    /*********************************************************************
    @brief Informa el valor del centinela que sugiere refrescar las llaves
    dado que se detectó que se está utilizando una próxima a expirar.
    *********************************************************************/
    {
        return $this->refrescar;
    }


    /*------------------------------------------------------------------*/
    function cargar_llaves_RSA()
    /*********************************************************************
    @brief Si las llaves están almacenadas en los archivos correspondientes
    las carga desde los archivos.
    Si no existen los archivos de las llaves, genera un nuevo par de llaves.
    Si existe el par de llaves antiguas, las carga también, de lo contrario
    las deja en null.
    *********************************************************************/
    {
        if ($this->valido) {
            $directorio = $this->directorio;
            $nombre_base = $this->nombre_base;
            $timeout = $this->duracion;
            $this->borrarLlavesViejas();
            if (  !file_exists($directorio."/".$nombre_base.".crt")
               || !file_exists($directorio."/".$nombre_base.".pub")) {
                //------------------------------------------------------------
                // SI NO EXISTE NINGUNA LLAVE: SE GENERAN.
                //------------------------------------------------------------
                list($ll_privada, $ll_publica) = $this->crear_llaves_RSA();
            } else {
                //------------------------------------------------------------
                // CARGA DE LLAVES RSA ARCHIVADAS EN ANTERIOR SESIÓN
                //------------------------------------------------------------
                $ruta_base = $directorio."/".$nombre_base;
                $ll_privada = file_get_contents($ruta_base.".crt");
                $ll_publica = file_get_contents($ruta_base.".pub");
                if (!$dat_ll_prv = openssl_pkey_get_private($ll_privada))
                    $this->valido = false;
                    // die('Loading Private Key failed [$ruta_base]');
            }

            //----------------------------------------
            // Si existen las antiguas llaves las
            // carga desde los archivos, si no las
            // deja en null
            //----------------------------------------
            if (  file_exists($directorio."/".$nombre_base."_old.crt")
               && file_exists($directorio."/".$nombre_base."_old.pub")) {
                $ll_pri_old = file_get_contents($ruta_base."_old.crt");
                $ll_pub_old = file_get_contents($ruta_base."_old.pub");
            } else {
                $ll_pri_old = null;
                $ll_pub_old = null;
            }
        }
        $this->ll_privada = $ll_privada;
        $this->ll_publica = $ll_publica;
        $this->ll_pri_old = $ll_pri_old;
        $this->ll_pub_old = $ll_pub_old;
    }


    /*------------------------------------------------------------------*/
    final protected function crear_llaves_RSA()
    /*********************************************************************
    @brief Generacion de llaves RSA en php.
    Si existen llaves previas almacenadas, las pasa a los archivos con
    sufijo _old para guardar registro de su existencia previa.
    
    SALIDA:
    Se generarán dos archivos, uno con la llave privada con
    extensión .crt, el otro con llave pública con extensión
    .pub; la función retorna tanto la llave pública como la
    privada en un arreglo.

    PRE:
    $this->directorio es un directorio con permisos de escritura para Php
    Los archivos .pub y .crt almacenados allí son parejas de llaves
    pública y privada válidos.
    *********************************************************************/
    {
        $ruta_base = $this->directorio."/".$this->nombre_base;
        $ll_pub_old = null;
        $ll_pri_old = null;

        //----------------------------------------
        // Si existen las parejas de llave antiguas
        // borra los archivos con sufijo _old y 
        // renombra los archivos actuales con _old
        //----------------------------------------
        if (file_exists($ruta_base."_old.pub")) {
            unlink($ruta_base."_old.pub");
            unlink($ruta_base."_old.crt");
        }
        if (file_exists($ruta_base.".pub")) {
            rename($ruta_base.".pub", $ruta_base."_old.pub");
            rename($ruta_base.".crt", $ruta_base."_old.crt");
            $ll_pub_old = file_get_contents($ruta_base."_old.pub");
            $ll_pri_old = file_get_contents($ruta_base."_old.crt");
        }

        //----------------------------------------
        // Creación de la nueva pareja de llaves
        //----------------------------------------
        $config = array( "private_key_bits" => $this->tamanho
                       , "private_key_type" => OPENSSL_KEYTYPE_RSA
                       );
        $llavePrivadaCruda = openssl_pkey_new($config);
        openssl_pkey_export_to_file($llavePrivadaCruda, $ruta_base.".crt");
        $ll_privada = file_get_contents($ruta_base.".crt");
        openssl_pkey_export($llavePrivadaCruda, $ll_privada);
    
        $ll_publicaData = openssl_pkey_get_details($llavePrivadaCruda);
        $ll_publica = $ll_publicaData["key"];
        file_put_contents($ruta_base.".pub", $ll_publica);

        // openssl_free_key($llavePrivadaCruda);

        //----------------------------------------
        // Actualización de los campos del objeto.
        //----------------------------------------
        $this->ll_privada = $ll_privada;
        $this->ll_publica = $ll_publica;
        $this->ll_pri_old = $ll_pri_old;
        $this->ll_pub_old = $ll_pub_old;

        return array($ll_privada, $ll_publica);
    }


    /*------------------------------------------------------------------*/
    function firmar($mensaje, $sellado = false, $usar_old = false)
    /*********************************************************************
    @brief Se utiliza para cifrar con la llave privada un mensaje
    que cualquier persona podrá descifrar con la llave pública que se
    compartirá.
    
    Equivale a un mensaje firmado, puesto que cualquiera puede conocer su
    contenido, pero se garantiza que el único que pudo firmarlo fue éste
    objeto dado que nadie más conoce la llave privada.
    
    El mensaje no es confidencial dado que cualquiera puede conocer la
    llave pública para descifrarlo.
    
    ENTRADAS:
    @param $mensaje El mensaje que se va a firmar.
    @param $sellado true indica que se cifrará todo el mensaje con
                    la llave privada para su firma.
                    false indica que se sacará la huella digital y se
                    firmará únicamente la huella.
    @param $usar_old true indica que debe utilizarse la pareja de llaves
                        antiguas y no las más recientes.
    SALIDA:
    Arreglo con
     - la firma resultante o el mensaje sellado resultante.
     - la llave pública para verificar la firma.
     - El tipo de hash utilizado para la huella digital, si se utilizó.
    ************************************************************************/
    {
        $cifrado = "";
        if ($this->valido) {
            $this->cargar_llaves_RSA();
            if ($sellado)
                $mensaje = base64_encode($mensaje);
            else {
                $huella_mensaje = hash($this->tipo_huella, $mensaje);
                $mensaje = base64_encode($huella_mensaje);
            }
            $firma = '';
            $bloques = str_split($mensaje, $this->bloques_cifrar);
            $firmo = true;
            foreach ($bloques as $bloque) {
                $parcial = '';
                $logro = openssl_private_encrypt( $bloque
                                                , $parcial
                                                , (!$usar_old ? $this->ll_privada : $this->ll_pri_old)
                                                , OPENSSL_PKCS1_PADDING);
                if ($logro === false)
                    $firmo = false;
                $firma .= $parcial;
            }
            if ($firmo)
                $firma = base64_encode($firma);
            else
                $firma = null;
        }
    
        return array( $firma
                    , (!$usar_old ? $this->ll_publica : $this->ll_pub_old)
                    , (!$sellado ? $this->tipo_huella : null)
                    );
    }

    /*------------------------------------------------------------------*/
    public static function validar_firma( $mensaje, $firma, $ll_publica
                                        , $tamanho = 2048, $tipo_huella = "sha512")
    /*********************************************************************
    @brief Se utiliza cuando llega un mensaje de un emisor externo del
    cual se conoce la llave pública para descifrarlo.
    
    Dado que el emisor es el único que pudo cifrar el mensaje con la llave
    privada correspondiente con la pública, se considera firmado o sellado.
    
    Dado que cualquiera puede conocer la llave pública, se considera que
    el mensaje no está oculto y no es confidencial.
    
    ENTRADAS:
    @param $mensaje El mensaje al que se le quiere validar la firma.
    @param $firma La firma correspondiente al mensaje cuando se haya
                  utilizado un algoritmo de hash, de lo contrario tendrá
                  el mensaje completo que fue cifrado con la llave pública.
    @param $ll_publica La llave pública con la que se puede descifrar el
                       mensaje.
    @param $tamanho El tamaño de las llaves
    @param $tipo_huella El algoritmo hash utilizado para generar la huella
                        digital del mensaje.
    SALIDA:
    arreglo con:
     - El mensaje descifrado.
     - booleano que indica si la firma se validó exitosamente o no.
    ************************************************************************/
    {
        $bloques_descifrar = array('1024' => 128
                                  ,'2048' => 256
                                  ,'4092' => 512
                                  );
        $valido = false;
        if (in_array($tipo_huella, hash_algos())) {
            openssl_public_decrypt(base64_decode($firma), $descifrado, $ll_publica);

            $descifrado = base64_decode($descifrado);
            $huella_mensaje = hash($tipo_huella, $mensaje);
            if ($huella_mensaje == $descifrado)
                $valido = true;
        } else if (in_array($tamanho, array_keys($bloques_descifrar))) {
            $descifrado = '';
            $valido = true;
            $bloques = str_split(base64_decode($firma), $bloques_descifrar[$tamanho]);
            foreach($bloques as $bloque) {
                $parcial = '';
                $logro = openssl_public_decrypt( $bloque, $parcial
                                                      , $ll_publica, OPENSSL_PKCS1_PADDING);
                if ($logro === false)
                    $valido = false;
                else
                    $descifrado .= $parcial;
            }
            $mensaje = base64_decode($descifrado);
        }

        return array($mensaje, $valido);
    }


    /*------------------------------------------------------------------*/
    public static function cifrar($mensaje, $ll_publica, $tamanho = 2048)
    /*********************************************************************
    @brief Se utiliza cuando se quiere enviar un mensaje confidencial a
    un receptor.
    
    Dado que el receptor es el único que conoce la llave privada
    correspondiente para descifrarlo, nadie más podrá conocer su contenido.
    
    ENTRADAS:
    @param $mensaje El mensaje a cifrar.
    @param $ll_publica La llave pública del receptor a quien se va a
                       enviar el mensaje.
    @param $tamanho El tamaño de las llaves.
    ************************************************************************/
    {
        $bloques_cifrar = $tamanho / 8 - 11;
        $bloques = str_split($mensaje, $bloques_cifrar);
        $Qbloques = count($bloques);
        $codifico = true;
        $cifrado = "";
        foreach ($bloques as $llave => $bloque) {
            $parcial = '';
            $logro = openssl_public_encrypt( $bloque
                                           , $parcial
                                           , $ll_publica
                                           , OPENSSL_PKCS1_PADDING);
            if ($logro === false)
                $codifico = false;
            $cifrado .= $parcial.($llave < $Qbloques - 1 ? "_-_SKINA_-_" : "");
        }
        if ($codifico)
            $cifrado = base64_encode($cifrado);
        else
            $cifrado = null;

        return $cifrado;
    }


    /*------------------------------------------------------------------*/
    function descifrar($mensaje)
    /*********************************************************************
    @brief Se utiliza cuando llega un mensaje de un emisor
    externo que tomó la llave pública de éste objeto para cifrar un
    mensaje.
    
    Se considera que éste mensaje es confidencial dado que éste objeto
    es el único que conoce la llave privada.

    Si no es posible descifrar el mensaje con la llave pública se intenta
    con la llave pública antigua. El retorno indicará si debe enviarse
    la actual llave pública al receptor.
    
    Dado que el algoritmo refresca las llaves RSA, sólamente se podrán
    descifrar mensajes recientes (de acuerdo con $this->duración).
    
    ENTRADAS:
    @param $mensaje: Mensaje a descifrar, lo cifró el emisor utilizando
                     la llave pública que previamente le compartimos.
    SALIDA:
    Arreglo con
    El mensaje descifrado (o null si no se pudo descifrar).
    Indicador de si hay que indicar al emisor que actualice la llave
    pública.
    *********************************************************************/
    {
        $descifrado = null;
        if ($this->valido) {
            $valido = true;
            $refrescar = false;
            $bloques = preg_split("#_-_SKINA_-_#", base64_decode($mensaje));

            //----------------------------------------
            // Se indaga cual llave privada debe usar.
            //----------------------------------------
            $ll_privada = $this->ll_privada;
            $parcial = '';
            $bloque = array_shift($bloques);
            $logro = openssl_private_decrypt( $bloque
                                            , $parcial
                                            , $ll_privada
                                            , OPENSSL_PKCS1_PADDING);
            if ($logro === false) {
                $ll_privada = $this->ll_pri_old;
                if ($this->depurando)
                    $logro = openssl_private_decrypt( $bloque
                                                    , $parcial
                                                    , $ll_privada
                                                    , OPENSSL_PKCS1_PADDING);
                else
                    $logro = @openssl_private_decrypt( $bloque
                                                    , $parcial
                                                    , $ll_privada
                                                    , OPENSSL_PKCS1_PADDING);
                if ($logro === true)
                    $refrescar = true;
                else
                    print "<h1>Llave de cifrado inv&aacute;lida</h1>";
            }
            if ($logro) {
                $descifrado = $parcial;
                foreach($bloques as $bloque) {
                    $parcial = '';
                    $logro = openssl_private_decrypt( $bloque
                                                    , $parcial
                                                    , $ll_privada
                                                    , OPENSSL_PKCS1_PADDING);
                    if ($logro === false)
                        $valido = false;
                    else
                        $descifrado .= $parcial;
                }
            }
        }

        return array($descifrado, $refrescar);
    }


    /*------------------------------------------------------------------*/
    function borrarLlavesViejas()
    /*********************************************************************
    @brief Revisa la antiguedad de las llaves almacenadas en el
    directorio con las llaves pública y privada, si son más viejas que
    lo indicado en "duración" se eliminan los archivos.

    Deja una copia de la anterior pareja de llaves para brindar continuidad
    durante un intercambio de datos recurrente y prolongado (una sesión que
    esté intercambiando mensajes con frecuencia menor a la duracion)

    Ésta clase se asegura de utilizar llaves recientes (de acuerdo con
    $this->duracion)
    Si la generación del mensaje de retorno se demora más que 2 veces la
    duración establecida en el objeto la conexión fallará. Es decir que
    hay que asegurarse que si la generación de un mensaje es demorado (i.e.
    la búsqueda en una base de datos gigante), debe tomarse ésto en cuenta
    para el establecimiento de la propiedad duración del objeto.
    *********************************************************************/
    {
        $directorio = $this->directorio;
        $segundos = $this->duracion;

        if ($this->directorio != null && $this->nombre_base != null) {
            $archivo = $this->directorio."/".$this->nombre_base;
            if (file_exists($archivo.".crt") && file_exists($archivo.".pub"))
                if (is_writable($archivo.".crt") && is_writable($archivo.".pub")) {
                    $ultima_modificacion_crt = filemtime($archivo.".crt");
                    $ultima_modificacion_pub = filemtime($archivo.".pub");
                    if (  (time() - $ultima_modificacion_crt) > $segundos
                       || (time() - $ultima_modificacion_pub) > $segundos) {
                        if (file_exists($archivo."_old.crt")) {
                            unlink($archivo."_old.crt");
                            unlink($archivo."_old.pub");
                        }
                        if (file_exists($archivo.".crt")) {
                            rename($archivo.".crt", $archivo."_old.crt");
                            rename($archivo.".pub", $archivo."_old.pub");
                        }
                    }
                }
        }

    }
}

?>
