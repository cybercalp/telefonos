    <div x-data="computerPhoneModal"
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

        <div class="bg-white/95 dark:bg-slate-900/80 rounded-3xl shadow-2xl overflow-hidden border border-slate-200 dark:border-slate-700 transform transition-all backdrop-blur-xl flex flex-col"
             style="width: 1000px !important; height: 750px !important; max-width: 95vw !important; max-height: 90vh !important;"
             @click.away="isOpen = false"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="scale-95 translate-y-4"
             x-transition:enter-end="scale-100 translate-y-0">

            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/50 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/20">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 border-amber-200/50 dark:border-amber-700/30 flex items-center justify-center shadow-sm border">
                        <i class="fas fa-desktop text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 leading-tight">Extensiones de Equipos</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Asigne extensiones telefónicas a estaciones de trabajo</p>
                    </div>
                </div>
                <button @click="isOpen = false" class="w-8 h-8 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-400 dark:text-slate-500 flex items-center justify-center transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="p-6 flex-1 min-h-0 flex flex-col">
                <!-- Search (always visible) -->
                <div class="relative group mb-8 flex-shrink-0">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-search text-slate-400 group-focus-within:text-amber-500 transition-colors"></i>
                    </div>
                    <input type="text"
                           x-model="searchQuery"
                           @input.debounce.300ms="performSearch"
                           placeholder="Buscar por nombre, ubicación o descripción..."
                           class="w-full pl-11 pr-4 py-3 bg-white/50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-amber-500 dark:focus:ring-amber-600/50 outline-none text-slate-700 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 transition-all shadow-sm text-sm">
                </div>

                <!-- Two-panel area (fills remaining space) -->
                <div class="flex gap-5 flex-1 min-h-0">
                    <!-- Left: Results list -->
                    <div class="flex-1 min-w-0 flex flex-col min-h-0">
                        <h4 class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-2 flex items-center gap-1.5 flex-shrink-0">
                            <i class="fas fa-server opacity-60"></i> Equipos encontrados
                        </h4>
                        <!-- Scrollable results -->
                        <div class="space-y-1.5 overflow-y-auto pr-1 flex-1 min-h-0">
                            <!-- Loading -->
                            <template x-if="isSearching">
                                <div class="flex flex-col items-center justify-center py-10 text-slate-400">
                                    <div class="animate-spin rounded-full h-7 w-7 border-b-2 border-amber-500 mb-3"></div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider">Buscando en Active Directory...</p>
                                </div>
                            </template>

                            <!-- No results -->
                            <template x-if="!isSearching && searchResults.length === 0 && searchQuery.length >= 2">
                                <div class="py-10 text-center text-slate-400">
                                    <i class="fas fa-desktop text-3xl mb-3 opacity-20"></i>
                                    <p class="text-[10px] font-bold uppercase tracking-widest">Sin resultados</p>
                                </div>
                            </template>

                            <!-- Hint -->
                            <template x-if="searchQuery.length > 0 && searchQuery.length < 2 && !isSearching">
                                <div class="py-10 text-center text-slate-400 italic">
                                    <p class="text-sm">Escriba al menos 2 caracteres...</p>
                                </div>
                            </template>

                            <!-- Empty state -->
                            <template x-if="searchQuery.length === 0 && !isSearching">
                                <div class="py-10 text-center text-slate-400">
                                    <i class="fas fa-keyboard text-3xl mb-3 opacity-20"></i>
                                    <p class="text-[10px] font-bold uppercase tracking-widest">Escriba un nombre de equipo</p>
                                    <p class="text-[10px] mt-1 opacity-70">para buscar y asignar extensiones</p>
                                </div>
                            </template>

                            <!-- Results -->
                            <template x-for="computer in searchResults" :key="computer.dn">
                                <button @click="selectComputer(computer)"
                                        class="w-full text-left p-2.5 rounded-xl border transition-all flex items-center gap-3 group/item"
                                        :class="editingDn === computer.dn
                                            ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-300 dark:border-amber-700/60 shadow-sm'
                                            : 'bg-white/60 dark:bg-slate-800/40 border-slate-200/60 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/80 hover:border-slate-300 dark:hover:border-slate-600'">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 transition-colors"
                                         :class="editingDn === computer.dn
                                            ? 'bg-amber-500 text-white shadow-md'
                                            : 'bg-slate-100 dark:bg-slate-700/50 text-slate-400 dark:text-slate-500 group-hover/item:bg-amber-100 dark:group-hover/item:bg-amber-900/30 group-hover/item:text-amber-600 dark:group-hover/item:text-amber-400'">
                                        <i class="fas fa-desktop text-sm"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate">
                                            <span x-text="computer.cn"></span>
                                            <template x-if="computer.description">
                                                <span class="inline-flex items-center ml-1">
                                                    <span class="text-xs font-normal text-slate-500 dark:text-slate-400" x-text="'- ' + computer.description"></span>
                                                    <button @click.stop="clearDescription(computer)" 
                                                            class="ml-1.5 text-slate-300 hover:text-rose-500 dark:text-slate-600 dark:hover:text-rose-400 transition-colors"
                                                            title="Borrar último usuario del equipo.">
                                                        <i class="fas fa-trash-alt text-[10px]"></i>
                                                    </button>
                                                </span>
                                            </template>
                                        </p>
                                        <template x-if="computer.location">
                                            <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate mt-0.5 flex items-center gap-1">
                                                <i class="fas fa-map-marker-alt opacity-70"></i>
                                                <span x-text="computer.location"></span>
                                            </p>
                                        </template>
                                    </div>
                                    <template x-if="computer.phone">
                                        <span class="text-sm font-bold text-slate-600 dark:text-slate-100 flex-shrink-0 font-mono" x-text="computer.phone"></span>
                                    </template>
                                    <template x-if="!computer.phone">
                                        <span class="text-[10px] text-slate-400 dark:text-slate-500 flex-shrink-0 italic">Sin ext.</span>
                                    </template>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Right: Edit panel -->
                    <div class="border-l border-slate-200 dark:border-slate-700/50 px-6" style="flex: 0 0 30%; max-width: 30%;">
                        <h4 class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-4 flex items-center justify-center gap-1.5">
                            <i class="fas fa-edit opacity-60"></i> Editar extensión
                        </h4>

                        <template x-if="!editingDn">
                            <div class="flex flex-col items-center justify-center py-12 text-slate-300 dark:text-slate-600">
                                <i class="fas fa-arrow-left text-2xl mb-3 opacity-40"></i>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-center leading-relaxed">Seleccione un equipo<br>de la lista</p>
                            </div>
                        </template>

                        <template x-if="editingDn">
                            <div class="space-y-4">
                                <!-- Computer info card -->
                                <div class="bg-amber-50 dark:bg-amber-950/40 rounded-xl p-4 border border-amber-200 dark:border-amber-800/50">
                                    <div class="flex items-center justify-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-amber-500 text-white flex items-center justify-center shadow-sm">
                                            <i class="fas fa-desktop text-sm"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-black text-amber-900 dark:text-amber-200 truncate" x-text="editingCn"></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Phone input -->
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2 text-center">Extensión telefónica</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-phone-alt text-slate-400 dark:text-slate-500 text-xs"></i>
                                        </div>
                                        <input type="text"
                                               x-model="editingPhone"
                                               @keydown.enter="savePhone"
                                               maxlength="20"
                                               placeholder="Ej: 1234"
                                               class="w-full pl-9 pr-3 py-2.5 bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-amber-500 dark:focus:ring-amber-600/50 outline-none text-slate-700 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 transition-all text-sm font-mono">
                                    </div>
                                </div>

                                <!-- Action buttons -->
                                <div class="flex gap-2">
                                    <button @click="savePhone"
                                            :disabled="isSaving || !hasChanges"
                                            class="flex-1 px-3 py-2.5 rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-all shadow-sm"
                                            :class="hasChanges
                                                ? 'bg-amber-500 hover:bg-amber-600 text-white shadow-amber-200/50 dark:shadow-none'
                                                : 'bg-slate-100 dark:bg-slate-700/50 text-slate-400 dark:text-slate-500 cursor-not-allowed'">
                                        <template x-if="isSaving">
                                            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                                        </template>
                                        <template x-if="!isSaving">
                                            <i class="fas fa-save text-xs"></i>
                                        </template>
                                        <span x-text="isSaving ? 'Guardando...' : 'Guardar'"></span>
                                    </button>
                                    <button @click="removePhone"
                                            :disabled="isSaving || !editingOriginalPhone"
                                            class="px-3 py-2.5 rounded-xl font-bold text-sm flex items-center justify-center gap-1.5 transition-all border"
                                            :class="editingOriginalPhone
                                                ? 'bg-white dark:bg-slate-800 text-rose-500 dark:text-rose-400 border-rose-200 dark:border-rose-800/50 hover:bg-rose-50 dark:hover:bg-rose-900/20'
                                                : 'bg-slate-100 dark:bg-slate-700/50 text-slate-300 dark:text-slate-600 border-slate-200 dark:border-slate-700 cursor-not-allowed'"
                                            title="Eliminar extensión">
                                        <i class="fas fa-trash-alt text-xs"></i>
                                    </button>
                                </div>

                                <!-- Feedback message -->
                                <template x-if="saveMessage">
                                    <div class="p-2.5 rounded-xl text-xs font-bold flex items-center gap-2 transition-all"
                                         :class="saveSuccess
                                            ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50'
                                            : 'bg-rose-50 dark:bg-rose-900/20 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800/50'">
                                        <i class="fas" :class="saveSuccess ? 'fa-check-circle' : 'fa-exclamation-circle'"></i>
                                        <span x-text="saveMessage"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-900/20 border-t border-slate-100 dark:border-slate-700/50 flex items-center justify-between">
                <p class="text-[10px] text-slate-400 dark:text-slate-500 flex items-center gap-1.5">
                    <i class="fas fa-info-circle"></i>
                    La extensión asignada al equipo se mostrará a los usuarios sentados en él
                </p>
                <button @click="isOpen = false" class="px-4 py-2 rounded-xl bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-sm font-semibold transition-all">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
