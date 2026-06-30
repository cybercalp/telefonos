<?php
// Bootstrap: session security + HTTP security headers + config
require_once __DIR__ . '/bootstrap.php';

//Funciones para debug
require_once(__DIR__ . '/lib/debug_to_console.php');
//Funciones para mostrar mensajes
require_once(__DIR__ . '/lib/sendmessage.php');
//Funciones para el chequeo de la IP
require_once(__DIR__ . '/lib/checkip.php');
//Funciones para la validación del usuario
require_once(__DIR__ . '/lib/ldap_validateuser.php');
// Funciones para recuperación de contraseña
require_once(__DIR__ . '/lib/ldap_validatemail.php');
require_once(__DIR__ . '/lib/sendmail.php');
require_once(__DIR__ . '/lib/checktoken.php');
require_once(__DIR__ . '/lib/ldap_setnewpwd.php');
require_once(__DIR__ . '/lib/remember_me.php');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['csrf_token_ok'] = true;
}

// Obtenemos IP del cliente
$client_ip = getIP();
// Buscamos si es una IP permitida
$allowed_ip = ipAllowed($client_ip);

// Detectar si es móvil para la lógica de "Recordar sesión"
$useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$is_mobile_device = preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4));

// Intentar auto-login si existe cookie persistente
if (empty($_SESSION['ldap_user']) && check_remember_me()) {
    $destino = isset($_SESSION['origen_login']) ? $_SESSION['origen_login'] : './index';
    if ($destino === 'index') $destino = './index';
    header('Location: ' . $destino);
    exit;
}



// Si ya está autenticado, ir a index directamente
if (!empty($_SESSION['ldap_user'])) {
    $destino = isset($_SESSION['origen_login']) ? $_SESSION['origen_login'] : './index';
    if ($destino === 'index') $destino = './index';
    header('Location: ' . $destino);
    exit;
}

// Si 'from' está presente, reconstruimos la URL sin él
if (isset($_GET['from'])) {
   $_SESSION['origen_login'] = $_GET['from'];

    // Parsear la URL actual
    $params = $_GET;
    unset($params['from']); // Quitar solo 'from'

    // Reconstruir la query string sin 'from'
    $query = http_build_query($params);

    // Obtener la ruta actual sin parámetros (o usar el alias si existe)
    $baseUrl = strtok($_SERVER["REQUEST_URI"], '?');

    // Redirigir a la misma página sin 'from', pero dejando los otros parámetros (como rescue o user)
    $redirectUrl = $baseUrl . ($query ? "?$query" : "");
    header("Location: $redirectUrl");
    exit;
}

//Comprobamos si pasamos un usario
if(isset($_GET['user'])){
   $ldap_only_user = $_GET['user'];
}else{
   $ldap_only_user = 0;
}

// Generar check para el control de los tokens
if (!isset($_SESSION['csrf_token_ok'])) {
   $_SESSION['csrf_token_ok'] = true;
}

if (!isset($_SESSION['bloqueo_activo'])) {
   $_SESSION['bloqueo_activo'] = false;
}

// Prevenir acceso sin POST válido y comprobar si la IP está bloqueada
include_once('./lib/preventvalidpost.php');

// Procesar TOKEN de recuperación si existe
$recovery_user = '';
$show_reset_modal = false;

$current_token = '';
if (isset($_GET['token'])) {
    $current_token = $_GET['token'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnReset']) && isset($_SESSION['reset_token'])) {
    $current_token = $_SESSION['reset_token'];
} else {
    // Si es un acceso normal GET sin token, limpiamos el token de la sesión para evitar que aparezca el modal de forma residual
    unset($_SESSION['reset_token'], $_SESSION['reset_attempts']);
}

if (!empty($current_token)) {
    $recovery_user = check_token($current_token);
    if ($_SESSION['mensaje_css'] === 'yes') {
        $show_reset_modal = true;
        $_SESSION['reset_token'] = $current_token; // Guardar en sesión para reintentos
        if (!isset($_SESSION['reset_attempts'])) $_SESSION['reset_attempts'] = 0;
        unset($_SESSION['mensaje'], $_SESSION['mensaje_css']);
    } else {
        // Token inválido o caducado
        unset($_SESSION['reset_token'], $_SESSION['reset_attempts']);
    }
}

// Procesar POST de RECUPERACIÓN (Solicitud)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnRescue'])) {
    if ($_SESSION['csrf_token_ok'] === true) {
        validate_mail($_POST['txtRescueMail']);
        if ($_SESSION['mensaje_css'] === 'yes') {
            // El usuario ahora se obtiene automáticamente desde validate_mail a través de la sesión
            $found_user = $_SESSION['rescue_username'] ?? '';
            send_mail('rescue', $_POST['txtRescueMail'], $found_user);
            $destino = $_SESSION['origen_login'] ?? 'login';
            header('Location: ' . $destino);
            exit;
        }
    }
}

