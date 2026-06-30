    <!-- Navbar superior (Escudo corporativo) -->
    <!-- Navbar superior (Escudo corporativo) -->
    <header class="!bg-white dark:!bg-slate-800 shadow-sm border-b border-slate-200 dark:border-slate-700/80 z-20 flex-shrink-0 h-16 flex items-center px-6 justify-between transition-colors">
        <div class="flex items-center gap-4">
            <a href="./" class="flex items-center gap-3">
                <img src="images/escudo.svg" alt="Escudo" class="h-10 w-auto brightness-0 dark:invert opacity-90 transition-all">
                <div>
                    <h1 class="text-xl font-bold !text-slate-800 dark:!text-white leading-none tracking-tight">Directorio Corporativo</h1>
                    <p class="text-[10px] !text-slate-500 dark:!text-slate-400 uppercase tracking-wider font-semibold"><?php echo (string)$nameAyto; ?></p>
                </div>
            </a>
        </div>

        <div class="flex items-center gap-4">
            <!-- Theme Toggle -->
            <button @click="toggleTheme()" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-yellow-400 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all border border-slate-200 dark:border-slate-700/80" title="Cambiar Tema">
                <i class="fas" :class="dark ? 'fa-sun' : 'fa-moon'"></i>
            </button>

            <!-- Controles de vista interactivos (Alpine.js) -->
            <div class="flex items-center gap-3 bg-slate-100 dark:bg-slate-800/80 p-1.5 rounded-xl border border-slate-200 dark:border-slate-700/80 shadow-inner">
                <div class="flex items-center gap-1 border-r border-slate-300 dark:border-slate-600 pr-3 mr-1" x-show="viewMode === 'grid'" x-transition>
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium hidden md:inline">Columnas:</span>
                    <button @click="gridCols = 1" :class="{'bg-white dark:bg-slate-600 shadow text-blue-600 dark:text-blue-300': gridCols === 1, 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200': gridCols !== 1}" class="w-7 h-7 rounded-lg text-xs font-bold transition-all">1</button>
                    <button @click="gridCols = 2" :class="{'bg-white dark:bg-slate-600 shadow text-blue-600 dark:text-blue-300': gridCols === 2, 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200': gridCols !== 2}" class="w-7 h-7 rounded-lg text-xs font-bold transition-all">2</button>
                    <button @click="gridCols = 3" :class="{'bg-white dark:bg-slate-600 shadow text-blue-600 dark:text-blue-300': gridCols === 3, 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200': gridCols !== 3}" class="w-7 h-7 rounded-lg text-xs font-bold transition-all">3</button>
                    <button @click="gridCols = 4" :class="{'bg-white dark:bg-slate-600 shadow text-blue-600 dark:text-blue-300': gridCols === 4, 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200': gridCols !== 4}" class="w-7 h-7 rounded-lg text-xs font-bold transition-all">4</button>
                </div>

                <button @click="viewMode = 'grid'" :class="{'bg-white dark:bg-slate-600 shadow-sm text-blue-600 dark:text-blue-300': viewMode === 'grid', 'text-slate-400 dark:text-slate-400 hover:text-slate-600 dark:hover:text-slate-200': viewMode !== 'grid'}" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-all flex items-center gap-2" title="Vista en Mosaico">
                    <i class="fas fa-th-large"></i> <span class="hidden sm:inline">Mosaico</span>
                </button>
                <button @click="viewMode = 'list'" :class="{'bg-white dark:bg-slate-600 shadow-sm text-blue-600 dark:text-blue-300': viewMode === 'list', 'text-slate-400 dark:text-slate-400 hover:text-slate-600 dark:hover:text-slate-200': viewMode !== 'list'}" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-all flex items-center gap-2" title="Vista en Fila">
                    <i class="fas fa-list"></i> <span class="hidden sm:inline">Lista</span>
                </button>
            </div>

            <?php if (!empty($_SESSION['ldap_user'])): ?>
            <!-- Usuario autenticado: muestra nombre y opciones -->
            <div class="flex items-center gap-2">
                <?php if (is_admin_user()): ?>
                <button @click="$dispatch('open-computer-phone-modal')" class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700/50 text-emerald-700 dark:text-emerald-300 text-sm font-semibold hover:bg-emerald-100 dark:hover:bg-emerald-800/40 transition-all" title="Gestión de extensiones de equipos">
                    <i class="fas fa-desktop"></i>
                    <span class="hidden md:inline">Equipos</span>
                </button>
                <?php endif; ?>
                <a href="./datos_active?user=<?php echo urlencode($_SESSION['ldap_user']); ?>" class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700/50 text-blue-700 dark:text-blue-300 text-sm font-semibold hover:bg-blue-100 dark:hover:bg-blue-800/40 transition-all" title="Gestión de Mi Perfil">
                    <i class="fas fa-user-circle"></i>
                    <span class="hidden md:inline">Mi Perfil</span>
                </a>
                <a href="./logout.php" class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-700/50 text-rose-700 dark:text-rose-300 text-sm font-semibold hover:bg-rose-100 dark:hover:bg-rose-800/40 transition-all" title="Cerrar Sessión">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="hidden md:inline">Salir</span>
                </a>
            </div>
            <?php else: ?>
            <!-- Sin sesión: botón para acceder (útil en red interna) -->
            <a href="./login" class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-sm font-medium hover:bg-slate-200 dark:hover:bg-slate-600 transition-all" title="Iniciar sesión para ver opciones de gestión">
                <i class="fas fa-sign-in-alt"></i>
                <span class="hidden md:inline">Acceder</span>
            </a>
            <?php endif; ?>
        </div>
    </header>
