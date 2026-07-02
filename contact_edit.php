<?php
// Bootstrap: session security + HTTP security headers + config
require_once __DIR__ . '/bootstrap.php';

require_once(__DIR__ . '/lib/ldap_contacts.php');
require_once(__DIR__ . '/lib/ldap_permissions.php');
require_once(__DIR__ . '/lib/sendmessage.php');
require_once(__DIR__ . '/lib/fillcombobox.php');
require_once(__DIR__ . '/lib/csrf.php');

// El control de acceso se realiza después de obtener el DN

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

$dn = $_REQUEST['dn'] ?? null;
$action = $_POST['action'] ?? null;

// Control de acceso delegado
if (!can_edit_contact($dn)) {
    header('Location: ./index.php');
    exit;
}

// Lógica de acciones
if ($action) {
    if (!verify_csrf_token(get_token_from_request())) {
        $_SESSION['mensaje'] = ['Error: Token CSRF inválido'];
        $_SESSION['mensaje_css'] = 'no';
        header('Location: ' . ($_SESSION['last_search_url'] ?? './index.php'));
        exit;
    } else {
        if ($action === 'save') {
            if (save_contact($dn)) {
                header('Location: ' . ($_SESSION['last_search_url'] ?? './index.php'));
                exit;
            }
        } elseif ($action === 'delete') {
            if (delete_contact($dn)) {
                header('Location: ' . ($_SESSION['last_search_url'] ?? './index.php'));
                exit;
            }
        }
    }
}

// Cargar datos si editamos
$contact = null;
if ($dn) {
    $contact = load_contact_data($dn);
}

$nameAyto = $config['medley']['nameAyto'];

// Preparar foto actual como data URI
$currentPhotoSrc = null;
if (isset($contact['thumbnailphoto'])) {
    $rawPhoto = is_array($contact['thumbnailphoto']) ? $contact['thumbnailphoto'][0] : $contact['thumbnailphoto'];
    if (!empty($rawPhoto)) {
        $currentPhotoSrc = 'data:image/jpeg;base64,' . base64_encode($rawPhoto);
    }
}

// Extraer switches de wWWHomePage (4 dígitos: Visible, Foto, Email, Presencia)
$wValue = substr(($contact['wwwhomepage'][0] ?? '1110') . '0000', 0, 4);
$sw1 = $wValue[0];
$sw2 = $wValue[1];
$sw3 = $wValue[2];
$sw4 = $wValue[3];
?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-50 dark:bg-slate-900">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $dn ? 'Editar' : 'Nuevo'; ?> Contacto - <?php echo $nameAyto; ?></title>

    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="css/style.css">
    <script nonce="<?= $csp_nonce ?>">
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <script nonce="<?= $csp_nonce ?>" src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" integrity="sha384-eeLEhtwdMwD3X9y+8P3Cn7Idl/M+w8H4uZqkgD/2eJVkWIN1yKzEj6XegJ9dL3q0" crossorigin="anonymous"></script>
    <script nonce="<?= $csp_nonce ?>">window.Sortable||document.write('\x3Cscript src="js/vendor/sortable@1.15.0.min.js">\x3C/script>')</script>
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
    <script defer nonce="<?= $csp_nonce ?>" src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" integrity="sha384-Rpe/8orFUm5Q1GplYBHxbuA8Az8O8C5sAoOsdbRWkqPjKFaxPgGZipj4zeHL7lxX" crossorigin="anonymous"></script>
    <script nonce="<?= $csp_nonce ?>">document.addEventListener('DOMContentLoaded',function(){if(typeof Alpine==='undefined'){var s=document.createElement('script');s.src='js/vendor/alpine@3.13.3.min.js';s.defer=!0;document.head.appendChild(s)}})</script>
    <link rel="stylesheet" href="css/vendor/cropper@1.5.13.min.css">

    <style nonce="<?= $csp_nonce ?>">
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

        .drag-handle {
            cursor: grab;
        }

        .drag-handle:active {
            cursor: grabbing;
        }

        .sortable-ghost {
            opacity: 0.4;
            background: rgba(59, 130, 246, 0.1);
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #334155;
        }

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
    </style>
