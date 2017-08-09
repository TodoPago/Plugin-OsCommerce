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
    
        $rta = '';
        $refunds = $status['Operations']['REFUNDS'];

        $auxArray = array(
             "REFUND" => $refunds
             );

        if($refunds != null){  
            $aux = 'REFUND'; 
            $auxColection = 'REFUNDS'; 
        }else{ 
            $aux = 'refound';
            $auxColection = 'refounds'; 
        }

        if (isset($status['Operations']) && is_array($status['Operations']) ) {
            foreach ($status['Operations'] as $key => $value) {

                 if(is_array($value) && $key == $auxColection){

                    $rta .= " $key: \n";
                    foreach ($auxArray[$aux] as $key2 => $value2) {
                        $rta .= "  $aux: \n";
                        if(is_array($value2)){
                            foreach ($value2 as $key3 => $value3) {
                                if(is_array($value3)){
                                    foreach ($value3 as $key4 => $value4) {
                                        $rta .= "   - $key4: $value4 </br>";
                                    }
                                 } else {
				        $rta .= "   - $key3: $value3 </br>";
				 }
                            }
                        } else {
				$rta .= "$key2: $value2 </br>";
			}
                    }
                 }else{
                     if(is_array($value)){
                         $rta .= "$key: </br>";
                     }else{
                         $rta .= "$key: $value </br>";
                     }
                 } 
            }
        }else{ $rta = 'No hay operaciones para esta orden.'; }  

        echo($rta);
    
    }
?>
