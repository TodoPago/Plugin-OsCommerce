<?php
/*
osCommerce, Open Source E-Commerce Solutions
http://www.oscommerce.com
Copyright (c) 2003 osCommerce
Released under the GNU General Public License
*/

set_include_path(get_include_path() . PATH_SEPARATOR . realpath(dirname(__FILE__)));
require_once dirname(__FILE__) . '/todopagoplugin/includes/todopago_ctes.php';
//require_once dirname(__FILE__).'/todopagoplugin/includes/TodoPagoLogger.php';
require_once dirname(__FILE__) . '/todopagoplugin/includes/Logger/loggerFactory.php';
//require_once dirname(__FILE__).'/todopagoplugin/includes/TodoPago/lib/Sdk.php';
require_once dirname(__FILE__) . '/todopagoplugin/includes/vendor/autoload.php';
include_once dirname(__FILE__) . '/todopagoplugin/includes/phone.php';

require_once dirname(__FILE__) . '/todopagoplugin/includes/ControlFraude/includes.php';
include_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'todopagoplugin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'TodopagoTransaccion.php');
include_once dirname(__FILE__) . '/todopagoplugin/includes/Utilidades/StatusCodeCS.php';

class todopagoplugin
{

    var $code, $title, $description, $enabled, $logo, $tp_states;

    function todopagoplugin()
    {

        global $order;

        $this->todopagoTransaccion = new TodopagoTransaccion();

        $this->code = 'todopagoplugin';

        $this->title = "TodoPago";

        $this->description = "TodoPago Plugin de pago.";

        $this->api_version = TP_VERSION;

        $this->sort_order = MODULE_PAYMENT_TODOPAGOPLUGIN_SORT_ORDER;

        $this->enabled = ((MODULE_PAYMENT_TODOPAGOPLUGIN_STATUS == 'True') ? true : false);

        if ((int) MODULE_PAYMENT_TODOPAGOPLUGIN_ORDER_STATUS_ID > 0) {

            $this->order_status = MODULE_PAYMENT_TODOPAGOPLUGIN_ORDER_STATUS_ID;
        }

        if (is_object($order))
            $this->update_status();

        $this->logo = 'http://www.todopago.com.ar/sites/todopago.com.ar/files/pluginstarjeta.jpg';
    }


    function update_status()
    {

        return false;
    }


    function javascript_validation()
    {

        return false;
    }



