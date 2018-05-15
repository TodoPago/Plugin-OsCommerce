<?php

use osCommerce\OM\Core\Registry;

require("includes/application_top.php");
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'payment' . DIRECTORY_SEPARATOR . 'todopagoplugin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'TodopagoTransaccion.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'payment' . DIRECTORY_SEPARATOR . 'stripe.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'payment' . DIRECTORY_SEPARATOR . 'todopagoplugin.php');

require(DIR_WS_LANGUAGES . $language . '/todopago_form_pago.php');

$breadcrumb->add(NAVBAR_TITLE, tep_href_link(FILENAME_SHOPPING_CART));

require(DIR_WS_INCLUDES . 'template_top.php');

$res = tep_db_query("SELECT * FROM " . TABLE_TP_CONFIGURACION);
$fetch_result = tep_db_fetch_array($res);

//set url external form library
$library = "hibrido2.js";

if ($fetch_result['ambiente'] == "test") {
    $endpoint = "https://developers.todopago.com.ar/resources/v2/TPBSAForm.min.js";
} else {
    $endpoint = "https://forms.todopago.com.ar/resources/v2/TPBSAForm.min.js";
}


if (isset($_GET['id'])) {
    $id_decode = $_GET['id'];

    if (is_numeric($id_decode)) {
        $tpTransaccion = new TodopagoTransaccion();
        $response = $tpTransaccion->getTransaction($id_decode);
        //echo "response...";
        //var_dump($response);

        if ($response['public_request_key'] != null || $response['public_request_key'] != '') {

            $publicKey = $response['public_request_key'];

            //user, mail
            $customer_data = tep_db_query('SELECT * FROM customers');
            $customer_data = tep_db_fetch_array($customer_data);

            $user = $customer_data['customers_firstname'] . " " . $customer_data['customers_lastname'];
            $mail = $customer_data['customers_email_address'];


            //Amount
            require('includes/classes/order.php');
            $order = new order($id_decode);
            //var_dump($order);
            //total amount
            $total_clean = str_replace("$","",$order->info['total']);
            $total_clean = str_replace(",","",$total_clean);
            
            $total_amount = number_format($total_clean, 2, ',', ' ');



        } else {
            header('Location: ' . tep_href_link('checkout_shipping_retry.php', '', 'SSL'));
            die();
        }

    } else {
        header('Location: ' . tep_href_link('checkout_shipping_retry.php', '', 'SSL'));
        die();
    }

} else {
    header('Location: ' . tep_href_link('checkout_shipping_retry.php', '', 'SSL'));
    die();
}

?>


<script language="javascript" src="<?php echo $endpoint; ?>"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="includes/modules/payment/todopagoplugin/form_todopago.css"/>


<div class="progress">
    <div class="progress-bar progress-bar-striped active" id="loading-hibrid">
    </div>
</div>

