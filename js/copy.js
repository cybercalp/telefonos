$(document).ready(function() {
  // Copiar desde el componente compacto (thumbnail)
  $('#copyQR').on('click', function(e) {
    e.preventDefault();
    const texto = $(this).closest('.flex').find('.font-mono').text().trim();
    if (navigator.clipboard && texto) {
      navigator.clipboard.writeText(texto).then(() => {
        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.html('<i class="fas fa-check text-emerald-500"></i>');
        setTimeout(() => { $btn.html(originalHtml); }, 2000);
      });
    }
  });

  // Copiar desde el MODAL (Clave grande)
  $('#copyQRModal').on('click', function(e) {
    e.preventDefault();
    const texto = $(this).closest('div').find('h4').text().trim();
    if (navigator.clipboard && texto) {
      navigator.clipboard.writeText(texto).then(() => {
        const $btn = $(this);
        const originalText = $btn.html();
        $btn.html('¡Copiado! <i class="fas fa-check"></i>').addClass('text-emerald-500');
        setTimeout(() => { $btn.html(originalText).removeClass('text-emerald-500'); }, 2000);
      });
    }
  });
});

