document.addEventListener('DOMContentLoaded', function() {
    // Función para reordenar secretarios (Drag & Drop)
    function initSortableSecretaries() {
        const lists = document.querySelectorAll('.sortable-secretary-list');
        lists.forEach(el => {
            if (el.sortableLoaded) return;
            el.sortableLoaded = true;

            new Sortable(el, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'bg-amber-100/50',
                onEnd: function (evt) {
                    const targetDn = el.getAttribute('data-target-dn');
                    const items = el.querySelectorAll('.secretary-item');
                    const newOrder = Array.from(items).map(item => item.getAttribute('data-dn'));

                    reorderSecretary(targetDn, newOrder);
                }
            });
        });
    }

    function reorderSecretary(targetDn, newOrder) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fetch('./lib/ldap_reorder_secretary.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                'target_dn': targetDn,
                'new_order': JSON.stringify(newOrder),
                'csrf_token': csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('Error al reordenar: ' + data.message);
                location.reload();
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            alert('Error de conexión al intentar reordenar.');
        });
    }

    // Inicializar al cargar y después de cualquier cambio ajax si aplica
    initSortableSecretaries();
    // Si Alpine.js o algún otro script inyecta HTML, podrías necesitar llamarlo de nuevo
    document.addEventListener('alpine:initialized', function() {
        initSortableSecretaries();
    });

    // === Global HTML Tooltip ===
    const tip = document.getElementById('global-html-tooltip');
    if (!tip) return;
    let activeTrigger = null;

    document.addEventListener('mouseenter', function(e) {
        const el = e.target.closest('[data-html-tooltip]');
        if (!el) return;
        activeTrigger = el;
        tip.innerHTML = el.getAttribute('data-html-tooltip');
        tip.style.display = 'block';
        const r = el.getBoundingClientRect();
        tip.style.left = (r.left + r.width / 2) + 'px';
        tip.style.top  = (r.bottom + 10) + 'px';
    }, true);

    document.addEventListener('mouseleave', function(e) {
        const el = e.target.closest('[data-html-tooltip]');
        if (el && el === activeTrigger) {
            tip.style.display = 'none';
            activeTrigger = null;
        }
    }, true);
});

// Global function for onclick handlers in ldap_showresults.php
window.unassignComputerPhone = function(userSam, computerName) {
    if (!confirm('¿Desasignar el equipo ' + computerName + ' del usuario ' + userSam + '?')) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const formData = new FormData();
    formData.append('target_sam', userSam);
    formData.append('csrf_token', csrfToken);

    fetch('./lib/ldap_unassign_user_computer.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Error al desasignar: ' + data.message);
        } else {
            location.reload();
        }
    })
    .catch(err => {
        console.error('Fetch error:', err);
        alert('Error de conexión al intentar desasignar.');
    });
};
