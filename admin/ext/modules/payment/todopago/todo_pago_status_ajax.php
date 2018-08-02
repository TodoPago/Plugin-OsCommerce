<?php
    chdir('../../../../');
    require('includes/application_top.php');
    
    require_once(DIR_FS_CATALOG."includes/modules/payment/todopagoplugin/includes/vendor/autoload.php");
    require_once DIR_FS_CATALOG.'/includes/modules/payment/todopagoplugin/includes/Logger/loggerFactory.php';

    $orderId = $_REQUEST["order_id"];


    $sql = "select * from todo_pago_configuracion";

    $res = tep_db_query($sql);

    if ($row = tep_db_fetch_array($res)){    
    
        $modo = $row["ambiente"]."_";

        $logger = loggerFactory::createLogger(true, substr($modo, 0 , 4), 0, $orderId);

        $logger->info("get Status");

        $wsdl = json_decode($row[$modo."wsdl"],1);
        
        $http_header = json_decode($row["authorization"],1);

        
        $http_header["user_agent"] = 'PHPSoapClient';
        
        define('END_POINT', $row[$modo."endpoint"]);

        $connector = new TodoPago\Sdk($http_header, substr($modo, 0, 4));
    
        $optionsGS = array('MERCHANT'=>$row[$modo."merchant"], 'OPERATIONID'=>$orderId); 

        $logger->info("params getStatus: ".json_encode($optionsGS));

        $status = $connector->getStatus($optionsGS);

        if ($status) {
            if (isset($status['Operations']) && is_array($status['Operations'])) {
                $rta = printGetStatus($status['Operations'], 0);
            } else {
                $rta = 'No hay operaciones para esta orden.';
            }
        } else {
            $rta = 'No se ecuentra la operación. Esto puede deberse a que la operación no se haya finalizado o a una configuración erronea.';
        }
    }

    echo $rta;


    function printGetStatus($array, $indent) {
        $rta = '';

        foreach ($array as $key => $value) {
            if ($key !== 'nil' && $key !== "@attributes") {
                if (is_array($value) ){
                    $rta .= str_repeat("-", $indent) . "$key: <br/>";
                    $rta .= printGetStatus($value, $indent + 2);
                } else {
                    $rta .= str_repeat("-", $indent) . "$key: $value <br/>";
                }
            }
        }
        return $rta;
    }

?>
