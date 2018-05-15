<?php
require('includes/application_top.php');
require(DIR_WS_INCLUDES . 'template_top.php');
?>

  <h2>Mensaje</h2>

  <div class="contentText">
     <?php if(isset($_GET["msg"])):?>    
          <h4 style="color: red;"><?php echo $_GET["msg"]; ?></h4>
     <?php else: ?> 
         <h4 style="color: red;">No se ha podido realizar el pago, por favor intente nuevamente.</h4>
     <?php endif;?>
  </div>

<?php
  require(DIR_WS_INCLUDES . 'template_bottom.php');
  require(DIR_WS_INCLUDES . 'application_bottom.php');
?>