</head>

<body
    class="bg-gradient-animate min-h-screen flex flex-col justify-center items-center py-2 px-2 md:px-4 text-slate-700 dark:text-slate-200 antialiased relative">
    <div
        class="fixed top-[-10%] left-[-10%] w-96 h-96 bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-20 z-0 pointer-events-none">
    </div>
    <div
        class="fixed bottom-[-10%] right-[-10%] w-96 h-96 bg-purple-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-20 z-0 pointer-events-none">
    </div>

    <div
        class="w-full max-w-5xl bg-white/90 dark:bg-slate-800/80 backdrop-blur-xl border border-slate-200 dark:border-slate-700/50 rounded-3xl p-4 md:p-5 shadow-2xl relative z-10 transition-all duration-300">

        <!-- CABECERA COMPACTA INLINE -->
        <div class="flex items-center gap-4 md:gap-8 border-b border-slate-200 dark:border-slate-700/50 pb-2 mb-3">

            <!-- Foto (izquierda) -->
            <div class="flex flex-col items-center group flex-shrink-0">
                <div id="avatarContainer"
                    class="relative w-32 h-32 ring-2 ring-white dark:ring-slate-700 rounded-2xl overflow-hidden shadow-lg bg-slate-100 dark:bg-slate-800 border-2 border-transparent transition-all cursor-pointer group-hover:ring-blue-500/50"
                    title="Arrastra una foto aquí">
                    <?php if ($currentPhotoSrc): ?>
                        <img id="imgPhotoUser" src="<?php echo $currentPhotoSrc; ?>"
                            class="w-full h-full object-cover shadow-inner">
                    <?php else: ?>
                        <img id="imgPhotoUser" src="./images/users.jpg"
                            class="w-full h-full object-cover opacity-50 grayscale">
                    <?php endif; ?>
                    <div
                        class="absolute inset-0 bg-blue-600/20 backdrop-blur-sm flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                        <i class="fas fa-cloud-upload-alt text-white text-xl"></i>
                    </div>
                </div>
                <!-- Botones mini -->
                <div class="flex items-center gap-1 mt-1.5">
                    <button id="btnPhotoChange" type="button"
                        class="p-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-500 shadow-md transition-all text-xs flex items-center justify-center"
                        title="Subir Foto"><i class="fas fa-camera"></i></button>
                    <button id="btnPhotoDel" type="button"
                        class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-rose-600 hover:text-white transition-all text-xs flex items-center justify-center border border-slate-200 dark:border-slate-600"
                        title="Borrar Foto"><i class="fas fa-trash-alt"></i></button>
                </div>
                <input type="file" id="txtPhoto" name="_photoFile" accept="image/jpeg,image/png" style="display: none;">
            </div>

            <!-- Texto (derecha de la foto) -->
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 mb-1">
                    <a href="./" class="inline-block transform hover:scale-105 transition-transform flex-shrink-0">
                        <img src="./images/escudo.svg" alt="Escudo"
                            class="h-8 w-auto brightness-0 dark:invert opacity-90">
                    </a>
                    <h2
                        class="text-lg md:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight truncate">
                        <?php echo $dn ? htmlspecialchars($contact['displayname'][0] ?? 'Editar Contacto') : 'Nuevo Contacto Externo'; ?>
                    </h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php if (!empty($contact['company'][0])): ?>
                        <span
                            class="flex items-center gap-1.5 bg-blue-50/80 dark:bg-blue-900/20 border border-blue-100/50 dark:border-blue-800/30 px-2.5 py-1 rounded-full text-xs text-blue-700 dark:text-blue-300">
                            <i class="fas fa-building text-blue-500 opacity-80 text-[10px]"></i>
                            <?php echo htmlspecialchars($contact['company'][0]); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($contact['department'][0])): ?>
                        <span
                            class="flex items-center gap-1.5 bg-emerald-50/80 dark:bg-emerald-900/20 border border-emerald-100/50 dark:border-emerald-800/30 px-2.5 py-1 rounded-full text-xs text-emerald-700 dark:text-emerald-300">
                            <i class="fas fa-sitemap text-emerald-500 opacity-80 text-[10px]"></i>
                            <?php echo htmlspecialchars($contact['department'][0]); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (empty($contact['company'][0]) && empty($contact['department'][0])): ?>
                        <span
                            class="flex items-center gap-1.5 bg-slate-100/80 dark:bg-slate-900/40 border border-slate-200/50 dark:border-slate-700/30 px-2.5 py-1 rounded-full text-[10px] font-bold text-slate-500 uppercase">
                            <i class="fas fa-id-card opacity-50"></i> Contacto Externo
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Caja de alertas de foto -->
        <div id="alertBoxImageChange" class="alert hidden text-center mb-1 mt-0 py-1 rounded-lg text-xs" role="alert">
        </div>

        <!-- FORMULARIO PRINCIPAL -->
        <form id="frmMain" action="contact_edit.php" method="POST" class="w-full space-y-3">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <input type="hidden" name="dn" value="<?php echo htmlspecialchars($dn ?? ''); ?>">
            <!--
                Campo foto: id="txtThumbnailPhoto" para que file.js lo rellene automÃ¡ticamente,
                name="txtPhoto" para que ldap_contacts.php::save_contact() lo procese.
            -->
            <input type="hidden" id="txtThumbnailPhoto" name="txtPhoto"
                value="<?php echo $currentPhotoSrc ? htmlspecialchars($currentPhotoSrc) : ''; ?>">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-4 md:gap-x-6 gap-y-3 w-full">

                <!-- SECCIÃ“N 1: DATOS PERSONALES -->
                <div class="space-y-3">
                    <h3
                        class="text-xs md:text-sm uppercase tracking-widest font-black text-blue-500 dark:text-blue-400 border-b border-slate-200 dark:border-slate-700/50 pb-1.5 mb-3">
                        <i class="fas fa-user-circle mr-1"></i> Identidad del Contacto
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <label
                                class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Nombre
                                Completo <span class="text-rose-500">*</span></label>
                            <input type="text" name="txtDisplayName" required
                                value="<?php echo htmlspecialchars($contact['displayname'][0] ?? ''); ?>"
                                
                                title="Nombre que se visualizará en las búsquedas del directorio."
                                class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all font-semibold shadow-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Nombre</label>
                                <input type="text" name="txtGivenName"
                                    value="<?php echo htmlspecialchars($contact['givenname'][0] ?? ''); ?>"
                                     title="Primer nombre del contacto."
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Apellidos</label>
                                <input type="text" name="txtSN"
                                    value="<?php echo htmlspecialchars($contact['sn'][0] ?? ''); ?>"
                                     title="Apellidos del contacto."
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                            </div>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Email</label>
                            <input type="email" name="txtEmail"
                                value="<?php echo htmlspecialchars($contact['mail'][0] ?? ''); ?>"
                                
                                title="Dirección de correo electrónico institucional de contacto."
                                class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Teléfono</label>
                                <input type="text" name="txtTel"
                                    value="<?php echo htmlspecialchars($contact['telephonenumber'][0] ?? ''); ?>"
                                     title="Extensión o teléfono fijo principal del contacto."
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all font-bold shadow-sm">
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Móvil</label>
                                <input type="text" name="txtMobile"
                                    value="<?php echo htmlspecialchars($contact['mobile'][0] ?? ''); ?>"
                                     title="Número de teléfono móvil de contacto."
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all font-bold shadow-sm">
                            </div>
                        </div>
                    </div>

                    <!-- TAGS (BÚSQUEDA) - Relocalizado en Sección 1 -->
                    <div class="mt-3">
                        <label for="txtInfo"
                            class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">
                            <i class="fas fa-tags mr-1.5 opacity-60"></i> Tags (Palabras clave para búsqueda)
                        </label>
                        <textarea id="txtInfo" name="txtInfo" rows="2"
                            placeholder="Ej: Carpintería, Urgencias, Fontanero..." 
                            title="Palabras clave para facilitar la localización de este contacto en las búsquedas."
                            class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-700 dark:text-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all resize-none shadow-sm font-semibold custom-scrollbar"><?php echo htmlspecialchars($contact['info'][0] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- SECCIÓN 2: ORGANIZACIÓN Y UBICACIÓN -->
                <div class="space-y-3 w-full">
                    <h3
                        class="text-xs md:text-sm uppercase tracking-widest font-black text-emerald-500 dark:text-emerald-400 border-b border-slate-200 dark:border-slate-700/50 pb-1.5 mb-3">
                        <i class="fas fa-sitemap mr-1"></i> Organización y Ubicación
                    </h3>
                    <div class="space-y-3 w-full">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Departamento</label>
                                <div class="[&>select]:w-full [&>select]:px-4 [&>select]:py-2 [&>select]:bg-slate-50 dark:[&>select]:bg-slate-900 [&>select]:border [&>select]:border-slate-200 dark:[&>select]:border-slate-700 [&>select]:rounded-xl [&>select]:text-sm [&>select]:text-slate-700 dark:[&>select]:text-slate-200 outline-none shadow-sm transition-all focus-within:ring-2 focus-within:ring-blue-500"
                                     title="Área u organismo al que se asocia este contacto.">
                                    <?php print fill_combobox('index.php', 'department', 'txtDept', 'w-full', $contact['department'][0] ?? ''); ?>
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Subtítulo</label>
                                <input type="text" name="txtTitle"
                                    value="<?php echo htmlspecialchars($contact['title'][0] ?? ''); ?>"
                                    
                                    title="Cargo o descripción breve que aparecerá bajo el nombre en el directorio."
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                            </div>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Dirección</label>
                            <input type="text" name="txtAddress"
                                value="<?php echo htmlspecialchars($contact['streetaddress'][0] ?? ''); ?>"
                                 title="Ubicación física o sede del contacto."
                                class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="col-span-2">
                                <label
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Población
                                    (Ciudad)</label>
                                <input type="text" name="txtCity"
                                    value="<?php echo htmlspecialchars($contact['l'][0] ?? ''); ?>"
                                     title="Ciudad o municipio de residencia."
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">C.P.</label>
                                <input type="text" name="txtPostalCode"
                                    value="<?php echo htmlspecialchars($contact['postalcode'][0] ?? ''); ?>"
                                     title="Código Postal de 5 dígitos."
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">Provincia</label>
                                <input type="text" name="txtState"
                                    value="<?php echo htmlspecialchars($contact['st'][0] ?? ''); ?>"
                                     title="Provincia de residencia u oficina."
                                    class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all">
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">País</label>
                                <div
                                    class="border border-slate-200 dark:border-slate-700 rounded-xl focus-within:ring-2 focus-within:ring-blue-500 transition-all shadow-sm">
                                    <select name="txtCountry" 
                                        title="País de origen o ubicación."
                                        class="block w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border-none text-slate-800 dark:text-slate-200 text-sm rounded-xl outline-none">
                                        <option value="">Elija país...</option>
                                        <?php
                                        // Lista ordenada alfabéticamente
                                        $countries = ["Alemania", "Andorra", "Austria", "Bélgica", "EE.UU.", "España", "Francia", "Italia", "Países Bajos", "Portugal", "Reino Unido", "Suiza"];
                                        
                                        $currentCountry = $contact['co'][0] ?? '';
                                        
                                        // Por defecto España si no hay valor previo
                                        if (empty($currentCountry)) {
                                            $currentCountry = "España";
                                        }

                                        foreach ($countries as $c) {
                                            $sel = ($currentCountry === $c) ? 'selected' : '';
                                            echo "<option value=\"$c\" $sel>$c</option>";
                                        }
                                        
                                        // Caso especial: si el valor actual no está en la lista estándar
                                        if (!empty($currentCountry) && !in_array($currentCountry, $countries)) {
                                            echo "<option value=\"$currentCountry\" selected>$currentCountry</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 3: PRIVACIDAD Y VISIBILIDAD -->
                        <div class="mt-3 group/privacy">
                                <label
                                    class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1 ml-1">
                                    <i class="fas fa-eye mr-1.5 opacity-60"></i> Privacidad y Visibilidad
                                </label>

                                <div class="grid grid-cols-2 md:grid-cols-3 gap-3" x-data="{ 
                                s1: '<?php echo $sw1; ?>', 
                                s2: '<?php echo $sw2; ?>', 
                                s3: '<?php echo $sw3; ?>' 
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

                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- PIE: ACCIONES (SINCRONIZADO CON datos_active.php) -->
                <div
                    class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700/50 flex flex-wrap gap-3 justify-between items-center">

                    <!-- IZQUIERDA: Espacio para paridad visual con datos_active -->
                    <div class="hidden md:block"></div>

                    <!-- DERECHA: Acciones principales -->
                    <div class="flex items-center gap-3">
                        <a href="./contact_edit.php?dn=<?php echo urlencode($dn ?? ''); ?>"
                            class="px-5 py-2.5 rounded-xl text-sm font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all flex items-center gap-2"
                            title="Descartar cambios y recargar">
                            <i class="fas fa-rotate-left"></i> Restablecer
                        </a>
                        <button id="btnUpdate" type="submit" name="btnUpdate" value="Actualizar"
                            class="px-6 py-2.5 rounded-xl text-sm font-bold bg-blue-600 text-white hover:bg-blue-500 shadow-xl shadow-blue-600/20 transition-all flex items-center gap-2">
                            <i class="fas fa-floppy-disk"></i> Guardar Cambios
                        </button>
                        <a href="./index.php" id="btnVolver"
                            class="px-5 py-2.5 rounded-xl text-sm font-bold bg-rose-600/10 text-rose-600 dark:text-rose-400 hover:bg-rose-600 hover:text-white transition-all border border-rose-500/20 flex items-center gap-2"
                            title="Volver al Directorio">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                        <script nonce="<?= $csp_nonce ?>">document.getElementById('btnVolver').addEventListener('click',function(e){e.preventDefault();history.back()})</script>
                    </div>
                </div>
        </form>
    </div>
    </div>


    <!-- Modal de Recorte de Foto -->
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
                        <button id="btnSelectFile" type="button"
                            class="px-6 py-2.5 rounded-xl !bg-slate-800 dark:!bg-slate-100 !text-white dark:!text-slate-800 font-bold hover:!bg-slate-700 dark:hover:!bg-white transition-all shadow-lg text-sm">
                            Seleccionar Archivo
                        </button>
                        <script nonce="<?= $csp_nonce ?>">document.getElementById('btnSelectFile').addEventListener('click',function(){document.getElementById('modalFileSelect').click()})</script>
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

    <!-- Scripts -->
    <script nonce="<?= $csp_nonce ?>" src="js/vendor/jquery@3.7.1.min.js"></script>
    <script nonce="<?= $csp_nonce ?>" src="js/vendor/cropper@1.5.13.min.js"></script>
    <script nonce="<?= $csp_nonce ?>" src="js/file.js?v=<?php echo filemtime(__DIR__ . '/js/file.js'); ?>"></script>
</body>

</html>