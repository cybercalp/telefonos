function actualizarCronometro() {
    if (typeof window.tiempoRestante !== 'number') return;

    let tiempo = window.tiempoRestante;

    const cronometro = document.getElementById('cronometro');
    const modal = document.getElementById('bloqueoModal');

    if (!cronometro || !modal) return;

    // Mostrar modal
    modal.style.display = 'block';

    // Mostrar el tiempo inicial inmediatamente
    actualizarTexto(tiempo);

    // Iniciar cuenta regresiva
    const intervalo = setInterval(() => {
        tiempo--;
        if (tiempo < 0) {
            clearInterval(intervalo);
            location.reload();
            return;
        }

        actualizarTexto(tiempo);
    }, 1000);

    // Función para actualizar el contenido del cronómetro
    function actualizarTexto(segundosRestantes) {
        const minutos = Math.floor(segundosRestantes / 60);
        const segundos = segundosRestantes % 60;

        cronometro.textContent = `${String(minutos).padStart(2, '0')}:${String(segundos).padStart(2, '0')}`;
    }
}

// Espera que el DOM esté listo
window.addEventListener('DOMContentLoaded', actualizarCronometro);