    function selection()
    {

        $sql           = "select * from todo_pago_configuracion";
        $res           = tep_db_query($sql);
        $row = tep_db_fetch_array($res);

        $count_modules_payment_enables = 0;

        if(empty($row['bannerbilletera'])){
            $row['bannerbilletera']=1;
        }

        if (defined('MODULE_PAYMENT_INSTALLED') && tep_not_null(MODULE_PAYMENT_INSTALLED)) {
           $modules_payment_installed = explode(';', MODULE_PAYMENT_INSTALLED);
          } 

        if (is_array($modules_payment_installed)) {
           
            while (list(, $value_pay) = each($modules_payment_installed)) {
              $class_payment = substr($value_pay, 0, strrpos($value_pay, '.'));
              if ($GLOBALS[$class_payment]->enabled) {
                $count_modules_payment_enables++;
              }
            }
          }
        
        $tp_html = '<img src="' . $this->logo . '" />
                </strong>

            </td>
            <td align="right" id="idsin_billetera">
                <input name="payment" value="todopagoplugin_sin_billetera" type="radio">
            </td>
        </tr>
    </tbody>

    <script type="text/javascript">
    function seleccionar_billetera(valor){
        console.log("Seleccionar billetera");
        $("#bvtp").val(valor);
        $("input[value=\'todopagoplugin\']").prop(\'checked\', valor);
    }
    $("#idsin_billetera").closest("tr").click(function(){
        seleccionar_billetera(false);
    });
    </script>


    <tbody>
        <tr class="moduleRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="seleccionar_billetera(true)">
            <td>
                <strong>
                    <img src="https://todopago.com.ar/sites/todopago.com.ar/files/billetera/pluginstarjeta'. $row['bannerbilletera'] .'.jpg">
                    <br /><input type="hidden" id="bvtp" name="bvtp" value="true" >
                    Billetera Virtual Todo Pago';
    //solo todo pago
    if($count_modules_payment_enables == 1){
        $tp_html .= ' </td>
                <td align="right">
                    <input name="payment" value="todopagoplugin" type="radio">
                </td>
            </tr>
            </tbody>';
    }


        //Con billetera
        return array(
            'id' => $this->code,
            'module' => $tp_html,
            'icon' => '<img src="' . DIR_WS_CATALOG . $this->logo . '" />'
        );



        /*
        //Sin billetera
        return array(
            'id' => $this->code,

            'module' => '<img src="' . $this->logo . '" />',
            'icon' => '<img src="' . DIR_WS_CATALOG . $this->logo . '" />'
        );
        */

    }



    function pre_confirmation_check()
    {

        return false;
    }



    function confirmation()
    {

        $states = $this->_get_tp_states();

        echo "<div style='color:red;font-weight:bold'>Por favor eleg&iacute; tu provincia para continuar</div>";
        echo "<select name='tp_states'>";

        $firstState = true;
        $stateCode  = "";
        foreach ($states as $city => $code) {
            if ($firstState) {
                echo '<option value="' . $code . '" selected>' . $city . '</option>';
                $stateCode  = $code;
                $firstState = false;
            } else {
                echo '<option value="' . $code . '">' . $city . '</option>';
            }
        }


        echo "</select>";

        return false;
    }



    function process_button()
    {

        global $order, $currencies, $currency, $insert_id;

        $my_currency = $currency;

        $partialTotal = $order->info['total'];

        $shippingCost = $order->info['shipping_cost'];

        $myCurrencyValue = $currencies->get_value($my_currency);

        $myCurrencyDecimalPlaces = $currencies->get_decimal_places($my_currency);

        $total = $partialTotal * $myCurrencyValue;

        $precio = number_format($total, 2, '.', '');

        $productos = "";


        for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {

            $productos .= "- " . $order->products[$i]['name'] . " ";

        }

        $productos = substr($productos, 0, 70) . '...';



        if ($my_currency == 'USD') {

            $TipoMoneda = 'DOL';
        } else {

            $TipoMoneda = 'ARG';
        }



        $process_button_string = tep_draw_hidden_field('name', $productos) . tep_draw_hidden_field('currency', $TipoMoneda) . tep_draw_hidden_field('price', $precio) . tep_draw_hidden_field('url_cancel', tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL')) . tep_draw_hidden_field('item_id', MODULE_PAYMENT_TODOPAGOPLUGIN_ID) . tep_draw_hidden_field('acc_id', MODULE_PAYMENT_TODOPAGOPLUGIN_ID) . tep_draw_hidden_field('shipping_cost', '') . tep_draw_hidden_field('url_process', '') . tep_draw_hidden_field('url_succesfull', tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL')) . tep_draw_hidden_field('enc', MODULE_PAYMENT_TODOPAGOPLUGIN_CODE);

    }


    function before_process()
    {


    }

    function checkout_initialization_method()
    {

        $string = '';
        return $string;
    }


    function after_process()
    {
        $dir = DIR_WS_INCLUDES . 'work' . DIRECTORY_SEPARATOR . 'todopago.log';

        $this->first_step_todopago();

        return false;
    }



    function check()
    {

        if (!isset($this->_check)) {

            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_TODOPAGOPLUGIN_STATUS'");

            $this->_check = tep_db_num_rows($check_query);

        }

        return $this->_check;
    }

