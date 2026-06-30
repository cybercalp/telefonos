    <!-- MODAL CONFIRMACIÓN ELIMINAR CONTACTO -->
    <div x-data="{ show: false, dn: '', name: '' }" 
         @confirm-delete-contact.window="show = true; dn = $event.detail.dn; name = $event.detail.name"
         x-show="show" 
         class="fixed inset-0 z-[100] overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen p-4 text-center">
            <div x-show="show" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm transition-opacity" 
                 @click="show = false"></div>

            <div x-show="show" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4" 
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0" 
                 class="relative bg-white/90 dark:bg-slate-800/90 backdrop-blur-xl rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full p-8 border border-white/20 dark:border-slate-700/50">
                
                <div class="flex items-center justify-center w-16 h-16 mx-auto bg-rose-100 dark:bg-rose-900/30 rounded-full mb-6">
                    <i class="fas fa-trash-alt text-2xl text-rose-600 dark:text-rose-400"></i>
                </div>

                <div class="text-center">
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2">Eliminar Contacto</h3>
                    <p class="text-slate-600 dark:text-slate-400 mb-6 text-sm leading-relaxed">
                        ¿Estás seguro de que deseas eliminar permanentemente a <span class="font-extrabold text-slate-900 dark:text-white underline decoration-rose-500/30" x-text="name"></span>? 
                        Esta acción no se puede deshacer en el Directorio Activo.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <button @click="show = false" class="flex-1 px-4 py-3 bg-slate-100 dark:bg-slate-700/50 text-slate-700 dark:text-slate-300 rounded-xl font-bold hover:bg-slate-200 dark:hover:bg-slate-600 transition-all">
                        Cancelar
                    </button>
                    <form action="contact_edit.php" method="POST" class="flex-1">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                        <input type="hidden" name="dn" :value="dn">
                        <button type="submit" class="w-full px-4 py-3 bg-rose-600 text-white rounded-xl font-bold hover:bg-rose-700 transition-all shadow-lg shadow-rose-200 dark:shadow-rose-900/20">
                            Eliminar ahora
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
