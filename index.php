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
<script>window.Sortable||document.write('\x3Cscript src="js/vendor/sortable@1.15.0.min.js">\x3C/script>')</script>
<!-- Alpine.js para dinamismo de vistas (grid/list) sin recargar página -->
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

    <?php include __DIR__ . '/templates/header.php'; ?>

    <!-- Contenedor Principal Dashboard -->
    <div class="flex-1 flex overflow-hidden bg-slate-50 dark:bg-slate-900 relative">
        
        <?php include __DIR__ . '/templates/sidebar.php'; ?>

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

    <?php include __DIR__ . '/templates/modals/secretary.php'; ?>
    <?php include __DIR__ . '/templates/modals/delete-contact.php'; ?>

    <!-- MODAL: Gestión de Extensiones de Equipos (Solo Admin) -->
    <?php if (!empty($_SESSION['ldap_user']) && is_admin_user()): ?>
        <?php include __DIR__ . '/templates/modals/computer-phone.php'; ?>
    <?php endif; ?>

<!-- Global HTML Tooltip (outside cards to avoid overflow-hidden + transform clipping) -->
<div id="global-html-tooltip" class="html-tooltip" style="display:none;"></div>
</body>
</html>
