<?php
function second_step_todopago() {
	global $todopagoTransaccion;
	$order_id = (isset($_GET['Order']) && is_numeric($_GET['Order'])) ? $_GET['Order'] : null;
	$todopagoTransaccion = new TodopagoTransaccion();

	$response = callGAA($order_id);
	if($response) {
		take_action($response, $order_id);
	}
}
function _unregisterSessionVars(){
	// unregister session variables used during checkout
	tep_session_unregister('sendto');
	tep_session_unregister('billto');
	tep_session_unregister('shipping');
	tep_session_unregister('payment');
	tep_session_unregister('comments');
}

function _recollect_data($order_id) {
	global $customer_id, $todopagoTransaccion, $todoPagoConfig;
	$todoPagoConfig = tep_db_query('SELECT * FROM todo_pago_configuracion');
	$todoPagoConfig = tep_db_fetch_array($todoPagoConfig);

	if ($todoPagoConfig['ambiente'] == "test") {
		$mode = "test";
		$security =  $todoPagoConfig['test_security'];
		$merchant = $todoPagoConfig['test_merchant'];
	} else {
		$mode = "prod";
		$security =  $todoPagoConfig['production_security'];
		$merchant = $todoPagoConfig['production_merchant'];
	}
	$logger = loggerFactory::createLogger(true, $mode, $customer_id, $order_id);
	$logger->debug('todoPagoConfig: '.json_encode($todoPagoConfig));

	if($todopagoTransaccion==null){
		$todopagoTransaccion=new TodopagoTransaccion();
		$transaccion=$todopagoTransaccion->getTransaction($order_id);
		var_dump($transaccion);
	}

	$logger->debug(json_encode($transaccion));

	//if ($order_id !== null && $todopagoTransaccion->_getStep($order_id) == TodopagoTransaccion::SECOND_STEP) {
	if ($order_id !== null) {
		$logger->info("second step");
		$auth = json_decode($todoPagoConfig['authorization'], 1);
		$http_header = array('Authorization'=>  $auth['Authorization'],
				'user_agent' => 'PHPSoapClient');
		$transaction = $todopagoTransaccion->getTransaction($order_id);
		$requestKey = $transaction['request_key'];
		$answerKey = $_GET['Answer'];

		$optionsGAA = array (
				'Security'   => $security,
				'Merchant'   => $merchant,
				'RequestKey' => $requestKey,
				'AnswerKey'  => $answerKey
		);

		$logger->debug(json_encode($optionsGAA));

		return array('authorization'=> $auth, 'mode'=> $mode, 'params'=> $optionsGAA, 'logger'=> $logger);

	}

}

function callGAA($order_id) {
	$dataGAA = _recollect_data($order_id);
	if($dataGAA) {
		$logger = $dataGAA['logger'];
		$connector = new TodoPago\Sdk($dataGAA['authorization'], $dataGAA['mode']);
		$logger->info("params GAA: ".json_encode($dataGAA['params']));
		$rta2 = $connector->getAuthorizeAnswer($dataGAA['params']);
		if($rta2->StatusCode!="404"){
			$logger->info("response GAA: ".json_encode($rta2));
			return array('rta' => $rta2, 'logger' => $logger, 'optionsGAA' => $dataGAA['params']);
		}
		else{
			$logger->warn("No se puede entrar al second step porque ya se ha registrado una entrada exitosa en la tabla todopago_transaccion o el Order id no ha llegado correctamente");
		}

		$logger->info("response GAA: ".json_encode($rta2));

		return false;
	}

}

function take_action($data, $order_id) {
	global $todopagoTransaccion, $todoPagoConfig, $cart, $oscTemplate, $breadcrumb;
	$todopagoTransaccion->recordSecondStep($order_id, $data['optionsGAA'], $data['rta']);
	if ($data['rta']['StatusCode'] == TP_STATUS_OK) {

		$data['logger']->debug("todoPagoConfig en take_action: ".json_encode($todoPagoConfig));
		if (!empty($data['rta']['Payload']['Answer']['ASSOCIATEDDOCUMENTATION'])){
			tep_db_query('UPDATE '.TABLE_ORDERS.' SET orders_status = '.$todoPagoConfig['estado_offline'].' WHERE orders_id = '.$order_id);
			$offline = true;
		}
		else {
			tep_db_query('UPDATE '.TABLE_ORDERS.' SET orders_status = '.$todoPagoConfig['estado_aprobada'].' WHERE orders_id = '.$order_id);
			$offline = false;
		}

		$cart->reset(true);

		$page_content = $oscTemplate->getContent('checkout_success');
		if ( isset($HTTP_GET_VARS['action']) && ($HTTP_GET_VARS['action'] == 'update') ) {
			tep_redirect(tep_href_link(FILENAME_DEFAULT));
		}
		$breadcrumb->add(NAVBAR_TITLE_1);
		$breadcrumb->add(NAVBAR_TITLE_2);
		?>

<h1><?php echo $offline? "¡Cup&oacute;n de pago generado!" : HEADING_TITLE; ?></h1>

<?php echo tep_draw_form('order', tep_href_link(FILENAME_CHECKOUT_SUCCESS, 'action=update', 'SSL')); ?>

<div class="contentContainer">
  <?php echo $page_content; ?>
</div>

<div class="contentContainer">
  <div class="buttonSet">

    <div>
    <?php
    $logo = "http://www.todopago.com.ar/sites/todopago.com.ar/files/pluginstarjeta.jpg";

    echo " <div><img src='".$logo."' title='todo pago'  /></div>";
    if ($offline){
        $url_cupon = $todopagoTransaccion->getCouponUrl($order_id);
        echo tep_draw_button('Descargar PDF', 'triangle-1-e', $url_cupon,'',array('newwindow' => true));
    }
    ?>
    <span class="buttonAction"><?php echo tep_draw_button(IMAGE_BUTTON_CONTINUE, 'triangle-1-e', null, 'primary'); ?></span>
    </div>
  </div>
</div>
</form>
<?php
  require(DIR_WS_INCLUDES . 'template_bottom.php');
  require(DIR_WS_INCLUDES . 'application_bottom.php');

    }
        else{
            tep_redirect(tep_href_link('checkout_shipping_retry.php'));
        }
}