    function install()
    {

?>
<script>
        $('#pop-up-message').html("\
            <p>Se le informa que se realizar&aacute;n los siguientes cambios:</p>   \
            <ul>    \
                <li>Se agregar&acute;n campos en la tabla de configuraci&oacute;n propios del m&oacute;dulo</li>    \
                <li>Se agregar&acute; una tabla <em><?php
        echo TABLE_TP_CONFIGURACION;
?></em> para parametros de configuración adicionales</li>    \
                <li>Se agregar&aacute; una tabla <em>todopago_transaccion</em> a su base de datos la cu&aacute;l guardar&aacute; informaci&oacute;n sobre las transacciones realizadas por el medio de pago.</li>   \
            </ul>   \
        ");
        $('#pop-up').show();
</script>

        <?php
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Habilitar módulo TodoPago', 'MODULE_PAYMENT_TODOPAGOPLUGIN_STATUS', 'True', 'Desea aceptar pagos a traves de TodoPago?', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");

        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_TODOPAGOPLUGIN_SORT_ORDER', '0', 'Order de despliegue. El mas bajo se despliega primero.', '6', '0', now())");

        tep_db_query("CREATE TABLE IF NOT EXISTS `".TABLE_TP_ATRIBUTOS."` ( `product_id` BIGINT NOT NULL , `CSITPRODUCTCODE` VARCHAR(150) NOT NULL COMMENT 'Codigo del producto' , `CSMDD33` VARCHAR(150) NOT NULL COMMENT 'Dias para el evento' , `CSMDD34` VARCHAR(150) NOT NULL COMMENT 'Tipo de envio' , `CSMDD28` VARCHAR(150) NOT NULL COMMENT 'Tipo de servicio' , `CSMDD31` VARCHAR(150) NOT NULL COMMENT 'Tipo de delivery' ) ENGINE = MyISAM;");

        tep_db_query("CREATE TABLE IF NOT EXISTS `".TABLE_TP_CONFIGURACION."` ( `idConf` INT NOT NULL PRIMARY KEY, `authorization` VARCHAR(100) NOT NULL , `segmento` VARCHAR(100) NOT NULL , `canal` VARCHAR(100) NOT NULL , `ambiente` VARCHAR(100) NOT NULL , `deadline` VARCHAR(100) NOT NULL , `test_endpoint` TEXT NOT NULL , `test_wsdl` TEXT NOT NULL , `test_merchant` VARCHAR(100) NOT NULL , `test_security` VARCHAR(100) NOT NULL , `production_endpoint` TEXT NOT NULL , `production_wsdl` TEXT NOT NULL , `production_merchant` VARCHAR(100) NOT NULL , `production_security` VARCHAR(100) NOT NULL , `estado_inicio` VARCHAR(100) NOT NULL , `estado_aprobada` VARCHAR(100) NOT NULL , `estado_rechazada` VARCHAR(100) NOT NULL , `tipo_formulario` TINYINT UNSIGNED DEFAULT 0,`estado_offline` VARCHAR(100) NOT NULL, `medios_pago` TEXT NOT NULL,`todopago_timeout` INT ,`timeout_enabled` TINYINT UNSIGNED DEFAULT 0 , `emptycart_enabled` TINYINT UNSIGNED DEFAULT 0 , `maxinstallments` INT UNSIGNED DEFAULT 12 , `maxinstallments_enabled` TINYINT UNSIGNED DEFAULT 0,  `bannerbilletera` TINYINT UNSIGNED DEFAULT 0 ) ENGINE = MyISAM;");

        $qry       = "SHOW COLUMNS FROM `" . TABLE_TP_CONFIGURACION . "` LIKE 'todopago_timeout'";
        $resFields = tep_db_query($qry);
        if ($resFields->num_rows == 0) { // debo crearlo

            tep_db_query("ALTER TABLE `" . TABLE_TP_CONFIGURACION . "` ADD `todopago_timeout` INT(11) NOT NULL DEFAULT '0' ");
        }

        $qryTimeout_enabled = "SHOW COLUMNS FROM `" . TABLE_TP_CONFIGURACION . "` LIKE 'timeout_enabled'";
        $resFields          = tep_db_query($qryTimeout_enabled);
        if ($resFields->num_rows == 0) { // debo crearlo

            tep_db_query("ALTER TABLE `" . TABLE_TP_CONFIGURACION . "` ADD `timeout_enabled` INT(1) NOT NULL DEFAULT '0' ");
        }

        $qryEmptycart_enabled = "SHOW COLUMNS FROM `" . TABLE_TP_CONFIGURACION . "` LIKE 'emptycart_enabled'";
        $resFields            = tep_db_query($qryEmptycart_enabled);
        if ($resFields->num_rows == 0) { // debo crearlo

            tep_db_query("ALTER TABLE `" . TABLE_TP_CONFIGURACION . "` ADD `emptycart_enabled` INT(1) NOT NULL DEFAULT '0' ");
        }

        $qryTimeout_enabled = "SHOW COLUMNS FROM `".TABLE_TP_CONFIGURACION."` LIKE 'timeout_enabled'";
        $resFields = tep_db_query($qryTimeout_enabled);
        if ( $resFields->num_rows == 0 ){ // debo crearlo

        $qryEmptycart_enabled = "SHOW COLUMNS FROM `".TABLE_TP_CONFIGURACION."` LIKE 'emptycart_enabled'";
        $resFields = tep_db_query($qryEmptycart_enabled);
	}
        if ( $resFields->num_rows == 0 ){ // debo crearlo
            tep_db_query("ALTER TABLE `".TABLE_TP_CONFIGURACION."` ADD `emptycart_enabled` INT(1) NOT NULL DEFAULT '0' ");
        }
        tep_db_query("DELETE FROM `".TABLE_TP_CONFIGURACION."`");

        tep_db_query("INSERT INTO `" . TABLE_TP_CONFIGURACION . "` (`idConf`, `authorization`, `segmento`, `canal`, `ambiente`, `deadline`, `test_endpoint`, `test_wsdl`, `test_merchant`, `test_security`, `production_endpoint`, `production_wsdl`, `production_merchant`, `production_security`, `estado_inicio`, `estado_aprobada`, `estado_rechazada`, `estado_offline`, `medios_pago`) VALUES ('1', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '')");

        tep_db_query("CREATE TABLE IF NOT  EXISTS `" . TABLE_TP_TRANSACCION . "` (
                                                               `id` INT NOT NULL AUTO_INCREMENT,
                                                               `id_orden` INT NULL,
                                                               `first_step` TIMESTAMP NULL,
                                                               `params_SAR` TEXT NULL,
                                                               `response_SAR` TEXT NULL,
                                                               `second_step` TIMESTAMP NULL,
                                                               `params_GAA` TEXT NULL,
                                                               `response_GAA` TEXT NULL,
                                                               `request_key` TEXT NULL,
                                                               `public_request_key` TEXT NULL,
                                                               `answer_key` TEXT NULL,
                                                               `url_cupon` TEXT NULL,
                                                               PRIMARY KEY (`id`)
                                               )");



        $sql = 'CREATE TABLE IF NOT EXISTS todopago_gmaps (
          ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
              billing_street varchar(60) NOT NULL,
              billing_state varchar(60) NOT NULL,
              billing_city varchar(64) NOT NULL,
              billing_country varchar(100) NOT NULL,
              billing_postalcode varchar(100) NOT NULL,
              shipping_street varchar(60) NOT NULL ,
              shipping_state varchar(60) NOT NULL,
              shipping_city varchar(64) NOT NULL,
              shipping_country varchar(100) NOT NULL,
              shipping_postalcode varchar(100) NOT NULL,
              identify_key varchar(100) NOT NULL,
              PRIMARY KEY (ID))';
        tep_db_query($sql);


        $queryResult      = tep_db_query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='" . TABLE_TP_TRANSACCION . "' AND column_name='url_cupon'");
        $queryResultArray = tep_db_fetch_array($queryResult);

        if (empty($queryResultArray)) {
            tep_db_query("ALTER TABLE `" . TABLE_TP_TRANSACCION . "` ADD COLUMN `url_cupon` TEXT NULL AFTER `answer_key`;");
        }
    }

