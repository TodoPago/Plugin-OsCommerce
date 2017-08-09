<?php
/*
  $Id$
  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com
  Copyright (c) 2013 osCommerce
  Released under the GNU General Public License
*/
require('includes/application_top.php');
//require_once dirname(__FILE__).'/../includes/modules/payment/todopagoplugin/includes/TodoPago/lib/Sdk.php';
require_once dirname(__FILE__).'/../includes/modules/payment/todopagoplugin/includes/vendor/autoload.php';
require_once dirname(__FILE__).'/../includes/modules/payment/todopagoplugin/includes/todopago_ctes.php';
require(DIR_WS_INCLUDES . 'template_top.php');
$mensaje ="";
//valida y guarda codigo de autorizacion
if (isset($_POST["authorization"]) && isset($_POST["submit"])){
    $autorization_post = str_replace('\"', '"', $_POST["authorization"]);
    if(json_decode($autorization_post) == NULL) {
        //armo json de autorization
        $autorizationId = new stdClass();
        $autorizationId->Authorization = $_POST["authorization"];
        $_POST["authorization"] = json_encode($autorizationId);
    }

    if (isset($_POST["maxinstallments_enabled"])){
         $_POST["maxinstallments_enabled"] = ($_POST["maxinstallments_enabled"] == true)? 1:0;
    }else $_POST["maxinstallments_enabled"] = 0;
    if (isset($_POST["timeout_enabled"])){
         $_POST["timeout_enabled"] = ($_POST["timeout_enabled"] == true)? 1:0;
    }else $_POST["timeout_enabled"] = 0;
    if (isset($_POST["emptycart_enabled"])){
         $_POST["emptycart_enabled"] = ($_POST["emptycart_enabled"] == true)? 1:0;
    }else $_POST["emptycart_enabled"] = 0;
    unset($_POST["submit"]);

    $query = "update todo_pago_configuracion set ";
    foreach($_POST as $key=>$value){
	if($key == "todopago_timeout") {
		if(empty($value)) {
			$value = 30*60*1000;
		}
		else if($value >= 6*60*60*1000) {
			$value = 6*60*60*1000;
		}
		else if($value <= 5*60*1000) {
			$value = 5*60*1000;
		} else {
			$value = 30*60*1000;
		}
	}
        $query .= $key. "='".$value."',";
    }
    $query = trim($query,",");
    $res = tep_db_query($query);
    $mensaje = "La configuracion se guardo correctamente";
}
//obtengo elementos del formulario
$sql = "select * from todo_pago_configuracion";
$res = tep_db_query($sql);
$row = tep_db_fetch_array($res);
$autorization = json_decode($row['authorization']);

?>
<link rel="stylesheet" type="text/css" href="../includes/modules/payment/todopagoplugin/todopago.css"/>
<!-- DataTables CSS -->
<link rel="stylesheet" type="text/css" href="ext/modules/payment/todopago/stylesheets/jquery.dataTables.css"/>
<!-- jQuery -->
<script type="text/javascript" charset="utf8" src="ext/modules/payment/todopago/javascripts/jquery-1.10.2.min.js"></script>
<script src="ext/modules/payment/todopago/javascripts/jquery-ui.js"></script>
<!-- DataTables -->
<script type="text/javascript" charset="utf8" src="ext/modules/payment/todopago/javascripts/jquery.dataTables.min.js"></script>
<script type="text/javascript" charset="utf8" src="ext/modules/payment/todopago/javascripts/dataTables.tableTools.min.js"></script>

