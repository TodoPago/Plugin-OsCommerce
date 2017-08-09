<?php
  use osCommerce\OM\Core\Registry;

  require("includes/application_top.php");
  require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'payment'.DIRECTORY_SEPARATOR.'todopagoplugin'.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'TodopagoTransaccion.php');
  require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'payment'.DIRECTORY_SEPARATOR.'stripe.php');
  require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'payment'.DIRECTORY_SEPARATOR.'todopagoplugin.php');

  require(DIR_WS_LANGUAGES . $language . '/todopago_form_pago.php');

  $breadcrumb->add(NAVBAR_TITLE, tep_href_link(FILENAME_SHOPPING_CART));

  require(DIR_WS_INCLUDES . 'template_top.php');

  $res = tep_db_query("SELECT * FROM ".TABLE_TP_CONFIGURACION);
  $fetch_result = tep_db_fetch_array($res);

  //set url external form library
  $library = "resources/TPHybridForm-v0.1.js";

  if($fetch_result['ambiente'] == "test"){
    $endpoint = "https://developers.todopago.com.ar/";
  }else{
    $endpoint = "https://forms.todopago.com.ar/";
  }

  $endpoint .= $library;


  if(isset($_GET['id'])){
    $id_decode = $_GET['id'];

    if(is_numeric($id_decode)){
      $tpTransaccion = new TodopagoTransaccion();
      $response = $tpTransaccion->getTransaction($id_decode);

      if($response['public_request_key'] != null || $response['public_request_key'] != ''){

        $publicKey = $response['public_request_key'];

        //user, mail
        $customer_data = tep_db_query('SELECT * FROM customers');
        $customer_data = tep_db_fetch_array($customer_data);

        $user = $customer_data['customers_firstname']." ".$customer_data['customers_lastname'];
        $mail = $customer_data['customers_email_address'];

      }else{
        header('Location: '.tep_href_link('checkout_shipping_retry.php', '', 'SSL'));
        die();
      }

    }else{
      header('Location: '.tep_href_link('checkout_shipping_retry.php', '', 'SSL'));
      die();
    }

  }else{
    header('Location: '.tep_href_link('checkout_shipping_retry.php', '', 'SSL'));
    die();
  }

?>
<script src="<?php echo $endpoint ?>"></script>
<link rel="stylesheet" type="text/css" href="includes/modules/payment/todopagoplugin/form_todopago.css" />
<div id="security" data-securityKey="<?php echo $publicKey ?>"></div>
<div id="user" data-user="<?php echo $user ?>"></div>
<div id="mail" data-mail="<?php echo $mail ?>"></div>

