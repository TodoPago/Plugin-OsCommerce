<?php
    use TodoPago\Sdk as Sdk;

    chdir('../../../../');
    require('includes/application_top.php');
    require_once(DIR_FS_CATALOG."/includes/modules/payment/todopagoplugin/includes/vendor/todopago/php-sdk/TodoPago/lib/Sdk.php");
    require_once(DIR_FS_CATALOG."/includes/modules/payment/todopagoplugin/includes/Logger/loggerFactory.php");

    global $db;

    $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : null;
    $amount = isset($_POST['amount']) ? $_POST['amount'] : null;
    $refund_type = isset($_POST['refund_type']) ? $_POST['refund_type'] : null;

    $logger = loggerFactory::createLogger(false, "test", 0, $order_id);

    if($order_id != null && $refund_type != null){

        $config_data = tep_db_query('SELECT * FROM todo_pago_configuracion');
        $config = tep_db_fetch_array($config_data);    

        $http_header = json_decode($config["authorization"],1);
        $http_header["user_agent"] = 'PHPSoapClient';   

        $connector = new Sdk($http_header, ($config["ambiente"] == 'test') ? 'test' : 'prod');

        //get requestkey
        $trans_data = tep_db_query("SELECT request_key FROM todopago_transaccion WHERE id_orden = ".$order_id);
        $tp_transaccion = tep_db_fetch_array($trans_data);
        $requestKey = $tp_transaccion['request_key'];

        if($refund_type == "total"){
            //anulacion 
            $options = array(
                "Security" => $config['test_security'],
                "Merchant" => $config['test_merchant'],
                "RequestKey" => $requestKey 
            );
	    $logger->info("Devolucion PARAMS: ".json_encode($options));
            $voidResponse = $connector->voidRequest($options);
	    $logger->info("Devolucion RESPONSE: ".json_encode($voidResponse));

            if($voidResponse['StatusCode'] == 2011){

                $statusCode = tep_db_query("SELECT orders_status_id FROM ".TABLE_ORDERS_STATUS." where orders_status_name = 'Refund'");
                $idStatusOrder = tep_db_fetch_array($statusCode);
                $new_order_status = $idStatusOrder['orders_status_id'];

                if ($new_order_status == 0) $new_order_status = 1;

                //comment
                $comment = 'Devolucion total, monto devuelto: $'.(string)$todoPagoVoid['order_total'];

                tep_db_query("INSERT INTO orders_status_history (orders_id,orders_status_id,date_added,comments,customer_notified) VALUES(".$order_id.",".$new_order_status.",NOW(),'".$comment."',0)");

                tep_db_query("update orders set orders_status = '" . (int)$new_order_status . "' where orders_id = '" . (int)$order_id . "'");

                $response = "La devolucion se realizo satisfactoriamente";
            }else{
                $response = "<h4>Ocurrio un error en la devolucion, " . $voidResponse['StatusMessage'] . " vuelva a intentarlo en unos minutos</h4>"; // 13/05/2016 Se agrego el bold.
            }    
            
        }elseif($refund_type == "parcial"){
            $options = array(
                "Security" => $config['test_security'],
                "Merchant" => $config['test_merchant'], 
                "RequestKey" => $requestKey, 
                "AMOUNT" => $amount 
            );
	    $logger->info("Devolucion PARAMS: ".json_encode($options));
            $refundResponse = $connector->returnRequest($options);
	    $logger->info("Devolucion RESPONSE: ".json_encode($refundResponse));

            if($refundResponse['StatusCode'] == 2011){
            
                $statusCode = tep_db_query("SELECT orders_status_id FROM ".TABLE_ORDERS_STATUS." where orders_status_name = 'Partial Refund'");
                $idStatusOrder = tep_db_fetch_array($statusCode);
                $new_order_status = $idStatusOrder['orders_status_id'];
                if ($new_order_status == 0) $new_order_status = 1;

                //comment
                $comment = 'Devolucion parcial, monto devuelto: $' . (string)$amount;

                tep_db_query("INSERT INTO orders_status_history (orders_id,orders_status_id,date_added,comments,customer_notified) VALUES(".$order_id.",".$new_order_status.",NOW(),'".$comment."',0)");

                tep_db_query("update orders set orders_status = '" . (int)$new_order_status . "' where orders_id = '" . (int)$order_id . "'");

                $response = "La devolucion se realizo satisfactoriamente";
            }else{
                $response = "<h4>Ocurrio un error en la devolucion: " . $refundResponse['StatusMessage'] . '</h4>';
            }
        }    
        
    }else{
        $response = "No se pudo realizar la operacion, se requiere id y monto de la orden";
    }

    echo ($response);
    
?>