<table border="0" width="100%" cellspacing="0" cellpadding="2">
    <tr>
        <td>
            <table border="0" width="100%" cellspacing="0" cellpadding="2" height="40">
                <tr>
                <td class="pageHeading">TodoPago (v. <?php echo TP_VERSION; ?>) | Configuraci&oacute;n </td>
                <td align="right"></td>
                <td class="smallText" align="right"></td>
                </tr>
                <tr>
                <td colspan="3">
                <img src="http://www.todopago.com.ar/sites/todopago.com.ar/files/pluginstarjeta.jpg" />
                </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td><?php echo $mensaje;?></td>
    </tr>
    <tr>
        <td>
            <div id="todopago">
                <ul class="secciones-todopago-config" >
                    <li><a class="tabs-todopago" todopago="#config">Configuracion</a></li>
                    <li><a class="tabs-todopago" todopago="#prod">Productos</a></li>
                    <li><a class="tabs-todopago" todopago="#orden">Ordenes</a></li>
                </ul>
                <div id="config">
                    <button id="btn-credentials" class="btn-config-credentials" >Obtener credenciales</button>
                    <div id="credentials-login" class="order-action-popup" style="display:none;">
                        <img src="http://www.todopago.com.ar/sites/todopago.com.ar/files/logo.png">
                        <p>Ingresa con tus credenciales para Obtener los datos de configuración</p>

                            <label class="control-label">E-mail</label>
                            <input id="mail" class="form-control" name="mail" type="email" value="" placeholder="E-mail"/>
                            <label class="control-label">Contrase&ntilde;a</label>
                            <input id="pass" class="form-control" name="pass" type="password" value="" placeholder="Contrase&ntilde;a"/>
                            <button id="btn-form-credentials" style="margin:20%;" class="btn-config-credentials" >Acceder</button>

                    </div>
                    <style>

                    </style>

                    <script type="text/javascript">
                        $("#btn-credentials").click(function(){
                            $( "#credentials-login" ).dialog();
                        });
                        $("#btn-form-credentials").click(function(){
                            console.log('obtengo credenciales por ajax.');
                            $.post( "todopago_credentials.php",
                                    { mail: $("#mail").val(),
                                      pass: $("#pass").val(),
				      amb:  $("#amb").val()
                                    }, function(data){
                                        var obj = jQuery.parseJSON( data );
                                        if (obj.error_message != '0'){
                                            console.log(obj.error_message);
                                            alert(obj.error_message);
                                        }else{
                                            $('input:text[name=authorization]').val(obj.Authorization);
                                            if (obj.ambiente == 'test'){
                                                $('input:text[name=test_merchant]').val(obj.merchantId);
                                                $('input:text[name=test_security]').val(obj.apiKey);
                                            }else{
                                                $('input:text[name=production_merchant]').val(obj.merchantId);
                                                $('input:text[name=production_security]').val(obj.apiKey);
                                            }
                                        }
                                    }
                            );
                            $("#credentials-login").dialog('close');
                        });
                    </script>


                    <form id="form" action="" method="post">
                        <div class="input-todopago">
                            <label>Authorization HTTP (c&oacute;digo de autorizacion)</label>
                            <input type="text" value='<?php echo  (isset($autorization->Authorization)? $autorization->Authorization:"")?>' placeholder="Authorization HTTP" name="authorization"/>
                        </div>
                        <?php
                        $segmento = (isset($row["segmento"])?$row["segmento"]:"");
                        ?>
                        <div class="input-todopago">
                            <label>Segmento del Comercio</label>
                            <select name="segmento">
                                <option value="">Seleccione</option>
                                <option value="retail" <?php echo ($segmento=="retail"?"selected":"")?>>Retail</option>
                                <!--<option value="ticketing" <?php echo ($segmento=="ticketing"?"selected":"")?>>Ticketing</option>
                                <option value="services" <?php echo ($segmento=="services"?"selected":"")?>>Services</option>
                                <option value="digital" <?php echo ($segmento=="digital"?"selected":"")?>>Digital Goods</option>-->
                            </select>
                        </div>
                        <?php
                        $canal = (isset($row["canal"])?$row["canal"]:"");
                        ?>

                        <!--<div class="input-todopago">
                         <label>Canal de Ingreso del Pedido</label>
                        <select name="canal">
                        <option value="">Seleccione</option>
                        <option value="web" <?php echo ($canal=="web"?"selected":"")?>>Web</option>
                        <option value="mobile" <?php echo ($canal=="mobile"?"selected":"")?>>Mobile</option>
                        <option value="telefonica" <?php echo ($canal=="telefonica"?"selected":"")?>>Telefonica</option>
                        </select>
                        </div>-->
                        <?php
                        $ambiente = (isset($row["ambiente"])?$row["ambiente"]:"");
                        ?>
                        <div class="input-todopago">
                            <label>Modo Desarrollo o Producci&oacute;n</label>
                            <select name="ambiente" id="amb">
                            <option value="">Seleccione</option>
                            <option value="test" <?php echo ($ambiente=="test"?"selected":"")?>>Desarrollo</option>
                            <option value="production" <?php echo ($ambiente=="production"?"selected":"")?>>Producci&oacute;n</option>
                            </select>
                        </div>
                        <div class="input-todopago">
                            <label>Dead Line</label>
                            <input type="text" value="<?php echo  (isset($row["deadline"])?$row["deadline"]:"")?>" placeholder="Dead Line" name="deadline"/>
                        </div>

                        <div class="subtitulo-todopago">AMBIENTE DESARROLLO</div>
                        <div class="input-todopago">
                            <label>ID Site Todo Pago (Merchant ID)</label>
                            <input type="text" value="<?php echo  (isset($row["test_merchant"])?$row["test_merchant"]:"")?>" placeholder="ID Site Todo Pago" name="test_merchant"/>
                        </div>
                        <div class="input-todopago">
                            <label>Security Code (Key sin PRISMA/TOD.. ni espacio)</label>
                            <input type="text" value="<?php echo  (isset($row["test_security"])?$row["test_security"]:"")?>" placeholder="Security Code" name="test_security"/>
                        </div>

                        <div class="subtitulo-todopago">AMBIENTE PRODUCCION</div>
                        <div class="input-todopago">
                            <label>ID Site Todo Pago (Merchant ID)</label>
                            <input type="text" value="<?php echo  (isset($row["production_merchant"])?$row["production_merchant"]:"")?>" placeholder="ID Site Todo Pago" name="production_merchant"/>
                        </div>
                        <div class="input-todopago">
                            <label>Security Code (Key sin PRISMA/TOD.. ni espacio)</label>
                            <input type="text" value="<?php echo  (isset($row["production_security"])?$row["production_security"]:"")?>" placeholder="Security Code" name="production_security"/>
                        </div>

                        <div class="subtitulo-todopago">ESTADOS DE LA ORDEN</div>
                        <div class="input-todopago">
                            <?php
                            $sql = "select  orders_status_id,orders_status_name from ".TABLE_ORDERS_STATUS. " where language_id = 1";
                            $res = tep_db_query($sql);
                            while ($row1 = tep_db_fetch_array($res)){
                                $opciones[$row1["orders_status_id"]] = $row1["orders_status_name"];
                            }
                            ?>
                            <label>Estado cuando la transaccion ha sido iniciada</label>
                            <select name="estado_inicio">
                                <?php
                                foreach($opciones as $key=>$value){
                                    $selected = "";
                                    if ($key == $row["estado_inicio"]) $selected ="selected"
                                ?>
                                    <option <?php echo $selected?> value="<?php echo $key?>"><?php echo $value?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>

                        <div class="input-todopago">
                            <label>Estado cuando la transaccion ha sido aprobada</label>
                            <select name="estado_aprobada">
                                <?php
                                foreach($opciones as $key=>$value){
                                     $selected = "";
                                    if ($key == $row["estado_aprobada"]) $selected ="selected"
                                ?>
                                    <option <?php echo $selected?> value="<?php echo $key?>"><?php echo $value?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>

                        <div class="input-todopago">
                            <label>Estado cuando la transaccion ha sido rechazada</label>
                            <select name="estado_rechazada">
                                <?php
                                foreach($opciones as $key=>$value){
                                     $selected = "";
                                    if ($key == $row["estado_rechazada"]) $selected ="selected"
                                ?>
                                    <option <?php echo $selected?> value="<?php echo $key?>"><?php echo $value?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>

                        <div class="input-todopago">
                            <label>Estado cuando la transaccion ha sido offline</label>
                            <select name="estado_offline">
                                <?php
                                foreach($opciones as $key=>$value){
                                     $selected = "";
                                    if ($key == $row["estado_offline"]) $selected ="selected"
                                ?>
                                    <option <?php echo $selected?> value="<?php echo $key?>"><?php echo $value?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>

                        <div class="subtitulo-todopago">FORMULARIO DE PAGO</div>
                        <div class="input-todopago">
                            <label style="float:left;">Seleccion el tipo de formulario de pago</label>
                            <div style="float:left;">
                                <div style="margin-bottom:8px;"><input type="radio" name="tipo_formulario" value="0" <?php echo ($row['tipo_formulario'] == 0)?'checked="checked"' :'' ?> >Formulario externo<br></div>
                                <div><input type="radio" name="tipo_formulario" value="1" <?php echo ($row['tipo_formulario'] == 1)?'checked="checked"' :'' ?> >Formulario integrado al e-commerce</div>
                            </div>
                            <div style="clear:both;"></div>
                        </div>
                        <br><br>


                        <div class="input-todopago">
                            <label style="float:left;">Cantidad máxima de cuotas que ofrecerá el formulario</label>
                            <div style="float:left;">
                                <select name="maxinstallments">
                                    <?php
                                    for( $i=1 ; $i <= 12 ; $i++){
                                         $selected = "";
                                        if ($i == $row["maxinstallments"]) $selected ="selected"
                                        ?>
                                        <option <?php echo $selected?> value="<?php echo $i?>"><?php echo $i ?></option>
                                        <?php
                                    } // ENDFOR
                                    ?>
                                </select>
                            </div>
                            <div style="clear:both;"></div>
                        </div>
                        <div class="input-todopago">
                            <label style="float:left;">Habilitar cantidad máxima de cuotas</label>
                            <div style="float:left;">
                                <div style="margin-bottom:8px;">
                                    <input type="checkbox" name="maxinstallments_enabled" <?php echo ($row['maxinstallments_enabled'] == 1)?' checked="checked"' :'' ?> >Habilitar<br>
                                </div>
                            </div>
                            <div style="clear:both;"></div>
                        </div>
                        <div class="input-todopago">
                            <label>Timeout</label>
                            <input type="number" value="<?php echo  (isset($row["todopago_timeout"])?$row["todopago_timeout"]:"")?>" placeholder="" name="todopago_timeout"/>
                        </div>
                        <div class="input-todopago">
                            <label style="float:left;">Activar timeout</label>
                            <div style="float:left;">
                                <div style="margin-bottom:8px;">
                                    <input type="checkbox" name="timeout_enabled" <?php echo ($row['timeout_enabled'] == 1)?' checked="checked"' :'' ?> >Habilitar<br>
                                </div>
                            </div>
                            <div style="clear:both;"></div>
                        </div>
                        <div class="input-todopago">
                            <label style="float:left;">Vaciar carrito</label>
                            <div style="float:left;">
                                <div style="margin-bottom:8px;">
                                    <input type="checkbox" name="emptycart_enabled" <?php echo ($row['emptycart_enabled'] == 1)?' checked="checked"' :'' ?> >Habilitar<br>
<p>Si esta habilitado, ante un pago fallido se vaciará el carrito de compras.</p>
                                </div>
                            </div>
                            <div style="clear:both;"></div>
                        </div>
                        <br><br>
                        <input  type="submit" name="submit" value="Guardar Datos"/>
                    </form>
                </div>
                <div id="prod">
                    <form action="" method="POST">
                        <table id="data-table"  style="width:100%">
                            <thead>
                                <tr>
                                    <td>ID</td>
                                    <td>Nombre</td>
                                    <td>Codigo de Producto</td>
                                    <td>Fecha Evento</td>
                                    <td>Tipo de Envio</td>
                                    <td>Tipo de Servicio</td>
                                    <td>Tipo de Delivery</td>
                                    <td>Editar</td>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "select p.products_id,pd.products_name,p.products_model from ". TABLE_PRODUCTS. " as p inner join ".TABLE_PRODUCTS_DESCRIPTION." as pd on p.products_id = pd.products_id where language_id=1";
                                $res = tep_db_query($sql);
                                // echo $sql;
                                $i =0;
                                while ($row = tep_db_fetch_array($res)){
                                    $sql = "select * from todo_pago_atributos where product_id=".$row["products_id"];
                                    $res2 = tep_db_query($sql);
                                    $tipoDelivery = "";
                                    $tipoEnvio = "";
                                    $tipoServicio = "";
                                    $codigoProducto = "";
                                    $diasEvento = "";
                                    if ($row2 = tep_db_fetch_array($res2)){
                                        if ($row2["CSITPRODUCTCODE"] != "") $codigoProducto = $row2["CSITPRODUCTCODE"];
                                        if ($row2["CSMDD33"] != "") $diasEvento = $row2["CSMDD33"];
                                        if ($row2["CSMDD34"] != "") $tipoEnvio = $row2["CSMDD34"];
                                        if ($row2["CSMDD28"] != "") $tipoServicio = $row2["CSMDD28"];
                                        if ($row2["CSMDD31"] != "") $tipoDelivery = $row2["CSMDD31"];
                                    }
                                    $i=$row["products_id"];
                                    echo "<tr><td>".$row["products_id"]."</td><td id='nombre".$i."'>".$row["products_name"]."</td><td id='codigo".$i."'>".$codigoProducto."</td><td id='evento".$i."'>".$diasEvento."</td><td id='envio".$i."'>".$tipoEnvio."</td><td id='servicio".$i."'>".$tipoServicio."</td><td id='delivery".$i."'>".$tipoDelivery."</td><td class='editar' id='".$i."'>Editar</td></tr>";
                                }

                                ?>
                            </tbody>
                        </table>
                        <div id="config-producto-todopago">
                            <div class="close-todopago">x</div>
                            <table>
                                <tr>
                                    <td align="center" id="titulo">Titulo del producto</td>
                                </tr>
                                <tr>
                                    <td>
                                        <table>
                                            <tr>
                                                <td>Codigo de Producto</td>
                                                <td>
                                                    <select id="codigo_producto">
                                                        <option value="">- None -</option>
                                                        <option value="adult_content">adult_content</option>
                                                        <option value="coupon">coupon</option>
                                                        <option value="default">default</option>
                                                        <option value="electronic_good" selected="selected">electronic_good</option>
                                                        <option value="electronic_software">electronic_software</option>
                                                        <option value="gift_certificate">gift_certificate</option>
                                                        <option value="handling_only">handling_only</option>
                                                        <option value="service">service</option>
                                                        <option value="shipping_and_handling">shipping_and_handling</option>
                                                        <option value="shipping_only">shipping_only</option>
                                                        <option value="subscription">subscription</option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <?php if($segmento=="ticketing"){ ?>
                                            <tr>
                                                <td>Dias para el Evento</td>
                                                <td>
                                                    <input id="dias_evento" type="text" value="" />
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Tipo de Envio</td>
                                                <td>
                                                    <select id="envio_producto">
                                                        <option value="">- None -</option>
                                                        <option value="Pickup">Pickup</option>
                                                        <option value="Email">Email</option>
                                                        <option value="Smartphone">Smartphone</option>
                                                        <option value="Other">Other</option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <?php } ?>
                                            <?php if($segmento=="services"){ ?>
                                            <tr>
                                                <td>Tipo de Delivery</td>
                                                <td>
                                                    <select id="delivery_producto">
                                                        <option value="">- None -</option>
                                                        <option value="WEB Session">WEB Session</option>
                                                        <option value="Email">Email</option>
                                                        <option value="SmartPhone">SmartPhone</option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Tipo de Servicio</td>
                                                <td>
                                                    <select id="servicio_producto">
                                                        <option value="">- None -</option>
                                                        <option value="Luz">Luz</option>
                                                        <option value="Gas">Gas</option>
                                                        <option value="Agua">Agua</option>
                                                        <option value="TV">TV</option>
                                                        <option value="Cable">Cable</option>
                                                        <option value="Internet">Internet</option>
                                                        <option value="Impuestos">Impuestos</option>
                                                    </select>
                                                </td>
                                            </tr>
                                            <?php } ?>
                                            <tr>
                                                <td colspan="2">
                                                    <input type="hidden" value="" id="id_producto" />
                                                    <input id="guardar" type="button" value="Guardar" />
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <script type="text/javascript">
                            $(document).ready(function(){

                                $('.close-todopago').click(function(){
                                    $('#config-producto-todopago').hide();
                                });

                                $("#guardar").click(function(){

                                    $('#config-producto-todopago').hide();
                                    $.post( "ext/modules/payment/todopago/todo_pago_config_ajax.php",
                                        { CSITPRODUCTCODE:$("#codigo_producto").val(),
                                          CSMDD33: $("#dias_evento").val(),
                                          CSMDD34: $("#envio_producto").val() ,
                                          CSMDD28: $("#servicio_producto").val() ,
                                          CSMDD31: $("#delivery_producto").val() ,
                                          product_id: $("#id_producto").val()
                                        }, function(){

                                            $("#codigo"+$("#id_producto").val()).html($("#codigo_producto").val());
                                            $("#evento"+$("#id_producto").val()).html($("#dias_evento").val());
                                            $("#envio"+$("#id_producto").val()).html($("#envio_producto").val());
                                            $("#delivery"+$("#id_producto").val()).html($("#delivery_producto").val());
                                            $("#servicio"+$("#id_producto").val()).html($("#servicio_producto").val());
                                        }
                                  );

                                });


                                $(".editar").click(function(){

                                    $('#config-producto-todopago').hide();
                                    $('#config-producto-todopago').show();
                                    $("#id_producto").val($(this).attr("id"));
                                    $("#titulo").html($("#nombre"+$(this).attr("id")).html());
                                    $("#codigo_producto").val($("#codigo"+$(this).attr("id")).html());
                                    $("#dias_evento").val($("#evento"+$(this).attr("id")).html());
                                    $("#envio_producto").val($("#envio"+$(this).attr("id")).html());
                                    $("#delivery_producto").val($("#delivery"+$(this).attr("id")).html());
                                    $("#servicio_producto").val($("#servicio"+$(this).attr("id")).html());
                            });
                            $('#data-table').dataTable(
                                            {bFilter: true,
                                            bInfo: true,
                                            bPaginate :true,

                                            });
                              })
                        </script>
                    </form>
                </div>
                <div id="orden">
                    <table id="orders-table"  style="width:100%">
                        <thead>
                            <tr>
                                <td>ID</td>
                                <td>Nombre</td>
                                <td>Telefono</td>
                                <td>Email</td>
                                <td>Fecha</td>
                                <td>Status</td>
                                <td>Devoluci&oacute;n</td>
                                <td>GetStatus</td>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "select orders_id,customers_name,customers_telephone,customers_email_address,date_purchased,orders_status_name from ". TABLE_ORDERS. " as o inner join ". TABLE_ORDERS_STATUS. " as os on os.orders_status_id = o.orders_status order by date_purchased desc";
                            //echo $sql;
                            $res = tep_db_query($sql);
                            // echo $sql;
                            $i =0;
                            while ($row = tep_db_fetch_array($res)){

                             echo "<tr><td>".$row["orders_id"]."</td><td>".$row["customers_name"]."</td><td>".$row["customers_telephone"]."</td><td>".$row["customers_email_address"]."</td><td>".$row["date_purchased"]."</td><td>".$row["orders_status_name"]."</td><td class='refund-td' data-order_id='".$row["orders_id"]."' style='cursor:pointer'>Devolver</td><td class='status' id='".$row["orders_id"]."' style='cursor:pointer'>Ver Status</td></tr>";
                            }

                            ?>
                        </tbody>
                    </table>
                    <div id="status-orders" class="order-action-popup">
                        <div class="close-status-todopago close-todopago">x</div>
                        <div id="status" style="overflow: scroll;max-height:350px">
                        </div>
                    </div>
                    <div id="refund-dialog" class="order-action-popup" hidden="hidden" style="display:block;">
                        <div id="refund-form">
                            <div class="close-refund-todopago close-todopago">x</div>
                            <input type="hidden" id="order-id-hidden" />
                            <label for="refund-type-select">Elija el tipo de devolucion: </label>
                            <select id="refund-type-select" name="refund-type">
                                <option value="total" selected="selected">Total</option>
                                <option value="parcial">Parcial</option>
                            </select>
                            <p id="amount-div" hidden="hidden">
                                <label for="amount-input">Monto: $</label>
                                <input type="number" id="amount-input" name="amount" min=0.01 step=0.01 />
                                <br/>
                                <br/>
                                <i>    * El monto de devolución se calcula en base al costo original del producto sin los impuestos agregados.</i>
                                <span id="invalid-amount-message" style="color: red;"><br />Ingrese un monto</span>
                            </p>
                            <p style="text-align: right;">
                                <button id="refund-button">Devolver</button>
                            </p>
                        </div>
                        <div id="refund-result"></div>
                    </div>
                </div>
            </div>
            <script>
                $(document).ready(function(){
                    $('.close-status-todopago').click(function() {
                        $('#status-orders').hide();
                    });
                    $("#orders-table").on("click", ".status", function status() {
                        $('.order-action-popup').hide();
                        $.post( "ext/modules/payment/todopago/todo_pago_status_ajax.php",
                               { order_id:$(this).attr("id"),
                                             }, function( data ) {
                                    $("#status").html(data);
                                $('#status-orders').show();
                            });
                    });
                    //Devoluciones
                    $(".close-refund-todopago").click(function() {
                        $('#refund-dialog').hide();
                        $('#refund-result').hide();
                    });
                    $("#orders-table").on("click", ".refund-td", function refundTd_click() {
                        $('.order-action-popup').hide();
                        $('#order-id-hidden').val($(this).attr("data-order_id"));
                        $("#refund-result").hide();
                        $("#invalid-amount-message").hide();
                        $("#amount-input").val("");
                        $('#refund-form').show();
                        $('#refund-dialog').show();
                    });
                    $("#refund-type-select").change(function refundTypeSelect_change() {
                        if ($(this).val() == 'parcial') {
                            $("#amount-div").show();
                        } else {
                            $("#amount-div").hide();
                        }
                    });
                    $("#refund-button").click(function refundButton_click() {
                        if (isValidAmount()) {
                            $.post("ext/modules/payment/todopago/todo_pago_devoluciones_ajax.php", {
                                order_id: $("#order-id-hidden").val(),
                                refund_type: $("#refund-type-select").val(),
                                amount: $("#amount-input").val()
                            }, function(response) {
                                var old = document.getElementById("refund-form").innerHTML;
				document.getElementById("refund-form").innerHTML = '<div class="close-todopago">x</div>' + response;
                                $(".close-todopago").click(function(){
				  document.getElementById("refund-form").innerHTML = old;
				  $("#refund-button").click(refundButton_click);
$(".close-todopago").click(function(){
	$("#refund-form").hide();
});
$("#refund-type-select").val("total");$("#amount-div").hide();
$("#refund-type-select").change(function refundTypeSelect_change() {
	if ($(this).val() == 'parcial') {
        	$("#amount-div").show();
        } else {
        	$("#amount-div").hide();
        }
});

                                  $("#refund-form").hide();
                                })
                                $("#refund-result").show();
                            })
                        }
                        else {
                            $("#invalid-amount-message").show();
                        }
                    });
                });
                $(".close-todopago").click(function(){
<<<<<<< HEAD
=======
                  alert("sdfdsefd");
>>>>>>> origin/feature-partial-maxAmount
                  $("#refund-form").hide();
                })
                $('#orders-table').dataTable({
                bFilter: true,
                bInfo: true,
                bPaginate: true,
                });
            </script>
        </td>
    </tr>
</table>
<!--</td>
</tr>
</table>-->
<script>
    $(document).ready(function() {
        $("#prod").hide();
        $("#orden").hide();
        $(".tabs-todopago").each(function() {
            $(this).css("cursor", "pointer");
        })
        $(".tabs-todopago").click(function() {
            $("#config").hide();
            $("#prod").hide();
            $("#orden").hide();
            $("" + $(this).attr("todopago") + "").show();
        })
    });
    function isValidAmount() {
        return (($("#refund-type-select").val() == 'parcial' && !isNaN($("#amount-input").val()) && isFinite($("#amount-input").val()) && $("#amount-input").val() != "") || $("#refund-type-select").val() == 'total');
    }
</script>
</body>
<?php
  require(DIR_WS_INCLUDES . 'application_bottom.php');
?>
<script>
jQuery(document).ready(function() {
  jQuery("input[name='todopago_timeout']").attr('disabled', 'disabled');

    jQuery(function() {
        if (jQuery("input[name='timeout_enabled']").is(':checked')) {
            jQuery("input[name='todopago_timeout']").attr('disabled', false);


        } else {

            jQuery("input[name='todopago_timeout']").attr('disabled', 'disabled');

        }

        if (jQuery("input[name='maxinstallments_enabled']").is(':checked')) {
            jQuery("select[name='maxinstallments']").attr('disabled', false);


        } else {

            jQuery("select[name='maxinstallments']").attr('disabled', 'disabled');

        }


    });

    jQuery("input[name='timeout_enabled']").click(function() {

        if (jQuery("input[name='timeout_enabled']").is(':checked')) {
            jQuery("input[name='todopago_timeout']").attr('disabled', false);


        } else {

            jQuery("input[name='todopago_timeout']").attr('disabled', 'disabled');

        }

    });

    jQuery("input[name='maxinstallments_enabled']").click(function() {
        if (jQuery("input[name='maxinstallments_enabled']").is(':checked')) {
            jQuery("select[name='maxinstallments']").attr('disabled', false);


        } else {

            jQuery("select[name='maxinstallments']").attr('disabled', 'disabled');

        }

    });


});
  </script>
