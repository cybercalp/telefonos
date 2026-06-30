/**
 * Lógica para la gestión de 'Pasar llamadas a' (Secretary)
 */

document.addEventListener('alpine:init', () => {
    Alpine.data('secretaryModal', () => ({
        isOpen: false,
        targetDn: '',
        targetName: '',
        type: 'users', // 'users' o 'contacts'
        searchQuery: '',
        searchResults: [],
        isLoading: false,

        init() {
            window.addEventListener('open-secretary-modal', (e) => {
                this.targetDn = e.detail.dn;
                this.targetName = e.detail.name;
                this.type = e.detail.type || 'users';
                this.searchQuery = '';
                this.searchResults = [];
                this.isOpen = true;
            });
        },

        async performSearch() {
            if (this.searchQuery.length < 3) {
                this.searchResults = [];
                return;
            }
            this.isLoading = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const response = await fetch(`lib/ldap_search_users.php?q=${encodeURIComponent(this.searchQuery)}&type=${this.type}&csrf_token=${encodeURIComponent(csrfToken || '')}`, {
                    headers: { 'X-CSRF-TOKEN': csrfToken || '' }
                });
                this.searchResults = await response.json();
            } catch (err) {
                console.error("Error buscando:", err);
            } finally {
                this.isLoading = false;
            }
        },

        async addSecretary(secretaryDn) {
            const msg = this.type === 'contacts' 
                ? `¿Desea vincular esta empresa a ${this.targetName}?`
                : `¿Desea añadir a este usuario a la lista de desvío de ${this.targetName}?`;
            
            if (!confirm(msg)) return;
            
            this.isLoading = true;
            try {
                const formData = new FormData();
                formData.append('action', 'add');
                formData.append('target_dn', this.targetDn);
                formData.append('secretary_dn', secretaryDn);
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                if (csrfToken) formData.append('csrf_token', csrfToken);

                const response = await fetch('lib/ldap_manage_secretary.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    // Capturar valores antes de cerrar el modal (el contexto Alpine puede desmontarse)
                    const targetDn = this.targetDn;
                    const type = this.type;
                    this.isOpen = false; // Cierre instantáneo del modal para máxima responsividad
                    // Esperar 1000ms para asegurar la indexación/propagación en Active Directory
                    setTimeout(() => {
                        refreshSecretaryList(targetDn, type);
                    }, 1000);
                } else {
                    this.isLoading = false;
                    alert("Error: " + result.message);
                }
            } catch (err) {
                console.error("Error en AJAX:", err);
                alert("Excepción en AJAX (añadir): " + err.message);
            } finally {
                this.isLoading = false;
            }
        }
    }));
});

/**
 * Función global para quitar secretarios (llamada desde onclick en ldap_showresults)
 */
async function manageSecretary(action, targetDn, secretaryDn, type = 'users') {
    if (action === 'remove') {
        if (!confirm("¿Está seguro de que desea eliminar este vínculo?")) return;
    }

    try {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('target_dn', targetDn);
        formData.append('secretary_dn', secretaryDn);
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrfToken) formData.append('csrf_token', csrfToken);

        const response = await fetch('lib/ldap_manage_secretary.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            // Esperar 1000ms para asegurar la indexación/propagación en Active Directory
            setTimeout(() => {
                refreshSecretaryList(targetDn, type);
            }, 1000);
        } else {
            alert("Error: " + result.message);
        }
    } catch (err) {
        console.error("Error en AJAX:", err);
        alert("Excepción en AJAX (quitar): " + err.message);
    }
}

/**
 * Refresca dinámicamente la lista de secretarios para un usuario/contacto mediante AJAX.
 * Aplica una micro-animación de transición y re-inicializa Sortable (Drag & Drop).
 */
async function refreshSecretaryList(targetDn, type = 'users') {
    // Búsqueda iterativa en lugar de CSS selector para evitar problemas con DNs
    // que contienen caracteres especiales de CSS (\\, =, ,)
    const allLists = document.querySelectorAll('.sortable-secretary-list');
    let listEl = null;
    for (const el of allLists) {
        if (el.getAttribute('data-target-dn') === targetDn) {
            listEl = el;
            break;
        }
    }
    if (!listEl) {
        console.warn(`No se encontró el contenedor de lista para target_dn: ${targetDn}`);
        return;
    }

    // Feedback táctil: Opacidad y transición suaves
    listEl.classList.add('opacity-40', 'pointer-events-none', 'transition-opacity', 'duration-300');

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const response = await fetch(`lib/ldap_get_secretary_html.php?target_dn=${encodeURIComponent(targetDn)}&type=${type}&csrf_token=${encodeURIComponent(csrfToken)}`);
        
        if (!response.ok) {
            throw new Error(`Error de servidor: ${response.status}`);
        }
        
        const html = await response.text();
        
        // Actualizamos el DOM parcial
        listEl.innerHTML = html;

        // Re-inicializar Sortable (Drag & Drop) en la lista refrescada
        listEl.sortableLoaded = false;
        if (typeof initSortableSecretaries === 'function') {
            initSortableSecretaries();
        }
    } catch (err) {
        console.error("Error al refrescar la lista dinámicamente:", err);
        alert("Excepción al refrescar la lista de desvío: " + err.message);
    } finally {
        // Restaurar estado visual
        listEl.classList.remove('opacity-40', 'pointer-events-none');
    }
}