// Procesar POST de RESET (Cambio real)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnReset'])) {
    if ($_SESSION['csrf_token_ok'] === true) {
        if ($_POST['txtNewPwd'] === $_POST['txtConfirmPwd']) {
            set_user_password($_POST['txtResetUser'], $_POST['txtNewPwd']);
            if ($_SESSION['mensaje_css'] === 'yes') {
                // Enviar correo de confirmación al mail principal y al de recuperación
                $reset_mail = $_SESSION['reset_user_mail'] ?? '';
                $reset_recovery = $_SESSION['reset_user_recoverymail'] ?? '';
                $reset_sn = $_SESSION['reset_user_sn'] ?? '';
                if (!empty($reset_mail)) send_mail('changePassword', $reset_mail, $reset_sn);
                if (!empty($reset_recovery) && $reset_recovery !== $reset_mail) send_mail('changePassword', $reset_recovery, $reset_sn);
                unset($_SESSION['reset_user_mail'], $_SESSION['reset_user_recoverymail'], $_SESSION['reset_user_sn']);

                require_once('./lib/db_pendinguser_del.php');
                del_pending_users($_SESSION['reset_token'], $_POST['txtResetUser'], time()); 
                unset($_SESSION['reset_token'], $_SESSION['reset_attempts']);
                $destino = $_SESSION['origen_login'] ?? 'login';
                header('Location: ' . $destino);
                exit;
            } else {
                $_SESSION['reset_attempts']++;
                if ($_SESSION['reset_attempts'] >= 3) {
                    require_once('./lib/db_pendinguser_del.php');
                    del_pending_users($_SESSION['reset_token'], $_POST['txtResetUser'], time());
                    unset($_SESSION['reset_token'], $_SESSION['reset_attempts']);
                    $_SESSION['mensaje'][] = 'Has superado el número máximo de intentos. Solicita un nuevo enlace.';
            $destino = $_SESSION['origen_login'] ?? 'login';
            header('Location: ' . $destino);
            exit;
        }
        $show_reset_modal = true;
    }
} else {
    $mensajes_pwd = array('Las contraseñas no coinciden.');
    $_SESSION['mensaje'] = $mensajes_pwd;
    $_SESSION['mensaje_css'] = 'no';
    $show_reset_modal = true;
}
    }
}

