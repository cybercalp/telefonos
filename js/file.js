let glb_cropper;
$(document).ready(function () {
   

   // Abrir modal de gestión de foto
   $('#btnPhotoChange').on('click', function () {
      showStep('selection');
      window.dispatchEvent(new CustomEvent('open-crop-modal'));
      document.dispatchEvent(new CustomEvent('open-crop-modal'));
   });

   // Manejar el input local del modal para evitar bloqueos del navegador
   $(document).on('change', '#modalFileSelect', function (e) {
      const files = e.target.files;
      if (files.length > 0) {
         $('#txtPhoto')[0].files = files;
         $('#txtPhoto').trigger('change');
         $(this).val('');
      }
   });

   // Drag & Drop en el Modal
   const $dropZoneModal = $('#dropZoneModal');

   $dropZoneModal.on('dragover dragenter', function (e) {
      e.preventDefault();
      e.stopPropagation();
      $(this).addClass('drop-zone-active');
   });

   $dropZoneModal.on('dragleave dragend drop', function (e) {
      e.preventDefault();
      e.stopPropagation();
      $(this).removeClass('drop-zone-active');
   });

   $dropZoneModal.on('drop', function (e) {
      const files = e.originalEvent.dataTransfer.files;
      if (files.length > 0) {
         $('#txtPhoto')[0].files = files;
         $('#txtPhoto').trigger('change');
      }
   });

   // Click en el Avatar de la ficha para abrir modal directamente
   $('#avatarContainer').on('click', function () {
      showStep('selection');
      window.dispatchEvent(new CustomEvent('open-crop-modal'));
      document.dispatchEvent(new CustomEvent('open-crop-modal'));
   });

   // Drag & Drop en el Avatar de la ficha (también abre el modal directamente)
   $('#avatarContainer').on('dragover dragenter', function (e) {
      e.preventDefault();
      e.stopPropagation();
   });

   $('#avatarContainer').on('drop', function (e) {
      e.preventDefault();
      e.stopPropagation();
      const files = e.originalEvent.dataTransfer.files;
      if (files.length > 0) {
         $('#txtPhoto')[0].files = files;
         showStep('selection');
         window.dispatchEvent(new CustomEvent('open-crop-modal'));
         document.dispatchEvent(new CustomEvent('open-crop-modal'));
         $('#txtPhoto').trigger('change');
      }
   });

   // Botón Volver a Cargar
   $('#btnBackToUpload').on('click', function() {
       showStep('selection');
       resetCropper();
   });

   // Eliminar foto
   $('#btnPhotoDel').on('click', function () {
      if (confirm('¿Estás seguro de que deseas eliminar la foto de perfil?')) {
          $('#imgPhotoUser').attr('src', './images/users.jpg').addClass('opacity-50 grayscale');
          $('#txtThumbnailPhoto, #croppedPhoto').val('');
          mostrarMensaje('Foto eliminada. Pulsa "Guardar Cambios" para confirmar.', 'info');
      }
   });

    // Al seleccionar archivo (usando delegación de eventos para máxima fiabilidad en el DOM)
    $(document).on('change', '#txtPhoto', function (e) {
       const archivo = e.target.files[0];
       if (!archivo) {
          return;
       }

       // Si no tiene tipo MIME por ser una extensión alterada o error de registro en Windows,
       // intentamos deducirla de la extensión del nombre del archivo como fallback seguro
       let esValido = false;
       if (archivo.type && archivo.type.match(/^image\/(jpeg|jpg|png)$/)) {
          esValido = true;
       } else {
          const extension = archivo.name.split('.').pop().toLowerCase();
          if (['jpg', 'jpeg', 'png'].includes(extension)) {
             esValido = true;
          }
       }

       if (!esValido) {
          mostrarMensaje('Formato inválido. Solo JPG o PNG.', 'danger', 'alertBoxImageCrop');
          return;
       }

       const lector = new FileReader();
       lector.onload = function (event) {
         $('#cropImage').attr('src', event.target.result);
         
         // Reset sliders
         $('#rangeBrightness').val(100);
         $('#rangeContrast').val(100);
         $('#brightnessVal').text('100%');
         $('#contrastVal').text('100%');

         showStep('edit');
         
         // Limpiamos el valor del input file para que si vuelven a seleccionar el mismo archivo se dispare el evento change sin problemas
         $('#txtPhoto').val('');
       };
       lector.onerror = function(err) {
         mostrarMensaje('Error al leer el archivo.', 'danger', 'alertBoxImageCrop');
         $('#txtPhoto').val('');
       };
       lector.readAsDataURL(archivo);
    });

   function showStep(step) {
       if (step === 'selection') {
           $('#stepSelection').show().removeClass('hidden');
           $('#stepEdit').hide().addClass('hidden');
           $('#btnBackToUpload').hide().addClass('hidden');
           $('#btnCrop').hide().addClass('hidden');
           $('#modalPhotoTitle').text('Seleccionar Fotografía');
       } else {
           $('#stepSelection').hide().addClass('hidden');
           $('#stepEdit').show().removeClass('hidden');
           $('#btnBackToUpload').show().removeClass('hidden');
           $('#btnCrop').show().removeClass('hidden');
           $('#modalPhotoTitle').text('Ajustar y Recortar');
           
           // Inicializar cropper con delay para asegurar visibilidad
           setTimeout(initCropper, 300);
       }
   }

    function initCropper() {
       try {
          if (typeof Cropper === 'undefined') {
             console.error("La librería Cropper.js no está cargada.");
             return;
          }
          
          if (glb_cropper) {
             glb_cropper.destroy();
          }
 
          glb_cropper = new Cropper(document.getElementById('cropImage'), {
               viewMode: 0,        // Sin restricciones: el cuadro puede salir fuera de la imagen
               dragMode: 'move',
               autoCropArea: 1,
               restore: false,
               zoomOnWheel: true,
               cropBoxMovable: true,
               cropBoxResizable: true,
               ready: function() {
                    const cropper = this.cropper;
                    const img  = cropper.getImageData();
                    const natW = img.naturalWidth;
                    const natH = img.naturalHeight;
                    // El cuadrado 1:1 basado en la dimensión MAYOR
                    const side = Math.max(natW, natH);
                    // x/y en píxeles originales; puede ser negativo (fuera del borde)
                    const x = (natW - side) / 2;
                    const y = (natH - side) / 2;
 
                    // setData sin ningún aspect ratio activo para que no achique el cuadro
                    cropper.setData({ x, y, width: side, height: side });
 
                    updateFilters();
               }
          });
       } catch (err) {
          console.error("Excepción en initCropper:", err);
       }
    }


   function resetCropper() {
       if (glb_cropper) {
           glb_cropper.destroy();
           glb_cropper = null;
       }
       $('#txtPhoto').val('');
   }

   // Sliders de Brillo y Contraste
   $('#rangeBrightness, #rangeContrast').on('input', function() {
       updateFilters();
       const val = $(this).val() + '%';
       if ($(this).attr('id') === 'rangeBrightness') $('#brightnessVal').text(val);
       else $('#contrastVal').text(val);
   });

   function updateFilters() {
       const b = $('#rangeBrightness').val();
       const c = $('#rangeContrast').val();
       const filterStr = `brightness(${b}%) contrast(${c}%)`;
       $('.cropper-container .cropper-canvas img, .cropper-container .cropper-view-box img').css('filter', filterStr);
   }

   // Aplicar Recorte y Guardar
   $('#btnCrop').on('click', function () {
      if (!glb_cropper) return;

      const brightness = $('#rangeBrightness').val();
      const contrast = $('#rangeContrast').val();

      const rawCanvas = glb_cropper.getCroppedCanvas({
        width: 100,
        height: 100,
        // Eliminamos fillColor para permitir transparencia (formato PNG)
        imageSmoothingQuality: 'high'
      });

      const finalCanvas = document.createElement('canvas');
      finalCanvas.width = 100;
      finalCanvas.height = 100;
      const ctx = finalCanvas.getContext('2d');

      ctx.filter = `brightness(${brightness}%) contrast(${contrast}%)`;
      ctx.drawImage(rawCanvas, 0, 0, 100, 100);

      finalCanvas.toBlob(function (blob) {
        if (blob.size > 100 * 1024) {
          mostrarMensaje('El resultado supera los 100KB. Reduce el brillo o ajusta el recorte.', 'danger', 'alertBoxImageCrop');
          return;
        }

        const reader = new FileReader();
        reader.onloadend = function () {
            const base64data = reader.result;
            $('#imgPhotoUser').attr('src', base64data).removeClass('opacity-50 grayscale');
            $('#txtThumbnailPhoto, #croppedPhoto').val(base64data);
            
            window.dispatchEvent(new CustomEvent('close-crop-modal'));
            document.dispatchEvent(new CustomEvent('close-crop-modal'));
            mostrarMensaje('Foto preparada con éxito.', 'success');
        };
        reader.readAsDataURL(blob);
      }, 'image/png'); // Cambiamos a PNG para soportar transparencia
   });

   document.addEventListener('hidden-crop-modal', function () {
      resetCropper();
   });
});

function mostrarMensaje(texto, tipo = 'danger', form = 'alertBoxImageChange') {
  const $mensaje = $('#' + form);
  $mensaje
    .removeClass('d-none alert-success alert-danger alert-warning alert-info')
    .addClass('alert alert-' + tipo)
    .text(texto)
    .fadeIn(300);

  setTimeout(() => {
    $mensaje.fadeOut(300, function () {
      $(this).addClass('d-none').show().text('');
    });
  }, 5000);
}
