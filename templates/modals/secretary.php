    <!-- Modal para Añadir Pasen (Secretary) -->
    <div x-data="secretaryModal" 
         x-show="isOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         style="display: none;"
         @keydown.escape.window="isOpen = false">
        
        <div class="bg-white/95 dark:bg-slate-900/80 w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden border border-slate-200 dark:border-slate-700 transform transition-all backdrop-blur-xl"
             @click.away="isOpen = false"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="scale-95 translate-y-4"
             x-transition:enter-end="scale-100 translate-y-0">
            
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/50 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/20">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-sm border"
                         :class="type === 'contacts' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 border-emerald-200/50 dark:border-emerald-700/30' : 'bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 border-amber-200/50 dark:border-amber-700/30'">
                        <i class="text-lg" :class="type === 'contacts' ? 'fas fa-link' : 'fas fa-share-square'"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 leading-tight"
                            x-text="type === 'contacts' ? 'Relacionar empresa con...' : 'Configurar pasar llamadas a...'"></h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                            <span x-text="type === 'contacts' ? 'Vinculando empresa a:' : 'Gestionando desvío para:'"></span>
                            <span class="font-bold ml-1" :class="type === 'contacts' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'" x-text="targetName"></span>
                        </p>
                    </div>
                </div>
                <button @click="isOpen = false" class="w-8 h-8 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-400 dark:text-slate-500 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="p-6">
                <!-- Buscador -->
                <div class="relative group mb-6">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-search text-slate-400 group-focus-within:text-amber-500 transition-colors"></i>
                    </div>
                    <input type="text" 
                           x-model="searchQuery" 
                           @input.debounce.300ms="performSearch"
                           placeholder="Escriba el nombre para buscar..."
                           class="w-full pl-11 pr-4 py-3 bg-white/50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 dark:focus:ring-amber-600/50 outline-none text-slate-700 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 transition-all shadow-sm">
                </div>

                <!-- Resultados -->
                <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                    <template x-if="isLoading">
                        <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-amber-500 mb-3"></div>
                            <p class="text-xs">Buscando en Active Directory...</p>
                        </div>
                    </template>

                    <template x-if="!isLoading && searchResults.length === 0 && searchQuery.length >= 3">
                        <div class="text-center py-12 text-slate-400">
                            <i class="fas fa-user-slash text-3xl mb-3 opacity-20"></i>
                            <p class="text-sm">No se encontraron usuarios que coincidan con la búsqueda.</p>
                        </div>
                    </template>

                    <template x-if="searchQuery.length > 0 && searchQuery.length < 3">
                        <div class="text-center py-12 text-slate-400 italic">
                            <p class="text-sm">Escriba al menos 3 caracteres...</p>
                        </div>
                    </template>

                    <template x-for="user in searchResults" :key="user.dn">
                        <div class="flex items-center justify-between p-3 rounded-2xl bg-white/60 dark:bg-slate-900/40 hover:bg-amber-50 dark:hover:bg-amber-900/30 border border-slate-200/60 dark:border-slate-700 transition-all group/item shadow-sm">
                            <div class="flex items-center gap-3 overflow-hidden">
                                <template x-if="user.photo">
                                    <img :src="'data:image/jpeg;base64,' + user.photo" class="w-10 h-10 rounded-full object-cover shrink-0 border border-amber-200 dark:border-amber-700 shadow-sm" alt="Foto">
                                </template>
                                <template x-if="!user.photo">
                                    <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 dark:text-slate-500 shrink-0 border border-slate-200 dark:border-slate-700">
                                        <i class="fas fa-user text-sm"></i>
                                    </div>
                                </template>
                                <div class="min-w-0 text-left flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate" x-text="user.name"></p>
                                        <span class="px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-tighter border shadow-sm group-hover/item:bg-white/20 group-hover/item:text-white" 
                                              :class="user.type === 'Contacto' ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800/50' : (user.type === 'Usuario' ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-800/50' : 'bg-slate-50 dark:bg-slate-700/30 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700/50')"
                                              x-text="user.type"></span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate uppercase tracking-tight font-medium" x-text="user.title || user.sam || 'Personal'"></p>
                                </div>
                            </div>
                            <button @click="addSecretary(user.dn)" class="flex-shrink-0 w-9 h-9 rounded-xl bg-amber-500 text-white hover:bg-amber-600 flex items-center justify-center shadow-lg shadow-amber-500/20 transition-all hover:scale-110 ml-2" title="Añadir a lista">
                                <i class="fas fa-plus text-xs"></i>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-900/20 border-t border-slate-100 dark:border-slate-700/50 flex justify-end">
                <button @click="isOpen = false" class="px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-semibold transition-all">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
