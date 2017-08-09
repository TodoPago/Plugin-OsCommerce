<?php
abstract class ControlFraude{

	protected $order;
	protected $customer;
    protected $logger;

	public function __construct($order, $logger){
		$this->order = $order;
		$this->customer = $order->customer;
        $this->logger = $logger;
		$this->logger->debug("constructor del CF");
	}

	public function getDataCF(){
		$datosCF = $this->completeCF();
		$controlFraudeParams = array_merge($datosCF, $this->completeCFVertical());
        $this->logger->debug("Control de Fraude: ".json_encode($controlFraudeParams));
        return $controlFraudeParams;
	}

	private function completeCF(){
		$payDataOperacion = array();
		$billingAdress = $this->order->billing;

        $this->logger->info("Completando parámetros de cotrol de fraude");
		$this->logger->debug("CSBTCITY - Ciudad de facturación");
		$payDataOperacion ['CSBTCITY'] = $this->getField($billingAdress['state']);
        
		$this->logger->debug(" CSBTCOUNTRY - pa&iacute;s de facturación");
		$payDataOperacion ['CSBTCOUNTRY'] = $billingAdress['country']['iso_code_2'];
        
		$this->logger->debug(" CSBTCUSTOMERID - identificador del usuario (no correo electronico)");
		$payDataOperacion ['CSBTCUSTOMERID'] = $this->order->customer_aditional_info['customer_id'];

//		if($payDataOperacion ['CSBTCUSTOMERID']=="" or $payDataOperacion ['CSBTCUSTOMERID']==null)
//		{
//			$payDataOperacion ['CSBTCUSTOMERID']= "guest".date("ymdhs");
//		}
        
		$this->logger->debug(" CSBTIPADDRESS - ip de la pc del comprador");
		$payDataOperacion ['CSBTIPADDRESS'] = $this->_get_todo_pago_client_ip();
        
		$this->logger->debug(" CSBTEMAIL - email del usuario al que se le emite la factura");
		$payDataOperacion ['CSBTEMAIL'] = $this->getField($this->order->customer['email_address']);
        
		$this->logger->debug(" CSBTFIRSTNAME - nombre de usuario el que se le emite la factura");
		$payDataOperacion ['CSBTFIRSTNAME'] = $this->getField($this->order->customer['firstname']);
		
		$this->logger->debug(" CSBTLASTNAME - Apellido del usuario al que se le emite la factura");
		$payDataOperacion ['CSBTLASTNAME'] = $this->getField($this->order->customer['lastname']);
		
		$this->logger->debug(" CSBTPOSTALCODE - Código Postal de la dirección de facturación");
		$payDataOperacion ['CSBTPOSTALCODE'] = $this->getField($this->order->customer['postcode']);
        
		$this->logger->debug(" CSBTPHONENUMBER - Tel&eacute;fono del usuario al que se le emite la factura. No utilizar guiones, puntos o espacios. Incluir c&oacute;digo de pa&iacute;s");
		$payDataOperacion ['CSBTPHONENUMBER'] = phone::clean($this->getField($this->order->customer['telephone']), $this->logger);
        
		$this->logger->debug(" CSBTSTATE - Provincia de la direcci&oacute;n de facturaci&oacute;n (hay que cambiar esto!!! por datos hacepatdos por el gateway)");
		$payDataOperacion ['CSBTSTATE'] = $billingAdress['tp_state'];
                                                    
		$this->logger->debug(" CSBTSTREET1 - Domicilio de facturaci&oacute;n (calle y nro)");
		$payDataOperacion ['CSBTSTREET1'] = $this->getField($this->order->customer['street_address']);
                                                             
		//$this->logger->debug(" CSBTSTREET2 - Complemento del domicilio. (piso, departamento)_ No Mandatorio");
		//$payDataOperacion ['CSBTSTREET2'] = $this->getField($billingAdress->getStreet2());
                                                             
		$this->logger->debug(" CSPTCURRENCY- moneda");
		$payDataOperacion ['CSPTCURRENCY'] = $this->getField($this->order->info['currency']);
                                                             
		$this->logger->debug(" CSPTGRANDTOTALAMOUNT - 999999[.CC] Con decimales opcional usando el puntos como separador de decimales. No se permiten comas, ni como separador de miles ni como separador de decimales.");
		$payDataOperacion ['CSPTGRANDTOTALAMOUNT'] = number_format($this->order->info['total'],'2','.','');
                                                             
		//$this->logger->debug(" CSMDD6 - Canal de venta");
		//$payDataOperacion ['CSMDD6'] = Mage::getStoreConfig('payment/modulodepago2/cs_canaldeventa');
                                                             
		$this->logger->debug(" CSMDD7 - Fecha Registro Comprador (num Dias) - ver que pasa si es guest");
		$payDataOperacion ['CSMDD7'] = $this->_getDaysQty($this->order->aditional_info['date_creation']);
                                                             
		$this->logger->debug(" CSMDD8 - Usuario Guest? (Y/N). En caso de ser Y, el campo CSMDD9 no deber&aacute; enviarse");
			$payDataOperacion ['CSMDD8'] = "N";
			$this->logger->debug(" CSMDD9 - Customer password Hash: criptograma asociado al password del comprador final");
			$payDataOperacion ['CSMDD9'] = $this->order->customer_aditional_info['password'];
                                                             $payDataOperacion['CSMDD10'] = $this->order->customer_aditional_info['orders_qty'];

		$this->logger->debug(" CSMDD11 Customer Cell Phone");
                                                             $payDataOperacion ['CSMDD11'] = phone::clean($this->order->customer['telephone'], $this->logger);

		return $payDataOperacion;

	}

