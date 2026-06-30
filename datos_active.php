<?php
// Bootstrap: session security + HTTP security headers + config
require_once __DIR__ . '/bootstrap.php';

// Evitar el almacenamiento en caché del navegador para cargar datos AD en tiempo real
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

//Funciones para debug
require_once(__DIR__ . '/lib/debug_to_console.php');
//Funciones para mostrar mensajes
require_once(__DIR__ . '/lib/sendmessage.php');
//Funciones para la carga de los datos del combobox
require_once(__DIR__ . '/lib/fillcombobox.php');
//Funciones para la carga de datos del usuario
require_once(__DIR__ . '/lib/ldap_loaduser.php');
//Funciones para la modificación de los datos
require_once(__DIR__ . '/lib/ldap_changeuser.php');
//Funcion para la creación del secreto
require_once(__DIR__ . '/lib/googleauth.php');
require_once(__DIR__ . '/lib/csrf.php');

// Limpiar mensajes si no es POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    unset($_SESSION['mensaje']);
    unset($_SESSION['mensaje_css']);
}

// Generar check para el control de los tokens
if (empty($_SESSION['user_data'])) {
    $_SESSION['user_data'] = array();
}

// Control de Acceso: IP y 2FA
require_once('./lib/checkip.php');
$client_ip = getIP();
$allowed_ip = ipAllowed($client_ip);

if (empty($_SESSION['ldap_user']) && !$allowed_ip) {
    header('Location: ./login.php');
    exit;
}

if (!$allowed_ip && !empty($_SESSION['ldap_user']) && empty($_SESSION['2fa_verified'])) {
    if (!empty($_SESSION['secretkey'])) {
        header('Location: ./totp.php');
    } else {
        session_destroy();
        header('Location: ./login.php');
    }
    exit;
}

// LÓGICA DE PREPARACIÓN DE TOTP
$qrImage = '';
$secretKey = '';
$estado_toogle = 0;

// En POST: preservar las variables TOTP de sesión establecidas vía AJAX (settoogle.php)
// para que ldap_changeuser.php pueda leerlas y guardar el secreto en AD.
// En GET: limpiar y regenerar para evitar contaminación entre fichas de distintos usuarios.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Leer el estado TOTP de la sesión tal como lo dejó el AJAX del toggle
    $estado_toogle = isset($_SESSION['estado_toggle']) ? (int)$_SESSION['estado_toggle'] : 0;
    $secretKey = $_SESSION['secretkey'] ?? '';
    $qrImage = $_SESSION['image'] ?? '';
} else {
    // GET: limpiar y reconstruir desde AD
    unset($_SESSION['estado_toggle'], $_SESSION['secretkey'], $_SESSION['image'], $_SESSION['guid']);

    if ((isset($_SESSION['auth_user_dn'])) && (!empty($_SESSION['auth_user_dn']))) {
        load_userdata();
        $userData = $_SESSION['user_data'] ?? [];
        $GUID = (isset($userData['objectguid'])) ? (is_array($userData['objectguid']) ? $userData['objectguid'][0] : $userData['objectguid']) : '';

        if (isset($userData['pager']) && !empty($userData['pager'])) {
            $secretKey = (is_array($userData['pager'])) ? $userData['pager'][0] : $userData['pager'];
            $qrImage = (is_array($userData['photo'])) ? $userData['photo'][0] : $userData['photo'];
            $estado_toogle = 1;
        } else {
            $sam = (isset($userData['samaccountname'])) ? (is_array($userData['samaccountname']) ? $userData['samaccountname'][0] : $userData['samaccountname']) : '';
            $accountName = ($sam) ? $sam . '@' . $_SERVER['HTTP_HOST'] : '';
            if ($accountName) {
                try {
                    $secretKey = load_Secret($nameAyto, $accountName, $qrImage);
                } catch (\Throwable $e) {
                    $secretKey = 'Error: Librería GD no activa';
                    $qrImage = './images/notfound.png';
                    error_log("Error generando TOTP: " . $e->getMessage());
                }
            }
            $estado_toogle = 0;
        }

        if ($secretKey) {
            $_SESSION['estado_toggle'] = $estado_toogle;
            $_SESSION['secretkey'] = $secretKey;
            $_SESSION['image'] = $qrImage;
            $_SESSION['guid'] = $GUID;
        }
    }
}


if ((isset($_POST['btnUpdate'])) && (isset($_SESSION['editing_user_dn']))) {
    if (!verify_csrf_token(get_token_from_request())) {
        $_SESSION['mensaje'] = ['Error: Token CSRF inválido'];
        $_SESSION['mensaje_css'] = 'no';
    } else {
        update_ldap_data($_SESSION['editing_user_dn']);
        if ($_SESSION['mensaje_css'] == 'yes') {
            unset($_SESSION['editing_user_dn']);
            header('Location: ' . ($_SESSION['last_search_url'] ?? './index.php'));
            exit;
        }
    }
}

load_userdata();
$userData = $_SESSION['user_data'];
// NOTA: La lógica de carga ya se movió arriba para preparar TOTP, pero mantenemos esto por compatibilidad si es necesario
$homepage = (isset($userData['wwwhomepage'])) ? $userData['wwwhomepage'][0] : '1110';
$wValue = substr($homepage . '0000', 0, 4);
$sw1 = $wValue[0];
$sw2 = $wValue[1];
$sw3 = $wValue[2];
$sw4 = $wValue[3];

// Generar patrón de validación para dominios corporativos (Case-insensitive para el dominio)
$domainRegexes = [];
foreach (($corp_domains ?? ['ajcalp.es']) as $d) {
    $res = '';
    foreach (str_split($d) as $p) {
        if (ctype_alpha($p)) {
            $res .= '[' . strtoupper($p) . strtolower($p) . ']';
        } else if ($p == '.') {
            $res .= '\.';
        } else {
            $res .= $p;
        }
    }
    $domainRegexes[] = '(' . $res . ')';
}
// Regex: Prefijo de email estándar + @ + (lista de dominios corporativos)
$fullCorpPattern = "^[a-zA-Z0-9._%+\-]+@(" . implode('|', $domainRegexes) . ")$";
$corpDomainsList = implode(', ', ($corp_domains ?? ['ajcalp.es']));
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

    <title>Perfil - <?php echo $nameAyto; ?></title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo get_csrf_token(); ?>">

    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="css/style.css">
    

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
    <link href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" rel="stylesheet" integrity="sha384-oMy41mb/qJnpJlpXOF57hSu2KGi47l/UV9+tPNrBOs7/ap5Vubj/3phrCtjutHMQ" crossorigin="anonymous" onerror="this.onerror=null;this.href='css/vendor/cropper@1.5.13.min.css'">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" integrity="sha384-Rpe/8orFUm5Q1GplYBHxbuA8Az8O8C5sAoOsdbRWkqPjKFaxPgGZipj4zeHL7lxX" crossorigin="anonymous"></script>
    <script nonce="<?= $csp_nonce ?>">document.addEventListener('DOMContentLoaded',function(){if(typeof Alpine==='undefined'){var s=document.createElement('script');s.src='js/vendor/alpine@3.13.3.min.js';s.defer=!0;document.head.appendChild(s)}})</script>



    <style nonce="<?= $csp_nonce ?>">
        /* Utilities */
        .glass-panel {
            background: rgba(255, 255, 0, 0);
            /* Placeholder managed by Tailwind */
        }

        .bg-gradient-animate {
            background: linear-gradient(120deg, #f8fafc, #e2e8f0, #f8fafc);
            background-size: 200% 200%;
            animation: gradientShift 10s ease infinite;
        }

        .dark .bg-gradient-animate {
            background: linear-gradient(120deg, #0f172a, #1e293b, #0f172a);
        }

        @keyframes gradientShift {
            0% {
                background-position: 0% 50%
            }

            50% {
                background-position: 100% 50%
            }

            100% {
                background-position: 0% 50%
            }
        }

        /* Drop Zone pulse animation */
        @keyframes pulse-border {

            0%,
            100% {
                border-color: rgba(59, 130, 246, 0.5);
            }

            50% {
                border-color: rgba(59, 130, 246, 1);
            }
        }

        .drop-zone-active {
            animation: pulse-border 1.5s infinite;
            background-color: rgba(59, 130, 246, 0.1) !important;
        }

        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.2);
        }

        .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>

