<?php
// Bootstrap: session security + HTTP security headers + config
require_once __DIR__ . '/bootstrap.php';

//Funciones para debug
require_once(__DIR__ . '/lib/debug_to_console.php');
//Funciones para mostrar mensajes
require_once(__DIR__ . '/lib/sendmessage.php');
//Funciones para envio de correo
require_once(__DIR__ . '/lib/sendmail.php');
//Funciones para la validación del correo de recuperación
require_once(__DIR__ . '/lib/ldap_validatemail.php');
//Funciones para el chequeo de la IP
require_once(__DIR__ . '/lib/checkip.php');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['csrf_token_ok'] = true;
}

// Si 'from' está presente, lo guardamos en la sesión para el redireccionamiento final
if (isset($_GET['from']) && !empty($_GET['from'])) {
    $_SESSION['origen_login'] = $_GET['from'];
    // Redirigir a la misma página sin el parámetro para mantener limpia la URL (Masking)
    $clean_url = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: $clean_url");
    exit;
}

// Generar check para el control de los tokens
if (empty($_SESSION['csrf_token_ok'])) {
   $_SESSION['csrf_token_ok'] = true;
}

if (!isset($_SESSION['bloqueo_activo'])) {
   $_SESSION['bloqueo_activo'] = false;
}

//Obtenemos IP del cliente
$client_ip = getIP();
//Buscamos si es una IP permitida
$allowed_ip = ipAllowed($client_ip);

// Prevenir acceso sin POST válido  y comprobar si la IP está bloqueada
include_once('./lib/preventvalidpost.php');

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

<title>Recuperación de Contraseña - <?php echo $nameAyto; ?></title>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">