    function remove()
    {

        /*?>
        $('#pop-up-message').html("\
        <p>Se le informa que se realizar&aacute;n los siguientes cambios:</p>   \
        <ul>    \
        <li>Se agregar&acute;n campos en la tabla de configuraci&oacute;n propios del m&oacute;dulo</li>    \
        <li>Se agregar&acute; una tabla <em><?php echo TABLE_TP_CONFIGURACION ?></em> para parametros de configuración adicionales</li>    \
        <li>Se agregar&aacute; una tabla <em>todopago_transaccion</em> a su base de datos la cu&aacute;l guardar&aacute; informaci&oacute;n sobre las transacciones realizadas por el medio de pago.</li>   \
        </ul>   \
        ");
        $('#pop-up').show();

        <?php*/

        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
        tep_db_query("DROP TABLE todo_pago_configuracion");
        //tep_db_query("DROP TABLE todopago_transaccion");
    }


    function keys()
    {

        return array(
            'MODULE_PAYMENT_TODOPAGOPLUGIN_STATUS',
            'MODULE_PAYMENT_TODOPAGOPLUGIN_ID',
            'MODULE_PAYMENT_TODOPAGOPLUGIN_SORT_ORDER'
        );

    }

    private function _get_tp_configuracion()
    {

        $todoPagoConfig = tep_db_query('SELECT * FROM todo_pago_configuracion');
        $todoPagoConfig = tep_db_fetch_array($todoPagoConfig);

        if ($todoPagoConfig['ambiente'] == "test") {
            $mode     = "test";
            $security = $todoPagoConfig['test_security'];
            $merchant = $todoPagoConfig['test_merchant'];
        } else {
            $mode     = "prod";
            $security = $todoPagoConfig['production_security'];
            $merchant = $todoPagoConfig['production_merchant'];
        }
        $header = json_decode(html_entity_decode($todoPagoConfig['authorization']), TRUE);

        return array(
                'header' => $header,
                'segmento' => $todoPagoConfig['segmento'],
                'canal' => $todoPagoConfig['canal'],
                'mode' => $mode,
                'deadline' => $todoPagoConfig['deadline'],
                'security' => $security,
                'merchant' => $merchant,
                'estados' => array(
                    'inicio' => $todoPagoConfig['estado_inicio'],
                    'aprobada' => $todoPagoConfig['estado_aprobada'],
                    'rechazada' => $todoPagoConfig['estado_rechazada'],
                    'offline' => $todoPagoConfig['estado_offline']
                    ),
                'medios_pago' => $todoPagoConfig['medios_pago'],
                'maxinstallments' => $todoPagoConfig['maxinstallments'],
                'maxinstallments_enabled' => $todoPagoConfig['maxinstallments_enabled'],
                'timeout_enabled' => $todoPagoConfig['timeout_enabled'],
                'emptycart_enabled' => $todoPagoConfig['emptycart_enabled'],
                'todopago_timeout' => $todoPagoConfig['todopago_timeout']

                 );
    }

