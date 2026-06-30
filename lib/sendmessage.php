<?php
// IMPRIME UN MENSAJE DE ERROR/AVISO EN PANTALLA
function print_message($origin = 'normal') {
   if ((isset($_SESSION['mensaje'])) && (!empty($_SESSION['mensaje']))) {
      if ($origin == 'datos_active') {
//         echo '<div id="alertaaturada">';
//         echo '<div id="alertaaturadames">';
//         echo '<a class="tancar" href="javascript:void(0);" onclick="document.getElementById(\'alertaaturada\').className = \'ocultem\'">Cerrar</a>';
         echo '<div class="modal fade" id="staticBackdrop" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="staticBackdropLabel" aria-hidden="true">';
         echo '<div class="modal-dialog">';
         echo '<div class="modal-content">';
         echo '<div class="modal-header">';
         echo ' <h1 class="modal-title fs-5" id="staticBackdropLabel">Atenci&oacute;n</h1>';
         echo '<button type="button" tabindex="-1" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>';
         echo '</div>';
         echo '<div class="modal-body">';
      }      
      if (isset($_SESSION['mensaje_css'])) {
         if ($_SESSION['mensaje_css'] == 'yes') {
           echo '<div class="alert alert-success" role="alert">';
         } else {
           echo '<div class="alert alert-danger" role="alert">';
	 }
      }else{
         echo '<div class="alert alert-secondary" role="alert">';
      }
      echo '<p>';
      foreach ($_SESSION['mensaje'] as $msg) {
         if (is_array($msg)) {
            foreach ($msg as $submsg) {
               echo htmlspecialchars($submsg) . '<br>';
	    }
	    unset($submsg);
         } else {
            echo htmlspecialchars($msg) . '<br>';
         }
      }
      unset($msg); //La referencia a $value del último elemento del array permanece incluso después del bucle foreach. Se recomienda destruir estas referencias utilizando unset().
      echo'</p></div>';
      if ($origin == 'datos_active') {
//         echo '</div></div>';
         echo '</div>';
         echo '<div class="modal-footer">';
         echo '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>';
         echo '</div></div></div></div>';
      }
   }
   unset($_SESSION['mensaje']);
   unset($_SESSION['mensaje_css']);
}
?>

