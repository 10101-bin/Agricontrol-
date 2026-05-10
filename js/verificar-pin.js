
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página de verificación cargada');
    
    const form = document.getElementById('verificarPinForm');
    const pinInput = document.getElementById('pin');
    const btnVerificar = document.getElementById('btnVerificar');
    const btnReenviar = document.getElementById('reenviarPin');
    const tiempoRestanteSpan = document.getElementById('tiempoRestante');
    
    // Obtener correo del sessionStorage
    let correo = sessionStorage.getItem('correo_verificar');
    console.log('Correo a verificar:', correo);
    
    if (!correo) {
        console.log('No hay correo en sessionStorage, redirigiendo...');
        window.location.href = 'registro.html';
        return;
    }
    
    // Temporizador
    let tiempoLimite = 15 * 60; 
    let temporizador = null;
    
    function iniciarTemporizador() {
        const inicio = sessionStorage.getItem('pin_tiempo_inicio');
        if (inicio) {
            actualizarTemporizador();
            temporizador = setInterval(actualizarTemporizador, 1000);
        } else {
            sessionStorage.setItem('pin_tiempo_inicio', Date.now().toString());
            iniciarTemporizador();
        }
    }
    
    function actualizarTemporizador() {
        const inicio = parseInt(sessionStorage.getItem('pin_tiempo_inicio'));
        if (inicio) {
            const transcurrido = Math.floor((Date.now() - inicio) / 1000);
            const restante = tiempoLimite - transcurrido;
            
            if (restante <= 0) {
                if (temporizador) clearInterval(temporizador);
                tiempoRestanteSpan.textContent = 'El código ha expirado. Solicita uno nuevo.';
                if (btnReenviar) btnReenviar.style.display = 'inline';
                btnVerificar.disabled = true;
            } else {
                const minutos = Math.floor(restante / 60);
                const segundos = restante % 60;
                tiempoRestanteSpan.textContent = `Tiempo restante: ${minutos}:${segundos.toString().padStart(2, '0')}`;
                btnVerificar.disabled = false;
            }
        }
    }
    
    function mostrarAlerta(mensaje, tipo) {
        const alertDiv = document.createElement('div');
        alertDiv.className = tipo === 'success' ? 'success-message' : 'error-message-global';
        alertDiv.textContent = mensaje;
        const card = document.querySelector('.card');
        const existingAlert = card.querySelector('.success-message, .error-message-global');
        if (existingAlert) existingAlert.remove();
        card.insertBefore(alertDiv, card.firstChild);
        setTimeout(() => alertDiv.remove(), 5000);
    }
    
    function mostrarLoader() {
        const originalText = btnVerificar.innerHTML;
        btnVerificar.disabled = true;
        btnVerificar.innerHTML = `Verificando... <span class="loader"></span>`;
        btnVerificar.setAttribute('data-original-text', originalText);
    }
    
    function ocultarLoader() {
        const originalText = btnVerificar.getAttribute('data-original-text');
        btnVerificar.disabled = false;
        btnVerificar.innerHTML = originalText || 'Verificar Cuenta';
    }
    
    // Verificar PIN
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const pin = pinInput.value.trim();
        
        if (!pin || pin.length !== 6 || !/^\d+$/.test(pin)) {
            mostrarAlerta('Por favor ingresa un código PIN válido de 6 dígitos', 'error');
            return;
        }
        
        mostrarLoader();
        
        try {
            const formData = new FormData();
            formData.append('correo', correo);
            formData.append('pin', pin);
            
            const response = await fetch('php/verificar_pin.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                mostrarAlerta(result.message, 'success');
                sessionStorage.removeItem('correo_verificar');
                sessionStorage.removeItem('pin_tiempo_inicio');
                setTimeout(() => {
                    window.location.href = result.redirect || '../admin/usuario/index.php';
                }, 2000);
            } else {
                mostrarAlerta(result.message, 'error');
                pinInput.value = '';
                pinInput.focus();
            }
        } catch (error) {
            console.error('Error:', error);
            mostrarAlerta('Error de conexión con el servidor', 'error');
        } finally {
            ocultarLoader();
        }
    });
    
    // Reenviar PIN
    btnReenviar?.addEventListener('click', async function(e) {
        e.preventDefault();
        
        try {
            const formData = new FormData();
            formData.append('correo', correo);
            
            const response = await fetch('php/enviar_pin.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                mostrarAlerta('Se ha enviado un nuevo código PIN a tu correo', 'success');
                sessionStorage.setItem('pin_tiempo_inicio', Date.now().toString());
                if (temporizador) clearInterval(temporizador);
                iniciarTemporizador();
            } else {
                mostrarAlerta(result.message, 'error');
            }
        } catch (error) {
            mostrarAlerta('Error al reenviar el código', 'error');
        }
    });
    
    // Solo números en el input
    pinInput?.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
    });
    
    iniciarTemporizador();
});