    private function _create_tp_connector()
    {

        $connector = new TodoPago\Sdk($this->todoPagoConfig['header'], $this->todoPagoConfig['mode']);


        //$return = array_merge('connector'=>$connector, 'merchant'=>$merchant, 'security'=>$security, 'header'=>$header, 'config' => $todoPagoConfig);


        return $connector;
    }



    private function _get_digital_goods_fields()
    {

        $CSMDD31 = array();

        foreach ($data->products as $prod) {

            $customfields = array();
            $customfields = $this->_get_tp_custom_values($prod['id']);

            if (is_array($customfields)) {

                foreach ($customfields as $customIndex => $customValue) {

                    if ($customIndex == 'CSMDD31') {
                        $CSMDD31[] = trim(urlencode(htmlentities(strip_tags($customValue))));
                    }
                }
            }
        }

        $fields = array(
            'CSMDD31' => implode('#', $CSMDD31)
        );

        return $fields;
    }

    private function _get_services_fields()
    {

        $CSMDD28 = array();

        foreach ($data->products as $prod) {

            $customfields = array();
            $customfields = $this->_get_tp_custom_values($prod['id']);

            if (is_array($customfields)) {

                foreach ($customfields as $customIndex => $customValue) {

                    if ($customIndex == 'CSMDD28') {
                        $CSMDD28[] = trim(urlencode(htmlentities(strip_tags($customValue))));
                    }
                }
            }
        }

        $fields = array(
            'CSMDD28' => implode('#', $CSMDD28)
        );

        return $fields;
    }

    private function _get_ticketing_fields()
    {

        $CSMDD33 = array();
        $CSMDD34 = array();

        foreach ($data->products as $prod) {

            $customfields = array();
            $customfields = $this->_get_tp_custom_values($prod['id']);

            if (is_array($customfields)) {

                foreach ($customfields as $customIndex => $customValue) {

                    if ($customIndex == 'CSMDD33') {
                        $CSMDD33[] = trim(urlencode(htmlentities(strip_tags($customValue))));
                    }
                    if ($customIndex == 'CSMDD34') {
                        $CSMDD34[] = trim(urlencode(htmlentities(strip_tags($customValue))));
                    }
                }
            }
        }

        $fields = array(
            'CSMDD33' => implode('#', $CSMDD33),
            'CSMDD34' => implode('#', $CSMDD34)
        );

        return $fields;

    }


    private function _set_tp_order_status($order_id, $status_id)
    {

        tep_db_query('UPDATE ' . TABLE_ORDERS . ' SET orders_status = ' . $status_id . ' WHERE orders_id = ' . $order_id);
    }