<body
    class="bg-gradient-animate min-h-screen flex flex-col justify-center items-center py-2 px-2 md:px-4 text-slate-700 dark:text-slate-200 antialiased relative">
    <div
        class="fixed top-[-10%] left-[-10%] w-96 h-96 bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 z-0 pointer-events-none">
    </div>
    <div
        class="fixed bottom-[-10%] right-[-10%] w-96 h-96 bg-purple-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 z-0 pointer-events-none">
    </div>

    <!-- Contenedor principal relanzado -->
    <div
        class="w-full max-w-5xl bg-white/90 dark:bg-slate-800/80 backdrop-blur-xl border border-slate-200 dark:border-slate-700/50 rounded-3xl p-4 md:p-5 shadow-2xl relative z-10 transition-all duration-300 group/card">
        <!-- COMPONENT TOTP (ARRIBA A LA DERECHA - DISEÑO ESCANEABLE) -->
        <div class="absolute top-4 right-4 md:top-5 md:right-7 z-20">
            <div
                class="bg-white/95 dark:bg-slate-800/95 backdrop-blur-xl border border-slate-200 dark:border-slate-700/60 rounded-3xl px-4 py-3 flex flex-row items-center gap-4 shadow-2xl hover:shadow-blue-500/10 transition-all group/totp border-t-white/20">
                <!-- QR Thumbnail (Escaneable directamente) -->
                <div @click="$dispatch('open-totp-modal')"
                    class="cursor-pointer relative group/qr">
                    <?php /* TOTP ACTIVO: mostrar QR real */ ?>
                    <img id="totpQRReal" src="<?php echo $qrImage; ?>"
                        class="w-32 h-32 rounded-[2rem] border-2 border-emerald-500 bg-white shadow-inner transition-all group-hover/qr:scale-105 <?php echo ($estado_toogle) ? '' : 'hidden'; ?>"
                        alt="QR">

                    <?php /* TOTP INACTIVO: QR ficticio denso con señal de prohibición */ ?>
                    <div id="totpQRPlaceholder"
                        class="w-32 h-32 rounded-[2rem] border-2 border-slate-300 dark:border-slate-600 bg-white shadow-inner transition-all group-hover/qr:scale-105 overflow-hidden relative flex items-center justify-center <?php echo ($estado_toogle) ? 'hidden' : ''; ?>">
                        <!-- QR ficticio: patrón denso realista en escala de grises -->
                        <svg width="112" height="112" viewBox="0 0 25 25" xmlns="http://www.w3.org/2000/svg"
                            style="opacity:0.55;">
                            <!-- Finder pattern: esquina superior izquierda -->
                            <rect x="0" y="0" width="7" height="7" fill="#111" />
                            <rect x="1" y="1" width="5" height="5" fill="white" />
                            <rect x="2" y="2" width="3" height="3" fill="#111" />
                            <!-- Finder pattern: esquina superior derecha -->
                            <rect x="18" y="0" width="7" height="7" fill="#111" />
                            <rect x="19" y="1" width="5" height="5" fill="white" />
                            <rect x="20" y="2" width="3" height="3" fill="#111" />
                            <!-- Finder pattern: esquina inferior izquierda -->
                            <rect x="0" y="18" width="7" height="7" fill="#111" />
                            <rect x="1" y="19" width="5" height="5" fill="white" />
                            <rect x="2" y="20" width="3" height="3" fill="#111" />
                            <!-- Timing patterns (líneas alternas) -->
                            <rect x="8" y="6" width="1" height="1" fill="#111" />
                            <rect x="10" y="6" width="1" height="1" fill="#111" />
                            <rect x="12" y="6" width="1" height="1" fill="#111" />
                            <rect x="14" y="6" width="1" height="1" fill="#111" />
                            <rect x="16" y="6" width="1" height="1" fill="#111" />
                            <rect x="6" y="8" width="1" height="1" fill="#111" />
                            <rect x="6" y="10" width="1" height="1" fill="#111" />
                            <rect x="6" y="12" width="1" height="1" fill="#111" />
                            <rect x="6" y="14" width="1" height="1" fill="#111" />
                            <rect x="6" y="16" width="1" height="1" fill="#111" />
                            <!-- Alignment pattern (centro) -->
                            <rect x="16" y="16" width="5" height="5" fill="#111" />
                            <rect x="17" y="17" width="3" height="3" fill="white" />
                            <rect x="18" y="18" width="1" height="1" fill="#111" />
                            <!-- Módulos de datos (zona superior central) -->
                            <rect x="8" y="0" width="1" height="1" fill="#111" />
                            <rect x="9" y="0" width="1" height="1" fill="#111" />
                            <rect x="11" y="0" width="1" height="1" fill="#111" />
                            <rect x="13" y="0" width="1" height="1" fill="#111" />
                            <rect x="15" y="0" width="1" height="1" fill="#111" />
                            <rect x="16" y="0" width="1" height="1" fill="#111" />
                            <rect x="8" y="1" width="1" height="1" fill="#111" />
                            <rect x="10" y="1" width="1" height="1" fill="#111" />
                            <rect x="12" y="1" width="1" height="1" fill="#111" />
                            <rect x="14" y="1" width="1" height="1" fill="#111" />
                            <rect x="17" y="1" width="1" height="1" fill="#111" />
                            <rect x="9" y="2" width="1" height="1" fill="#111" />
                            <rect x="11" y="2" width="1" height="1" fill="#111" />
                            <rect x="13" y="2" width="1" height="1" fill="#111" />
                            <rect x="16" y="2" width="1" height="1" fill="#111" />
                            <rect x="8" y="3" width="1" height="1" fill="#111" />
                            <rect x="10" y="3" width="1" height="1" fill="#111" />
                            <rect x="12" y="3" width="1" height="1" fill="#111" />
                            <rect x="15" y="3" width="1" height="1" fill="#111" />
                            <rect x="17" y="3" width="1" height="1" fill="#111" />
                            <rect x="9" y="4" width="1" height="1" fill="#111" />
                            <rect x="11" y="4" width="1" height="1" fill="#111" />
                            <rect x="14" y="4" width="1" height="1" fill="#111" />
                            <rect x="16" y="4" width="1" height="1" fill="#111" />
                            <rect x="8" y="5" width="1" height="1" fill="#111" />
                            <rect x="12" y="5" width="1" height="1" fill="#111" />
                            <rect x="13" y="5" width="1" height="1" fill="#111" />
                            <rect x="15" y="5" width="1" height="1" fill="#111" />
                            <!-- Módulos de datos (zona lateral izquierda) -->
                            <rect x="0" y="8" width="1" height="1" fill="#111" />
                            <rect x="1" y="8" width="1" height="1" fill="#111" />
                            <rect x="3" y="8" width="1" height="1" fill="#111" />
                            <rect x="5" y="8" width="1" height="1" fill="#111" />
                            <rect x="0" y="9" width="1" height="1" fill="#111" />
                            <rect x="2" y="9" width="1" height="1" fill="#111" />
                            <rect x="4" y="9" width="1" height="1" fill="#111" />
                            <rect x="1" y="10" width="1" height="1" fill="#111" />
                            <rect x="3" y="10" width="1" height="1" fill="#111" />
                            <rect x="5" y="10" width="1" height="1" fill="#111" />
                            <rect x="0" y="11" width="1" height="1" fill="#111" />
                            <rect x="2" y="11" width="1" height="1" fill="#111" />
                            <rect x="4" y="11" width="1" height="1" fill="#111" />
                            <rect x="1" y="12" width="1" height="1" fill="#111" />
                            <rect x="3" y="12" width="1" height="1" fill="#111" />
                            <rect x="5" y="12" width="1" height="1" fill="#111" />
                            <rect x="0" y="13" width="1" height="1" fill="#111" />
                            <rect x="2" y="13" width="1" height="1" fill="#111" />
                            <rect x="4" y="13" width="1" height="1" fill="#111" />
                            <rect x="1" y="14" width="1" height="1" fill="#111" />
                            <rect x="3" y="14" width="1" height="1" fill="#111" />
                            <rect x="0" y="15" width="1" height="1" fill="#111" />
                            <rect x="2" y="15" width="1" height="1" fill="#111" />
                            <rect x="4" y="15" width="1" height="1" fill="#111" />
                            <rect x="5" y="15" width="1" height="1" fill="#111" />
                            <rect x="1" y="16" width="1" height="1" fill="#111" />
                            <rect x="3" y="16" width="1" height="1" fill="#111" />
                            <!-- Módulos de datos (zona central) -->
                            <rect x="7" y="8" width="1" height="1" fill="#111" />
                            <rect x="8" y="8" width="1" height="1" fill="#111" />
                            <rect x="10" y="8" width="1" height="1" fill="#111" />
                            <rect x="12" y="8" width="1" height="1" fill="#111" />
                            <rect x="14" y="8" width="1" height="1" fill="#111" />
                            <rect x="7" y="9" width="1" height="1" fill="#111" />
                            <rect x="9" y="9" width="1" height="1" fill="#111" />
                            <rect x="11" y="9" width="1" height="1" fill="#111" />
                            <rect x="13" y="9" width="1" height="1" fill="#111" />
                            <rect x="15" y="9" width="1" height="1" fill="#111" />
                            <rect x="8" y="10" width="1" height="1" fill="#111" />
                            <rect x="10" y="10" width="1" height="1" fill="#111" />
                            <rect x="12" y="10" width="1" height="1" fill="#111" />
                            <rect x="14" y="10" width="1" height="1" fill="#111" />
                            <rect x="7" y="11" width="1" height="1" fill="#111" />
                            <rect x="9" y="11" width="1" height="1" fill="#111" />
                            <rect x="11" y="11" width="1" height="1" fill="#111" />
                            <rect x="13" y="11" width="1" height="1" fill="#111" />
                            <rect x="15" y="11" width="1" height="1" fill="#111" />
                            <rect x="8" y="12" width="1" height="1" fill="#111" />
                            <rect x="10" y="12" width="1" height="1" fill="#111" />
                            <rect x="12" y="12" width="1" height="1" fill="#111" />
                            <rect x="7" y="13" width="1" height="1" fill="#111" />
                            <rect x="9" y="13" width="1" height="1" fill="#111" />
                            <rect x="11" y="13" width="1" height="1" fill="#111" />
                            <rect x="13" y="13" width="1" height="1" fill="#111" />
                            <rect x="8" y="14" width="1" height="1" fill="#111" />
                            <rect x="10" y="14" width="1" height="1" fill="#111" />
                            <rect x="12" y="14" width="1" height="1" fill="#111" />
                            <rect x="14" y="14" width="1" height="1" fill="#111" />
                            <rect x="7" y="15" width="1" height="1" fill="#111" />
                            <rect x="9" y="15" width="1" height="1" fill="#111" />
                            <rect x="11" y="15" width="1" height="1" fill="#111" />
                            <rect x="13" y="15" width="1" height="1" fill="#111" />
                            <rect x="8" y="16" width="1" height="1" fill="#111" />
                            <rect x="10" y="16" width="1" height="1" fill="#111" />
                            <rect x="12" y="16" width="1" height="1" fill="#111" />
                            <!-- Módulos zona inferior derecha -->
                            <rect x="8" y="18" width="1" height="1" fill="#111" />
                            <rect x="10" y="18" width="1" height="1" fill="#111" />
                            <rect x="12" y="18" width="1" height="1" fill="#111" />
                            <rect x="14" y="18" width="1" height="1" fill="#111" />
                            <rect x="7" y="19" width="1" height="1" fill="#111" />
                            <rect x="9" y="19" width="1" height="1" fill="#111" />
                            <rect x="11" y="19" width="1" height="1" fill="#111" />
                            <rect x="13" y="19" width="1" height="1" fill="#111" />
                            <rect x="8" y="20" width="1" height="1" fill="#111" />
                            <rect x="10" y="20" width="1" height="1" fill="#111" />
                            <rect x="12" y="20" width="1" height="1" fill="#111" />
                            <rect x="7" y="21" width="1" height="1" fill="#111" />
                            <rect x="9" y="21" width="1" height="1" fill="#111" />
                            <rect x="11" y="21" width="1" height="1" fill="#111" />
                            <rect x="13" y="21" width="1" height="1" fill="#111" />
                            <rect x="8" y="22" width="1" height="1" fill="#111" />
                            <rect x="10" y="22" width="1" height="1" fill="#111" />
                            <rect x="12" y="22" width="1" height="1" fill="#111" />
                            <rect x="7" y="23" width="1" height="1" fill="#111" />
                            <rect x="9" y="23" width="1" height="1" fill="#111" />
                            <rect x="11" y="23" width="1" height="1" fill="#111" />
                            <rect x="8" y="24" width="1" height="1" fill="#111" />
                            <rect x="10" y="24" width="1" height="1" fill="#111" />
                            <rect x="12" y="24" width="1" height="1" fill="#111" />
                            <rect x="14" y="24" width="1" height="1" fill="#111" />
                        </svg>
                        <!-- Señal de PROHIBICIÓN: solo contorno rojo + diagonal, sin relleno opaco -->
                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                <!-- Círculo solo contorno, sin relleno sólido -->
                                <circle cx="50" cy="50" r="44" fill="none" stroke="#ef4444" stroke-width="8" />
                                <!-- Línea diagonal diagonal (de arriba-izq a abajo-der) -->
                                <line x1="19" y1="19" x2="81" y2="81" stroke="#ef4444" stroke-width="8"
                                    stroke-linecap="round" />
                            </svg>
                        </div>
                    </div>
                    <div
                        class="absolute inset-0 bg-black/0 group-hover/qr:bg-black/5 rounded-[2rem] transition-colors flex items-center justify-center">
                        <i
                            class="fas fa-expand text-white opacity-0 group-hover/qr:opacity-100 text-sm drop-shadow-md"></i>
                    </div>

                </div>

                <!-- Control Directo (DERECHA DEL QR) -->
                <div class="flex flex-col items-center gap-1 border-l border-slate-100 dark:border-slate-700 pl-4 py-1">
                    <span id="totpStatusText"
                        class="text-[7px] leading-tight font-black uppercase tracking-wider text-center <?php echo ($estado_toogle) ? 'text-emerald-500' : 'text-slate-400'; ?>">
                        2FA<br><?php echo ($estado_toogle) ? 'ACTIVO' : 'INACTIVO'; ?>
                    </span>
                    <div class="form-check form-switch m-0 p-0 transform scale-110">
                        <input class="form-check-input m-0 cursor-pointer" type="checkbox" id="directToggleTOTP" <?php echo ($estado_toogle) ? 'checked' : ''; ?>
                            data-original-state="<?php echo $estado_toogle; ?>">
                    </div>
                </div>
            </div>
        </div>


        <!-- CABECERA COMPACTA -->
        <div
            class="flex flex-col md:flex-row items-center md:items-start justify-start border-b border-slate-200 dark:border-slate-700/50 pb-2 mb-3 gap-4 md:gap-8">
            
            <!-- IMAGEN DE PERFIL Y BOTONES FIJOS DEBAJO -->
            <div class="flex flex-col items-center order-1 group md:mr-4">
                <div id="avatarContainer"
                    class="relative w-28 h-28 md:w-36 md:h-36 mb-2 ring-4 ring-white dark:ring-slate-700 rounded-3xl overflow-hidden shadow-2xl bg-slate-100 dark:bg-slate-800 border-2 border-transparent transition-all cursor-pointer group-hover:ring-blue-500/50"
                    title="Arrastra una foto aquí">
                    <?php
                    if (isset($userData['thumbnailphoto'])) {
                        echo '<img id="imgPhotoUser" src="' . $userData['thumbnailphoto'][0] . '" class="w-full h-full object-cover shadow-inner">';
                    } else {
                        echo '<img id="imgPhotoUser" src="./images/users.jpg" class="w-full h-full object-cover opacity-50 grayscale">';
                    }
                    ?>
                    <!-- Drop overlay -->
                    <div
                        class="absolute inset-0 bg-blue-600/20 backdrop-blur-sm flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                        <i class="fas fa-cloud-upload-alt text-white text-3xl"></i>
                    </div>
                </div>
                <!-- Botones ICONOS debajo de la imagen -->
                <div class="flex items-center gap-2">
                    <button id="btnPhotoChange" type="button"
                        class="p-2 rounded-xl bg-blue-600 text-white hover:bg-blue-500 shadow-lg shadow-blue-600/20 transition-all text-sm font-bold flex items-center justify-center border border-transparent"
                        title="Subir Foto">
                        <i class="fas fa-camera"></i>
                    </button>
                    <button id="btnPhotoDel" type="button"
                        class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-rose-600 hover:text-white transition-all text-sm font-bold flex items-center justify-center border border-slate-200 dark:border-slate-600"
                        title="Borrar Foto">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>


                <form id="frmPhoto" enctype="multipart/form-data" method="POST" action="./lib/load_photo.php"
                    style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; opacity: 0; pointer-events: none;">
                    <input type="file" id="txtPhoto" name="txtPhoto" accept="image/jpeg,image/png" style="display: block; width: 0; height: 0; opacity: 0; pointer-events: none; position: absolute;">
                    <input type="hidden" id="croppedPhoto" name="txtThumbnailPhoto" form="frmPhoto">
                    <button id="btnPhotoLoad" type="submit" name="btnLoadPhto">Subir</button>
                </form>
            </div>

            <div class="flex flex-col items-center md:items-start order-2 flex-1 mt-2 md:mt-0">
                <a href="./" class="inline-block transform hover:scale-105 transition-transform mb-4">
                    <img src="./images/escudo.svg" alt="Escudo" class="h-12 w-auto brightness-0 dark:invert opacity-90">
                </a>
                <h2
                    class="text-2xl md:text-4xl font-extrabold text-slate-800 dark:text-white mb-2 text-center md:text-left tracking-tight">
                    <?php if (isset($userData['displayname']))
                        echo $userData['displayname'][0]; ?>
                </h2>
                <div
                    class="text-slate-500 dark:text-slate-400 flex flex-wrap justify-center md:justify-start gap-3 md:gap-5 text-xs md:text-sm font-medium">
                    <?php if (isset($userData['employeenumber'])): ?>
                        <span
                            class="flex items-center gap-2 bg-blue-50/80 dark:bg-blue-900/20 border border-blue-100/50 dark:border-blue-800/30 px-3 py-1.5 rounded-full text-blue-700 dark:text-blue-300 shadow-sm"><i
                                class="fas fa-id-badge text-blue-500 opacity-80"></i>
                            <?php echo $userData['employeenumber'][0]; ?></span>
                    <?php endif; ?>
                    <?php if (isset($userData['samaccountname'])): ?>
                        <span
                            class="flex items-center gap-2 bg-emerald-50/80 dark:bg-emerald-900/20 border border-emerald-100/50 dark:border-emerald-800/30 px-3 py-1.5 rounded-full text-emerald-700 dark:text-emerald-300 shadow-sm"><i
                                class="fas fa-user text-emerald-500 opacity-80"></i>
                            <?php echo $userData['samaccountname'][0]; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="alertBoxImageChange" class="alert hidden text-center mb-2 mt-0 py-1 rounded-lg text-xs" role="alert">
        </div>

        <?php print_message('datos_active'); ?>

        <form id="frmMain" method="post" action="<?php print $_SERVER['REQUEST_URI']; ?>" accept-charset="utf-8">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-4 md:gap-x-6 gap-y-3">

                <!-- DATOS GENERALES -->
                <div class="space-y-3">
                    <h3
                        class="text-xs md:text-sm uppercase tracking-widest font-black text-blue-500 dark:text-blue-400 border-b border-slate-200 dark:border-slate-700/50 pb-1.5 mb-3">
                        <i class="fas fa-info-circle mr-1"></i> Datos Generales
                    </h3>

                    <div class="space-y-3">
                        <!-- FILA 1: NOMBRE Y APELLIDOS (PROPORCIÓN REFINADA) -->
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            <div class="md:col-span-5">
                                <label for="txtGivenName"
                                    class="flex items-center gap-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">
                                    Nombre <i class="fas fa-lock text-[10px] opacity-40" 
                                        title="Campo no modificable manualmente"></i>
                                </label>
                                <input id="txtGivenName" type="text" name="txtGivenName"
                                    value="<?php echo (isset($userData['givenname'])) ? $userData['givenname'][0] : ''; ?>"
                                    readonly 
                                    title="<?php echo (isset($userData['givenname'])) ? $userData['givenname'][0] : ''; ?> - Este campo solo puede ser modificado por Administración"
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-600 dark:text-slate-200 text-sm cursor-not-allowed transition-all shadow-sm" />
                            </div>
                            <div class="md:col-span-7">
                                <label for="txtSN"
                                    class="flex items-center gap-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">
                                    Apellidos <i class="fas fa-lock text-[10px] opacity-40" 
                                        title="Campo no modificable manualmente"></i>
                                </label>
                                <input id="txtSN" type="text" name="txtSN"
                                    value="<?php echo (isset($userData['sn'])) ? $userData['sn'][0] : ''; ?>" readonly
                                    
                                    title="<?php echo (isset($userData['sn'])) ? $userData['sn'][0] : ''; ?> - Este campo solo puede ser modificado por Administración"
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-600 dark:text-slate-200 text-sm cursor-not-allowed transition-all shadow-sm" />
                            </div>
                        </div>

                        <!-- FILA 2: INICIALES Y ALIAS -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-1">
                                <label for="txtInitials"
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Iniciales</label>
                                <input id="txtInitials" type="text" name="txtInitials"
                                    value="<?php echo (isset($userData['initials'])) ? $userData['initials'][0] : ''; ?>"
                                     title="Iniciales del nombre"
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm" />
                            </div>
                            <div class="md:col-span-3">
                                <label for="txtDisplayName"
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Alias
                                    (Nombre a Mostrar)</label>
                                <input id="txtDisplayName" type="text" name="txtDisplayName"
                                    value="<?php echo (isset($userData['displayname'])) ? $userData['displayname'][0] : ''; ?>"
                                     title="Nombre público que aparecerá en el directorio"
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm" />
                            </div>
                        </div>

                        <!-- FILA 3: PUESTO (FULL WIDTH) -->
                        <div class="w-full">
                            <label for="txtTitle"
                                class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Puesto
                                / Cargo</label>
                            <input id="txtTitle" type="text" name="txtTitle"
                                value="<?php echo (isset($userData['title'])) ? $userData['title'][0] : ''; ?>"
                                 title="Cargo oficial en el ayuntamiento"
                                class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm" />
                        </div>

                        <!-- FILA 4: UBICACIÓN (FULL WIDTH) -->
                        <div class="w-full">
                            <label
                                class="flex items-center gap-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">
                                Ubicación (Oficina) <i class="fas fa-lock text-[10px] opacity-40"
                                     title="Campo no modificable manualmente"></i>
                            </label>
                            <div class="relative group">
                                <input type="text"
                                    value="<?php echo (isset($userData['physicaldeliveryofficename'])) ? $userData['physicaldeliveryofficename'][0] : 'Sin asignar'; ?>"
                                    readonly 
                                    title="<?php echo (isset($userData['physicaldeliveryofficename'])) ? $userData['physicaldeliveryofficename'][0] : 'No hay ubicación asignada'; ?> • No modificable manualmente"
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-600 dark:text-slate-200 text-sm cursor-not-allowed transition-all truncate pr-10" />
                                <i
                                    class="fas fa-map-marker-alt absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 opacity-30"></i>
                                <input type="hidden" name="txtOffice"
                                    value="<?php echo (isset($userData['physicaldeliveryofficename'])) ? $userData['physicaldeliveryofficename'][0] : ''; ?>">
                            </div>
                        </div>

                        <!-- FILA 5: COMPAÑÍA Y DEPARTAMENTO -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="txtCompany"
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Compañía</label>
                                <input id="txtCompany" type="text" name="txtCompany"
                                    value="<?php echo (isset($userData['company'])) ? $userData['company'][0] : ''; ?>"
                                     title="Organismo o empresa a la que pertenece"
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm" />
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Departamento</label>
                                <div
                                    class="[&>select]:w-full [&>select]:px-4 [&>select]:py-2 [&>select]:bg-slate-50 dark:[&>select]:bg-slate-900 [&>select]:border [&>select]:border-slate-200 dark:[&>select]:border-slate-700 [&>select]:rounded-xl [&>select]:text-sm [&>select]:text-slate-700 dark:[&>select]:text-slate-200 shadow-sm transition-all focus-within:ring-2 focus-within:ring-blue-500">
                                    <?php
                                    $currDept = (isset($userData['department'])) ? $userData['department'][0] : null;
                                    print fill_combobox($_SERVER['SCRIPT_NAME'], 'department', 'txtDept1', 'w-full', $currDept);
                                    ?>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>

                <!-- DATOS DE CONTACTO -->

                <!-- DATOS DE CONTACTO -->
                <div class="space-y-3">
                    <h3
                        class="text-xs md:text-sm uppercase tracking-widest font-black text-emerald-500 dark:text-emerald-400 border-b border-slate-200 dark:border-slate-700/50 pb-1.5 mb-3">
                        <i class="fas fa-map-marked-alt mr-1"></i> Contacto y Ubicación
                    </h3>

                    <div class="space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="txtTelPhone"
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Ext.
                                    Interna</label>
                                <input id="txtTelPhone" type="text" name="txtTelPhone" maxlength="4" pattern="\d{4}"
                                    value="<?php echo (isset($userData['telephonenumber'])) ? $userData['telephonenumber'][0] : ''; ?>"
                                     title="Extensión interna de 4 dígitos"
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm"
                                    title="4 dígitos" />
                            </div>
                            <div>
                                <label for="txtTelMobile"
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Móvil
                                    Corto / Largo</label>
                                <input id="txtTelMobile" type="text" name="txtTelMobile" maxlength="16" pattern="(\d{4}\s*/\s*)?\d{9}"
                                    value="<?php echo (isset($userData['mobile'])) ? $userData['mobile'][0] : ''; ?>"
                                     title="Número de teléfono móvil (ej: 123456789 o 1234 / 123456789)"
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm"
                                    title="9 dígitos o formato nnnn / nnnnnnnnn" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="txtEmailAddress"
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Correo
                                    Corporativo</label>
                                <input id="txtEmailAddress" type="email" name="txtEmailAddress" maxlength="256"
                                    pattern="<?php echo $fullCorpPattern; ?>"
                                    value="<?php echo (isset($userData['mail'])) ? $userData['mail'][0] : ''; ?>"
                                     title="Dirección de correo electrónico institucional"
                                    data-corp-domains='<?php echo json_encode($corp_domains ?? ["ajcalp.es"]); ?>'
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm"
                                    title="Debe terminar en: <?php echo $corpDomainsList; ?>" />
                            </div>
                            <div>
                                <label for="txtEmailRestore"
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Email
                                    Recuperación</label>
                                <input id="txtEmailRestore" type="email" name="txtEmailRestore" maxlength="256"
                                    pattern="^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$"
                                    value="<?php echo (isset($userData['othermailbox'])) ? $userData['othermailbox'][0] : ''; ?>"
                                    
                                    title="Email externo para recuperación de contraseña. Use un email personal (no @ajcalp.es), ya que sin contraseña no podría recibir el enlace."
                                    data-corp-domains='<?php echo json_encode($corp_domains ?? ["ajcalp.es"]); ?>'
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm" />
                            </div>
                        </div>

                        <div>
                            <label for="txtAddress"
                                class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Dirección
                                Postal</label>
                            <input id="txtAddress" type="text" name="txtAddress"
                                value="<?php echo (isset($userData['streetaddress'])) ? $userData['streetaddress'][0] : ''; ?>"
                                 title="Domicilio o calle de la oficina"
                                class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm" />
                        </div>

                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-12 md:col-span-4">
                                <label for="txtCity"
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Ciudad</label>
                                <input id="txtCity" type="text" name="txtCity"
                                    value="<?php echo (isset($userData['l'])) ? $userData['l'][0] : ''; ?>"
                                     title="Localidad de trabajo"
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm" />
                            </div>
                            <div class="col-span-6 md:col-span-4">
                                <label for="txtState"
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Prov.</label>
                                <input id="txtState" type="text" name="txtState"
                                    value="<?php echo (isset($userData['st'])) ? $userData['st'][0] : ''; ?>"
                                     title="Provincia"
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm" />
                            </div>
                            <div class="col-span-6 md:col-span-4">
                                <label for="txtPostalCode"
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">C.P.</label>
                                <input id="txtPostalCode" type="text" name="txtPostalCode" maxlength="5" pattern="\d{5}"
                                    value="<?php echo (isset($userData['postalcode'])) ? $userData['postalcode'][0] : ''; ?>"
                                     title="Código Postal"
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm" />
                            </div>
                        </div>

                        <!-- SECCIÓN 3: PRIVACIDAD Y VISIBILIDAD -->
                        <div class="mt-3 group/privacy">
                            <label
                                class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">
                                <i class="fas fa-eye mr-1.5 opacity-60"></i> Privacidad y Visibilidad
                            </label>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3" x-data="{ 
                                s1: '<?php echo $sw1; ?>', 
                                s2: '<?php echo $sw2; ?>', 
                                s3: '<?php echo $sw3; ?>', 
                                s4: '<?php echo $sw4; ?>' 
                            }">
                                <!-- Switch 1: Mostrar -->
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-blue-500/30 transition-colors"
                                    
                                    title="Si se desactiva, tu perfil no será visible en las búsquedas generales del directorio.">
                                    <span
                                        class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Ver</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer" :checked="s1 === '1'"
                                            @change="s1 = $el.checked ? '1' : '0'">
                                        <input type="hidden" name="txtSwitch1" :value="s1">
                                        <div
                                            class="w-8 h-4 bg-slate-200 dark:bg-slate-700 rounded-full peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-500">
                                        </div>
                                    </label>
                                </div>

                                <!-- Switch 2: Foto -->
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-emerald-500/30 transition-colors"
                                    
                                    title="Controla si otros usuarios pueden ver tu fotografía en el directorio.">
                                    <span
                                        class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Foto</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer" :checked="s2 === '1'"
                                            @change="s2 = $el.checked ? '1' : '0'">
                                        <input type="hidden" name="txtSwitch2" :value="s2">
                                        <div
                                            class="w-8 h-4 bg-slate-200 dark:bg-slate-700 rounded-full peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-emerald-500">
                                        </div>
                                    </label>
                                </div>

                                <!-- Switch 3: Email -->
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-amber-500/30 transition-colors"
                                    
                                    title="Muestra u oculta tu dirección de correo electrónico institucional en tu ficha de contacto.">
                                    <span
                                        class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Email</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer" :checked="s3 === '1'"
                                            @change="s3 = $el.checked ? '1' : '0'">
                                        <input type="hidden" name="txtSwitch3" :value="s3">
                                        <div
                                            class="w-8 h-4 bg-slate-200 dark:bg-slate-700 rounded-full peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-amber-500">
                                        </div>
                                    </label>
                                </div>

                                <!-- Switch 4: Presencia -->
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:border-indigo-500/30 transition-colors"
                                    
                                    title="Permite que otros vean si estás disponible, ocupado o ausente en tiempo real.">
                                    <span
                                        class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">Presen.</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer" :checked="s4 === '1'"
                                            @change="s4 = $el.checked ? '1' : '0'">
                                        <input type="hidden" name="txtSwitch4" :value="s4">
                                        <div
                                            class="w-8 h-4 bg-slate-200 dark:bg-slate-700 rounded-full peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-indigo-500">
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>


                        <?php
                        if (isset($userData['thumbnailphoto'])) {
                            echo '<input type="hidden" id="txtThumbnailPhoto" name="txtThumbnailPhoto" value="' . htmlspecialchars($userData['thumbnailphoto'][0]) . '" form="frmMain">';
                        } else {
                            echo '<input type="hidden" id="txtThumbnailPhoto" name="txtThumbnailPhoto" form="frmMain">';
                        }
                        ?>
                    </div>
                </div>

                <!-- TAGS (BÚSQUEDA) -->
                <div class="lg:col-span-2">
                    <label for="txtInfo"
                        class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">
                        <i class="fas fa-tags mr-1.5 opacity-60"></i> Tags (Palabras clave para búsqueda)
                    </label>
                    <textarea id="txtInfo" name="txtInfo" rows="3" placeholder="Ej: RRHH, Nóminas, Técnico..."
                        
                        title="Palabras clave separadas por comas que facilitan su localización en el directorio"
                        class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all text-sm font-semibold resize-none shadow-sm"><?php echo (isset($userData['info'])) ? $userData['info'][0] : ''; ?></textarea>
                </div>
            </div>

            <!-- BOTONES DE ACCION MODERNOS -->
            <div
                class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700/50 flex flex-wrap gap-3 justify-between items-center">

                <div class="flex flex-wrap items-center gap-2">
                    <?php if (isset($userData['samaccountname'])): ?>
                        <a href="./change_pwd?user=<?php echo urlencode($userData['samaccountname'][0]); ?>"
                            class="px-3 py-2 rounded-lg text-xs font-bold border border-orange-500/40 text-orange-600 dark:text-orange-400 hover:bg-orange-500 hover:text-white transition-all shadow-md shadow-orange-500/10 flex items-center gap-1.5">
                            <i class="fas fa-key"></i> Contraseña
                        </a>
                    <?php else: ?>
                        <a href="./change_pwd"
                            class="px-3 py-2 rounded-lg text-xs font-bold border border-orange-500/40 text-orange-600 dark:text-orange-400 hover:bg-orange-500 hover:text-white transition-all shadow-md shadow-orange-500/10 flex items-center gap-1.5">
                            <i class="fas fa-key"></i> Contraseña
                        </a>
                    <?php endif; ?>

                    <div x-data>
                        <button type="button"
                            @click="$dispatch('open-subordinates-modal', {
                                dn: '<?php echo addslashes($_SESSION['editing_user_dn'] ?? $_SESSION['auth_user_dn']); ?>', 
                                name: '<?php echo addslashes($userData['displayname'][0] ?? ''); ?>',
                                isAdmin: <?php echo is_admin_user() ? 'true' : 'false'; ?>
                            })"
                            class="px-3 py-2 rounded-lg text-xs font-bold border border-blue-500/40 text-blue-600 dark:text-blue-400 hover:bg-blue-500 hover:text-white transition-all shadow-md shadow-blue-500/10 flex items-center gap-1.5">
                            <i class="fas fa-sitemap"></i> Jerarquía
                        </button>
                    </div>
                </div>

                <!-- DERECHA: Acciones principales -->
                <div class="flex items-center gap-2">
                    <a href="./datos_active"
                        class="px-3 py-2 rounded-lg text-xs font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all flex items-center gap-1.5"
                        title="Descartar cambios y recargar">
                        <i class="fas fa-rotate-left"></i> Restablecer
                    </a>
                    <button id="btnUpdate" type="submit" name="btnUpdate" value="Actualizar"
                        class="px-4 py-2 rounded-lg text-xs font-bold bg-blue-600 text-white hover:bg-blue-500 shadow-xl shadow-blue-600/20 transition-all flex items-center gap-1.5">
                        <i class="fas fa-floppy-disk"></i> Guardar
                    </button>
                    <a href="javascript:history.back()"
                        class="px-3 py-2 rounded-lg text-xs font-bold bg-rose-600/10 text-rose-600 dark:text-rose-400 hover:bg-rose-600 hover:text-white transition-all border border-rose-500/20 flex items-center gap-1.5"
                        title="Volver al Directorio">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
        </form>
    </div>


    <!-- Modal Flujo de Foto -->
    <div x-data="{ isOpen: false }" x-show="isOpen" @open-crop-modal.window="isOpen = true" @close-crop-modal.window="isOpen = false" @keydown.escape.window="isOpen = false; document.dispatchEvent(new CustomEvent('hidden-crop-modal'))" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/40 backdrop-blur-sm" style="display: none;">
        <div class="w-full max-w-lg bg-white/95 dark:bg-slate-800/95 backdrop-blur-xl border border-slate-200 dark:border-slate-700/50 rounded-3xl shadow-2xl overflow-hidden transform transition-all flex flex-col" @click.away="isOpen = false; document.dispatchEvent(new CustomEvent('hidden-crop-modal'))" x-show="isOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="scale-95 translate-y-4" x-transition:enter-end="scale-100 translate-y-0">
                <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-700/50 flex items-center justify-between shrink-0 bg-slate-50/30 dark:bg-slate-900/20">
                    <h5 class="text-xl font-black text-slate-800 dark:text-white tracking-tight flex items-center gap-3">
                        <span id="modalPhotoIcon" class="w-12 h-12 rounded-2xl bg-blue-600 flex items-center justify-center text-white shadow-xl shadow-blue-500/20"><i class="fas fa-camera"></i></span>
                        <span id="modalPhotoTitle">Gestionar Fotografía</span>
                    </h5>
                    <button type="button" @click="isOpen = false; document.dispatchEvent(new CustomEvent('hidden-crop-modal'))" class="w-10 h-10 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-400 flex items-center justify-center transition-all group">
                        <i class="fas fa-times text-lg group-hover:rotate-90 transition-transform"></i>
                    </button>
                </div>

                <!-- PASO 1: SELECCIÓN -->
                <div id="stepSelection" class="p-10 !bg-white dark:!bg-slate-800 transition-all">
                    <div id="dropZoneModal"
                        class="w-full aspect-square md:aspect-auto md:h-80 border-4 border-dashed border-slate-200 dark:border-slate-700 rounded-3xl flex flex-col items-center justify-center gap-6 hover:border-blue-500/50 hover:bg-blue-50/10 transition-all cursor-pointer group">
                        <div
                            class="w-20 h-20 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 group-hover:scale-110 transition-transform">
                            <i class="fas fa-cloud-upload-alt text-4xl"></i>
                        </div>
                        <div class="text-center">
                            <p class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-1">Arrastra tu foto aquí
                            </p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">O haz clic para explorar tus archivos
                            </p>
                        </div>
                        <input type="file" id="modalFileSelect" accept="image/jpeg,image/png" style="display: none;">
                        <button type="button" onclick="document.getElementById('modalFileSelect').click()"
                            class="px-6 py-2.5 rounded-xl !bg-slate-800 dark:!bg-slate-100 !text-white dark:!text-slate-800 font-bold hover:!bg-slate-700 dark:hover:!bg-white transition-all shadow-lg text-sm">
                            Seleccionar Archivo
                        </button>
                    </div>
                    <p
                        class="text-center text-[10px] text-slate-400 dark:text-slate-500 mt-6 uppercase tracking-widest font-bold">
                        Formatos compatibles: JPG, PNG • Máximo aconsejado: 100KB</p>
                </div>

                <!-- PASO 2: EDICIÓN -->
                <div id="stepEdit" class="p-4 !bg-slate-50 dark:!bg-slate-900 hidden">
                    <!-- Área de recorte (fondo neutro para ver transparencias) -->
                    <div class="w-full mb-3 rounded-xl overflow-hidden" style="min-height:300px;">
                        <img id="cropImage" style="display:block; max-width:100%;">
                    </div>
                    <!-- Controles Brillo/Contraste -->
                    <div
                        class="grid grid-cols-2 gap-3 !bg-white dark:!bg-slate-800 p-3 rounded-xl border border-slate-200 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase shrink-0"><i
                                    class="fas fa-sun"></i> Brillo</span>
                            <input type="range" id="rangeBrightness" min="0" max="200" value="100"
                                class="flex-1 accent-blue-500">
                            <span id="brightnessVal"
                                class="text-[10px] font-mono text-slate-500 dark:text-slate-400 w-8 text-right shrink-0">100%</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase shrink-0"><i
                                    class="fas fa-adjust"></i> Contr.</span>
                            <input type="range" id="rangeContrast" min="0" max="200" value="100"
                                class="flex-1 accent-blue-500">
                            <span id="contrastVal"
                                class="text-[10px] font-mono text-slate-500 dark:text-slate-400 w-8 text-right shrink-0">100%</span>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-4 border-t border-slate-200 dark:border-slate-700/50 flex flex-col sm:flex-row items-center justify-end gap-3 bg-slate-50/50 dark:bg-slate-900/50 shrink-0">
                    <div id="alertBoxImageCrop" class="hidden text-center mb-0 sm:mr-auto py-2 px-4 rounded-xl text-xs font-medium bg-red-100 text-red-600"></div>
                    <button type="button" @click="isOpen = false; document.dispatchEvent(new CustomEvent('hidden-crop-modal'))" class="w-full sm:w-auto px-4 py-2 rounded-lg text-xs font-bold bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-300 transition-all">Cancelar</button>
                    <button id="btnBackToUpload" type="button" class="w-full sm:w-auto px-4 py-2 rounded-lg text-xs font-bold bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-300 transition-all hidden"><i class="fas fa-arrow-left mr-2"></i> Cambiar Foto</button>
                    <button id="btnCrop" type="button" class="w-full sm:w-auto px-4 py-2 rounded-lg text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-500 shadow-xl shadow-emerald-600/20 transition-all flex items-center justify-center gap-2 hidden"><i class="fas fa-check"></i> Aplicar y Guardar</button>
                </div>
        </div>
    </div>
    <!-- Modal Doble Autenticación (TOTP) -->
    <div x-data="{ isOpen: false }" x-show="isOpen" @open-totp-modal.window="isOpen = true" @close-totp-modal.window="isOpen = false" @keydown.escape.window="isOpen = false; document.dispatchEvent(new CustomEvent('hidden-totp-modal'))" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/40 backdrop-blur-sm" style="display: none;">
        <div class="w-full max-w-md bg-white/95 dark:bg-slate-800/95 backdrop-blur-xl border border-slate-200 dark:border-slate-700/50 rounded-3xl shadow-2xl overflow-hidden transform transition-all flex flex-col" @click.away="isOpen = false; document.dispatchEvent(new CustomEvent('hidden-totp-modal'))" x-show="isOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="scale-95 translate-y-4" x-transition:enter-end="scale-100 translate-y-0">
                <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-700/50 flex items-center justify-between shrink-0 bg-slate-50/30 dark:bg-slate-900/20">
                    <h5 class="text-lg font-black text-slate-800 dark:text-white tracking-tight">DOBLE FACTOR DE AUTENTIFICACION (2FA)</h5>
                    <button type="button" @click="isOpen = false; document.dispatchEvent(new CustomEvent('hidden-totp-modal'))" class="w-10 h-10 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-400 flex items-center justify-center transition-all group">
                        <i class="fas fa-times text-lg group-hover:rotate-90 transition-transform"></i>
                    </button>
                </div>

                <div class="p-6 md:p-8">
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-6 leading-relaxed">
                        El doble factor de autenticación es una medida de seguridad extra para proteger tu cuenta.
                    </p>

                    <div id="modalContentTOTP" class="space-y-6 transition-all duration-300" <?php echo ($estado_toogle) ? '' : 'style="display: none;"'; ?>>

                        <p class="text-xs text-slate-400 dark:text-slate-500 leading-relaxed">
                            Al activarlo, podrás utilizar el código que la app <span
                                class="font-bold text-slate-500">Google Authenticator</span> genera para validar tu
                            cuenta cada vez que te identifiques en el sistema.
                        </p>

                        <p class="text-xs text-slate-400 dark:text-slate-500 mb-4">
                            Para activarlo escanea el siguiente código QR con la aplicación móvil Google Authenticator:
                        </p>

                        <div class="flex flex-col items-center justify-center gap-6">
                            <!-- QR y Texto Ayuda -->
                            <div class="flex flex-col md:flex-row items-center gap-8 w-full">
                                <div class="bg-white p-3 rounded-2xl shadow-lg border border-slate-100 flex-shrink-0">
                                    <img src="<?php echo $qrImage; ?>" class="w-32 h-32" alt="QR Code">
                                </div>
                                <div class="text-center md:text-left">
                                    <p
                                        class="text-xs font-bold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-wide">
                                        ¿No puedes escanear el código?</p>
                                    <p class="text-[11px] text-slate-400 dark:text-slate-500 leading-tight">
                                        Ingresa el siguiente código en la aplicación móvil Google Authenticator.
                                    </p>
                                </div>
                            </div>

                            <!-- Clave Manual Destacada -->
                            <div
                                class="w-full text-center py-4 border-t border-slate-100 dark:border-slate-700/50 mt-2">
                                <h4
                                    class="text-2xl md:text-3xl font-black text-slate-800 dark:text-white tracking-[0.2em] mb-3 select-all">
                                    <?php echo $secretKey; ?>
                                </h4>
                                <button type="button" id="copyQRModal"
                                    class="text-blue-500 hover:text-blue-600 font-bold text-xs flex items-center justify-center gap-2 mx-auto transition-colors">
                                    Copiar código <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Mensaje de advertencia si se desactiva -->
                    <div id="modalInfoInactivo"
                        class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800/30 rounded-2xl flex items-start gap-3 mt-4"
                        <?php echo ($estado_toogle) ? 'style="display: none;"' : ''; ?>>
                        <i class="fas fa-exclamation-triangle text-amber-500 mt-0.5"></i>
                        <p class="text-[11px] text-amber-700 dark:text-amber-400 leading-tight">
                            <strong>Atención:</strong> Si desactivas el TOTP, perderás el acceso remoto desde fuera de
                            la oficina. Deberás volver a configurarlo para recuperar dicha funcionalidad.
                        </p>
                    </div>
                </div>

                <div class="px-8 py-4 border-t border-slate-200 dark:border-slate-700/50 flex items-center justify-end gap-3 bg-slate-50/50 dark:bg-slate-900/50 shrink-0">
                    <button type="button" @click="isOpen = false; document.dispatchEvent(new CustomEvent('hidden-totp-modal'))" class="px-6 py-2.5 rounded-xl text-sm font-bold bg-slate-500 hover:bg-slate-600 text-white transition-all shadow-lg shadow-slate-500/10">Cerrar</button>
                    <button id="btnSaveTOTP" type="button" @click="isOpen = false; document.dispatchEvent(new CustomEvent('hidden-totp-modal'))" class="px-6 py-2.5 rounded-xl text-sm font-bold bg-blue-600 hover:bg-blue-500 text-white transition-all shadow-xl shadow-blue-600/20">Guardar cambios</button>
                </div>
        </div>
    </div>

    <!-- Scripts al final para asegurar carga del DOM -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js" integrity="sha384-r+ljwOAhwY4/kdyzMnuBg7MEVoWpTMp5EYUDntB/E9qzNwL9dAEcNrb2XaV+mJc2" crossorigin="anonymous"></script>
    <script nonce="<?= $csp_nonce ?>">window.Cropper||document.write('\x3Cscript src="js/vendor/cropper@1.5.13.min.js">\x3C/script>')</script>

    <script src="js/toogle.js?v=<?php echo filemtime(__DIR__ . '/js/toogle.js'); ?>"></script>
    <script src="js/copy.js?v=<?php echo filemtime(__DIR__ . '/js/copy.js'); ?>"></script>
    <script src="js/file.js?v=<?php echo filemtime(__DIR__ . '/js/file.js'); ?>"></script>
    <script src="js/subordinates.js?v=<?php echo time(); ?>"></script>
    <!-- Modal Gestionar Subordinados -->
    <div x-data="subordinatesModal" x-show="isOpen" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/40 backdrop-blur-sm"
        style="display: none;" @keydown.escape.window="isOpen = false">

        <div class="w-full max-w-5xl bg-white/95 dark:bg-slate-800/95 backdrop-blur-xl border border-slate-200 dark:border-slate-700/50 rounded-3xl shadow-2xl overflow-hidden transform transition-all flex flex-col max-h-[85vh]"
            @click.away="isOpen = false; document.dispatchEvent(new CustomEvent('hidden-totp-modal'))" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="scale-95 translate-y-4" x-transition:enter-end="scale-100 translate-y-0">

            <!-- Header -->
            <div
                class="px-8 py-6 border-b border-slate-200 dark:border-slate-700/50 flex items-center justify-between shrink-0 bg-slate-50/30 dark:bg-slate-900/20">
                <div class="flex items-center gap-5">
                    <div
                        class="w-14 h-14 rounded-2xl bg-blue-600 dark:bg-blue-500 flex items-center justify-center text-white shadow-xl shadow-blue-500/20">
                        <i class="fas fa-sitemap text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-slate-800 dark:text-white tracking-tight">Gestión de
                            Jerarquía</h3>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Administrando usuarios
                            dependientes de: <span class="text-blue-600 dark:text-blue-400 font-bold"
                                x-text="targetName"></span></p>
                    </div>
                </div>
                <button @click="isOpen = false"
                    class="w-10 h-10 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-400 dark:text-slate-500 flex items-center justify-center transition-all group">
                    <i class="fas fa-times text-lg group-hover:rotate-90 transition-transform"></i>
                </button>
            </div>

            <!-- Body Dual-List (Independent Scrolling + Drag & Drop) -->
            <div class="flex-1 flex flex-col min-h-0 bg-slate-50 dark:bg-slate-900 overflow-hidden">
                <div class="flex-1 flex overflow-hidden min-h-0" :class="isAdmin ? 'grid grid-cols-1 md:grid-cols-2' : 'flex justify-center'">

                    <!-- IZQUIERDA: Directorio -->
                    <div x-show="isAdmin" class="flex-1 flex flex-col p-5 md:p-6 border-r border-slate-200 dark:border-slate-800 min-h-0"
                        @click="selectedLeftDns = []; lastSelectedIndex = -1"
                        :class="isOverLeft ? 'bg-blue-50/30 dark:bg-blue-900/5' : ''"
                        @dragover.prevent="isOverLeft = true" @dragleave="isOverLeft = false"
                        @drop="handleDrop($event, 'left')">

                        <div class="shrink-0 flex items-center justify-between gap-1 mb-3">
                            <h4
                                class="text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-search text-blue-500"></i> Buscar Usuarios
                            </h4>
                            <template x-if="selectedLeftDns.length > 0">
                                <button @click="addMember()"
                                    class="px-2 py-1 rounded-lg bg-blue-600 text-white text-[9px] font-black hover:bg-blue-500 transition-all shadow-lg shadow-blue-500/20 flex items-center gap-1 active:scale-90">
                                    <i class="fas fa-plus-circle"></i> Añadir <span
                                        x-text="selectedLeftDns.length"></span>
                                </button>
                            </template>
                        </div>

                        <div class="relative mb-4 shrink-0">
                            <i
                                class="fas fa-filter absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" x-model="searchQuery" @input.debounce.300ms="performSearch"
                                placeholder="Filtre por nombre o usuario..."
                                class="w-full pl-11 pr-4 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl outline-none text-xs text-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 shadow-sm transition-all placeholder:text-slate-400">
                        </div>

                        <div class="flex-1 overflow-y-auto space-y-2 custom-scrollbar px-2 pr-2">
                            <template x-if="isSearching">
                                <div class="flex items-center justify-center py-10">
                                    <div
                                        class="animate-spin rounded-full h-6 w-6 border-2 border-blue-500 border-t-transparent">
                                    </div>
                                </div>
                            </template>

                            <template x-for="user in searchResults" :key="user.dn">
                                <div draggable="true" @dragstart="handleDragStart($event, {...user, isCurrent: false})"
                                    @dragend="handleDragEnd()" @click.stop="toggleSelection(user, $event)"
                                    @dblclick="addMember(user.dn, user.name)"
                                    :class="isSelected(user.dn) ? 'bg-blue-600 border-blue-600 text-white shadow-xl scale-[1.02]' : 'bg-slate-50/50 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700/50 text-slate-700 dark:text-slate-100 hover:border-blue-400 dark:hover:border-blue-500'"
                                    class="flex items-center gap-2.5 p-2 rounded-xl border transition-all cursor-move shadow-sm group">
                                    <template x-if="user.photo">
                                        <img :src="'data:image/jpeg;base64,' + user.photo"
                                            class="w-8 h-8 rounded-lg object-cover shrink-0 shadow-sm border border-white/20"
                                            alt="U">
                                    </template>
                                    <template x-if="!user.photo">
                                        <div
                                            class="w-8 h-8 rounded-lg bg-white dark:bg-slate-950 flex items-center justify-center text-slate-400 shrink-0 border border-slate-200 dark:border-slate-800">
                                            <i :class="user.type === 'Contacto' ? 'fas fa-id-card' : 'fas fa-user'" class="text-xs"></i>
                                        </div>
                                    </template>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="text-xs font-black truncate leading-tight" x-text="user.name"></p>
                                            <template x-if="user.type === 'Contacto'">
                                                <span class="text-[8px] px-1.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 font-bold uppercase tracking-tighter">Contacto</span>
                                            </template>
                                        </div>
                                        <p class="text-[9px] opacity-60 truncate font-mono tracking-tight"
                                            x-text="user.sam"></p>
                                    </div>
                                </div>
                            </template>

                            <template x-if="searchResults.length === 0 && !isSearching && searchQuery.length >= 3">
                                <div class="py-10 text-center text-slate-400">
                                    <i class="fas fa-search-minus text-3xl mb-3 opacity-20"></i>
                                    <p class="text-[10px] font-bold uppercase tracking-widest">Sin resultados</p>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- DERECHA: Equipo -->
                    <div class="flex flex-col p-5 md:p-6 min-h-0"
                        :class="isAdmin ? 'flex-1' : 'w-full max-w-xl'"
                        @click="selectedRightDns = []; lastSelectedRightIndex = -1"
                        :class="isOverRight ? 'bg-rose-50/30 dark:bg-rose-900/5' : ''"
                        @dragover.prevent="isOverRight = true" @dragleave="isOverRight = false"
                        @drop="handleDrop($event, 'right')">

                        <!-- ZONA FIJA: Jefe -->
                        <div class="shrink-0">
                            <h4
                                class="text-[10px] font-black text-indigo-500 dark:text-indigo-400 uppercase tracking-widest flex items-center gap-2 mb-3">
                                <i class="fas fa-crown"></i> Administrador Superior
                            </h4>
                            <template x-if="subTargetManager">
                                <div class="mb-3">
                                    <div
                                        class="bg-indigo-50/50 dark:bg-indigo-900/10 border-indigo-200/60 dark:border-indigo-800/40 text-slate-700 dark:text-slate-100 flex items-center gap-3 p-2 rounded-xl border transition-all shadow-sm relative group overflow-hidden">
                                        <div
                                            class="absolute top-0 right-0 w-16 h-16 bg-indigo-500/5 blur-2xl rounded-full translate-x-8 -translate-y-8">
                                        </div>
                                        <div class="relative">
                                            <template x-if="subTargetManager.photo">
                                                <img :src="'data:image/jpeg;base64,' + subTargetManager.photo"
                                                    class="w-9 h-9 rounded-lg object-cover shrink-0 shadow-sm border border-white/20"
                                                    alt="U">
                                            </template>
                                            <template x-if="!subTargetManager.photo">
                                                <div
                                                    class="w-9 h-9 rounded-lg bg-white dark:bg-slate-950 flex items-center justify-center text-indigo-400 shrink-0 border border-slate-200 dark:border-slate-800">
                                                    <i class="fas fa-user-tie text-base"></i>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-black truncate leading-tight"
                                                x-text="subTargetManager.name"></p>
                                            <p class="text-[9px] opacity-60 truncate font-mono tracking-tight"
                                                x-text="subTargetManager.sam"></p>
                                        </div>
                                        <div class="shrink-0 text-indigo-400/30 px-2">
                                            <i class="fas fa-shield-alt text-base"></i>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!subTargetManager">
                                <div
                                    class="mb-3 p-2 bg-slate-50/50 dark:bg-slate-800/30 rounded-lg border border-dashed border-slate-200 dark:border-slate-800 text-center">
                                    <p class="text-[9px] text-slate-400 font-medium italic">Sin administrador asignado
                                    </p>
                                </div>
                            </template>
                        </div>

                        <!-- SEPARADOR Y TITULO LISTA -->
                        <div class="shrink-0 flex items-center justify-between gap-1 mb-2">
                            <h4
                                class="text-[10px] font-black text-rose-500 dark:text-rose-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-users"></i> Usuarios Dependientes
                            </h4>
                            <template x-if="isAdmin && selectedRightDns.length > 0">
                                <button @click="removeMember()"
                                    class="px-2 py-1 rounded-lg bg-rose-600 text-white text-[9px] font-black hover:bg-rose-500 transition-all shadow-lg shadow-rose-500/20 flex items-center gap-1 active:scale-90">
                                    <i class="fas fa-trash-alt"></i> Quitar <span
                                        x-text="selectedRightDns.length"></span>
                                </button>
                            </template>
                            <div x-show="!isAdmin || selectedRightDns.length === 0"
                                class="h-px bg-slate-100 dark:bg-slate-800/80 flex-1 ml-2"></div>
                        </div>

                        <!-- LISTA DESPLAZABLE -->
                        <div class="flex-1 overflow-y-auto space-y-2 custom-scrollbar px-2 pr-2">
                            <template x-if="isLoading">
                                <div class="flex items-center justify-center py-10">
                                    <div
                                        class="animate-spin rounded-full h-6 w-6 border-2 border-rose-500 border-t-transparent">
                                    </div>
                                </div>
                            </template>

                            <template x-for="sub in currentSubordinates" :key="sub.dn">
                                <div :draggable="isAdmin" x-transition:enter="transition ease-out duration-300"
                                    x-transition:enter-start="opacity-0 translate-x-4"
                                    x-transition:enter-end="opacity-100 translate-x-0"
                                    x-transition:leave="transition ease-in duration-200"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    @dragstart="isAdmin ? handleDragStart($event, {...sub, isCurrent: true}) : null"
                                    @dragend="isAdmin ? handleDragEnd() : null"
                                    @click.stop="isAdmin ? toggleSelection(sub, $event, 'right') : null"
                                    @dblclick="isAdmin ? removeMember(sub.dn) : null"
                                    :class="isSelected(sub.dn, 'right') ? 'bg-rose-600 border-rose-600 text-white shadow-xl scale-[1.02]' : 'bg-slate-50/50 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700/50 text-slate-700 dark:text-slate-100 hover:border-rose-400 dark:hover:border-rose-500'"
                                    class="flex items-center gap-3 p-2 rounded-xl border transition-all shadow-sm group relative"
                                    :class="isAdmin ? 'cursor-move' : 'cursor-default'">
                                    <template x-if="sub.photo">
                                        <img :src="'data:image/jpeg;base64,' + sub.photo"
                                            class="w-9 h-9 rounded-lg object-cover shrink-0 shadow-sm border border-white/20"
                                            alt="U">
                                    </template>
                                    <template x-if="!sub.photo">
                                        <div
                                            class="w-9 h-9 rounded-lg bg-white dark:bg-slate-950 flex items-center justify-center text-slate-400 shrink-0 border border-slate-200 dark:border-slate-800">
                                            <i :class="sub.type === 'Contacto' ? 'fas fa-id-card' : 'fas fa-user'" class="text-base"></i>
                                        </div>
                                    </template>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="text-xs font-black truncate leading-tight" x-text="sub.name"></p>
                                            <template x-if="sub.type === 'Contacto'">
                                                <span class="text-[8px] px-1.5 py-0.5 rounded-full bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 font-bold uppercase tracking-tighter">Contacto</span>
                                            </template>
                                        </div>
                                        <p class="text-[9px] opacity-60 truncate font-mono tracking-tight"
                                            x-text="sub.sam"></p>
                                    </div>
                                    <!-- Botón de desasignar (Trash) -->
                                    <button x-show="isAdmin" @click.stop="removeMember(sub.dn)"
                                        class="opacity-40 group-hover:opacity-100 p-1.5 rounded-lg transition-all active:scale-90"
                                        :class="isSelected(sub.dn, 'right') ? 'bg-white/20 text-white' : 'bg-rose-500/10 text-rose-500 hover:bg-rose-600 hover:text-white'"
                                        title="Desasignar subordinado">
                                        <i class="fas fa-trash-alt text-sm"></i>
                                    </button>
                                </div>
                            </template>

                            <template x-if="currentSubordinates.length === 0 && !isLoading">
                                <div
                                    class="h-full flex flex-col items-center justify-center text-slate-400 dark:text-slate-600 opacity-60 py-32 pointer-events-none">
                                    <i class="fas fa-user-lock text-6xl mb-6"></i>
                                    <p class="text-xs font-black uppercase tracking-[0.5em]">Sin asignar</p>
                                    <p class="text-[10px] mt-2 opacity-50">Arrastre usuarios aquí para asignarlos</p>
                                </div>
                            </template>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Footer -->
            <div
                class="px-8 py-4 border-t border-slate-200 dark:border-slate-700/50 flex justify-end bg-slate-50/50 dark:bg-slate-900/50 shrink-0">
                <button @click="isOpen = false"
                    class="px-4 py-2 rounded-lg text-xs font-bold bg-blue-600 text-white hover:bg-blue-500 shadow-xl shadow-blue-600/20 transition-all flex items-center gap-1.5 active:scale-95">
                    <i class="fas fa-check-circle"></i> Finalizar y Cerrar
                </button>
            </div>
        </div>
    </div>
</body>

</html>