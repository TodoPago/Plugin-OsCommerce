<?php
/*
  $Id$
  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com
  Copyright (c) 2014 osCommerce
  Released under the GNU General Public License
*/


require_once("includes/modules/payment/todopagoplugin/includes/Logger/loggerFactory.php");
require('includes/application_top.php');
//require_once('includes/modules/payment/todopagoplugin/includes/TodoPago/lib/Sdk.php');
require_once dirname(__FILE__).'/includes/modules/payment/todopagoplugin/includes/vendor/autoload.php';
require_once('includes/modules'.DIRECTORY_SEPARATOR.'payment'.DIRECTORY_SEPARATOR.'todopagoplugin'.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'TodopagoTransaccion.php');
require_once('includes/languages/'.$language.'/'.FILENAME_CHECKOUT_SUCCESS);
require_once('includes/template_top.php');

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
    if ($order_id !== null && $todopagoTransaccion->_getStep($order_id) == TodopagoTransaccion::SECOND_STEP) {

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
        return array('authorization'=> $auth, 'mode'=> $mode, 'params'=> $optionsGAA, 'logger'=> $logger);
    }

    $logger->warn("No se puede entrar al second step porque ya se ha registrado una entrada exitosa en la tabla todopago_transaccion o el Order id no ha llegado correctamente");
    tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
    return false;
}

function callGAA($order_id) {
    $dataGAA = _recollect_data($order_id);

    if($dataGAA) {
        $logger = $dataGAA['logger'];

        $connector = new TodoPago\Sdk($dataGAA['authorization'], $dataGAA['mode']);

        $logger->info("params GAA: ".json_encode($dataGAA['params']));
        $rta2 = $connector->getAuthorizeAnswer($dataGAA['params']);
        $logger->info("response GAA: ".json_encode($rta2));
        return array('rta' => $rta2, 'logger' => $logger, 'optionsGAA' => $dataGAA['params']);
    }

    return false;
}

function take_action($data, $order_id) {

    global $todopagoTransaccion, $todoPagoConfig, $cart, $oscTemplate, $breadcrumb;
    $todopagoTransaccion->recordSecondStep($order_id, $data['optionsGAA'], $data['rta']);

    $query = tep_db_query("select text from " . TABLE_ORDERS_TOTAL . " where orders_id = ".$order_id." ");
    $subtotal = mysqli_fetch_object($query);
    if(strstr($subtotal->text, ",")) {
      $subtotal->text = str_replace(".", "", $subtotal->text); // replace dots (thousand seps) with blancs
      $subtotal->text = str_replace(",", ".", $subtotal->text); // replace ',' with '.'
    }

    if(preg_match("#([0-9\.]+)#", $subtotal->text, $match)) { // search for number that may contain '.'
      $amount =  floatval($match[0]);
    } else {
      $amount = floatval($str); // take some last chances with floatval
    }

    if ($data['rta']['StatusCode'] == TP_STATUS_OK) {

      $comision = $data['rta']['Payload']['Request']['AMOUNTBUYER'] - $amount;
      $account_query = tep_db_query("UPDATE ".TABLE_ORDERS_TOTAL." SET text = '<strong>$".$data['rta']['Payload']['Request']['AMOUNTBUYER']."</strong>', value = ".$data['rta']['Payload']['Request']['AMOUNTBUYER']." WHERE orders_id = ".$order_id." AND class = 'ot_total'");
      $account_query = tep_db_query("INSERT INTO ". TABLE_ORDERS_TOTAL ." (orders_id, title, text, value, class, sort_order) VALUES (".$order_id.", 'Otros cargos', '$".$comision."', ".$comision.", 'ot_subtotal', 3)" );
//      $account_query = tep_db_query("UPDATE ".TABLE_ORDERS_PRODUCTS." SET final_price = '".$data['rta']['Payload']['Request']['AMOUNTBUYER']."' WHERE orders_id = ".$order_id." ");

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

    }else{
          if($todoPagoConfig['emptycart_enabled'] == 1) {
          	$cart->reset(true);
            tep_redirect(tep_href_link('todopago_msg_error.php?msg='.$data['rta']['StatusMessage']));
          }else{
            tep_redirect(tep_href_link('checkout_shipping_retry.php?msg='.$data['rta']['StatusMessage']));
          }
    }
}


_unregisterSessionVars(); //Necesario para el framework
second_step_todopago();