    private function _get_tp_custom_values($product_id)
    {

        $product_id = explode('{', $product_id);

        $todoPagoConfig = tep_db_query('SELECT * FROM ' . TABLE_TP_ATRIBUTOS . ' WHERE product_id = ' . $product_id[0]);
        $todoPagoConfig = tep_db_fetch_array($todoPagoConfig);

        return $todoPagoConfig;
    }

    private function _get_tp_states()
    {

        $states = array(
            'CABA' => 'C',

            'Buenos Aires' => 'B',

            'Catamarca' => 'K',

            'Chaco' => 'H',

            'Chubut' => 'U',

            'C&oacute;rdoba' => 'X',

            'Corrientes' => 'W',

            'Entre R&iacute;os' => 'R',

            'Formosa' => 'P',

            'Jujuy' => 'Y',

            'La Pampa' => 'L',

            'La Rioja' => 'F',

            'Mendoza' => 'M',

            'Misiones' => 'N',

            'Neuqu&eacute;n' => 'Q',

            'R&iacute;o Negro' => 'R',

            'Salta' => 'A',

            'San Juan' => 'J',

            'San Luis' => 'D',

            'Santa Cruz' => 'Z',

            'Santa F&eacute;' => 'S',

            'Santiago del Estero' => 'G',

            'Tierra del Fuego' => 'V',

            'Tucum&aacute;n' => 'T'
        );

        return $states;
    }

    private function _cleanId($id)
    {
        return substr($id, 0, strpos($id . '{', '{'));
    }

    private function _get_customer_aditional_info($customer_id)
    {
        $query = tep_db_query("SELECT c.customers_id as 'customer_id', c.customers_password as 'password', ci.customers_info_date_account_created as 'date_creation', COUNT(*) AS 'orders_qty' FROM customers c INNER JOIN customers_info ci ON c.customers_id = ci.customers_info_id INNER JOIN orders o ON ci.customers_info_id = o.customers_id WHERE c.customers_id = " . $customer_id);
        return tep_db_fetch_array($query);
    }

    private function prepare_order()
    {
        global $order, $insert_id, $customer_id;

        $order->id            = $insert_id;
        $this->todoPagoConfig = $this->_get_tp_configuracion();

        $this->logger = loggerFactory::createLogger(true, $this->todoPagoConfig['mode'], $customer_id, $order->id);

        $this->todopagoTransaccion->createRegister($insert_id);
    }

    private function get_paydata()
    {

        global $order;

        //$this->logger = new TodoPagoLogger($order->id);
        if (empty($this->todoPagoConfig)) {
            echo "El medio de pago TodoPago no está disponible en este momento";
            $this->logger->info("El medio de pago no ha sido configurado");
        }

        $data = $_POST;

        $this->logger->debug("_POST: " . $data);

        $this->logger->debug("_POST['tp_states']: " . $data['tp_states']);

        $this->tp_states = $data['tp_states'];

        $this->logger->debug("this->tp_states: " . $this->tp_states);

        $optionsSAR_comercio  = $this->getOptionsSARComercio($order);
        $optionsSAR_operacion = $this->getOptionsSAROperacion($order);

        return array(
            $optionsSAR_comercio,
            $optionsSAR_operacion
        );
    }


