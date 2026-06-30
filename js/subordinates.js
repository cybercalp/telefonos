/**
 * Lógica para la gestión de Subordinados (Dual-List + Drag & Drop)
 */

document.addEventListener('alpine:init', () => {
    Alpine.data('subordinatesModal', () => ({
        isOpen: false,
        targetDn: '',
        targetName: '',
        currentSubordinates: [],
        searchQuery: '',
        searchResults: [],
        subTargetManager: null,
        isLoading: false,
        isSearching: false,
        isAdmin: false,
        
        // Estados de selección
        selectedLeftDns: [], // Array de DNs seleccionados (Buscador)
        lastSelectedIndex: -1, 
        selectedRightDns: [], // Array de DNs seleccionados (Dependientes)
        lastSelectedRightIndex: -1,
        selectedOnRight: null, // Mantenido por compatibilidad legacy si fuera necesario

        // Estado Drag & Drop
        isDragging: false,
        isOverLeft: false,
        isOverRight: false,

        init() {
            window.addEventListener('open-subordinates-modal', (e) => {
                this.targetDn = e.detail.dn;
                this.targetName = e.detail.name;
                this.isAdmin = e.detail.isAdmin || false;
                this.searchQuery = '';
                this.searchResults = [];
                this.selectedLeftDns = [];
                this.lastSelectedIndex = -1;
                this.selectedRightDns = [];
                this.lastSelectedRightIndex = -1;
                this.selectedOnRight = null;
                this.isOpen = true;
                this.loadSubordinates();
            });
        },

        async loadSubordinates() {
            this.isLoading = true;
            try {
                const formData = new FormData();
                formData.append('action', 'list');
                formData.append('target_dn', this.targetDn);
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                if (csrfToken) formData.append('csrf_token', csrfToken);

                const response = await fetch('lib/ldap_manage_subordinates.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    this.currentSubordinates = result.data.sort((a, b) => a.name.localeCompare(b.name));
                    this.subTargetManager = result.manager;
                }
            } catch (err) {
                console.error("Error cargando subordinados:", err);
            } finally {
                this.isLoading = false;
                this.selectedRightDns = [];
                this.lastSelectedRightIndex = -1;
                this.selectedOnRight = null;
            }
        },

        async performSearch() {
            if (this.searchQuery.length < 3) {
                this.searchResults = [];
                this.selectedLeftDns = [];
                this.lastSelectedIndex = -1;
                return;
            }
            this.isSearching = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const response = await fetch(`lib/ldap_search_users.php?q=${encodeURIComponent(this.searchQuery)}&type=hierarchy&csrf_token=${encodeURIComponent(csrfToken || '')}`, {
                    headers: { 'X-CSRF-TOKEN': csrfToken || '' }
                });
                const results = await response.json();
                // Filtrar para evitar bucles: excluir al propio usuario y a su jefe directo
                this.searchResults = results.filter(u => 
                    u.dn !== this.targetDn && 
                    (!this.subTargetManager || u.dn !== this.subTargetManager.dn)
                );
            } catch (err) {
                console.error("Error buscando:", err);
            } finally {
                this.isSearching = false;
                this.selectedLeftDns = [];
                this.lastSelectedIndex = -1;
            }
        },

        // Lógica de multiselección
        toggleSelection(user, event, listType = 'left') {
            const list = listType === 'left' ? this.searchResults : this.currentSubordinates;
            const selection = listType === 'left' ? 'selectedLeftDns' : 'selectedRightDns';
            const lastIndexKey = listType === 'left' ? 'lastSelectedIndex' : 'lastSelectedRightIndex';
            
            const index = list.findIndex(u => u.dn === user.dn);
            const lastIndex = this[lastIndexKey];
            
            if (event.shiftKey && lastIndex !== -1) {
                // Seleccionar rango
                const start = Math.min(index, lastIndex);
                const end = Math.max(index, lastIndex);
                const rangeDns = list.slice(start, end + 1).map(u => u.dn);
                
                // Añadir rango a la selección (evitando duplicados)
                rangeDns.forEach(dn => {
                    if (!this[selection].includes(dn)) this[selection].push(dn);
                });
            } else if (event.ctrlKey || event.metaKey) {
                // Seleccionar individual (toggle)
                if (this[selection].includes(user.dn)) {
                    this[selection] = this[selection].filter(dn => dn !== user.dn);
                } else {
                    this[selection].push(user.dn);
                }
                this[lastIndexKey] = index;
            } else {
                // Selección única (clic simple)
                this[selection] = [user.dn];
                this[lastIndexKey] = index;
            }
        },

        isSelected(dn, listType = 'left') {
            const selection = listType === 'left' ? this.selectedLeftDns : this.selectedRightDns;
            return selection.includes(dn);
        },

        // Métodos de acción unificados (para botones y drag & drop)
        async addMember(dn, name) {
            // Si nos pasan un solo DN lo usamos, si no, intentamos proceso masivo
            let dnsToAdd = dn ? [dn] : [...this.selectedLeftDns];
            
            if (dnsToAdd.length === 0) return;
            
            this.isLoading = true;
            try {
                const formData = new FormData();
                formData.append('action', 'add');
                formData.append('target_dn', this.targetDn);
                
                // Si es más de uno, mandamos como JSON string
                formData.append('subordinate_dn', dnsToAdd.length > 1 ? JSON.stringify(dnsToAdd) : dnsToAdd[0]);
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                if (csrfToken) formData.append('csrf_token', csrfToken);

                const response = await fetch('lib/ldap_manage_subordinates.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    this.selectedLeftDns = [];
                    this.lastSelectedIndex = -1;
                    await this.loadSubordinates();
                } else {
                    alert("Error: " + result.message);
                }
            } catch (err) {
                console.error("Error añadiendo:", err);
            } finally {
                this.isLoading = false;
            }
        },

        async removeMember(dn) {
            let dnsToRemove = dn ? [dn] : [...this.selectedRightDns];
            
            if (dnsToRemove.length === 0) return;
            // Eliminado confirm() por petición del usuario: "que directamente desaparezca"
            
            this.isLoading = true;
            try {
                const formData = new FormData();
                formData.append('action', 'remove');
                formData.append('target_dn', this.targetDn || '');
                
                // Soporte para lote
                formData.append('subordinate_dn', dnsToRemove.length > 1 ? JSON.stringify(dnsToRemove) : dnsToRemove[0]);
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                if (csrfToken) formData.append('csrf_token', csrfToken);

                const response = await fetch('lib/ldap_manage_subordinates.php', {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error("Respuesta no válida del servidor:", text);
                    return;
                }

                if (result.success) {
                    this.selectedRightDns = [];
                    this.lastSelectedRightIndex = -1;
                    this.selectedOnRight = null;
                    await this.loadSubordinates();
                } else {
                    console.warn("Error backend:", result.message);
                }
            } catch (err) {
                console.error("Error quitando:", err);
            } finally {
                this.isLoading = false;
            }
        },

        // Handlers para Drag & Drop
        handleDragStart(e, user) {
            this.isDragging = true;
            
            // Si el elemento arrastrado es parte de la selección, arrastramos toda la selección
            let dnsToDrag = [user.dn];
            if (!user.isCurrent && this.selectedLeftDns.includes(user.dn)) {
                dnsToDrag = [...this.selectedLeftDns];
            }

            try {
                e.dataTransfer.setData('application/json', JSON.stringify({
                    dn: dnsToDrag.length > 1 ? JSON.stringify(dnsToDrag) : dnsToDrag[0],
                    name: user.name,
                    source: user.isCurrent ? 'right' : 'left'
                }));
                e.dataTransfer.effectAllowed = 'move';
            } catch (err) {
                console.error("Error en drag start:", err);
            }
        },

        handleDragEnd() {
            this.isDragging = false;
            this.isOverLeft = false;
            this.isOverRight = false;
        },

        async handleDrop(e, target) {
            this.isOverLeft = false;
            this.isOverRight = false;
            this.isDragging = false;

            try {
                const rawData = e.dataTransfer.getData('application/json');
                if (!rawData) return;
                const data = JSON.parse(rawData);
                if (data.source === 'left' && target === 'right') {
                    await this.addMember(data.dn, data.name);
                } else if (data.source === 'right' && target === 'left') {
                    await this.removeMember(data.dn);
                }
            } catch (err) {
                console.error("Error en drop:", err);
            }
        }
    }));
});
