$(document).ready(function() {
  let saveClicked = false;

  // Función común para sincronizar con la sesión vía AJAX con callback opcional
  function syncTOTPStatus(isChecked, callback) {
      const content = $('#modalContentTOTP');
      const info = $('#modalInfoInactivo');
      
      if (isChecked) {
          content.fadeIn(300);
          info.fadeOut(200);
      } else {
          content.fadeOut(200);
          info.fadeIn(300);
          // Al desactivar volvemos al estado visual "inactivo"
          updateVisualState(false);
      }

      // Sincronizar con el estado de sesión vía AJAX
      $.ajax({
          url: './lib/settoogle.php',
          type: 'POST',
          data: { 
              accion: 'actualizarToggle',
              estado: isChecked ? 1 : 0 
          },
          success: function(response) {
              console.log('Sesión sincronizada:', response);
              if (typeof callback === 'function') callback();
          }
      });
  }

  function updateVisualState(isActive) {
      const qrReal = $('#totpQRReal');
      const qrPlaceholder = $('#totpQRPlaceholder');
      const statusText = $('#totpStatusText');

      if (isActive) {
          // Al activar: mostrar QR real, ocultar placeholder y actualizar texto
          qrReal.removeClass('hidden border-slate-200 border-slate-600 opacity-50 grayscale').addClass('border-emerald-500');
          qrPlaceholder.addClass('hidden');
          statusText.removeClass('text-slate-400').addClass('text-emerald-500').html('2FA<br>ACTIVO');
      } else {
          // Al desactivar: mostrar placeholder, ocultar real y actualizar texto
          qrPlaceholder.removeClass('hidden');
          qrReal.addClass('hidden');
          statusText.addClass('text-slate-400').removeClass('text-emerald-500').html('2FA<br>INACTIVO');
      }
  }

  // Manejador del switch DIRECTO
  $('#directToggleTOTP').on('click', function(e) {
      e.stopPropagation();
      const isChecked = $(this).is(':checked');
      saveClicked = false;

      if (!isChecked) {
          syncTOTPStatus(false);
      } else {
          syncTOTPStatus(true, function() {
              document.dispatchEvent(new CustomEvent('open-totp-modal'));
          });
      }
  });

  // Al cerrar el modal, si no se pulsó Guardar, volvemos atrás el switch
  document.addEventListener('hidden-totp-modal', function () {
      const switchEl = $('#directToggleTOTP');
      const originalState = switchEl.data('original-state') == 1;
      
      if (!saveClicked && switchEl.is(':checked') && !originalState) {
          switchEl.prop('checked', false);
          syncTOTPStatus(false);
      }
  });

  // Botón "Guardar cambios" del modal
  $('#btnSaveTOTP').on('click', function() {
      saveClicked = true;
      
      // AL GUARDAR: Refrescamos visualmente la ficha principal
      updateVisualState(true);
      
      document.dispatchEvent(new CustomEvent('close-totp-modal'));
  });
});




