<?php
    error_reporting(0);
    require_once('../../../../configuration.php');
    $objConf = new JConfig();
    
    //Escriba su Host, por lo general es 'localhost'
    $host = $objConf->host;
    //Escriba el nombre de usuario de la base de datos
    $login = $objConf->user;
    //Escriba la contrase単a del usuario de la base de datos
    $password = $objConf->password;
    //Escriba el nombre de la base de datos a utilizar
    $basedatos = $objConf->db;
    //prefijo de la base de datos
    $pf = $objConf->dbprefix;
    //conexion a mysql
    
    $conexion = mysql_connect($host, $login, $password);
    mysql_select_db($basedatos, $conexion);

    $sql = "select params from " . $pf . "extensions where element='payu';";
    $params_query = mysql_query($sql);
    
    if(mysql_num_rows($params_query) == 1)
    {
        $params = mysql_fetch_array($params_query);        
        $params = json_decode($params['params']);        
    }
    
    switch($params->estilo)
    {
        case 0:
            $estilo="default.css";
            break;
        case 1:
            $estilo="red.css";
            break;
        case 2:
            $estilo="blue.css";
            break;
        default:
            $estilo = "default.css";
            break;
    }
    
    //escapar datos
    foreach ($_REQUEST as $key => $value) {
        $_REQUEST[$key] = mysql_real_escape_string(htmlentities($value));
    }
    foreach ($_GET as $key => $value) {
        $_REQUEST[$key] = mysql_real_escape_string(htmlentities($value));
    }
    foreach ($_POST as $key => $value) {
        $_REQUEST[$key] = mysql_real_escape_string(htmlentities($value));
    }
    
    $dat_payment = mysql_query('SELECT * FROM  `' . $pf . 'virtuemart_paymentmethods` WHERE `payment_element` = "payu"', $conexion);
    $dat_pe = mysql_fetch_object($dat_payment);
    $tpe = str_replace('"', '', explode('|', $dat_pe->payment_params));

    foreach ($tpe as $kP => $vP) {
        if ($vP != "") {
            $tmp = explode('=', $vP);
            $pe[$tmp[0]] = $tmp[1];
        }
    }

    $firma = md5($pe['payu_encrypt_key']
            . "~" . $_GET['merchantId']
            . "~" . $_GET['referenceCode']
            . "~" . number_format(floatval($_GET['TX_VALUE']), '1', '.', '')
            . "~" . $_GET['currency']
            . "~" . $_GET['transactionState']);
    
    if($firma != $_GET['signature']) {
        die('Datos alterados.');
    }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Confirmaci&oacute;n del pago</title>
        <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
        <link rel="stylesheet" type="text/css" href="./<?php echo $estilo; ?>" />
    </head>
    <body>
        <div align="center">
            <table>
                <tr>
                    <th colspan="2">
                        <?php
                            $path_img = dirname(__FILE__).'/../../../../images/'.$params->logo;
                            if(file_exists($path_img))
                            {
                                echo '<img alt="'.$_SERVER['SERVER_NAME'].'" src="../../../../images/'.$params->logo.'" />';
                            }
                            else
                            {
                                echo "<h2>".$_GET['merchant_name']."</h2>";
                            }
                        ?>
                        <h4>Su pago est&aacute; siendo confirmado para procesar su orden...</h4>
                    </th>
                </tr>
            <tr>
            <tr>
                <td><strong>Orden de compra:</strong> <?php echo $_GET['referenceCode'] ?></td><td> <?php echo(date("F d \d\e\l Y",strtotime("now"))); ?></td>
            </tr>
            <tr>
                <td><strong>ID de la transacci&oacute;n (PAYU):</strong></td><td> <?php echo $_GET['transactionId']; ?></td>
            </tr>
            <tr>
                <td><strong>Estado de la Transaccion:</strong></td><td> <?php echo $_GET['message']; ?></td>
            </tr>
            <tr>
                <td><strong>Banco:</strong></td><td> <?php echo $_GET['lapPaymentMethod']; ?> </td>
            </tr>
            <tr>
                <td><strong>Mensaje de PAYU:</strong></td><td> <?php echo ucfirst(strtolower(str_ireplace('_', ' ', $_GET['lapResponseCode']))); ?></td>
            </tr>
            <tr>
                <td><strong>Valor:</strong></td><td> <?php echo '$ ' . $_GET['TX_VALUE'] . ' COP'; ?></td>
            </tr>
            </tr>
            <tr>
                <td colspan="2" style="text-align: center;">
                    <h4>Gracias por comprar con nosotros!<br>
                    <?php echo $_GET['merchant_url']; ?></h4>
                </td>
            </tr>
            </table>
            <br/>
            <input type="button" value="Imprimir" onclick="window.print();"/>
        </div>
    </body>
</html>