	private function _sanitize_string($string){
		$string = htmlspecialchars_decode($string);

		$re = "/\\[(.*?)\\]|<(.*?)\\>/i";
		$subst = "";
		$string = preg_replace($re, $subst, $string);

		$replace = array("!","'","\'","\"","  ","$","\\","\n","\r",
			'\n','\r','\t',"\t","\n\r",'\n\r','&nbsp;','&ntilde;',".,",",.","+", "%", "-", ")", "(", "°");
		$string = str_replace($replace, '', $string);

		$cods = array('\u00c1','\u00e1','\u00c9','\u00e9','\u00cd','\u00ed','\u00d3','\u00f3','\u00da','\u00fa','\u00dc','\u00fc','\u00d1','\u00f1');
		$susts = array('Á','á','É','é','Í','í','Ó','ó','Ú','ú','Ü','ü','Ṅ','ñ');
		$string = str_replace($cods, $susts, $string);

		$no_permitidas= array ("á","é","í","ó","ú","Á","É","Í","Ó","Ú","ñ","À","Ã","Ì","Ò","Ù","Ã™","Ã ","Ã¨","Ã¬","Ã²","Ã¹","ç","Ç","Ã¢","ê","Ã®","Ã´","Ã»","Ã‚","ÃŠ","ÃŽ","Ã”","Ã›","ü","Ã¶","Ã–","Ã¯","Ã¤","«","Ò","Ã","Ã„","Ã‹");
		$permitidas= array ("a","e","i","o","u","A","E","I","O","U","n","N","A","E","I","O","U","a","e","i","o","u","c","C","a","e","i","o","u","A","E","I","O","U","u","o","O","i","a","e","U","I","A","E");
		$string = str_replace($no_permitidas, $permitidas ,$string);

		return $string;
	}