//Generar un token CSRF único si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// LOGICA DE POST BACKEND (Movida al principio para permitir setcookie)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['btnRescue']) && !isset($_POST['btnReset']))  {
    if ($_SESSION['csrf_token_ok'] === true) {
        validate_user($_POST['txtUserName'], $_POST['txtUserPwd']);
        if ($_SESSION['mensaje_css'] == 'yes') {
            // Mejoras de seguridad: Prevención de Session Fixation y rotación de CSRF
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']);
            
            if (isset($_POST['chkRemember'])) {
                $_SESSION['remember_me_flag'] = true;
            }
            if ($allowed_ip) {
                if (isset($_SESSION['remember_me_flag'])) {
                    set_remember_me($_SESSION['ldap_user']);
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
            if (isset($_SESSION['secretkey']) && !empty($_SESSION['secretkey'])) {
                unset($_SESSION['mensaje'], $_SESSION['mensaje_css'], $_SESSION['csrf_token'], $_SESSION['csrf_token_ok']);
                echo '<script>window.location.href="./totp";</script>';
                exit;
            } else {
                $_SESSION['mensaje'] = array('El acceso desde fuera de la red corporativa requiere tener configurado el Doble Factor (TOTP). Active esta opción en su perfil desde un equipo interno.');
                $_SESSION['mensaje_css'] = 'no';
                echo '<script>window.location.href="'.$_SERVER['REQUEST_URI'].'";</script>';
                exit;
            }
        }
        if ($_SESSION['bloqueo_activo'] === true) {
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

<title>Inicio de Sesión - <?php echo $nameAyto; ?></title>
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
/* Animaciones y utilidades de cristal */
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
<body class="bg-gradient-animate h-full flex items-center justify-center p-4 text-slate-700 dark:text-slate-200 antialiased relative overflow-hidden" x-data="{ loading: false, showRescue: <?php echo (isset($_GET['rescue'])) ? 'true' : 'false'; ?>, showReset: <?php echo $show_reset_modal ? 'true' : 'false'; ?> }">

    <!-- Ambient background glows -->
    <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-purple-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40"></div>

    <div class="w-full max-w-md glass-panel rounded-3xl p-8 relative z-10 transition-all duration-300">
        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="./" class="inline-block transform hover:scale-105 transition-transform">
                <!-- Filtro 'brightness-0 dark:invert' convierte mágicamente el PNG negro a blanco puro -->
                <img src="images/escudo.svg" alt="Escudo" class="h-20 w-auto mx-auto mb-4 drop-shadow-lg brightness-0 dark:invert opacity-90" onerror="this.style.display='none'">
                <h1 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-white">Inicio de Sesión</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1"><?php echo $nameAyto; ?></p>
            </a>
        </div>

        <!-- Alertas de Error PHP integradas estilizadas -->
        <?php 
        $mensajes_para_mostrar = $_SESSION['mensaje'] ?? array();
        $clase_mensaje = ($_SESSION['mensaje_css'] ?? 'no') == 'yes' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border border-rose-500/30 text-rose-400';
        if (!empty($mensajes_para_mostrar)) unset($_SESSION['mensaje'], $_SESSION['mensaje_css']);
        ?>

        <?php if (!empty($mensajes_para_mostrar) && !$show_reset_modal): ?>
            <div x-data="{ showMsg: true }" x-show="showMsg" class="mb-6 p-4 rounded-xl <?php echo $clase_mensaje; ?> flex flex-col gap-2 shadow-inner relative group">
                <button type="button" @click="showMsg = false" class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity text-current/50 hover:text-current">
                    <i class="fas fa-times"></i>
                </button>
                <?php foreach($mensajes_para_mostrar as $msg): ?>
                    <p class="text-sm flex items-center gap-2 pr-6"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Formulario -->
        <form method="post" action="<?php print $_SERVER['REQUEST_URI']; ?>" class="space-y-6" accept-charset="utf-8" @submit="loading = true">
            
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div>
                <label for="txtUserName" class="block text-sm font-medium text-slate-600 dark:text-slate-300 mb-1">Nombre de Usuario</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 dark:text-slate-400">
                        <i class="fas fa-user"></i>
                    </div>
                    <?php if (!empty($ldap_only_user)): ?>
                        <input id="txtUserName" type="text" name="txtUserName" value="<?php echo htmlspecialchars($ldap_only_user); ?>" readonly class="block w-full pl-10 pr-3 py-2.5 bg-white/70 dark:bg-slate-800/50 border border-slate-200/50 dark:border-slate-700/50 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all sm:text-sm" />
                    <?php else: ?>
                        <input id="txtUserName" type="text" name="txtUserName" placeholder="Nombre de usuario" required autofocus class="block w-full pl-10 pr-3 py-2.5 bg-white/70 dark:bg-slate-800/50 border border-slate-200/50 dark:border-slate-700/50 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all sm:text-sm" />
                    <?php endif; ?>

                </div>
            </div>

            <div x-data="{ show: false }">
                <label for="txtUserPwd" class="block text-sm font-medium text-slate-600 dark:text-slate-300 mb-1">Contraseña</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 dark:text-slate-400">
                        <i class="fas fa-lock"></i>
                    </div>
                    <?php if (!empty($ldap_only_user)): ?>
                        <input id="txtUserPwd" :type="show ? 'text' : 'password'" name="txtUserPwd" placeholder="••••••••" required autofocus class="block w-full pl-10 pr-10 py-2.5 bg-white/70 dark:bg-slate-800/50 border border-slate-200/50 dark:border-slate-700/50 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all sm:text-sm" />
                    <?php else: ?>
                        <input id="txtUserPwd" :type="show ? 'text' : 'password'" name="txtUserPwd" placeholder="••••••••" required class="block w-full pl-10 pr-10 py-2.5 bg-white/70 dark:bg-slate-800/50 border border-slate-200/50 dark:border-slate-700/50 rounded-xl text-slate-700 dark:text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all sm:text-sm" />
                    <?php endif; ?>
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-blue-500 transition-colors focus:outline-none">
                        <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                    </button>
                </div>
            </div>

            <?php if ($remember_me_days > 0 && $is_mobile_device): ?>
            <div class="flex items-center gap-3 py-2 px-1">
                <div class="flex items-center">
                    <input id="chkRemember" name="chkRemember" type="checkbox" <?php echo $is_mobile_device ? 'checked' : ''; ?> class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-slate-300 dark:border-slate-700 rounded bg-white/50 dark:bg-slate-800/50 cursor-pointer">
                </div>
                <label for="chkRemember" class="block text-sm text-slate-600 dark:text-slate-400 cursor-pointer select-none">
                    No volver a solicitar credenciales en este dispositivo
                    <?php if ($is_mobile_device): ?>
                        <span class="text-[10px] text-blue-500 font-bold ml-1 uppercase">(Recomendado en móvil)</span>
                    <?php endif; ?>
                </label>
            </div>
            <?php endif; ?>

            <div class="flex items-center gap-3">
                <button type="submit" name="btnSend" id="btnSend" class="flex-1 flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-900 focus:ring-blue-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed group relative overflow-hidden" :disabled="loading">
                    <span x-show="!loading" class="flex items-center gap-2 relative z-10"><i class="fas fa-sign-in-alt"></i> Iniciar Sesión</span>
                    <span x-show="loading" class="flex items-center gap-2 relative z-10" style="display: none;"><i class="fas fa-circle-notch fa-spin"></i> Autenticando...</span>
                    <!-- Hover effect background -->
                    <div class="absolute inset-0 h-full w-full opacity-0 group-hover:opacity-20 transition-opacity bg-gradient-to-r from-transparent via-white to-transparent -translate-x-full group-hover:translate-x-full duration-1000 ease-in-out"></div>
                </button>
                
                <button type="button" @click="showRescue = true" class="text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-blue-500 dark:hover:text-blue-400 transition-colors py-2 px-3">
                    ¿Olvidaste tu contraseña?
                </button>
            </div>
        </form>

        <?php
        // LA LOGICA DE POST SE HA MOVIDO AL PRINCIPIO DEL ARCHIVO PARA PERMITIR SETCOOKIE
        ?>

        <!-- Footer Logo -->
        <div class="mt-8 text-center text-xs text-slate-500 dark:text-slate-400">
            <?php $redir_back = $_SESSION['origen_login'] ?? 'index.php'; ?>
            <a href="<?php echo $redir_back; ?>" class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-blue-500 hover:text-white dark:hover:bg-blue-600 transition-all font-medium mb-6">
                <i class="fas fa-arrow-left text-[10px]"></i> Volver <?php echo isset($_SESSION['origen_login']) ? 'a la aplicación' : 'al Directorio'; ?>
            </a>
            <p>&copy; <?php echo date("Y") . " " . $nameAyto; ?>.<br>Todos los derechos reservados.</p>
        </div>
    </div>

    <!-- Modal Anti Brute Force -->
    <?php if ($_SESSION['bloqueo_activo']): ?>
    <div x-data="{ open: true }" x-show="open" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-100/80 dark:bg-slate-900/80 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="open" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-2xl text-left overflow-hidden shadow-2xl border border-slate-200 dark:border-slate-700 transform transition-all sm:my-8 sm:align-middle sm:max-w-sm sm:w-full">
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
    
    <script>
        window.tiempoRestante = <?php echo json_encode($tiempo_restante) ?>;
    </script>
    <script src="./js/crono.js"></script>
    <?php endif; ?>

    <!-- Modal Recuperar Contraseña (Solicitud) -->
    <div x-show="showRescue" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-slate-950/40 backdrop-blur-md" @click="showRescue = false"></div>
            
            <div class="glass-panel w-full max-w-sm rounded-3xl p-8 relative z-50 shadow-2xl border border-white/10"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0">
                
                <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-2">Recuperar Contraseña</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Introduce el <strong>correo personal de recuperación</strong> que configuraste en tu <strong>Perfil</strong> o en el <strong>Portal del Empleado</strong>. No uses tu cuenta @ajcalp.es, ya que sin contraseña no podrías acceder a ella para recibir el enlace.</p>

                <form method="post" action="login" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div>
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Correo de recuperación</label>
                        <input type="email" name="txtRescueMail" required autofocus class="block w-full px-4 py-2.5 bg-white/50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all text-sm" placeholder="tu_correo_personal@gmail.com">
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button type="button" 
                                @click="<?php echo isset($_SESSION['origen_login']) ? "window.location.href='" . $_SESSION['origen_login'] . "'" : 'showRescue = false'; ?>" 
                                class="flex-1 py-2.5 px-4 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                            Cancelar
                        </button>
                        <button type="submit" name="btnRescue" class="flex-1 py-2.5 px-4 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-sm font-medium shadow-lg shadow-blue-600/20 transition-all">
                            Aceptar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Restablecer Contraseña (Acción) -->
    <div x-show="showReset" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-slate-950/40 backdrop-blur-md"></div>
            
            <div class="glass-panel w-full max-w-sm rounded-3xl p-8 relative z-50 shadow-2xl border border-white/10"
                 x-data="{ showNew: false, showConfirm: false }">
                
                <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-2">Nueva Contraseña</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-2">Establece tu nueva contraseña para continuar.</p>
                
                <?php if (!empty($mensajes_para_mostrar) && $show_reset_modal): ?>
                    <div class="mb-4 p-3 bg-rose-500/10 border border-rose-500/30 text-rose-400 rounded-xl text-xs flex flex-col gap-1">
                        <?php foreach($mensajes_para_mostrar as $msg): ?>
                            <p class="flex items-center gap-2"><i class="fas fa-times-circle"></i> <?php echo htmlspecialchars($msg); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['reset_attempts']) && $_SESSION['reset_attempts'] > 0): ?>
                    <div class="mb-4 p-2 bg-amber-500/10 border border-amber-500/30 text-amber-500 rounded-lg text-xs flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Intento <?php echo $_SESSION['reset_attempts']; ?> de 3.</span>
                    </div>
                <?php endif; ?>

                <form method="post" action="login" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div>
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Usuario</label>
                        <input type="text" name="txtResetUser" value="<?php echo htmlspecialchars($recovery_user); ?>" readonly class="block w-full px-4 py-2.5 bg-slate-100 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-400 dark:text-slate-500 outline-none text-sm cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Nueva Contraseña</label>
                        <div class="relative">
                            <input :type="showNew ? 'text' : 'password'" name="txtNewPwd" required class="block w-full px-4 py-2.5 bg-white/50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all text-sm">
                            <button type="button" @click="showNew = !showNew" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-blue-500 transition-colors">
                                <i class="fas" :class="showNew ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Confirmar Contraseña</label>
                        <div class="relative">
                            <input :type="showConfirm ? 'text' : 'password'" name="txtConfirmPwd" required class="block w-full px-4 py-2.5 bg-white/50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all text-sm">
                            <button type="button" @click="showConfirm = !showConfirm" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-blue-500 transition-colors">
                                <i class="fas" :class="showConfirm ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button type="button" 
                                @click="<?php echo isset($_SESSION['origen_login']) ? "window.location.href='" . $_SESSION['origen_login'] . "'" : "showReset = false; window.location.href='login'"; ?>" 
                                class="flex-1 py-2.5 px-4 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                            Cancelar
                        </button>
                        <button type="submit" name="btnReset" class="flex-1 py-2.5 px-4 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-sm font-medium shadow-lg shadow-blue-600/20 transition-all">
                            Aceptar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>