    private function call_SAR($connector, $optionsSAR)
    {
        global $order, $insert_id;

        $user_location = str_replace(' ', '', $optionsSAR[1]['CSBTSTREET1']) . $optionsSAR[1]['CSBTPOSTALCODE'];
        $base_location = tep_db_query('SELECT * FROM todopago_gmaps  where identify_key = "' . $user_location . '"');
        $base_location = tep_db_fetch_array($base_location);


        if ($this->todoPagoConfig['gmaps_enabled'] == 1 && $base_location == null) {
            $g = new \TodoPago\Client\Google();
            $connector->setGoogleClient($g);
            $rta                  = $connector->sendAuthorizeRequest($optionsSAR[0], $optionsSAR[1]);
            $responseGoogleStatus = $connector->getGoogleClient()->getGoogleResponse()['billing']['status'];
            $responseGoogle       = $connector->getGoogleClient()->getFinalAddress();

            if ($responseGoogleStatus == 'OK') {
                tep_db_query("insert into todopago_gmaps (identify_key, billing_street, billing_state, billing_city, billing_country, billing_postalcode, shipping_street, shipping_state,shipping_city, shipping_country, shipping_postalcode) values ('" . $user_location . "','" . $responseGoogle['billing']['CSBTSTREET1'] . "','" . $responseGoogle['billing']['CSBTSTATE'] . "','" . $responseGoogle['billing']['CSBTCITY'] . "', '" . $responseGoogle['billing']['CSBTCOUNTRY'] . "','" . $responseGoogle['billing']['CSBTPOSTALCODE'] . "', '" . $responseGoogle['shipping']['CSSTSTREET1'] . "','" . $responseGoogle['shipping']['CSSTSTATE'] . "','" . $responseGoogle['shipping']['CSSTCITY'] . "','" . $responseGoogle['shipping']['CSSTCOUNTRY'] . "','" . $responseGoogle['shipping']['CSSTPOSTALCODE'] . "')");
            }
        } else if ($this->todoPagoConfig['gmaps_enabled'] == 1 && $base_location != null) {
            $optionsSAR[1]['CSBTSTREET1']    = $base_location['billing_street'];
            $optionsSAR[1]['CSBTSTATE']      = $base_location['billing_state'];
            $optionsSAR[1]['CSBTCITY']       = $base_location['billing_city'];
            $optionsSAR[1]['CSBTCOUNTRY']    = $base_location['billing_country'];
            $optionsSAR[1]['CSBTPOSTALCODE'] = $base_location['billing_postalcode'];
            $optionsSAR[1]['CSSTSTREET1']    = $base_location['shipping_street'];
            $optionsSAR[1]['CSSTSTATE']      = $base_location['shipping_state'];
            $optionsSAR[1]['CSSTCITY']       = $base_location['shipping_city'];
            $optionsSAR[1]['CSSTCOUNTRY']    = $base_location['shipping_country'];
            $optionsSAR[1]['CSSTPOSTALCODE'] = $base_location['shipping_country'];
            $rta = $connector->sendAuthorizeRequest($optionsSAR[0], $optionsSAR[1]);
        } else {
            $rta = $connector->sendAuthorizeRequest($optionsSAR[0], $optionsSAR[1]);
        }
        if ($rta['StatusCode'] == 702 && !(empty($this->todoPagoConfig['merchant']) or empty($this->todoPagoConfig['security']) or empty($this->todoPagoConfig['header']))) {
            $this->logger->debug("Reintento");
            $rta = $connector->sendAuthorizeRequest($optionsSAR[0], $optionsSAR[1]);
        }

        $error = new StatusCodeCS();
        $this->logger->info("response SAR: " . json_encode($rta));

        $errorcs = $error->getErrorByStatusCode($rta['StatusCode']);
        $this->logger->info("response SAR, error de datos " . json_encode($errorcs));


        if ($rta['StatusCode'] == TP_STATUS_OK) {
            $query = $this->todopagoTransaccion->recordFirstStep($order->id, $optionsSAR, $rta);

            //select payment form
            $todoPagoConfig = tep_db_query('SELECT * FROM todo_pago_configuracion');
            $todoPagoConfig = tep_db_fetch_array($todoPagoConfig);
            $formType       = $todoPagoConfig['tipo_formulario'];

            //choose form payment type
            if ($formType == 0) { //Formulario externo
                header('Location: ' . $rta['URL_Request']);
            } elseif ($formType == 1) { //Formulario híbrido




                ////////********************
                ///////////******************
                //PRINT_r($_SESSION);
                //die;
    
                //Si eligió billetera, redirige a billetera
                if(!empty($_SESSION['billetera'])){
                    header('Location: ' . tep_href_link('todopago_form_pago_billetera.php', 'id=' . $insert_id, 'SSL'));
                }else{
                    header('Location: ' . tep_href_link('todopago_form_pago.php', 'id=' . $insert_id, 'SSL'));
                }
            }
            die();

        } else {
            if ($rta['StatusCode'] > 98000 && $rta['StatusCode'] < 99000)
                header('Location: ' . tep_href_link('checkout_shipping_retry.php', 'msg=' . urlencode($rta["StatusMessage"]), 'SSL'));
            else
            // header('Location: '.tep_href_link('checkout_shipping_retry.php', '', 'SSL'));
                die();
        }
    }

