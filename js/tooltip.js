document.addEventListener('DOMContentLoaded', () => {
  // Inicialización global de tooltips de Bootstrap con soporte HTML
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el, {
      trigger: 'hover focus',
      html: true // Importante para renderizar las etiquetas en el tooltip
    });
  });
});

document.addEventListener('DOMContentLoaded', () => {
  // Regex estándar para validación de formato de email
  const emailRegex = /^[a-zA-Z0-9._%+-]+ @[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
  // Nota: eliminamos el espacio en el regex si es accidental, pero usaremos uno robusto:
  const standardEmailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

  const emailInput = document.getElementById('txtEmailAddress');
  if (emailInput !== null) {
    emailInput.addEventListener('input', () => {
      const val = emailInput.value.trim().toLowerCase();
      if (val === '') {
        emailInput.setCustomValidity('');
        return;
      }

      const corpDomains = JSON.parse(emailInput.getAttribute('data-corp-domains') || '["ajcalp.es"]');
      
      // 1. Validar formato general
      if (!standardEmailRegex.test(val)) {
        emailInput.setCustomValidity('Introduce una dirección de correo válida (ej: usuario@dominio.es)');
      } 
      // 2. Validar que sea un dominio corporativo
      else {
        const isValidDomain = corpDomains.some(domain => val.endsWith('@' + domain.toLowerCase()));
        if (!isValidDomain) {
          emailInput.setCustomValidity('El correo corporativo debe pertenecer a uno de estos dominios: ' + corpDomains.join(', '));
        } else {
          emailInput.setCustomValidity('');
        }
      }
    });
  }

  const emailRestoreInput = document.getElementById('txtEmailRestore');
  if (emailRestoreInput !== null) {
    emailRestoreInput.addEventListener('input', () => {
      const val = emailRestoreInput.value.trim().toLowerCase();
      if (val === '') {
        emailRestoreInput.setCustomValidity('');
        return;
      }

      const corpDomains = JSON.parse(emailRestoreInput.getAttribute('data-corp-domains') || '["ajcalp.es"]');
      
      // 1. Validar formato general
      if (!standardEmailRegex.test(val)) {
        emailRestoreInput.setCustomValidity('Introduce una dirección de correo válida');
      }
      // 2. Validar que NO sea un dominio corporativo
      else {
        const isCorpDomain = corpDomains.some(domain => val.endsWith('@' + domain.toLowerCase()));
        if (isCorpDomain) {
          emailRestoreInput.setCustomValidity('No utilices un correo corporativo (' + corpDomains.join(', ') + ') para la recuperación');
        } else {
          emailRestoreInput.setCustomValidity('');
        }
      }
    });
  }
});