	protected function getMultipleProductsInfo(){
		$payDataOperacion = array();
		$this->logger->debug("CSITPRODUCTCODE C&oacute;digo del producto");
        //$id = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        //$order = Mage::getModel('sales/order')->load($id);
        //$productos = $order->getAllItems();
		$productos = $this->order->products;
        ///datos de la orden separados con #
		$productcode_array = array();
		$description_array = array();
		$name_array = array();
		$sku_array = array();
		$totalamount_array = array();
		$quantity_array = array();
		$price_array = array();

		foreach($productos as $item){
			
            $id = strstr($item['id'], '{', true);// Si el producto tiene campos adicionales el id será del tipo I{Cn}Vn donde Cn y Vn son los campos adicionales y sus respectivos valores.
            $item['id'] = $id ? : $item['id']; //En caso de que el producto no tnga campos adicionales la función strstr() devolverá false.
            
			$productcode_array[] = $this->_getProductCode($item['id']);

            $descriptonQuery = tep_db_query("SELECT products_description as description FROM products_description WHERE products_id = ".$item['id'].";");
			$queryResult = tep_db_fetch_array($descriptonQuery);
			$_description = $queryResult['description'];

			if($_description != null || $_description != ""){
				$_description = trim($_description);
				$_description = substr($_description, 0,40);
			}else{
				$_description = $item['name'];
			}			
			$description_array [] = utf8_encode(str_replace("#","",$_description));

			$product_name = $item['name'];
			$name_array [] = $product_name;

			$sku = empty($item['model']) ? $item['id'] : $item['model'];
			$sku_array [] = $this->getField($sku);

			$product_quantity = $item['qty'];
			$product_price = number_format($item['final_price'],'2','.','');
			$product_amount = number_format($product_quantity * $product_price, 2, ".", "");
			$totalamount_array[] = $product_amount;

			$quantity_array [] = intval($product_quantity);

			$price_array [] = $product_price;

		}
		$payDataOperacion ['CSITPRODUCTCODE'] = join('#', $productcode_array);
		$payDataOperacion ['CSITPRODUCTDESCRIPTION'] = join("#", $description_array);
		$payDataOperacion ['CSITPRODUCTNAME'] = join("#", $name_array);
		$payDataOperacion ['CSITPRODUCTSKU'] = join("#", $sku_array);
		$this->logger->debug("CSITTOTALAMOUNT - CSITTOTALAMOUNT = CSITUNITPRICE * CSITQUANTITY");
		$payDataOperacion ['CSITTOTALAMOUNT'] = join("#", $totalamount_array);
		$payDataOperacion ['CSITQUANTITY'] = join("#", $quantity_array);
		$payDataOperacion ['CSITUNITPRICE'] = join("#", $price_array);
		return $payDataOperacion;
	}

	public function getField($datasources){
		$return = "";
        $this->logger->debug("entró: ".$datasources);
		try{
			$return = $this->_sanitize_string($datasources);
			$this->logger->debug("devolvio $return");

		}catch(Exception $e){
			$this->logger->debug("Modulo de pago - TodoPago ==> operation_id:  $this->order->getIncrementId() -
				no se pudo agregar el campo: Exception: $e");
		}

		return $return;
	}
    
    private function _get_todo_pago_client_ip() {

        $ipaddress = '';
        if ($_SERVER['HTTP_CLIENT_IP'])
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if($_SERVER['HTTP_X_FORWARDED_FOR'])
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if($_SERVER['HTTP_X_FORWARDED'])
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if($_SERVER['HTTP_FORWARDED_FOR'])
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if($_SERVER['HTTP_FORWARDED'])
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if($_SERVER['REMOTE_ADDR'])
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';

       // return $ipaddress;
        return ($ipaddress == '::1') ? '192.168.0.1' : $ipaddress;
    }

	private function _getProductCode($productId) {
        /*$customfields = $this->_get_tp_custom_values($productId);
        if (is_array($customfields)){
                $haveProductCode = false;
                foreach($customfields as $customIndex => $customValue){

                    if ($customIndex == 'CSITPRODUCTCODE'){
                        $productCode = trim(urlencode(htmlentities(strip_tags($customValue))));
                        $haveProductCode = true;
                    }
                }
                if (!$haveProductCode){
                    $productCode = 'default';
                }
            }
            else{
                $productCode = 'default';
            }*/

        $productCodeQuery = "SELECT c.categories_name AS category FROM products_to_categories as pc INNER JOIN categories_description AS c ON pc.categories_id = c.categories_id WHERE pc.products_id = ".$productId.";";
        $this->logger->debug("query product code: ".$productCodeQuery);
        $productCode = tep_db_query($productCodeQuery);
        $productCode = tep_db_fetch_array($productCode);
        $this->logger->debug("product code: ".json_encode($productCode));
		if (empty($productCode['category'])) {
			return 'default';
		}
        return $productCode['category'];
	}
    
        private function _get_tp_custom_values($product_id){
            
        $todoPagoConfig = tep_db_query('SELECT * FROM '.TABLE_TP_ATRIBUTOS.' WHERE product_id = '.$product_id);
        $todoPagoConfig = tep_db_fetch_array($todoPagoConfig);

        $this->logger->debug("custom values", $todoPagoConfig);
        return $todoPagoConfig; 
    }
    
    private function _getDaysQty($date){
        $date = new DateTime($date);
        $now = new DateTime();
        
        $diff = $date->diff($now);
        return $diff->days;
    }

    protected abstract function completeCFVertical();

}