<div class="tp_wrapper" id="tpForm">
    <section class="tp-total tp-flex">
        <div>
            <strong>Total a pagar $<?php echo $total_amount; ?></strong>
        </div>
        <div>
            Elegí tu forma de pago
        </div>
    </section> 





    <section class="billetera_virtual_tp">
        <div class="tp_row">
            <div class="tp_col tp_span_1_of_2 texto_billetera_virtual">
                <p>Pagá con tu <strong>Billetera Virtual Todo Pago</strong>
                    <span class="tp_new_line"></span>
                    y evit&aacute; cargar los datos de tu tarjeta</p>
            </div>
            <div class="tp_col tp_span_1_of_2">
                <button id="btn_Billetera" title="Pagar con Billetera" class="tp_btn tp_btn_sm">Pagar con Billetera
                </button>
            </div>
        </div>
    </section>

    <section class="billeterafm_tp">
        <div class="field field-payment-method">
            <label for="formaPagoCbx" class="text_small">Forma de Pago</label>
            <div class="input-box">
                <select id="formaPagoCbx" class="tp_form_control"></select>
                <span class="error" id="formaPagoCbxError"></span>
            </div>
        </div>
    </section>

    <section class="billetera_tp">
        <div class="tp_row">
            <h3>
                Con tu tarjeta de cr&eacute;dito o d&eacute;bito
            </h3>
        </div>
        <div class="tp_row">
            <div class="tp_col tp_span_1_of_2">
                <label for="numeroTarjetaTxt" class="text_small">Número de Tarjeta</label>
                <input id="numeroTarjetaTxt" class="tp_form_control" maxlength="19" title="Número de Tarjeta"
                       min-length="" autocomplete="off" onchange="inicializar_campos()" onkeyup="inicializar_campos()" >
                <img src="includes/modules/payment/todopagoplugin/images/empty.png" id="tp-tarjeta-logo"
                     alt=""/>
                <span class="error" id="numeroTarjetaTxtError"></span>
                <label id="numeroTarjetaLbl" class="error"></label>
            </div>
            <div class="tp_col tp_span_1_of_2">
                <label for="bancoCbx" class="text_small">Banco</label>
                <select id="bancoCbx" class="tp_form_control" placeholder="Selecciona banco"></select>
                <span class="error" id="bancoCbxError">
            </div>
            <div class="tp_col tp_span_1_of_2 payment-method">
                <label for="medioPagoCbx" class="text_small">Medio de Pago</label>
                <select id="medioPagoCbx" class="tp_form_control" placeholder="Mediopago"></select>
                <span class="error" id="medioPagoCbxError"></span>
            </div>
        </div>

        <section class="tp_row" id="peibox">
            <div class="tp_row">
                <div class="tp_col tp_span_1_of_2 pei_wrapper">
                    <label id="peiLbl" for="peiCbx" class="text_small right tp_pei">Pago con PEI</label>
                </div>
                <label class="switch" id="switch-pei">
                    <input type="checkbox" id="peiCbx">
                    <span class="slider round"></span>
                    <span id="slider-text"></span>
                </label>
            </div>
        </section>

        <!--div class="tp_row">
            <div class="tp_col tp_span_1_of_2">
                <label for="medioPagoCbx" class="text_small">Medio de Pago</label>
                <select id="medioPagoCbx" class="tp_form_control" placeholder="Mediopago"></select>
                <span class="error" id="medioPagoCbxError"></span>
            </div>
        </div-->

        <div class="tp_row">
            <div class="tp_col tp_span_1_of_2 campos_fecha_vencimiento">
                <div class="tp_col tp_span_1_of_2 box2_campos_fecha_vencimiento">
                    <label for="mesCbx" class="text_small">Vencimiento</label>

                    <div class="tp_row">
                        <div class="tp_col tp_span_1_of_2">
                            <select id="mesCbx" maxlength="2" class="tp_form_control" placeholder="Mes"></select>
                        </div>
                        <div class="tp_col tp_span_1_of_2">
                            <select id="anioCbx" maxlength="2" class="tp_form_control"></select>
                        </div>
                    </div>


                    <label id="fechaLbl" class="left error"></label>
                </div>

                <div class="tp_col tp_span_1_of_2 box_campos_de_seguridad">
                    <label for="codigoSeguridadTxt" class="text_small campos_codigo_de_seguridad">Código de Seguridad</label>
                    <input id="codigoSeguridadTxt" class="tp_form_control" maxlength="4" autocomplete="off"
                    onchange="validar_cvv()" onkeyup="validar_cvv()" />

               <div class="tp-cvv-helper-container" style="display: none">
                    <div class="tp-anexo clave-ico" id="tp-cvv-caller"></div>
                    <div id="tp-cvv-helper">
                        <p>
                            Para Visa, Master, Cabal y Diners, los 3 dígitos se encuentran en el <strong>dorso</strong>
                            de
                            tu tarjeta. (izq)
                        </p>
                        <p>
                            Para Amex, los 4 dígitos se encuentran en el frente de tu tarjeta. (der)
                        </p>
                        <img id="tp-cvv-helper-img" alt="ilustración tarjetas" src="includes/modules/payment/todopagoplugin/images/clave-ej.png">
                    </div>
                </div>  



                    <span class="error" id="codigoSeguridadTxtError"></span>
                    <label id="codigoSeguridadLbl" class="error left tp-label spacer"></label>
                </div>
            </div>



            <div class="tp_col tp_span_1_of_2">
                <div class="tp_col tp_span_1_of_2">
                    <label for="tipoDocCbx" class="text_small">Tipo</label>
                    <select id="tipoDocCbx" class="tp_form_control"></select>
                </div>
                <div class="tp_col tp_span_1_of_2">
                    <label for="NumeroDocCbx" class="text_small">Número</label>
                    <input id="nroDocTxt" maxlength="10" type="text" class="tp_form_control" placeholder=""
                           autocomplete="off"/>
                    <span class="error" id="nroDocTxtError"></span>
                    <label id="nroDocLbl" class="error"></label>
                </div>
            </div>
        </div>

        <div class="tp_row">
            <div class="tp_col tp_span_1_of_2">
                <label for="nombreTxt" class="text_small">Nombre y apellido</label>
                <input id="nombreTxt" class="tp_form_control" autocomplete="off" placeholder="" maxlength="50">
                <span class="error" id="nombreTxtError"></span>
                <label id="nombreLbl" class="error"></label>
            </div>
            <div class="tp_col tp_span_1_of_2">
                <label for="emailTxt" class="text_small">Email</label>
                <input id="emailTxt" type="email" class="tp_form_control" placeholder="nombre@mail.com" data-mail=""
                       autocomplete="off"/><br/>
                <span class="error" id="emailTxtError"></span>
                <label id="emailLbl" class="error"></label>
            </div>
        </div>

        <div class="tp_row">
            <div class="tp_col tp_span_1_of_2">
                <label for="promosCbx" class="text_small">Cantidad de cuotas</label>
                <select id="promosCbx" class="tp_form_control"></select>
                <span class="error" id="promosCbxError"></span>
            </div>
            <div class="tp_col tp_span_1_of_2" >
                    <label id="promosLbl" class="left"></label>
            </div>

        </div>


        <div class="tp_row">
            <div class="tp_col tp_span_1_of_2 tokenPeiLblBox">
               <label id="tokenPeiLbl" for="tokenPeiTxt" class="info_pei"></label>
               <input id="tokenPeiTxt"/>
               <span class="error" id="peiTokenTxtError"></span>
            </div>

        </div>

        <div class="tp_row">
            <div class="tp_col tp_span_2_of_2">
                <button id="btn_ConfirmarPago" class="tp_btn" title="Pagar" class="button" onclick="
