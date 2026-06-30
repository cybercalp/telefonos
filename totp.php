<?php
// Bootstrap: session security + HTTP security headers + config
require_once __DIR__ . '/bootstrap.php';

//Funciones para debug
require_once(__DIR__ . '/lib/debug_to_console.php');
//Funciones para mostrar mensajes
require_once(__DIR__ . '/lib/sendmessage.php');
//Funcion para la validación 2FA
require_once(__DIR__ . '/lib/check2fa.php');

require_once(__DIR__ . '/lib/checkip.php');
require_once(__DIR__ . '/lib/remember_me.php');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['csrf_token_ok'] = true;
}

// Bypass 2FA si la IP es permitida (Red Interna)
$client_ip = getIP();
if (ipAllowed($client_ip)) {
    $_SESSION['2fa_verified'] = true;
    if (isset($_SESSION['remember_me_flag'])) {
        set_remember_me($_SESSION['ldap_user']);
        unset($_SESSION['remember_me_flag']);
    }
    $urlRedirect = $_SESSION['origen_login'] ?? 'index';
    header('Location: ./' . $urlRedirect);
    exit;
}

// Generar check para el control de los tokens
if (empty($_SESSION['csrf_token_ok'])) {
   $_SESSION['csrf_token_ok'] = true;
}

//Comprobamos si pasamos un usario
if(isset($_GET['user'])){
   $ldap_only_user = $_GET['user'];
}else{
   $ldap_only_user = 0;
}

// Prevenir acceso sin POST válido y comprobar si la IP está bloqueada
include_once('./lib/preventvalidpost.php');

