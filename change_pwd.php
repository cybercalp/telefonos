<?php
// Bootstrap: session security + HTTP security headers + config
require_once __DIR__ . '/bootstrap.php';

//Funciones para debug
require_once(__DIR__ . '/lib/debug_to_console.php');
//Funciones para mostrar mensajes
require_once(__DIR__ . '/lib/sendmessage.php');
//Funciones para el chequeo de la IP
require_once(__DIR__ . '/lib/checkip.php');
//Funciones para el cambio de la contraseña
require_once(__DIR__ . '/lib/ldap_changepwd.php');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['csrf_token_ok'] = true;
}

// Si 'from' está presente, lo guardamos en la sesión para el redireccionamiento final
if (isset($_GET['from']) && !empty($_GET['from'])) {
    $_SESSION['origen_login'] = $_GET['from'];
    // Reconstruir la URL sin el parámetro 'from' para mantener la barra de direcciones limpia (Masking)
    $baseUrl = strtok($_SERVER["REQUEST_URI"], '?');
    $params = $_GET;
    unset($params['from']);
    $redirectUrl = $baseUrl . (count($params) ? '?' . http_build_query($params) : '');
    
    header("Location: $redirectUrl");
    exit;
}

// Prevenir acceso sin POST válido y comprobar si la IP está bloqueada
include_once(__DIR__ . '/lib/preventvalidpost.php');

// === LOGICA DE POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION['csrf_token_ok']) && $_SESSION['csrf_token_ok'] === true) {
        // Obtenemos el usuario de la sesión si viene de una ficha interna, o del POST
        $is_recovery = !empty($_SESSION['password_just_reset']);
        if ((isset($_SESSION['username'])) && (!empty($_SESSION['username']))) {
            $user_to_change = $_SESSION['username'];
            // Recovery flow: admin just reset the password; no plaintext password in session
            $old_pwd_to_change = $is_recovery ? '' : ($_SESSION['userpass'] ?? '');
        } else {
            $user_to_change = $_POST['txtUserName'] ?? '';
            $old_pwd_to_change = $_POST['txtUserPwd'] ?? '';
        }
        
        if (isset($_POST['txtUserNewPwd'])) {
            changePassword($user_to_change, $old_pwd_to_change, $_POST['txtUserNewPwd'], $_POST['txtUserNewRtPwd'], $is_recovery);
            // Clean plaintext password from session immediately after use
            unset($_SESSION['userpass']);
            
            if ($_SESSION['mensaje_css'] == 'yes') {
                unset($_SESSION['username'], $_SESSION['password_just_reset']);
                // Cierre de sesión: eliminamos variable de autenticación
                unset($_SESSION['ldap_user']);
                
                // Redirigir para limpiar el POST pero mantener los mensajes una carga más
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            }
            if ($_SESSION['bloqueo_activo'] === true) {
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            }
        }
    }
}


// Generar check para el control de los tokens
if (empty($_SESSION['csrf_token_ok'])) {
   $_SESSION['csrf_token_ok'] = true;
}

if (!isset($_SESSION['bloqueo_activo'])) {
   $_SESSION['bloqueo_activo'] = false;
}

//Comprobamos si pasamos un usario
if(isset($_GET['user'])){
   $ldap_only_user = $_GET['user'];
   
   // SEGURIDAD: Solo el propio usuario puede cambiar su contraseña
   if (isset($_SESSION['ldap_user']) && strcasecmp($_SESSION['ldap_user'], $ldap_only_user) !== 0) {
       $_SESSION['mensaje'] = array('No tienes permiso para cambiar la contraseña de otro usuario.');
       $_SESSION['mensaje_css'] = 'no';
       header('Location: ./index');
       exit;
   }
}else{
   $ldap_only_user = 0;
}

if ((isset($_SESSION['username'])) && (!empty($_SESSION['username']))) {
   $username = $_SESSION['username'];
}

//Obtenemos IP del cliente
$client_ip = getIP();
//Buscamos si es una IP permitida
$allowed_ip = ipAllowed($client_ip);

//Generar un token CSRF único si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-50 dark:bg-slate-900">
<head>

<script nonce="<?= $csp_nonce ?>">
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
</script>

<title>Cambio de Contraseña - <?php echo $nameAyto; ?></title>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Tailwind CSS via CDN -->
<link rel="stylesheet" href="css/style.css">