<script defer nonce="<?= $csp_nonce ?>" src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" integrity="sha384-Rpe/8orFUm5Q1GplYBHxbuA8Az8O8C5sAoOsdbRWkqPjKFaxPgGZipj4zeHL7lxX" crossorigin="anonymous"></script>
<script nonce="<?= $csp_nonce ?>">document.addEventListener('DOMContentLoaded',function(){if(typeof Alpine==='undefined'){var s=document.createElement('script');s.src='js/vendor/alpine@3.13.3.min.js';s.defer=!0;document.head.appendChild(s)}})</script>
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

    <div class="fixed top-[-10%] left-[-10%] w-96 h-96 bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 z-0 pointer-events-none"></div>
    <div class="fixed bottom-[-10%] right-[-10%] w-96 h-96 bg-purple-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 z-0 pointer-events-none"></div>

    <div class="w-full max-w-lg glass-panel rounded-3xl p-8 relative z-10 transition-all duration-300 mt-10 mb-10">
        
        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="./" class="inline-block transform hover:scale-105 transition-transform mb-2">
                <img src="./images/escudo.svg" alt="Escudo" class="h-16 w-auto mx-auto drop-shadow-lg brightness-0 dark:invert opacity-90" onerror="this.style.display='none'">
            </a>
            <h1 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-white mt-4">Recuperar Contraseña</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1"><?php echo $nameAyto; ?></p>
        </div>

        <?php if (!empty($_SESSION['mensaje'])): ?>
            <div class="mb-6 p-4 rounded-xl <?php echo ($_SESSION['mensaje_css'] == 'yes') ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border border-rose-500/30 text-rose-400'; ?> flex flex-col gap-2 shadow-inner text-sm">
                <?php foreach($_SESSION['mensaje'] as $msg): ?>
                    <p class="flex items-center gap-2"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ((isset($_SESSION['mensaje_css'])) && ($_SESSION['mensaje_css'] == 'yes')): ?>
            <!-- ÉXITO -->
            <div class="text-center py-6">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 mb-4 ring-4 ring-emerald-500/10">
                    <i class="fas fa-check text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-2">Solicitud Enviada</h3>
                <p class="text-slate-600 dark:text-slate-300 text-sm mb-6">Gracias por utilizar el servicio de recuperación. Si la dirección es válida, recibirás instrucciones en breve.</p>
                
                <?php
                  $redirect_url_exit = $_SESSION['origen_login'] ?? ((!$allowed_ip) ? $UDS_URL : './index');
                ?>
                <button type="button" @click="window.location.href='<?php echo $redirect_url_exit; ?>'" class="w-full flex justify-center items-center py-3 px-4 border border-slate-300 dark:border-slate-600 rounded-xl shadow-sm text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-700 focus:outline-none transition-all">
                    <i class="fas fa-sign-out-alt mr-2"></i> Salir
                </button>
            </div>
        <?php else: ?>
            <!-- FORMULARIO -->
            <form method="post" action="rescue" class="space-y-6" accept-charset="utf-8" @submit="loading = true">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="bg-blue-50/50 dark:bg-blue-900/20 border border-blue-300/50 dark:border-blue-500/30 rounded-xl p-4 mb-2">
                    <p class="text-xs text-blue-700/90 dark:text-blue-200/90 leading-relaxed text-center"><i class="fas fa-info-circle mr-1 text-blue-400"></i> Este método solo funciona si previamente introdujiste un <strong>correo personal de recuperación</strong> en tu <strong>Perfil</strong> o en el <strong>Portal del Empleado</strong>. <br><br> Por favor, <strong>NO uses tu cuenta corporativa (@ajcalp.es)</strong>, ya que sin contraseña no podrías acceder a ella para recibir el enlace de recuperación.</p>
                </div>

                <div>
                    <label for="txtEMail" class="block text-sm font-medium text-slate-600 dark:text-slate-300 mb-1">Correo Electrónico de Recuperación</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 dark:text-slate-400">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <input id="txtEMail" type="email" name="txtEMail" maxlength="256" pattern="[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$" placeholder="tu_correo_personal@gmail.com" required autofocus class="block w-full pl-10 pr-3 py-3 bg-white/70 dark:bg-slate-800/50 border border-slate-200/50 dark:border-slate-700/50 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all sm:text-sm" />
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <?php
                      $redirect_url_cancel = $_SESSION['origen_login'] ?? './';
                    ?>
                    <button type="button" @click="window.location.href='<?php echo $redirect_url_cancel; ?>'" class="w-1/3 flex justify-center items-center py-3 px-4 border border-slate-300 dark:border-slate-600 rounded-xl shadow-sm text-sm font-medium text-slate-600 dark:text-slate-300 bg-transparent hover:bg-slate-800 transition-all">
                        Cancelar
                    </button>
                    <button type="submit" name="btnSend" id="btnSend" class="w-2/3 flex justify-center items-center py-3 px-4 border border-transparent rounded-xl shadow-md text-sm font-medium text-slate-800 dark:text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all group relative overflow-hidden" :disabled="loading">
                        <span x-show="!loading" class="flex items-center gap-2 relative z-10"><i class="fas fa-paper-plane"></i> Enviar Correo</span>
                        <span x-show="loading" class="flex items-center gap-2 relative z-10" style="display: none;"><i class="fas fa-circle-notch fa-spin"></i> Enviando...</span>
                        <div class="absolute inset-0 h-full w-full opacity-0 group-hover:opacity-20 transition-opacity bg-gradient-to-r from-transparent via-white to-transparent -translate-x-full group-hover:translate-x-full duration-1000 ease-in-out"></div>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Modal Anti Brute Force -->
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

    <!-- Footer Logo -->
    <div class="mt-auto mb-4 text-center text-xs text-slate-500 dark:text-slate-400 relative z-10 p-4">
        &copy; <?php echo date("Y") . " " . $nameAyto; ?>. Todos los derechos reservados.
    </div>

    <?php
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnSend'])) {
         if ($_SESSION['csrf_token_ok']===true) {
            validate_mail($_POST['txtEMail']);
            if ($_SESSION['mensaje_css'] == 'yes') {
               $address_to = isset($_POST['txtEMail']) ? htmlspecialchars($_POST['txtEMail'], ENT_QUOTES, 'UTF-8') : '';
               $address_name = $_SESSION['rescue_username'] ?? ''; 
               $function_call = 'rescue';
               send_mail($function_call, $address_to, $address_name);
              // Redirigir
              header('Location: rescue');
              exit;
	    }
            if ($_SESSION['bloqueo_activo'] === true) {
               // Redirigir para evitar reenvío al refrescar
               header('Location: rescue');
               exit;
            }
         }
      }
      print_message();
    ?>
</body>
</html>