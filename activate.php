<?php
// Bootstrap: session security + HTTP security headers + config
require_once __DIR__ . '/bootstrap.php';

//Funciones para debug
require_once(__DIR__ . '/lib/debug_to_console.php');
//Funciones para mostrar mensajes
require_once(__DIR__ . '/lib/sendmessage.php');
//Funciones para checkear un token
require_once(__DIR__ . '/lib/checktoken.php');
//Funciones para la generación de un nuevo password
require_once(__DIR__ . '/lib/ldap_newpwd.php');
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

<title>Activar Nueva Contraseña - <?php echo $nameAyto; ?></title>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

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
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
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
<body class="bg-gradient-animate h-full flex flex-col items-center justify-center p-4 text-slate-700 dark:text-slate-200 antialiased relative overflow-hidden">
    
    <div class="fixed top-[-10%] left-[-10%] w-96 h-96 bg-blue-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 z-0 pointer-events-none"></div>
    <div class="fixed bottom-[-10%] right-[-10%] w-96 h-96 bg-purple-600 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 z-0 pointer-events-none"></div>

    <div class="w-full max-w-lg glass-panel rounded-3xl p-8 relative z-10 transition-all duration-300">
        
        <div class="text-center mb-8">
            <a href="./" class="inline-block transform hover:scale-105 transition-transform mb-2">
                <img src="./images/escudo.svg" alt="Escudo" class="h-16 w-auto mx-auto drop-shadow-lg brightness-0 dark:invert opacity-90" onerror="this.style.display='none'">
            </a>
            <h1 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-white mt-4">Restablecer Contraseña</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1"><?php echo $nameAyto; ?></p>
        </div>

        <div class="text-center">
            <?php if (!empty($_SESSION['mensaje'])): ?>
                <div class="mb-6 p-4 rounded-xl <?php echo ($_SESSION['mensaje_css'] == 'yes') ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border border-rose-500/30 text-rose-400'; ?> flex flex-col gap-2 shadow-inner text-sm">
                    <?php foreach($_SESSION['mensaje'] as $msg): ?>
                        <p class="flex items-center gap-2 justify-center"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($msg); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php
              $token = (isset($_GET['token'])) ? $_GET['token'] : '';
              $recoveryUserMail = check_token($token);

              if ($_SESSION['mensaje_css'] == 'no') {
                 create_new_pwd_for_user($recoveryUserMail);
                 if ($_SESSION['mensaje_css'] == 'yes') {
                    unset($_SESSION['mensaje']);
                    unset($_SESSION['mensaje_css']);
                    // Redirigir
                    header('Location: ./change_pwd');
                    exit;
                 }
              }
              print_message();
            ?>
            
            <a href="./index" class="mt-6 inline-flex justify-center items-center py-2.5 px-6 border border-slate-300 dark:border-slate-600 rounded-xl shadow-sm text-sm font-medium text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-700 focus:outline-none transition-all">
                <i class="fas fa-arrow-left mr-2"></i> Volver al Inicio
            </a>
        </div>
    </div>

    <!-- Footer Logo -->
    <div class="mt-auto mb-4 text-center text-xs text-slate-500 dark:text-slate-400 relative z-10 p-4">
        &copy; <?php echo date("Y") . " " . $nameAyto; ?>. Todos los derechos reservados.
    </div>

</body>
</html>