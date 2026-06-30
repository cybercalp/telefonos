/**
 * Alpine.js component for managing computer phone extensions.
 * Admin-only modal accessible from the navbar.
 */

document.addEventListener('alpine:init', () => {
    Alpine.data('computerPhoneModal', () => ({
        isOpen: false,
        searchQuery: '',
        searchResults: [],
        isSearching: false,
        // Editing state
        editingDn: '',
        editingCn: '',
        editingPhone: '',
        editingOriginalPhone: '',
        isSaving: false,
        saveMessage: '',
        saveSuccess: false,

        init() {
            window.addEventListener('open-computer-phone-modal', () => {
                this.isOpen = true;
                this.searchQuery = '';
                this.searchResults = [];
                this.clearEdit();
            });
        },

        clearEdit() {
            this.editingDn = '';
            this.editingCn = '';
            this.editingPhone = '';
            this.editingOriginalPhone = '';
            this.saveMessage = '';
            this.saveSuccess = false;
        },

        async performSearch() {
            if (this.searchQuery.length < 2) {
                this.searchResults = [];
                return;
            }
            this.isSearching = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch(
                    `lib/ldap_manage_computers.php?action=search&q=${encodeURIComponent(this.searchQuery)}&csrf_token=${encodeURIComponent(csrfToken)}`,
                    { headers: { 'X-CSRF-TOKEN': csrfToken } }
                );
                const data = await response.json();
                if (Array.isArray(data)) {
                    this.searchResults = data;
                } else {
                    this.searchResults = [];
                }
            } catch (err) {
                console.error('Error searching computers:', err);
                this.searchResults = [];
            } finally {
                this.isSearching = false;
            }
        },

        selectComputer(computer) {
            this.editingDn = computer.dn;
            this.editingCn = computer.cn;
            this.editingPhone = computer.phone || '';
            this.editingOriginalPhone = computer.phone || '';
            this.saveMessage = '';
            this.saveSuccess = false;
        },

        get hasChanges() {
            return this.editingPhone !== this.editingOriginalPhone;
        },

        async savePhone() {
            if (!this.editingDn) return;
            this.isSaving = true;
            this.saveMessage = '';
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const formData = new FormData();
                formData.append('action', 'update');
                formData.append('computer_dn', this.editingDn);
                formData.append('phone', this.editingPhone);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('lib/ldap_manage_computers.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    this.saveSuccess = true;
                    this.saveMessage = result.message || 'Extensión actualizada';
                    this.editingOriginalPhone = this.editingPhone;
                    // Update the result in the list too
                    const match = this.searchResults.find(c => c.dn === this.editingDn);
                    if (match) match.phone = this.editingPhone;
                } else {
                    this.saveSuccess = false;
                    this.saveMessage = result.message || 'Error desconocido';
                }
            } catch (err) {
                console.error('Error saving phone:', err);
                this.saveSuccess = false;
                this.saveMessage = 'Error de conexión: ' + err.message;
            } finally {
                this.isSaving = false;
            }
        },

        async removePhone() {
            if (!this.editingDn) return;
            if (!confirm(`¿Eliminar la extensión del equipo ${this.editingCn}?`)) return;
            this.editingPhone = '';
            await this.savePhone();
        },

        async clearDescription(computer) {
            if (!confirm(`¿Borrar último usuario del equipo ${computer.cn}?`)) return;
            
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const formData = new FormData();
                formData.append('action', 'clear_description');
                formData.append('computer_dn', computer.dn);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('lib/ldap_manage_computers.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    computer.description = '';
                } else {
                    alert(result.message || 'Error al eliminar descripción');
                }
            } catch (err) {
                console.error(err);
                alert('Error de conexión');
            }
        }
    }));
});