<!-- Alpine.js to handle minor visual reactivity -->
<script defer nonce="<?= $csp_nonce ?>" src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" integrity="sha384-Rpe/8orFUm5Q1GplYBHxbuA8Az8O8C5sAoOsdbRWkqPjKFaxPgGZipj4zeHL7lxX" crossorigin="anonymous"></script>
<script nonce="<?= $csp_nonce ?>">document.addEventListener('DOMContentLoaded',function(){if(typeof Alpine==='undefined'){var s=document.createElement('script');s.src='js/vendor/alpine@3.13.3.min.js';s.defer=!0;document.head.appendChild(s)}})</script>
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha384-t1nt8BQoYMLFN5p42tRAtuAAFQaCQODekUVeKKZrEnEyp4H2R0RHFz0KWpmj7i8g" crossorigin="anonymous">
<script nonce="<?= $csp_nonce ?>">
(function(){
    var faLoaded = false;
    var check = function() {
        if (faLoaded) return;
        var test = document.createElement('i');
        test.className = 'fas fa-check';
        test.style.cssText = 'position:absolute;visibility:hidden;font-size:20px;font-family:FontAwesome,"Font Awesome 6 Free"';
        document.body.appendChild(test);
        var w = test.offsetWidth;
        document.body.removeChild(test);
        if (w > 0) { faLoaded = true; return; }
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'css/vendor/fontawesome@6.5.1.min.css';
        document.head.appendChild(link);
        faLoaded = true;
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', check);
    else check();
    setTimeout(check, 3000);
})();
</script>
<script nonce="<?= $csp_nonce ?>" src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script nonce="<?= $csp_nonce ?>">window.jQuery||document.write('\x3Cscript src="js/vendor/jquery@3.7.1.min.js">\x3C/script>')</script>
<style nonce="<?= $csp_nonce ?>">
.glass-panel {
    background: rgba(255, 255, 255, 0.75);
}
.dark .glass-panel {
    background: rgba(30, 41, 59, 0.7);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}
.bg-gradient-animate {
    background: linear-gradient(120deg, #f8fafc, #e2e8f0, #f8fafc);
    background-size: 200% 200%;
    animation: gradientShift 10s ease infinite;
}
.dark .bg-gradient-animate {
    background: linear-gradient(120deg, #0f172a, #1e293b, #0f172a);
}
@keyframes gradientShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
</style>
</head>
<body class="bg-gradient-animate h-full flex flex-col items-center justify-center p-4 text-slate-700 dark:text-slate-200 antialiased relative overflow-x-hidden overflow-y-auto" x-data="{ loading: false }">

    <!-- Ambient background glows -->
    <div class="fixed top-[-10%] left-[-10%] w-96 h-96 bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 z-0 pointer-events-none"></div>
    <div class="fixed bottom-[-10%] right-[-10%] w-96 h-96 bg-purple-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 z-0 pointer-events-none"></div>

    <div class="w-full max-w-lg glass-panel rounded-3xl p-8 relative z-10 transition-all duration-300 mt-10 mb-10">
        
        <!-- Logo -->
            <?php
              $logo_redirect = $_SESSION['origen_login'] ?? ($allowed_ip ? './' : $UDS_URL);
            ?>
            <a href="<?php echo $logo_redirect; ?>" target="_self" class="inline-block transform hover:scale-105 transition-transform mb-2">
                <img src="./images/escudo.svg" alt="Escudo" class="h-16 w-auto mx-auto drop-shadow-lg brightness-0 dark:invert opacity-90" onerror="this.style.display='none'">
            </a>
            <h1 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-white mt-4">Cambio de Contraseña</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1"><?php echo $nameAyto; ?></p>
        </div>

        <!-- Toast Notification Container (Floating at the top) -->
        <!-- Version: 1.1 - Fixed Redirect & Persistence -->
        <div x-data="{ 
                show: <?php echo !empty($_SESSION['mensaje']) ? 'true' : 'false'; ?>,
                type: '<?php echo ($_SESSION['mensaje_css'] ?? 'no') == 'yes' ? 'success' : 'error'; ?>',
                init() {
                    if (this.show) {
                        if (this.type === 'success') {
                            setTimeout(() => {
                                window.location.href = '<?php echo $_SESSION['origen_login'] ?? 'login.php'; ?>';
                            }, 3000);
                        } else {
                            setTimeout(() => this.show = false, 5000);
                        }
                    }
                }
             }" 
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-8"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-8"
             class="fixed top-6 left-1/2 -translate-x-1/2 z-[100] w-full max-w-sm px-4"
             style="display: none;">
            
            <div :class="type === 'success' ? 'bg-emerald-500 border-emerald-400' : 'bg-rose-500 border-rose-400'"
                 class="p-4 rounded-2xl shadow-2xl border flex items-center gap-3 text-white">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                    <i class="fas" :class="type === 'success' ? 'fa-check' : 'fa-exclamation-triangle'"></i>
                </div>
                <div class="flex-1">
                    <?php if (!empty($_SESSION['mensaje'])): ?>
                        <?php foreach($_SESSION['mensaje'] as $msg): ?>
                            <p class="text-sm font-medium leading-tight"><?php echo htmlspecialchars($msg); ?></p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button @click="show = false" class="text-white/60 hover:text-white transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <?php 
        $success = (isset($_SESSION['mensaje_css']) && $_SESSION['mensaje_css'] == 'yes');
        // No unseteamos aquí para que el toast pueda verlo, pero lo haremos al final del script o después de leerlo.
        ?>
            <!-- FORMULARIO -->
            <form method="post" action="<?php print $_SERVER['REQUEST_URI']; ?>" accept-charset="utf-8" class="space-y-5" @submit="loading = true">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <!-- Usuario -->
                <div>
                    <label for="txtUserName" class="block text-sm font-medium text-slate-600 dark:text-slate-300 mb-1">Nombre de Usuario</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 dark:text-slate-400">
                            <i class="fas fa-user"></i>
                        </div>
                        <?php if (!empty($username)): ?>
                            <input id="txtUserName" type="text" name="txtUserName" value="<?php echo htmlspecialchars($username); ?>" disabled class="block w-full pl-10 pr-3 py-2.5 bg-white/90 dark:bg-slate-800/80 border border-slate-200/50 dark:border-slate-700/50 rounded-xl text-slate-500 dark:text-slate-400 cursor-not-allowed sm:text-sm" />
                        <?php elseif (!empty($ldap_only_user)): ?>
                            <input id="txtUserName" type="text" name="txtUserName" value="<?php echo htmlspecialchars($ldap_only_user); ?>" class="block w-full pl-10 pr-3 py-2.5 bg-white/70 dark:bg-slate-800/50 border border-slate-200/50 dark:border-slate-700/50 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all sm:text-sm" />
                        <?php else: ?>
                            <input id="txtUserName" type="text" name="txtUserName" placeholder="Nombre de usuario" autofocus required class="block w-full pl-10 pr-3 py-2.5 bg-white/70 dark:bg-slate-800/50 border border-slate-200/50 dark:border-slate-700/50 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all sm:text-sm" />
                        <?php endif; ?>

                    </div>
                </div>

                <!-- Contraseña Actual y Botón de Recuperación -->
                <div x-data="{ show: false }">
                    <label for="txtUserPwd" class="block text-sm font-medium text-slate-600 dark:text-slate-300 mb-1">Contraseña actual</label>
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 dark:text-slate-400">
                                <i class="fas fa-unlock"></i>
                            </div>
                            <?php if (!empty($username)): ?>
                                <input id="txtUserPwd" type="password" name="txtUserPwd" value="123456789" disabled class="block w-full pl-10 pr-10 py-2.5 bg-white/90 dark:bg-slate-800/80 border border-slate-200/50 dark:border-slate-700/50 rounded-xl text-slate-500 dark:text-slate-400 cursor-not-allowed sm:text-sm" />
                            <?php else: ?>
                                <input id="txtUserPwd" :type="show ? 'text' : 'password'" name="txtUserPwd" placeholder="••••••••" required class="block w-full pl-10 pr-10 py-2.5 bg-white/70 dark:bg-slate-800/50 border border-slate-200/50 dark:border-slate-700/50 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all sm:text-sm" />
                                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-blue-500 transition-colors focus:outline-none">
                                    <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                </div>

                <!-- INFO SECURITY -->
                <div class="bg-blue-50/50 dark:bg-blue-900/20 border border-blue-300/50 dark:border-blue-500/30 rounded-xl p-4 mt-6">
                    <p class="text-xs text-blue-600 dark:text-blue-300 mb-2 font-semibold"><i class="fas fa-shield-alt mr-1"></i> Requisitos de la contraseña:</p>
                    <ul class="text-[11px] text-blue-700/80 dark:text-blue-200/80 list-disc list-inside space-y-1 mb-2">
                        <li>Longitud mínima de <strong><?php echo $password_min_length ?></strong> caracteres.</li>
                        <li>No puede contener tu nombre o apellidos.</li>
                        <li>No puedes repetir las últimas 4 contraseñas.</li>
                    </ul>
                    <p class="text-[11px] text-blue-700/80 dark:text-blue-200/80 mb-1">Debe incluir caracteres de 3 de las 4 categorías:</p>
                    <ul class="text-[11px] text-blue-700/80 dark:text-blue-200/80 list-disc list-inside">
                        <li>Mayúsculas (A-Z) | Minúsculas (a-z)</li>
                        <li>Números (0-9) | Símbolos Permitidos</li>
                    </ul>
                </div>

                <!-- Nuevas contraseñas -->
                <div x-data="{ show: false }">
                    <label for="txtUserNewPwd" class="block text-sm font-medium text-slate-600 dark:text-slate-300 mb-1">Nueva Contraseña</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 dark:text-slate-400">
                            <i class="fas fa-key"></i>
                        </div>
                        <input id="txtUserNewPwd" :type="show ? 'text' : 'password'" name="txtUserNewPwd" placeholder="••••••••" required class="block w-full pl-10 pr-10 py-2.5 bg-white/70 dark:bg-slate-800/50 border border-slate-200/50 dark:border-slate-700/50 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all sm:text-sm" />
                        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-blue-500 transition-colors focus:outline-none">
                            <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                </div>

                <div class="pb-2" x-data="{ show: false }">
                    <label for="txtUserNewRtPwd" class="block text-sm font-medium text-slate-600 dark:text-slate-300 mb-1">Repita Nueva Contraseña</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 dark:text-slate-400">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <input id="txtUserNewRtPwd" :type="show ? 'text' : 'password'" name="txtUserNewRtPwd" placeholder="••••••••" required class="block w-full pl-10 pr-10 py-2.5 bg-white/70 dark:bg-slate-800/50 border border-slate-200/50 dark:border-slate-700/50 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all sm:text-sm" />
                        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-blue-500 transition-colors focus:outline-none">
                            <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                </div>

                <div class="flex gap-4 pt-2">
                    <?php 
                    $redirect_url_exit = $_SESSION['origen_login'] ?? './index';
                    if (!$allowed_ip && empty($_SESSION['origen_login'])) $redirect_url_exit = $UDS_URL; 
                    ?>
                    <a href="<?php echo $redirect_url_exit; ?>" target="_self"
                       class="w-1/3 flex justify-center items-center py-3 px-4 rounded-xl shadow-lg transition-all text-sm font-medium
                              <?php echo $success 
                                ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-white shadow-emerald-500/30 hover:scale-[1.02] active:scale-95' 
                                : 'bg-white/40 dark:bg-slate-800/40 backdrop-blur-sm border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-700/60'; ?>">
                        <i class="fas <?php echo $success ? 'fa-sign-out-alt' : 'fa-times'; ?> sm:mr-2"></i> 
                        <span class="hidden sm:inline"><?php echo $success ? 'Salir' : 'Cancelar'; ?></span>
                    </a>
                    <button type="submit" name="btnSend" id="btnSend" class="w-2/3 flex justify-center items-center py-3 px-4 border border-transparent rounded-xl shadow-md text-sm font-medium text-slate-800 dark:text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-900 focus:ring-blue-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed group relative overflow-hidden" :disabled="loading">
                        <span x-show="!loading" class="flex items-center gap-2 relative z-10"><i class="fas fa-save"></i> Actualizar</span>
                        <span x-show="loading" class="flex items-center gap-2 relative z-10" style="display: none;"><i class="fas fa-circle-notch fa-spin"></i> Procesando...</span>
                        <div class="absolute inset-0 h-full w-full opacity-0 group-hover:opacity-20 transition-opacity bg-gradient-to-r from-transparent via-white to-transparent -translate-x-full group-hover:translate-x-full duration-1000 ease-in-out"></div>
                    </button>
                </div>
            </form>
        <?php unset($_SESSION['mensaje'], $_SESSION['mensaje_css']); ?>
    </div>

    <!-- Footer Logo -->
    <div class="mt-auto mb-4 text-center text-xs text-slate-500 dark:text-slate-400 relative z-10 p-4">
        &copy; <?php echo date("Y") . " " . $nameAyto; ?>. Todos los derechos reservados.
    </div>

    <!-- Modal Anti Brute Force (Misma estética que login) -->
    <?php if ($_SESSION['bloqueo_activo']): ?>
    <div x-data="{ open: true }" x-show="open" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" class="fixed inset-0 bg-slate-100/80 dark:bg-slate-900/80 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="open" class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-2xl text-left overflow-hidden shadow-2xl border border-slate-200 dark:border-slate-700 transform transition-all sm:my-8 sm:align-middle sm:max-w-sm sm:w-full">
                <div class="bg-white dark:bg-slate-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4 border-b border-slate-200/50 dark:border-slate-700/50">
                    <div class="sm:flex sm:items-start text-center flex-col items-center">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-rose-500/20 text-rose-400 mb-4 ring-4 ring-rose-500/10">
                            <i class="fas fa-shield-alt text-xl"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-slate-800 dark:text-white text-center" id="modal-title">Acceso Bloqueado</h3>
                            <div class="mt-2 text-center text-sm text-slate-500 dark:text-slate-400">
                                <p>Tu IP está sujeta a una penalización temporal por intentos fallidos.</p>
                                <p class="mt-2 text-rose-400 font-semibold text-lg" id="cronometro">--:--</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script nonce="<?= $csp_nonce ?>">
        window.tiempoRestante = <?php echo json_encode($tiempo_restante) ?>;
    </script>
    <script nonce="<?= $csp_nonce ?>" src="./js/crono.js"></script>
    <?php endif; ?>

</body>
</html>