<!--h1><?php //echo HEADING_TITLE; ?></h1-->
<!-- comienza el fornulario-->
<div class="contentContainer">
  <div id="tp-form-tph">
    <div id="validationMessage"></div>
    <div id="tp-logo"></div>
    <div id="tp-content-form">
      <div>
        <span class="tp-label">Eleg&iacute; tu forma de pago </span>
        <select id="formaDePagoCbx"></select>
      </div>
      <div>
        <select id="bancoCbx"></select>
      </div>
      <div>
        <select id="promosCbx" class="left"></select>
        <label id="labelPromotionTextId" class="left tp-label"></label>
        <div class="clear"></div>
      </div>
      <div>
        <label id="labelPeiCheckboxId"></label>
        <input id="peiCbx"/>
      </div>
      <div>
        <input id="numeroTarjetaTxt"/>
      </div>
      <div class="dateFields">
          <input id="mesTxt" class="left">
          <span class="left spacer">/</span>
          <input id="anioTxt" class="left">
          <div class="clear"></div>
      </div>
      <div>
        <input id="codigoSeguridadTxt" class="left"/>
        <label id="labelCodSegTextId" class="left tp-label"></label>
        <div class="clear"></div>
      </div>
      <div>
        <input id="apynTxt"/>
      </div>
      <div>
        <select id="tipoDocCbx"></select>
      </div>
      <div>
        <input id="nroDocTxt"/>
      </div>
      <div>
        <input id="emailTxt"/><br/>
      </div>
      <div>
        <label id="labelPeiTokenTextId"></label>
        <input id="peiTokenTxt"/>
      </div>
      <div id="tp-bt-wrapper">
        <button id="MY_btnConfirmarPago"/>
        <button id="btnConfirmarPagoValida" class="tp-button ui-button ui-widget ui-state-default ui-corner-all ui-button-text-icon-primary ui-priority-secondary">Pagar</button>
        <button id='MY_btnPagarConBilletera' title="Pagar con billetera virtual" class='button tp-button ui-button ui-widget ui-state-default ui-corner-all ui-button-text-icon-primary ui-priority-secondary'><span>Pagar con billetera virtual</span></button>
      </div>
    </div>
  </div>

  <script>
    //build url
    urlOri = document.location.pathname;
    file ="/second_step_todopago.php?Order=";
    errorRed = "/checkout_shipping_retry.php";
    errorTimeout = "/checkout_timeout_retry.php";
    urlFormat = urlOri.split("/").slice(0, -1).join("/");
    urlSuccessRedirect = urlFormat+file;
    urlErrorRedirect = urlFormat+errorRed;
    urlErrorTimeout =  urlFormat+errorTimeout;
    /************* CONFIGURACION DEL API ************************/
    window.TPFORMAPI.hybridForm.initForm({
      callbackValidationErrorFunction: 'validationCollector',
      callbackBilleteraFunction: 'billeteraPaymentResponse',
      botonPagarConBilleteraId: 'MY_btnPagarConBilletera',
      modalCssClass: 'modal-class',
      modalContentCssClass: 'modal-content',
      beforeRequest: 'initLoading',
      afterRequest: 'stopLoading',
      callbackCustomSuccessFunction: 'customPaymentSuccessResponse',
      callbackCustomErrorFunction: 'customPaymentErrorResponse',
      botonPagarId: 'MY_btnConfirmarPago',
      codigoSeguridadTxt: 'Codigo',
    });

    window.TPFORMAPI.hybridForm.setItem({
      publicKey: $('#security').attr("data-securityKey"),
      defaultNombreApellido: $('#user').attr("data-user"),
      defaultMail: $('#mail').attr("data-mail"),
      defaultTipoDoc: 'DNI'
    });

    function validationCollector(response) {
      var errorMessage="<div class='messageStackError'><img src='images/icons/error.gif' alt='Error' title='Error'>"+response.error+"</div>";
      $("#validationMessage").append(errorMessage);
    }
      function billeteraPaymentResponse(response) {
      if(!response.AuthorizationKey){
        window.location.href = document.location.origin + urlErrorTimeout + "?msg=" + response.ResultMessage;
      }else{
        window.location.href = document.location.origin + urlSuccessRedirect + <?php echo $id_decode ?> + "&Answer=" + response.AuthorizationKey;
      }
    }

    function customPaymentSuccessResponse(response) {
      window.location.href = document.location.origin + urlSuccessRedirect + <?php echo $id_decode ?> + "&Answer=" + response.AuthorizationKey;
    }

    function customPaymentErrorResponse(response) {
      if(!response.AuthorizationKey){
        window.location.href = document.location.origin + urlErrorTimeout + "?msg=" + response.ResultMessage;
      }else{
        window.location.href = document.location.origin + urlSuccessRedirect + <?php echo $id_decode ?> + "&Answer=" + response.AuthorizationKey;
      }
    }
    
    function initLoading() {

    }
    function stopLoading() {

    }

    $( document ).ready(function() {
      $("#btnConfirmarPagoValida").on("click", function(){
        $('.messageStackError').remove();
        $('#MY_btnConfirmarPago').click();
      });

      $('#coProgressBar').progressbar({
        value: 100
      });
    });
  </script>
</div>
<div style="float: left; width: 60%; padding-top: 5px; padding-left: 15%;">
  <div id="coProgressBar" style="height: 5px;"></div>

  <table border="0" width="100%" cellspacing="0" cellpadding="2">
    <tr>
      <td align="center" width="25%" class="checkoutBarFrom"><?php echo '<a href="' . tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL') . '" class="checkoutBarFrom">' . CHECKOUT_BAR_DELIVERY . '</a>'; ?></td>
      <td align="center" width="25%" class="checkoutBarFrom"><?php echo '<a href="' . tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL') . '" class="checkoutBarFrom">' . CHECKOUT_BAR_PAYMENT . '</a>'; ?></td>
      <td align="center" width="25%" class="checkoutBarFrom"><?php echo CHECKOUT_BAR_CONFIRMATION; ?></td>
      <td align="center" width="25%" class="checkoutBarCurrent"><?php echo CHECKOUT_BAR_PAYMENTTP; ?></td>
    </tr>
  </table>
</div>
<?php
  require(DIR_WS_INCLUDES . 'template_bottom.php');
  require(DIR_WS_INCLUDES . 'application_bottom.php');
?>
