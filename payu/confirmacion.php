<?php
    error_reporting(0);
    $mensajeLog = "";
    require_once('../../../../configuration.php');
    $objConf = new JConfig();
    
    //Escriba su Host, por lo general es 'localhost'
    $host = $objConf->host;
    //Escriba el nombre de usuario de la base de datos
    $login = $objConf->user;
    //Escriba la contraseña del usuario de la base de datos
    $password = $objConf->password;
    //Escriba el nombre de la base de datos a utilizar
    $basedatos = $objConf->db;
    //prefijo de la base de datos
    $pf = $objConf->dbprefix;
    //conexion a mysql
    
    $conexion = mysql_connect($host, $login, $password);

    if(!$conexion){
        $mensajeLog .= "[".date("Y-m-d H:i:s")."] Error al conectar la base de datos - ".mysql_error()."\r\n";
    }
    if(!mysql_select_db($basedatos, $conexion))
    {
        $mensajeLog .= "[".date("Y-m-d H:i:s")."] Error al seleccionar la base de datos - ".mysql_error()."\r\n";
    }
    
    $sql = "select params from " . $pf . "extensions where element='payu';";
    $params_query = mysql_query($sql);
    
    if(mysql_num_rows($params_query) == 1)
    {
        $params = mysql_fetch_array($params_query);        
        $params = json_decode($params['params']);        
    }
    
    //escapar datos
    foreach ($_REQUEST as $key => $value) {
        $_REQUEST[$key] = mysql_real_escape_string(htmlentities($value));
    }
    foreach ($_GET as $key => $value) {
        $_REQUEST[$key] = mysql_real_escape_string(htmlentities($value));
    }
    foreach ($_REQUEST as $key => $value) {
        $_REQUEST[$key] = mysql_real_escape_string(htmlentities($value));
    }

        $confirm = mysql_query("select conf from " . $pf . "virtuemart_payment_plg_payu where refventa = '" . $_REQUEST['ref_venta'] . "' and conf = 1;", $conexion);

        if(mysql_num_rows($confirm) == 0)
        {
            $usuarioId = $_REQUEST['usuario_id'];
            $fecha = date("d.m.Y-H:i:s");
            $refVenta = $_REQUEST['ref_venta'];
            $refPol = $_REQUEST['ref_pol'];
            $estadoPol = $_REQUEST['codigo_respuesta_pol'];
            $formaPago = $_REQUEST['tipo_medio_pago'];
            $banco = $_REQUEST['medio_pago'];
            $codigo = $_REQUEST['codigo_respuesta_pol'];
            $mensaje = $_REQUEST['mensaje'];
            $valor = $_REQUEST['valor'];

            // consulta a la bd
            $sql = "UPDATE ". $pf ."virtuemart_payment_plg_payu set" 
                    ." fecha = '". $fecha
                    . "', refpol = '" . $refPol
                    ."', estado_pol = '".$estadoPol
                    ."', formapago = '" . $formaPago
                    ."', banco = '" . $banco
                    ."', codigo_respuesta_pol = '" . $codigo
                    ."', mensaje = '" . $mensaje
                    ."', valor = '" . $valor
                    ."', conf = 1"
                    ." where refventa = '" . $refVenta ."';";
            // select para actualizar la bd pedidos_confir y jos_vm_orders
            switch($estadoPol)
            {
                case '1':
                case '3':
                    $result_a = mysql_query("UPDATE ".$pf."virtuemart_orders SET order_status ='C' WHERE order_number = '".$refVenta."';");
                    if(!$result_a)
                    {
                            die(mysql_error());
                            $mensajeLog .= "[".date("Y-m-d H:i:s")."] Error al ejecutar el query (".$sql.") la base de datos - ".mysql_error()."\r\n";
                    }
                break;
                case '5':
                    $result_c = mysql_query("UPDATE ".$pf."virtuemart_orders SET order_status ='X' WHERE order_number = '".$refVenta."';");
                    if(!$result_c)
                    {
                            $mensajeLog .= "[".date("Y-m-d H:i:s")."] Error al ejecutar el query (".$sql.") la base de datos - ".mysql_error()."\r\n";
                    }
                break;
                case '6':
                case '4':
                case '10':
                case '9':
                    $result_r = mysql_query("UPDATE ".$pf."virtuemart_orders SET order_status ='R' WHERE order_number = '".$refVenta."';");
                    if(!$result_r)
                    {
                            $mensajeLog .= "[".date("Y-m-d H:i:s")."] Error al ejecutar el query (".$sql.") la base de datos - ".mysql_error()."\r\n";
                    }
                break;
                case '8':
                default:
                    $result_p = mysql_query("UPDATE ".$pf."virtuemart_orders SET order_status ='P' WHERE order_number = '".$refVenta."';");
                    if(!$result_p)
                    {
                            $mensajeLog .= "[".date("Y-m-d H:i:s")."] Error al ejecutar el query (".$sql.") la base de datos - ".mysql_error()."\r\n";
                    }
                break;
            }

            $result = mysql_query($sql);
            if (!$result) 
            {
                $mensajeLog .= "[".date("Y-m-d H:i:s")."] Error al ejecutar el query (".$sql.") la base de datos - ".mysql_error()."\r\n";
            }

        }
        else
        {
            //die("<center><br><h3 style='color: #F00;'><strong>Error: </strong>Esta solicitud ya ha sido registrada.</h3></center>");
        }
    
    if(strlen($mensajeLog)>0)
    {
        $filename = "./logs/confirmaciones.log";
        $fp = fopen($filename, "a");
        if($fp) 
        { 
            fwrite($fp, $mensajeLog, strlen($mensajeLog));
            fclose($fp);
        }
    }
?>