    public function first_step_todopago()
    {
        global $order;

        $this->prepare_order();

        if ($this->todopagoTransaccion->_getStep($order->id) == TodopagoTransaccion::FIRST_STEP) {

            $this->logger->info("first step");
            $connector = $this->_create_tp_connector();

            $optionsSAR = $this->get_paydata();

            $this->logger->info("params SAR: " . json_encode($optionsSAR));

            $this->call_SAR($connector, $optionsSAR);
        } else {
            $this->logger->warn("No se pudo efectuar el first step, ya se encuentra un first step exitoso registrado en la tabla todopago_transaccion");
            header('Location: ' . tep_href_link('index.php', '', 'SSL'));
            die();
        }

        return false;
    }

    private function getOptionsSARComercio($order)
    {
        $security_code = $this->todoPagoConfig['security'];
        $merchant      = $this->todoPagoConfig['merchant'];

        $optionsSAR_comercio = array(
            'URL_OK' => tep_href_link('second_step_todopago.php?Order=' . $order->id, '', 'SSL'),
            'URL_ERROR' => tep_href_link('second_step_todopago.php?Order=' . $order->id, '', 'SSL'),
            'Merchant' => $merchant,
            'Security' => $security_code,
            'EncodingMethod' => 'XML',
            //'AVAILABLEPAYMENTMETHODSIDS' => $this->getAvailablePaymentMethods(),
            'PUSHNOTIFYMETHOD' => 'application/x-www-form-urlencoded',
            'PUSHNOTIFYENDPOINT' => HTTP_SERVER . DIR_WS_CATALOG . 'todopago_push_notification.php',
            'PUSHNOTIFYSTATES' => 'CouponCharged'
        );
        return $optionsSAR_comercio;
    }

    private function getOptionsSAROperacion($order)
    {

        global $customer_id;
        $merchant                       = $this->todoPagoConfig['merchant'];
        $order->delivery['tp_state']    = $this->tp_states;
        $order->billing['tp_state']     = $this->tp_states;
        $order->customer_aditional_info = $this->_get_customer_aditional_info($customer_id);

        $controlFraude        = ControlFraudeFactory::get_ControlFraude_extractor($this->todoPagoConfig['segmento'], $order, $this->logger);
        $optionsSAR_operacion = $controlFraude->getDataCF();

        $optionsSAR_operacion['MERCHANT']     = $merchant;
        $optionsSAR_operacion['CURRENCYCODE'] = '032';
        $optionsSAR_operacion['OPERATIONID']  = $order->id;
        $optionsSAR_operacion['AMOUNT']       = $order->info['total'];











        $todoPagoConfig = tep_db_query('SELECT * FROM todo_pago_configuracion');
        $todoPagoConfig = tep_db_fetch_array($todoPagoConfig);
        $tipo_formulario       = $todoPagoConfig['tipo_formulario'];   
        if($tipo_formulario==0){
            $campo_tipo_formulario='E';
        }else{
            $campo_tipo_formulario='H';
        }

        $optionsSAR_operacion['ECOMMERCENAME']    = 'OSCOMMERCE';
        $ecom_version = explode(" v", (string)PROJECT_VERSION, 2);
        $optionsSAR_operacion['ECOMMERCEVERSION'] = $ecom_version[1];
        $optionsSAR_operacion['PLUGINVERSION'] = TP_VERSION."-".$campo_tipo_formulario;


        if (isset($this->todoPagoConfig['']) && $this->todoPagoConfig[''] == 1) {
            $optionsSAR_operacion['MAXINSTALLMENTS'] = ($this->todoPagoConfig['maxinstallments'] > 0 && $this->todoPagoConfig['maxinstallments'] <= 12) ? $this->todoPagoConfig['maxinstallments'] : 12;
        }
        if (isset($this->todoPagoConfig['timeout_enabled']) && $this->todoPagoConfig['timeout_enabled'] == 1) {
            $optionsSAR_operacion['TIMEOUT'] = $this->todoPagoConfig['todopago_timeout'];
        }


        //$this->logger = new TodoPagoLogger($order->id);
        $this->logger->debug("optionsSAR_operacion: " . json_encode($optionsSAR_operacion));

        return $optionsSAR_operacion;
    }
}
