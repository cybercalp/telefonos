        <!-- Sidebar Izquierdo: Formularios de Búsqueda (Fijo al hacer scroll) -->
        <aside class="w-80 !bg-white dark:!bg-slate-800 border-r border-slate-200 dark:border-slate-700/80 shadow-[4px_0_24px_rgba(0,0,0,0.02)] z-10 flex-shrink-0 flex flex-col">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/50 !bg-slate-50/50 dark:!bg-slate-900/50">
                <?php if (can_manage_contacts()): ?>
                <div class="mb-3">
                    <a href="./contact_edit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white rounded-xl font-bold shadow-lg shadow-emerald-200/50 dark:shadow-none hover:from-emerald-700 hover:to-teal-700 transition-all transform hover:-translate-y-0.5 text-sm">
                        <i class="fas fa-plus-circle"></i>
                        Nuevo Contacto
                    </a>
                </div>
                <?php endif; ?>
                <h2 class="text-lg font-bold !text-slate-800 dark:!text-white flex items-center gap-2">
                    <i class="fas fa-search text-blue-500"></i> Localizador
                </h2>
                <p class="text-xs !text-slate-500 dark:!text-slate-400 mt-1">Encuentre rápidamente el contacto deseado utilizando los filtros inferiores.</p>
             <div class="flex-1 overflow-y-auto px-5 py-4 !bg-white dark:!bg-slate-800">
                <form method="get" action="./index.php" class="space-y-3.5" accept-charset="utf-8">
                    <input type="hidden" name="sidebar" value="1">
                    <?php $fromSidebar = isset($_GET['sidebar']); ?>
                    
                    <div class="space-y-1">
                        <label for="txtTag" class="text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-wider">Búsqueda General</label>
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-3 text-slate-400 dark:text-slate-500"></i>
                            <input id="txtTag" type="text" name="txtTag" autofocus 
                                   value="<?php echo (isset($_GET['btnBuscar']) && $fromSidebar ? htmlspecialchars($_REQUEST['txtTag'] ?? '') : ''); ?>"
                                   class="w-full pl-9 pr-3 py-2 bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm text-slate-700 dark:text-white" placeholder="Nombre, email, ext..." />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label for="txtNombre" class="text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-wider">Nombre</label>
                        <input id="txtNombre" type="text" name="txtNombre" 
                               value="<?php echo (isset($_GET['btnBuscar']) && $fromSidebar ? htmlspecialchars($_REQUEST['txtNombre'] ?? '') : ''); ?>"
                               class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm text-slate-700 dark:text-white" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label for="txtCargo" class="text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-wider">Cargo</label>
                            <input id="txtCargo" type="text" name="txtCargo" 
                                   value="<?php echo (isset($_GET['btnBuscar']) && $fromSidebar ? htmlspecialchars($_REQUEST['txtCargo'] ?? '') : ''); ?>"
                                   class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm text-slate-700 dark:text-white" />
                        </div>
                        <div class="space-y-1">
                            <label for="txtExtension" class="text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-wider">Extensión</label>
                            <input id="txtExtension" type="text" name="txtExtension" 
                                   value="<?php echo (isset($_GET['btnBuscar']) && $fromSidebar ? htmlspecialchars($_REQUEST['txtExtension'] ?? '') : ''); ?>"
                                   class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-sm text-slate-700 dark:text-white" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label for="txtDepartamento" class="text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-wider">Departamento</label>
                        <div class="[&>select]:w-full [&>select]:px-3 [&>select]:py-2 [&>select]:bg-slate-50 dark:[&>select]:bg-slate-900/80 [&>select]:border [&>select]:border-slate-200 dark:[&>select]:border-slate-700/80 [&>select]:rounded-xl [&>select]:text-sm [&>select]:text-slate-700 dark:[&>select]:text-white focus-within:[&>select]:ring-2 focus-within:[&>select]:ring-blue-500">
                            <?php 
                                $depVal = (isset($_GET['btnBuscar']) && $fromSidebar ? ($_REQUEST['txtDepartamento'] ?? '0') : '0');
                                print fill_combobox($_SERVER['SCRIPT_NAME'], 'department', 'txtDepartamento', 'w-full', $depVal); 
                            ?>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label for="txtOficina" class="text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-wider">Ubicación</label>
                        <div class="[&>select]:w-full [&>select]:px-3 [&>select]:py-2 [&>select]:bg-slate-50 dark:[&>select]:bg-slate-900/80 [&>select]:border [&>select]:border-slate-200 dark:[&>select]:border-slate-700/80 [&>select]:rounded-xl [&>select]:text-sm [&>select]:text-slate-700 dark:[&>select]:text-white focus-within:[&>select]:ring-2 focus-within:[&>select]:ring-blue-500">
                            <?php 
                                $ubiVal = (isset($_GET['btnBuscar']) && $fromSidebar ? ($_REQUEST['txtOficina'] ?? '0') : '0');
                                print fill_combobox($_SERVER['SCRIPT_NAME'], 'physicaldeliveryofficename', 'txtOficina', 'w-full', $ubiVal); 
                            ?>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-wider">Estado</label>
                        <div class="pt-1.5 bg-slate-50 dark:bg-slate-900/80 p-3 rounded-xl border border-slate-200 dark:border-slate-700/80 space-y-2.5">
                            <div class="flex items-center justify-between gap-3">
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <input id="chkPresente" type="checkbox" name="chkPresente" value="1" <?php echo (!isset($_GET['btnBuscar']) || (isset($_GET['sidebar']) ? isset($_REQUEST['chkPresente']) : true) ? 'checked' : ''); ?> class="w-4 h-4 text-emerald-600 bg-white dark:bg-slate-700 border-slate-300 dark:border-slate-600 rounded focus:ring-emerald-500 dark:focus:ring-emerald-600 dark:ring-offset-slate-900 focus:ring-2 transition-all cursor-pointer">
                                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Presentes</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <input id="chkAusente" type="checkbox" name="chkAusente" value="1" <?php echo (!isset($_GET['btnBuscar']) || (isset($_GET['sidebar']) ? isset($_REQUEST['chkAusente']) : true) ? 'checked' : ''); ?> class="w-4 h-4 text-rose-600 bg-white dark:bg-slate-700 border-slate-300 dark:border-slate-600 rounded focus:ring-rose-500 dark:focus:ring-rose-600 dark:ring-offset-slate-900 focus:ring-2 transition-all cursor-pointer">
                                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Ausentes</span>
                                </label>
                            </div>
                            <div class="flex items-center justify-between gap-3 pt-1.5 border-t border-slate-200/50 dark:border-slate-700/50">
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <input id="chkIndeterminado" type="checkbox" name="chkIndeterminado" value="1" <?php echo (!isset($_GET['btnBuscar']) || (isset($_GET['sidebar']) ? isset($_REQUEST['chkIndeterminado']) : true) ? 'checked' : ''); ?> class="w-4 h-4 text-slate-500 bg-white dark:bg-slate-700 border-slate-300 dark:border-slate-600 rounded focus:ring-slate-400 dark:focus:ring-slate-500 dark:ring-offset-slate-900 focus:ring-2 transition-all cursor-pointer">
                                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Indeterminado</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <input id="chkInactivo" type="checkbox" name="chkInactivo" value="1" <?php echo (isset($_GET['btnBuscar']) && (isset($_GET['sidebar']) ? isset($_REQUEST['chkInactivo']) : false) ? 'checked' : ''); ?> class="w-4 h-4 text-blue-600 bg-white dark:bg-slate-700 border-slate-300 dark:border-slate-600 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-slate-900 focus:ring-2 transition-all cursor-pointer">
                                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Inactivos</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-500 dark:text-slate-300 uppercase tracking-wider">Modalidad</label>
                        <div class="pt-1.5 bg-slate-50 dark:bg-slate-900/80 p-3 rounded-xl border border-slate-200 dark:border-slate-700/80">
                            <div class="flex items-center justify-between gap-3">
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <input id="chkPresencial" type="checkbox" name="chkPresencial" value="1" <?php echo (!isset($_GET['btnBuscar']) || (isset($_GET['sidebar']) ? isset($_REQUEST['chkPresencial']) : true) ? 'checked' : ''); ?> class="w-4 h-4 text-cyan-600 bg-white dark:bg-slate-700 border-slate-300 dark:border-slate-600 rounded focus:ring-cyan-500 dark:focus:ring-cyan-600 dark:ring-offset-slate-900 focus:ring-2 transition-all cursor-pointer">
                                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300"><i class="fas fa-building text-[10px] opacity-60 mr-0.5"></i> Presencial</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <input id="chkTeletrabajo" type="checkbox" name="chkTeletrabajo" value="1" <?php echo (!isset($_GET['btnBuscar']) || (isset($_GET['sidebar']) ? isset($_REQUEST['chkTeletrabajo']) : true) ? 'checked' : ''); ?> class="w-4 h-4 text-violet-600 bg-white dark:bg-slate-700 border-slate-300 dark:border-slate-600 rounded focus:ring-violet-500 dark:focus:ring-violet-600 dark:ring-offset-slate-900 focus:ring-2 transition-all cursor-pointer">
                                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300"><i class="fas fa-house-laptop text-[10px] opacity-60 mr-0.5"></i> Teletrabajo</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 space-y-2">
                        <input type="hidden" name="btnBuscar" value="1">
                        <button type="submit" id="btnBuscar" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-xl shadow-md transition-all flex items-center justify-center gap-2 group">
                            <i class="fas fa-filter group-hover:scale-110 transition-transform"></i> Aplicar Filtros
                        </button>
                        <?php if (isset($_GET['btnBuscar'])): ?>
                        <a href="./index.php" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 text-xs font-medium transition-colors">
                            <i class="fas fa-times-circle"></i> Limpiar Filtros
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </aside>