//Generar un token CSRF único si no existe
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// LÓGICA DE NEGOCIO BACKEND (Movida al principio para permitir setcookie)
if ($_SERVER['REQUEST_METHOD'] === 'POST')  {
    if ($_SESSION['csrf_token_ok'] === true) {
        if (isset($_SESSION['secretkey'])) {
            check_2FA($_SESSION['secretkey'], $_POST['txtChallenge']);
            if ($_SESSION['mensaje_css'] == 'yes') {
                if (isset($_SESSION['remember_me_flag'])) {
                    set_remember_me($_SESSION['ldap_user']);
                    unset($_SESSION['remember_me_flag']);
                }
                $_SESSION['2fa_verified'] = true;
                if (isset($_SESSION['origen_login']))  {
                    $urlRedirect = $_SESSION['origen_login'];
                    unset($_SESSION['mensaje'], $_SESSION['mensaje_css'], $_SESSION['csrf_token'], $_SESSION['csrf_token_ok'], $_SESSION['secretkey']);
                } else {
                    $urlRedirect = 'index';
                    unset($_SESSION['mensaje'], $_SESSION['mensaje_css'], $_SESSION['csrf_token'], $_SESSION['csrf_token_ok'], $_SESSION['secretkey']);
                }
                echo '<script>window.location.href="./'.$urlRedirect.'";</script>';
                exit;
            }
        } else {
            $_SESSION['mensaje'] = array('Faltan datos para la validación.');
            $_SESSION['mensaje_css'] = 'no';
            echo '<script>window.location.href="'.$_SERVER['REQUEST_URI'].'";</script>';
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-50 dark:bg-slate-900">
<head>

<script>
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
</script>

<title>TOTP (Authenticator) - <?php echo $nameAyto; ?></title>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Tailwind CSS via CDN -->
<link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">



<!-- Alpine.js to handle minor visual reactivity -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" integrity="sha384-Rpe/8orFUm5Q1GplYBHxbuA8Az8O8C5sAoOsdbRWkqPjKFaxPgGZipj4zeHL7lxX" crossorigin="anonymous"></script>
<script>document.addEventListener('DOMContentLoaded',function(){if(typeof Alpine==='undefined'){var s=document.createElement('script');s.src='js/vendor/alpine@3.13.3.min.js';s.defer=!0;document.head.appendChild(s)}})</script>
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha384-t1nt8BQoYMLFN5p42tRAtuAAFQaCQODekUVeKKZrEnEyp4H2R0RHFz0KWpmj7i8g" crossorigin="anonymous">
<script>
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

<style>
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
<body class="bg-gradient-animate h-full flex items-center justify-center p-4 text-slate-700 dark:text-slate-200 antialiased relative overflow-hidden" x-data="{ loading: false }">

    <!-- Ambient glows -->
    <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-30"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-emerald-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-30"></div>

    <div class="w-full max-w-md glass-panel rounded-3xl p-8 relative z-10 transition-all duration-300">
        <!-- Logo Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center h-20 w-20 rounded-full bg-white/70 dark:bg-slate-800/50 ring-4 ring-slate-200/50 dark:ring-slate-700/50 mb-4 shadow-lg">
                <i class="fas fa-mobile-alt text-4xl text-blue-400"></i>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-white mb-2">Autenticación TOTP</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Introduce el código generado por<br>tu aplicación Authenticator.</p>
        </div>

        <!-- Alertas Error PHP -->
        <?php if (!empty($_SESSION['mensaje'])): ?>
            <div class="mb-6 p-4 rounded-xl <?php echo ($_SESSION['mensaje_css'] == 'yes') ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border border-rose-500/30 text-rose-400'; ?> flex flex-col gap-2 shadow-inner">
                <?php foreach($_SESSION['mensaje'] as $msg): ?>
                    <p class="text-sm flex items-center gap-2"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Formulario TOTP -->
        <form method="post" action="<?php print $_SERVER['REQUEST_URI']; ?>" class="space-y-6" accept-charset="utf-8" @submit="loading = true">
            
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500 dark:text-slate-400">
                        <i class="fas fa-key"></i>
                    </div>
                    <input id="txtChallenge" type="text" inputmode="numeric" minlength="6" maxlength="10" pattern="\d{6}(\d{4})?" title="Ingrese un número entre 6 y 10 dígitos." name="txtChallenge" placeholder="000000" required autofocus autocomplete="off" autocapitalize="none" class="block w-full pl-12 pr-4 py-4 text-center tracking-[0.5em] text-2xl font-mono bg-white/90 dark:bg-slate-800/80 border border-slate-700/80 rounded-2xl text-slate-800 dark:text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" />
                </div>
            </div>

            <button type="submit" name="btnSend" id="btnSend" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-sm text-sm font-medium text-slate-800 dark:text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-900 focus:ring-blue-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed group relative overflow-hidden" :disabled="loading">
                <span x-show="!loading" class="flex items-center gap-2 relative z-10"><i class="fas fa-shield-check"></i> Verificar Código</span>
                <span x-show="loading" class="flex items-center gap-2 relative z-10" style="display: none;"><i class="fas fa-circle-notch fa-spin"></i> Comprobando...</span>
                <div class="absolute inset-0 h-full w-full opacity-0 group-hover:opacity-20 transition-opacity bg-gradient-to-r from-transparent via-white to-transparent -translate-x-full group-hover:translate-x-full duration-1000 ease-in-out"></div>
            </button>
        </form>

        <!-- Botón Cancelar -->
        <div class="mt-6 text-center">
            <a href="./logout?requestoken=<?php echo urlencode($csrf_token); ?>" class="inline-flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 hover:text-rose-400 transition-colors">
                <i class="fas fa-arrow-left"></i> Cancelar inicio de sesión
            </a>
        </div>

        <?php
        // LA LOGICA DE POST SE HA MOVIDO AL PRINCIPIO DEL ARCHIVO PARA PERMITIR SETCOOKIE
        ?>

        <!-- Footer Logo -->
        <div class="mt-8 text-center text-xs text-slate-500 dark:text-slate-400 border-t border-slate-200/50 dark:border-slate-700/50 pt-6">
            &copy; <?php echo date('Y'); ?> <?php echo $nameAyto; ?>
        </div>
    </div>
</body>
</html>

