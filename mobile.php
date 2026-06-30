<?php
/**
 * mobile.php
 * Buscador de Directorio - Versión Móvil Simplificada
 * Enfoque: Consulta rápida, Foto, Email, Cargo, Dpto y Teléfonos (Click-to-Call)
 */

// Bootstrap: session security + HTTP security headers + config
require_once __DIR__ . '/bootstrap.php';

require_once(__DIR__ . '/lib/debug_to_console.php');
require_once(__DIR__ . '/lib/fillcombobox.php');
require_once(__DIR__ . '/lib/ldap_newfilter.php');
require_once(__DIR__ . '/lib/checkip.php');
require_once(__DIR__ . '/lib/db_presencia_select.php');
require_once(__DIR__ . '/lib/csrf.php');
require_once(__DIR__ . '/lib/remember_me.php');
if (empty($_SESSION['ldap_user'])) {
    check_remember_me();
}

// === SEGURIDAD Y AUTH ===
$client_ip = getIP();
$allowed_ip = ipAllowed($client_ip);

// Redirección Login si no hay sesión y externa
if (empty($_SESSION['ldap_user']) && !$allowed_ip) {
    header('Location: ./login.php');
    exit;
}

// Redirección TOTP si es externo y no verificado
if (!$allowed_ip && !empty($_SESSION['ldap_user']) && empty($_SESSION['2fa_verified'])) {
    if (!empty($_SESSION['secretkey'])) {
        header('Location: ./totp.php');
    } else {
        session_destroy();
        header('Location: ./login.php');
    }
    exit;
}

// === LÓGICA DE BÚSQUEDA (Si se envía) ===
$_SESSION['is_mobile_view'] = true;
$searchResults = isset($_GET['btnBuscar']);
?>
<!DOCTYPE html>
<html lang="es" class="h-full antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2563eb">
    <title>Directorio Móvil - <?php echo $nameAyto; ?></title>
    
    <!-- Tailwind CSS (CDN actual para consistencia) -->
    <link rel="stylesheet" href="css/style.css">
    
    
    <!-- Alpine.js & Font Awesome -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" integrity="sha384-Rpe/8orFUm5Q1GplYBHxbuA8Az8O8C5sAoOsdbRWkqPjKFaxPgGZipj4zeHL7lxX" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha384-t1nt8BQoYMLFN5p42tRAtuAAFQaCQODekUVeKKZrEnEyp4H2R0RHFz0KWpmj7i8g" crossorigin="anonymous">
    
    <!-- Google Fonts: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    
    <style>
        body { 
            font-family: 'Outfit', sans-serif; 
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .glass-morphism {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .dark .glass-morphism {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        /* Sincronizado con ldap_showresults.php */
        .card-animated {
            width: 100% !important;
            margin-bottom: 8px;
            animation: cardSlideUp 0.4s ease-out forwards;
            will-change: transform, opacity;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            border-radius: 20px !important;
        }
        @keyframes cardSlideUp {
            from { 
                opacity: 0; 
                transform: translate3d(0, 20px, 0); 
            }
            to { 
                opacity: 1; 
                transform: translate3d(0, 0, 0); 
            }
        }
        /* Ocultar botones de gestión en móvil */
        .fa-user-edit, .fa-trash-alt, .fa-link, .fa-plus, .fa-trash-alt, .drag-handle, [title^="Añadir"], [title^="Editar"], [title^="Eliminar"] {
            display: none !important;
        }
        /* Ocultar bloques secundarios pesados en móvil */
        [class*="bg-blue-50/50"], [class*="bg-amber-50/40"] {
            display: none !important;
        }
        .gpu-accelerated {
            transform: translate3d(0,0,0);
            -webkit-transform: translate3d(0,0,0);
        }
        /* Custom scrollbar para móvil */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
    </style>

    <script>
        // Detector de Tema Automático con soporte para cambios en tiempo real del SO
        function applyTheme(isDark) {
            if (isDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }

        const prefersDarkQuery = window.matchMedia('(prefers-color-scheme: dark)');
        
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && prefersDarkQuery.matches)) {
            applyTheme(true);
        } else {
            applyTheme(false);
        }

        // Escuchar cambios dinámicos en las preferencias del dispositivo
        prefersDarkQuery.addEventListener('change', (e) => {
            // Solo cambiar si el usuario no ha forzado un tema manualmente
            if (!localStorage.getItem('theme')) {
                applyTheme(e.matches);
            }
        });

        // Registrar Service Worker para soporte PWA/Offline
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => console.log('Service Worker registrado con éxito:', reg.scope))
                    .catch(err => console.error('Fallo al registrar el Service Worker:', err));
            });
        }
    </script>