inicializarMensajesError()"><span>Pagar</span></button>
            </div>
            <div class="tp_col tp_span_2_of_2">
                <div class="confirmacion">
                    Al confirmar el pago acepto los <a href="https://www.todopago.com.ar/terminos-y-condiciones-comprador"
                                                       target="_blank" 
                                                       title="Términos y Condiciones" id="tycId" class="tp_color_text">Términos
                        y Condiciones</a> de Todo Pago.
                </div>
            </div>
        </div>

    </section>

    <div class="tp_row">
        <div id="tp-powered">
            Powered by <img id="tp-powered-img" src="<?php echo $form_dir; ?>/images/tp_logo_prod.png"/>
        </div>
    </div>

</div>

<script language="javascript">

    var tpformJquery = $.noConflict();
    //securityRequesKey, esta se obtiene de la respuesta del SAR
    var security = "<?php echo $publicKey; ?>";
    console.log('security --->' + security);


    var switchPei = tpformJquery("#switch-pei");
    var sliderText = tpformJquery("#slider-text");
    var peiCbx = tpformJquery("#peiCbx"); 

    var mail = "<?php echo $mail; ?>";
    var completeName = "<?php echo $user; ?>";
    var defDniType = 'DNI';
    var peiChk = tpformJquery("#peiCbx");
    var imgUrl = 'includes/modules/payment/todopagoplugin/images/';
    var emptyImg = imgUrl + 'empty.png';
    var medioDePago = document.getElementById('medioPagoCbx');
    var tarjetaLogo = document.getElementById('tp-tarjeta-logo');
    var poweredLogo = document.getElementById('tp-powered-img');
    //build url
    var urlOri = document.location.pathname;
    var file = "/second_step_todopago.php?Order=";
    var errorRed = "/checkout_shipping_retry.php";
    var errorTimeout = "/checkout_timeout_retry.php";
    var urlFormat = urlOri.split("/").slice(0, -1).join("/");
    var urlSuccessRedirect = urlFormat + file;
    var urlErrorRedirect = urlFormat + errorRed;
    var urlErrorTimeout = urlFormat + errorTimeout;
    console.log('Url Format', urlFormat);
    console.log('Url Ori', urlOri);
    console.log('Url error', urlErrorRedirect);


    var helperCaller = $("#tp-cvv-caller");
    var helperPopup = $("#tp-cvv-helper"); 

    var idTarjetas = {
        42: 'VISA',
        43: 'VISAD',
        1: 'AMEX',
        2: 'DINERS',
        6: 'CABAL',
        7: 'CABALD',
        14: 'MC',
        15: 'MCD'
    };

    var diccionarioTarjetas = {
        'VISA': 'VISA',
        'VISA DEBITO': 'VISAD',
        'AMEX': 'AMEX',
        'DINERS': 'DINERS',
        'CABAL': 'CABAL',
        'CABAL DEBITO': 'CABALD',
        'MASTERCARD': 'MC',
        'MASTER CARD DEBITO': 'MCD',
        'NARANJA': 'NARANJA'
    };


    helperCaller.click(function () {
        helperPopup.toggle(500);
    }); 

    /************* HELPERS *************/

    numeroTarjetaTxt.onblur = clearImage;

    function clearImage() {
        tarjetaLogo.src = emptyImg;
    }

    function cardImage(select) {
        var tarjeta = idTarjetas[select.value];
        if (tarjeta === undefined) {
            tarjeta = diccionarioTarjetas[select.textContent];
        }
        if (tarjeta !== undefined) {
            tarjetaLogo.src = 'https://forms.todopago.com.ar/formulario/resources/images/' + tarjeta + '.png';
            tarjetaLogo.style.display = 'block';
        }
    }

    loadScript('<?php echo $endpoint; ?>', function () {
        loader();
    });

    function loadScript(url, callback) {
        var entorno = (url.indexOf('developers') === -1) ? 'prod' : 'developers';
        poweredLogo.src = imgUrl + 'tp_logo_' + entorno + '.png';
        var script = document.createElement("script");
        script.type = "text/javascript";
        if (script.readyState) {  //IE
            script.onreadystatechange = function () {
                if (script.readyState === "loaded" || script.readyState === "complete") {
                    script.onreadystatechange = null;
                    callback();
                }
            };
        } else {  //et al.
            script.onload = function () {
                callback();
            };
        }
        script.src = url;
        document.getElementsByTagName("head")[0].appendChild(script);
    }

    function loader() {
        tpformJquery("#loading-hibrid").css("width", "50%");
        setTimeout(function () {
            ignite();
        }, 100);

        setTimeout(function () {
            tpformJquery("#loading-hibrid").css("width", "100%");
        }, 1000);

        setTimeout(function () {
            tpformJquery(".progress").hide('fast');
        }, 2000);

        setTimeout(function () {
            tpformJquery("#tpForm").fadeTo('fast', 1);
        }, 2200);
    }



    //callbacks de respuesta del pago
    window.validationCollector = function (parametros) {
        console.log("Validando los datos");
        console.log(parametros.field + " -> " + parametros.error);
        var input = parametros.field;
        if (input.search("Txt") !== -1) {
            label = input.replace("Txt", "Lbl");
        } else {
            label = input.replace("Cbx", "Lbl");
        }
        if (document.getElementById(label) !== null) {
            document.getElementById(label).innerHTML = parametros.error;
        }

        /*
        arrMensajesError.push(label);        

        console.log('En el array hay: ' + arrMensajesError );
*/

        /*
      console.log("My validator collector");
      tpformJquery("#peibox").hide();
      console.log(parametros.field + " ==> " + parametros.error);
      tpformJquery("#"+parametros.field).addClass("error");
      var field = parametros.field;
      field = field.replace(/ /g, "");
      console.log(field);
      tpformJquery("#"+field+"Error").html(parametros.error);
      console.log(parametros);
      */
    };

    function billeteraPaymentResponse(response) {
        /*
          console.log("Iniciando billetera");
          console.log(response.ResultCode + " -> " + response.ResultMessage);
          if (response.AuthorizationKey) {
              window.top.location = urlSuccess + "?Answer=" + response.AuthorizationKey;
          } else {
              window.top.location = urlError + "?Error=" + response.ResultMessage;
          }
          */


        console.log("Iniciando billetera");
        console.log(response.ResultCode + " -> " + response.ResultMessage);

        if (!response.AuthorizationKey) {
            window.location.href = document.location.origin + urlErrorTimeout + "?msg=" + response.ResultMessage;
        } else {
            window.location.href = document.location.origin + urlSuccessRedirect + <?php echo $id_decode ?> +"&Answer=" + response.AuthorizationKey;
        }

    }

    function customPaymentSuccessResponse(response) {
        /*
          console.log("Success");
          console.log(response.ResultCode + " -> " + response.ResultMessage);
          window.top.location = urlSuccess + "?Answer=" + response.AuthorizationKey;
          */
        window.location.href = document.location.origin + urlSuccessRedirect + <?php echo $id_decode ?> +"&Answer=" + response.AuthorizationKey;

    }

    function customPaymentErrorResponse(response) {
        /*
          console.log(response.ResultCode + " -> " + response.ResultMessage);
          if (response.AuthorizationKey) {
              window.top.location = urlSuccess + "?Answer=" + response.AuthorizationKey;
          } else {
              window.top.location = urlError + "?Error=" + response.ResultMessage;
          }
          */

        if (!response.AuthorizationKey) {
            window.location.href = document.location.origin + urlErrorTimeout + "?msg=" + response.ResultMessage;
        } else {
            window.location.href = document.location.origin + urlSuccessRedirect + <?php echo $id_decode ?> +"&Answer=" + response.AuthorizationKey;
        }

    }

    window.initLoading = function () {
        console.log("init");
        cardImage(medioDePago);
        tpformJquery("#codigoSeguridadLbl").show();
        /*
        tpformJquery("#codigoSeguridadLbl").html("");
        tpformJquery("#peibox").hide();
        */
    };

    window.stopLoading = function () {
        console.log('Stop loading...');
        tpformJquery("#peibox").hide();
        if (tpformJquery('#peiLbl').is(':empty')) {
            tpformJquery("#peibox").hide("fast");
            tpformJquery("#peiCbx").prop("checked", false);
        } else {
            $("label > p").each(function() {
                var clean_strip = $(this).text().replace("<br>","");
                $(this).html(clean_strip);
            });


            tpformJquery("#peibox").show("slow");
            activateSwitch(getInitialPEIState());
            switchPei = tpformJquery("#switch-pei");
            switchPei.css("display", "block");
        }
    };

    // Verifica que el usuario no haya puesto para solo pagar con PEI y actúa en consecuencia
    function activateSwitch(soloPEI) {
        readPeiCbx();


        var peiCbx = tpformJquery("#peiCbx");
        if (soloPEI===false) {
            tpformJquery("#switch-pei").click(function () {
                console.log("CHECKED", peiCbx.prop("checked"));


                if (peiCbx.prop("checked") === false) {
                    peiCbx.prop("checked", true);


                    switchPei.prop("checked", false);
                    peiCbx.prop("checked", false); 
                    sliderText.text("NO");
                    sliderText.css('transform', 'translateX(24px)'); 


                } else {
                    peiCbx.prop("checked", false);


                    switchPei.prop("checked", true);
                    peiCbx.prop("checked", true);  
                    sliderText.text("SÍ");
                    sliderText.css('transform', 'translateX(3px)');                     
                }



            });
        }
    }

    function getInitialPEIState() {
        return (tpformJquery("#peiCbx").is(":disabled"));
    }

    tpformJquery('#peiLbl').bind("DOMSubtreeModified", function () {
        tpformJquery("#peibox").hide();
    });

    function ignite() {
        /************* CONFIGURACION DEL API ************************/
        window.TPFORMAPI.hybridForm.initForm({
            callbackValidationErrorFunction: 'validationCollector',
            callbackBilleteraFunction: 'billeteraPaymentResponse',
            callbackCustomSuccessFunction: 'customPaymentSuccessResponse',
            callbackCustomErrorFunction: 'customPaymentErrorResponse',
            botonPagarId: 'btn_ConfirmarPago',
            botonPagarConBilleteraId: 'btn_Billetera',
            modalCssClass: 'modal-class',
            modalContentCssClass: 'modal-content',
            beforeRequest: 'initLoading',
            afterRequest: 'stopLoading'
        });

        /************* SETEO UN ITEM PARA COMPRAR ************************/
        window.TPFORMAPI.hybridForm.setItem({
            publicKey: security,
            defaultNombreApellido: completeName,
            defaultMail: mail,
            defaultTipoDoc: defDniType
        });
    }


    function inicializarMensajesError() {
        console.log('inicializarMensajesError');


        document.getElementById('emailLbl').innerHTML = '';
        document.getElementById('nroDocLbl').innerHTML = '';
        document.getElementById('nombreLbl').innerHTML = '';
        document.getElementById('codigoSeguridadLbl').innerHTML = '';
        document.getElementById('fechaLbl').innerHTML = '';


        arrMensajesError = [];

    }

    function validar_cvv(){
        console.log($('#codigoSeguridadTxt').val().length + ' cvv');
        console.log('maxlength = ' + $('#codigoSeguridadTxt').attr('maxlength') );

        var max = $('#codigoSeguridadTxt').attr('maxlength');

        if($('#codigoSeguridadTxt').val().length == max ){
            $('#codigoSeguridadLbl').hide();
        }else{
            $('#codigoSeguridadLbl').show();
        }
    }

    function inicializar_campos(){
        //numeroTarjetaTxt
        if($('#numeroTarjetaTxt').val().length == 0 ){
            console.log('función inicializar_campos');
            $('#mesCbx').find("option:selected").removeAttr("selected");
            $('#anioCbx').find("option:selected").removeAttr("selected");
            $('#tipoDocCbx').find("option:selected").removeAttr("selected");
            $('#codigoSeguridadTxt').val('');
            $('#nroDocTxt').val('');
            $('#nombreTxt').val('');
            $('#emailTxt').val('');
            $('#tp-tarjeta-logo').hide();


            $('#fechaLbl').html('');
            $('#codigoSeguridadLbl').html('');
            $('#nroDocLbl').html('');
            $('#nombreLbl').html('');
            $('#emailLbl').html('');
        }
    }


    function readPeiCbx() {
        if (peiCbx.prop("checked", true)) {
            switchPei.prop("checked", true);
            sliderText.text("SÍ");
            sliderText.css('transform', 'translateX(3px)');
        } else {
            switchPei.prop("checked", true);
            sliderText.text("NO");
            sliderText.css('transform', 'translateX(24px)');
        }
    }     
</script>


<?php
require(DIR_WS_INCLUDES . 'template_bottom.php');
require(DIR_WS_INCLUDES . 'application_bottom.php');
?>
