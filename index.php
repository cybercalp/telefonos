<?php
// Bootstrap: session security + HTTP security headers + config
require_once __DIR__ . '/bootstrap.php';

// Evitar el almacenamiento en caché del navegador para cargar datos AD en tiempo real
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Funciones de debug
require_once(__DIR__ . '/lib/debug_to_console.php');
// Carga del combobox
require_once(__DIR__ . '/lib/fillcombobox.php');
// Filtros LDAP
require_once(__DIR__ . '/lib/ldap_newfilter.php');
// Chequeo de la IP
require_once(__DIR__ . '/lib/checkip.php');

// Comprobar sincronización de presencia Saviacloud (Global)
require_once(__DIR__ . '/lib/db_presencia_select.php');
require_once(__DIR__ . '/lib/csrf.php');
require_once(__DIR__ . '/lib/remember_me.php');
if (empty($_SESSION['ldap_user'])) {
    check_remember_me();
}

// === DETECCIÓN MÓVIL Y REDIRECCIÓN ===
if (!isset($_SESSION['desktop_mode'])) {
    if (isset($_GET['desktop'])) {
        $_SESSION['desktop_mode'] = true;
    } else {
        $useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4))) {
            header('Location: ./mobile.php');
            exit;
        }
    }
}

// Guardar la URL de búsqueda actual para permitir el retorno exacto tras una edición
if (isset($_GET['btnBuscar'])) {
    $_SESSION['last_search_url'] = $_SERVER['REQUEST_URI'];
}

// Limpiar datos de usuario de la sesión al entrar (evita que los filtros se auto-rellenen)
unset($_SESSION['user_data']);

$client_ip = getIP();
$allowed_ip = ipAllowed($client_ip);

// Si la IP NO está permitida Y NO hay sesión iniciada, redirigimos obligatoriamente al login
if (empty($_SESSION['ldap_user']) && !$allowed_ip) {
    header('Location: ./login.php');
    exit;
}

// Si la IP NO está permitida Y el usuario está autenticado pero NO HA VERIFICADO 2FA
if (!$allowed_ip && !empty($_SESSION['ldap_user']) && empty($_SESSION['2fa_verified'])) {
    // Si tiene secreto, a totp.php. Si no, error (no debería llegar aquí sin secreto si login.php funciona bien)
    if (!empty($_SESSION['secretkey'])) {
        header('Location: ./totp.php');
    } else {
        // Caso de seguridad: Sesión activa pero sin 2FA configurado siendo externo
        session_destroy();
        header('Location: ./login.php');
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-50 dark:bg-slate-900 antialiased">
<head>
<script>
    (function() {
        const theme = localStorage.getItem('theme') || (document.cookie.match(/(^| )theme=([^;]+)/) || [])[2];
        if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    })();
</script>

<title>Directorio - <?php echo (string)$nameAyto; ?></title>
<meta name="csrf-token" content="<?php echo get_csrf_token(); ?>">
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="shortcut icon" type="image/x-icon" href="images/telefono.png" />

<!-- Tailwind CSS -->
<link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <script src="js/directory.js?v=<?php echo filemtime(__DIR__ . '/js/directory.js'); ?>"></script>
    <script src="js/computers.js?v=<?php echo filemtime(__DIR__ . '/js/computers.js'); ?>"></script>

<!-- Sortable.js for Drag & Drop support -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" integrity="sha384-eeLEhtwdMwD3X9y+8P3Cn7Idl/M+w8H4uZqkgD/2eJVkWIN1yKzEj6XegJ9dL3q0" crossorigin="anonymous"></script>
<!-- Alpine.js para dinamismo de vistas (grid/list) sin recargar página -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" integrity="sha384-Rpe/8orFUm5Q1GplYBHxbuA8Az8O8C5sAoOsdbRWkqPjKFaxPgGZipj4zeHL7lxX" crossorigin="anonymous"></script>
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0" crossorigin="anonymous">
</head>

<body class="h-full overflow-hidden flex flex-col bg-slate-50 dark:bg-slate-900 transition-colors duration-300" x-data="{
    viewMode: (function() {
        const match = document.cookie.match(/(^| )viewMode=([^;]+)/);
        return match ? match[2] : 'list';
    })(),
    gridCols: (function() {
        const match = document.cookie.match(/(^| )gridCols=([^;]+)/);
        return match ? parseInt(match[2]) : 3;
    })(),
    dark: document.documentElement.classList.contains('dark'),
    toggleTheme() {
        this.dark = !this.dark;
        if (this.dark) {
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
            document.cookie = 'theme=dark; path=/; max-age=31536000';
        } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
            document.cookie = 'theme=light; path=/; max-age=31536000';
        }
    },
    init() {
        this.$watch('viewMode', value => {
            document.cookie = 'viewMode=' + value + '; path=/; max-age=31536000';
            this.replayCards();
        });
        this.$watch('gridCols', value => {
            document.cookie = 'gridCols=' + value + '; path=/; max-age=31536000';
            this.replayCards();
        });
    },
    replayCards() {
        const cards = document.querySelectorAll('.card-animated');
        cards.forEach((card, idx) => {
            card.classList.remove('card-animated');
            void card.offsetHeight;
            card.classList.add('card-animated');
            card.style.animationDelay = (idx * 40) + 'ms';
        });
    }
}">

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

    <!-- Contenedor Principal Dashboard -->
    <div class="flex-1 flex overflow-hidden bg-slate-50 dark:bg-slate-900 relative">
        
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

        <!-- Main Content Central: Resultados (Se desplaza haciendo scroll independiente) -->
        <main class="flex-1 overflow-y-auto scroll-smooth relative">
            
            <?php
            // Alertas globales interactivas 
            if ((isset($_SESSION['mensaje_css'])) && ($_SESSION['mensaje_css'] == 'yes')) {
                unset($_SESSION['mensaje']);
                unset($_SESSION['mensaje_css']);
            }
            ?>
            
            <!-- Aquí el PHP inyectará las fichas dinámicamente -->
            <div id="results-container" class="max-w-7xl mx-auto">
                <?php
                // Ejecuta la búsqueda y renderiza el contenido
                new_filter();
                ?>
            </div>
            
        </main>
    </div>

    <!-- No Bootstrap. Pure Tailwind / Alpine -->
    <script src="js/secretary.js?v=<?php echo filemtime(__DIR__ . '/js/secretary.js'); ?>"></script>

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

    <!-- MODAL: Gestión de Extensiones de Equipos (Solo Admin) -->
    <?php if (!empty($_SESSION['ldap_user']) && is_admin_user()): ?>
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

        <div class="bg-white/95 dark:bg-slate-900/80 max-h-[85vh] rounded-3xl shadow-2xl overflow-hidden border border-slate-200 dark:border-slate-700 transform transition-all backdrop-blur-xl flex flex-col"
             style="width: 60%; min-width: 700px; max-width: 1400px;"
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
                    <div class="border-l border-slate-200 dark:border-slate-700/50 px-6" style="flex: 0 0 25%; max-width: 25%;">
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
    <?php endif; ?>

<!-- Global HTML Tooltip (outside cards to avoid overflow-hidden + transform clipping) -->
<div id="global-html-tooltip" class="html-tooltip" style="display:none;"></div>
</body>
</html>