</head>
<body class="h-full bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 selection:bg-blue-500" 
      x-data="{ 
          view: '<?php echo $searchResults ? 'results' : 'search'; ?>',
          viewMode: 'list', // Prevents undefined error from showresults Alpine bindings
          loading: false,
          dark: document.documentElement.classList.contains('dark'),
          isListening: false,
          recognition: null,
          showExitModal: false,
          init() {
              // Sincronizar estado de Alpine cuando el SO cambia
              window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                  if (!localStorage.getItem('theme')) {
                      this.dark = e.matches;
                  }
              });
              
              // Inicializar Web Speech API para Búsqueda por Voz
              const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
              if (SpeechRecognition) {
                  this.recognition = new SpeechRecognition();
                  this.recognition.lang = 'es-ES';
                  this.recognition.continuous = false;
                  this.recognition.interimResults = false;
                  
                  this.recognition.onstart = () => {
                      this.isListening = true;
                      if (navigator.vibrate) navigator.vibrate(45);
                  };
                  
                  this.recognition.onend = () => {
                      this.isListening = false;
                  };
                  
                  this.recognition.onerror = (e) => {
                      console.error('Speech recognition error:', e.error);
                      this.isListening = false;
                  };
                  
                  this.recognition.onresult = (event) => {
                      const transcript = event.results[0][0].transcript;
                      let input = document.querySelector('input[name=txtTag]');
                      if (input) {
                          input.value = transcript;
                          if (navigator.vibrate) navigator.vibrate([30, 30]);
                          this.loading = true;
                          setTimeout(() => {
                              input.form.submit();
                          }, 500);
                      }
                  };
              }
          },
          toggleVoiceSearch() {
              if (!this.recognition) {
                  alert('La búsqueda por voz no es compatible con este navegador. Por favor, inténtalo en Google Chrome, Safari o Microsoft Edge.');
                  return;
              }
              if (this.isListening) {
                  this.recognition.stop();
              } else {
                  this.recognition.start();
              }
          },
          exitApp() {
              if (navigator.vibrate) navigator.vibrate(35);
              window.close();
              this.showExitModal = true;
          },
          toggleTheme() {
              this.dark = !this.dark;
              document.documentElement.classList.toggle('dark', this.dark);
              localStorage.setItem('theme', this.dark ? 'dark' : 'light');
              if (navigator.vibrate) navigator.vibrate(30);
          },
          clearSearch() {
              if (navigator.vibrate) navigator.vibrate(45);
              this.view = 'search';
              let form = document.querySelector('form');
              if (form) {
                  let inputs = form.querySelectorAll('input[type=text]');
                  inputs.forEach(i => i.value = '');
                  let checkboxes = form.querySelectorAll('input[type=checkbox]');
                  checkboxes.forEach(c => {
                      if (c.id === 'chkPresente' || c.id === 'chkAusente' || c.id === 'chkIndeterminado' || c.id === 'chkPresencial' || c.id === 'chkTeletrabajo') {
                          c.checked = true;
                      } else {
                          c.checked = false;
                      }
                  });
                  let selects = form.querySelectorAll('select');
                  selects.forEach(s => s.value = '0');
              }
              window.history.pushState({}, '', 'mobile.php');
          }
      }">

    <!-- FONDO OPTIMIZADO (Sin blur pesado para Safari) -->
    <div class="fixed inset-0 -z-10 pointer-events-none bg-slate-50 dark:bg-slate-900 transition-colors duration-500">
        <div class="absolute inset-0 opacity-40 dark:opacity-20 bg-[radial-gradient(circle_at_top_left,_var(--tw-gradient-stops))] from-blue-200 via-transparent to-transparent"></div>
        <div class="absolute inset-0 opacity-40 dark:opacity-20 bg-[radial-gradient(circle_at_bottom_right,_var(--tw-gradient-stops))] from-emerald-200 via-transparent to-transparent"></div>
    </div>

    <!-- NAVEGACIÓN SUPERIOR (Sticky) -->
    <header class="fixed top-0 left-0 right-0 z-50 glass-morphism border-b border-white/20 dark:border-white/5 px-4 h-16 flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-3">
            <template x-if="view === 'results'">
                <button @click="clearSearch()" class="w-10 h-10 flex items-center justify-center rounded-full active:bg-slate-200 dark:active:bg-slate-800 transition-colors">
                    <i class="fas fa-arrow-left text-blue-500"></i>
                </button>
            </template>
            <div class="flex flex-col">
                <span class="text-xs font-black uppercase tracking-widest text-slate-400 dark:text-slate-500">Directorio</span>
                <span class="font-bold text-slate-900 dark:text-white leading-tight"><?php echo $nameAyto; ?></span>
            </div>
        </div>
        
        <div class="flex items-center gap-2">
            <button @click="toggleTheme()" class="w-10 h-10 rounded-2xl flex items-center justify-center bg-slate-100 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 shadow-inner">
                <i class="fas" :class="dark ? 'fa-sun' : 'fa-moon'"></i>
            </button>
            
            <?php if (!empty($_SESSION['ldap_user'])): ?>
            <button @click="exitApp()" class="w-10 h-10 rounded-2xl flex items-center justify-center bg-slate-100 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 shadow-inner hover:text-rose-500 active:scale-95 transition-all" title="Salir de la aplicación">
                <i class="fas fa-sign-out-alt text-sm"></i>
            </button>
            <?php endif; ?>
        </div>
    </header>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="h-full pt-16 overflow-y-auto px-4 pb-8">
        
        <!-- VISTA: BUSCADOR -->
        <div x-show="view === 'search'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="flex flex-col h-full pt-2 max-w-md mx-auto">
                <div class="mb-6 flex justify-center w-full px-4 text-center">
                    <img src="images/escudo.svg" alt="Escudo" class="w-36 h-auto object-contain brightness-0 dark:invert opacity-95 transition-transform active:scale-95">
                </div>
                <div class="text-center mb-6 px-4">
                    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white leading-tight">Busca a alguien</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-medium whitespace-nowrap overflow-hidden text-ellipsis">Encuentra contactos rápidamente</p>
                </div>

            <form action="mobile.php" method="GET" @submit="loading = true" class="space-y-4">
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-blue-500 transition-transform group-focus-within:scale-110">
                        <i class="fas fa-user-tag text-lg"></i>
                    </div>
                    <input type="text" name="txtTag" placeholder="Nombre, cargo o palabra clave..." class="block w-full pl-12 pr-14 py-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all shadow-lg shadow-slate-200/50 dark:shadow-none" value="<?php echo $_REQUEST['txtTag'] ?? ''; ?>">
                    <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center">
                        <button type="button" @click="toggleVoiceSearch()" class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 relative active:scale-95 transform" :class="isListening ? 'bg-rose-500 text-white animate-pulse' : 'bg-slate-100 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 hover:text-blue-500'">
                            <i class="fas text-sm" :class="isListening ? 'fa-microphone-slash' : 'fa-microphone'"></i>
                            <template x-if="isListening">
                                <span class="absolute inset-0 rounded-full bg-rose-500/30 animate-ping"></span>
                            </template>
                        </button>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="space-y-1.5 px-1">
                        <label class="text-[11px] font-black uppercase tracking-widest text-slate-400 ml-1">Departamento</label>
                        <div class="relative [&>select]:w-full [&>select]:pl-4 [&>select]:pr-10 [&>select]:py-3.5 [&>select]:bg-white dark:[&>select]:bg-slate-900 [&>select]:border [&>select]:border-slate-200 dark:[&>select]:border-slate-800 [&>select]:rounded-2xl [&>select]:text-sm [&>select]:font-semibold [&>select]:appearance-none [&>select]:outline-none [&>select]:shadow-sm">
                            <?php print fill_combobox('mobile.php', 'department', 'txtDepartamento', 'w-full', $_REQUEST['txtDepartamento'] ?? '0'); ?>
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1.5 px-1">
                        <label class="text-[11px] font-black uppercase tracking-widest text-slate-400 ml-1">Ubicación</label>
                        <div class="relative [&>select]:w-full [&>select]:pl-4 [&>select]:pr-10 [&>select]:py-3.5 [&>select]:bg-white dark:[&>select]:bg-slate-900 [&>select]:border [&>select]:border-slate-200 dark:[&>select]:border-slate-800 [&>select]:rounded-2xl [&>select]:text-sm [&>select]:font-semibold [&>select]:appearance-none [&>select]:outline-none [&>select]:shadow-sm">
                            <?php print fill_combobox('mobile.php', 'physicaldeliveryofficename', 'txtOficina', 'w-full', $_REQUEST['txtOficina'] ?? '0'); ?>
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="space-y-1.5 px-1">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400 ml-1">Estado</label>
                    <div class="px-1 py-1 grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-3 p-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm active:bg-slate-50 dark:active:bg-slate-800 transition-colors cursor-pointer">
                            <input id="chkPresente" type="checkbox" name="chkPresente" value="1" <?php echo (!isset($_GET['btnBuscar']) || isset($_REQUEST['chkPresente']) ? 'checked' : ''); ?> class="w-5 h-5 text-emerald-600 bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 rounded-lg focus:ring-emerald-500 focus:ring-offset-0 transition-all cursor-pointer">
                            <span class="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-tight">Presentes</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm active:bg-slate-50 dark:active:bg-slate-800 transition-colors cursor-pointer">
                            <input id="chkAusente" type="checkbox" name="chkAusente" value="1" <?php echo (!isset($_GET['btnBuscar']) || isset($_REQUEST['chkAusente']) ? 'checked' : ''); ?> class="w-5 h-5 text-rose-600 bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 rounded-lg focus:ring-rose-500 focus:ring-offset-0 transition-all cursor-pointer">
                            <span class="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-tight">Ausentes</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm active:bg-slate-50 dark:active:bg-slate-800 transition-colors cursor-pointer">
                            <input id="chkIndeterminado" type="checkbox" name="chkIndeterminado" value="1" <?php echo (!isset($_GET['btnBuscar']) || isset($_REQUEST['chkIndeterminado']) ? 'checked' : ''); ?> class="w-5 h-5 text-slate-500 bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 rounded-lg focus:ring-slate-400 focus:ring-offset-0 transition-all cursor-pointer">
                            <span class="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-tight">Indet.</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm active:bg-slate-50 dark:active:bg-slate-800 transition-colors cursor-pointer">
                            <input id="chkInactivo" type="checkbox" name="chkInactivo" value="1" <?php echo (isset($_GET['btnBuscar']) && isset($_REQUEST['chkInactivo']) ? 'checked' : ''); ?> class="w-5 h-5 text-blue-600 bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 rounded-lg focus:ring-blue-500 focus:ring-offset-0 transition-all cursor-pointer">
                            <span class="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-tight">Inactivos</span>
                        </label>
                    </div>
                </div>

                <div class="space-y-1.5 px-1">
                    <label class="text-[11px] font-black uppercase tracking-widest text-slate-400 ml-1">Modalidad</label>
                    <div class="px-1 py-1 grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-3 p-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm active:bg-slate-50 dark:active:bg-slate-800 transition-colors cursor-pointer">
                            <input id="chkPresencial" type="checkbox" name="chkPresencial" value="1" <?php echo (!isset($_GET['btnBuscar']) || isset($_REQUEST['chkPresencial']) ? 'checked' : ''); ?> class="w-5 h-5 text-cyan-600 bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 rounded-lg focus:ring-cyan-500 focus:ring-offset-0 transition-all cursor-pointer">
                            <span class="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-tight"><i class="fas fa-building text-[9px] opacity-60 mr-0.5"></i> Presencial</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm active:bg-slate-50 dark:active:bg-slate-800 transition-colors cursor-pointer">
                            <input id="chkTeletrabajo" type="checkbox" name="chkTeletrabajo" value="1" <?php echo (!isset($_GET['btnBuscar']) || isset($_REQUEST['chkTeletrabajo']) ? 'checked' : ''); ?> class="w-5 h-5 text-violet-600 bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 rounded-lg focus:ring-violet-500 focus:ring-offset-0 transition-all cursor-pointer">
                            <span class="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-tight"><i class="fas fa-house-laptop text-[9px] opacity-60 mr-0.5"></i> Teletrabajo</span>
                        </label>
                    </div>
                </div>

                <div class="pt-4">
                    <input type="hidden" name="btnBuscar" value="1">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-3xl shadow-xl shadow-blue-500/25 transition-all active:scale-95 flex items-center justify-center gap-3" :disabled="loading">
                        <template x-if="!loading">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-arrow-right"></i> Buscar Ahora
                            </span>
                        </template>
                        <template x-if="loading">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-circle-notch fa-spin"></i> Buscando...
                            </span>
                        </template>
                    </button>
                </div>
            </form>
        </div>

        <!-- VISTA: RESULTADOS -->
        <div x-show="view === 'results'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-10" x-transition:enter-end="opacity-100 translate-x-0" class="max-w-2xl mx-auto space-y-4 pt-4">
            
            <?php if ($searchResults): ?>
                <div class="flex flex-col gap-4">
                    <?php new_filter(); ?>
                </div>
            <?php endif; ?>

            <!-- Botón flotante para nueva búsqueda -->
            <button @click="clearSearch()" class="fixed bottom-6 right-6 w-14 h-14 bg-blue-600 text-white rounded-full shadow-2xl flex items-center justify-center active:scale-90 transition-transform z-[60]">
                <i class="fas fa-search"></i>
            </button>
        </div>

    </main>

    <!-- MODAL PREMIUM: SALIR DE LA APP -->
    <div x-show="showExitModal" 
         x-transition:enter="transition ease-out duration-300" 
         x-transition:enter-start="opacity-0" 
         x-transition:enter-end="opacity-100" 
         x-transition:leave="transition ease-in duration-200" 
         x-transition:leave-start="opacity-100" 
         x-transition:leave-end="opacity-0" 
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md animate-fade-in"
         style="display: none;"
         @click.self="showExitModal = false">
        
        <div x-show="showExitModal" 
             x-transition:enter="transition ease-out duration-300 transform" 
             x-transition:enter-start="opacity-0 translate-y-8 scale-95" 
             x-transition:enter-end="opacity-100 translate-y-0 scale-100" 
             x-transition:leave="transition ease-in duration-200 transform" 
             x-transition:leave-start="opacity-100 translate-y-0 scale-100" 
             x-transition:leave-end="opacity-0 translate-y-8 scale-95" 
             class="bg-white dark:bg-slate-800 rounded-3xl p-6 max-w-sm w-full shadow-2xl border border-white/20 dark:border-slate-700/80 text-center relative overflow-hidden">
            
            <!-- Icon/Branding -->
            <div class="w-14 h-14 bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-2xl flex items-center justify-center mx-auto mb-4 text-xl shadow-inner">
                <i class="fas fa-mobile-alt"></i>
            </div>
            
            <h3 class="text-lg font-black text-slate-900 dark:text-white leading-tight mb-2">¡Hasta pronto!</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed font-medium mb-6">
                Para salir de la aplicación de forma segura en tu iPhone, simplemente deslízala hacia arriba desde el gestor de aplicaciones de tu dispositivo.
            </p>
            
            <button @click="showExitModal = false" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 px-6 rounded-2xl shadow-lg shadow-blue-500/25 active:scale-95 transition-transform text-xs uppercase tracking-wider">
                Entendido
            </button>
            
            <!-- Botón cerrar esquina -->
            <button @click="showExitModal = false" class="absolute top-4 right-4 w-7 h-7 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
    </div>

</body>
</html>
