
//  Recuperación de Contraseña 
 
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página de recuperación cargada');
    
    const paso1 = document.getElementById('paso1');
    const paso2 = document.getElementById('paso2');
    const paso3 = document.getElementById('paso3');
    
    const formSolicitar = document.getElementById('solicitarRecuperacionForm');
    const formVerificar = document.getElementById('verificarCodigoForm');
    const formCambiar = document.getElementById('cambiarContraseniaForm');
    
    let correo = null;
    let tiempoLimite = 30 * 60;
    let temporizador = null;
    
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
    
    function showError(input, message) {
        input.classList.add('error');
        input.classList.remove('valid');
        let errorDiv = input.parentElement.querySelector('.error-message');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            input.parentElement.appendChild(errorDiv);
        }
        errorDiv.textContent = message;
        errorDiv.classList.add('show');
    }
    
    function clearError(input) {
        input.classList.remove('error');
        input.classList.add('valid');
        const errorDiv = input.parentElement.querySelector('.error-message');
        if (errorDiv) {
            errorDiv.classList.remove('show');
        }
    }
    
    function showLoader(btnId) {
        const btn = document.getElementById(btnId);
        if (btn) {
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `Procesando... <span class="loader"></span>`;
            btn.setAttribute('data-original-text', originalText);
        }
    }
    
    function hideLoader(btnId) {
        const btn = document.getElementById(btnId);
        if (btn) {
            const originalText = btn.getAttribute('data-original-text');
            btn.disabled = false;
            btn.innerHTML = originalText || btn.innerHTML;
        }
    }
    
    // Validar fortaleza de contraseña
    function validarPasswordRecuperacion() {
        const password = document.getElementById('nueva_contrasenia')?.value || '';
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[@$!%*?&]/.test(password)
        };
        
        const reqElements = {
            length: document.getElementById('req-length2'),
            uppercase: document.getElementById('req-uppercase2'),
            lowercase: document.getElementById('req-lowercase2'),
            number: document.getElementById('req-number2'),
            special: document.getElementById('req-special2')
        };
        
        let allValid = true;
        for (const [key, element] of Object.entries(reqElements)) {
            if (element) {
                if (requirements[key]) {
                    element.classList.add('valid');
                    element.classList.remove('invalid');
                } else {
                    element.classList.add('invalid');
                    element.classList.remove('valid');
                    allValid = false;
                }
            }
        }
        
        const requirementsPanel = document.getElementById('passwordRequirements');
        if (password.length > 0 && !allValid) {
            requirementsPanel.style.display = 'block';
        } else {
            requirementsPanel.style.display = 'none';
        }
        
        if (!allValid && password) {
            showError(document.getElementById('nueva_contrasenia'), 'La contraseña no cumple los requisitos');
        } else if (!password) {
            showError(document.getElementById('nueva_contrasenia'), 'La contraseña es requerida');
        } else {
            clearError(document.getElementById('nueva_contrasenia'));
        }
        
        return allValid && password !== '';
    }
    
    function validarConfirmPassword() {
        const password = document.getElementById('nueva_contrasenia')?.value || '';
        const confirm = document.getElementById('confirmar_contrasenia')?.value || '';
        const isValid = password === confirm;
        
        const confirmInput = document.getElementById('confirmar_contrasenia');
        if (!isValid && confirm) {
            showError(confirmInput, 'Las contraseñas no coinciden');
        } else if (!confirm) {
            showError(confirmInput, 'Confirme su contraseña');
        } else {
            clearError(confirmInput);
        }
        
        return isValid && confirm !== '';
    }
    
    // Solicitar recuperación
    formSolicitar?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const correoInput = document.getElementById('correo');
        const email = correoInput?.value.trim();
        
        if (!email) {
            mostrarAlerta('Por favor ingresa tu correo electrónico', 'error');
            return;
        }
        
        showLoader('btnSolicitar');
        
        try {
            const formData = new FormData();
            formData.append('accion', 'solicitar');
            formData.append('correo', email);
            
            const response = await fetch('php/procesar_recuperacion.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                correo = email;
                mostrarAlerta(result.message, 'success');
                sessionStorage.setItem('recuperacion_correo', email);
                sessionStorage.setItem('recuperacion_tiempo', Date.now().toString());
                paso1.style.display = 'none';
                paso2.style.display = 'block';
                paso3.style.display = 'none';
                iniciarTemporizador();
            } else {
                mostrarAlerta(result.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            mostrarAlerta('Error de conexión con el servidor', 'error');
        } finally {
            hideLoader('btnSolicitar');
        }
    });
    
    // Verificar código
    formVerificar?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const codigo = document.getElementById('codigo')?.value.trim();
        
        if (!codigo || codigo.length !== 6) {
            mostrarAlerta('Por favor ingresa el código de 6 dígitos', 'error');
            return;
        }
        
        showLoader('btnVerificar');
        
        try {
            const formData = new FormData();
            formData.append('accion', 'verificar');
            formData.append('correo', correo);
            formData.append('codigo', codigo);
            
            const response = await fetch('php/procesar_recuperacion.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                mostrarAlerta(result.message, 'success');
                paso1.style.display = 'none';
                paso2.style.display = 'none';
                paso3.style.display = 'block';
                
                // Configurar eventos de validación de contraseña
                const nuevaPass = document.getElementById('nueva_contrasenia');
                const confirmPass = document.getElementById('confirmar_contrasenia');
                nuevaPass?.addEventListener('input', validarPasswordRecuperacion);
                confirmPass?.addEventListener('input', validarConfirmPassword);
            } else {
                mostrarAlerta(result.message, 'error');
                document.getElementById('codigo').value = '';
                document.getElementById('codigo').focus();
            }
        } catch (error) {
            console.error('Error:', error);
            mostrarAlerta('Error de conexión con el servidor', 'error');
        } finally {
            hideLoader('btnVerificar');
        }
    });
    
    // Cambiar contraseña
    formCambiar?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const nuevaPassword = document.getElementById('nueva_contrasenia')?.value;
        const confirmPassword = document.getElementById('confirmar_contrasenia')?.value;
        
        if (!nuevaPassword || !confirmPassword) {
            mostrarAlerta('Todos los campos son obligatorios', 'error');
            return;
        }
        
        if (nuevaPassword !== confirmPassword) {
            mostrarAlerta('Las contraseñas no coinciden', 'error');
            return;
        }
        
        if (!validarPasswordRecuperacion()) {
            mostrarAlerta('La contraseña no cumple con los requisitos de seguridad', 'error');
            return;
        }
        
        showLoader('btnCambiar');
        
        try {
            const formData = new FormData();
            formData.append('accion', 'cambiar');
            formData.append('nueva_contrasenia', nuevaPassword);
            formData.append('confirmar_contrasenia', confirmPassword);
            
            const response = await fetch('php/procesar_recuperacion.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                mostrarAlerta(result.message, 'success');
                sessionStorage.removeItem('recuperacion_correo');
                sessionStorage.removeItem('recuperacion_tiempo');
                setTimeout(() => {
                    window.location.href = 'index.html';
                }, 2000);
            } else {
                mostrarAlerta(result.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            mostrarAlerta('Error de conexión con el servidor', 'error');
        } finally {
            hideLoader('btnCambiar');
        }
    });
    
    // Reenviar código
    document.getElementById('reenviarCodigo')?.addEventListener('click', async function(e) {
        e.preventDefault();
        
        try {
            const formData = new FormData();
            formData.append('accion', 'solicitar');
            formData.append('correo', correo);
            
            const response = await fetch('php/procesar_recuperacion.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                mostrarAlerta('Se ha enviado un nuevo código a tu correo', 'success');
                sessionStorage.setItem('recuperacion_tiempo', Date.now().toString());
                iniciarTemporizador();
            } else {
                mostrarAlerta(result.message, 'error');
            }
        } catch (error) {
            mostrarAlerta('Error al reenviar el código', 'error');
        }
    });
    
    // Botones de navegación
    document.getElementById('btnVolver')?.addEventListener('click', function() {
        if (temporizador) clearInterval(temporizador);
        paso1.style.display = 'block';
        paso2.style.display = 'none';
        paso3.style.display = 'none';
        document.getElementById('correo').value = '';
    });
    
    document.getElementById('btnCancelar')?.addEventListener('click', function() {
        if (temporizador) clearInterval(temporizador);
        paso1.style.display = 'block';
        paso2.style.display = 'none';
        paso3.style.display = 'none';
        document.getElementById('correo').value = '';
    });
    
    // Temporizador
    function iniciarTemporizador() {
        if (temporizador) clearInterval(temporizador);
        actualizarTemporizador();
        temporizador = setInterval(actualizarTemporizador, 1000);
    }
    
    function actualizarTemporizador() {
        const inicio = sessionStorage.getItem('recuperacion_tiempo');
        if (inicio) {
            const transcurrido = Math.floor((Date.now() - parseInt(inicio)) / 1000);
            const restante = tiempoLimite - transcurrido;
            
            const tiempoRestanteSpan = document.getElementById('tiempoRestante');
            if (restante <= 0) {
                clearInterval(temporizador);
                if (tiempoRestanteSpan) {
                    tiempoRestanteSpan.textContent = 'El código ha expirado. Por favor solicita uno nuevo.';
                }
            } else {
                const minutos = Math.floor(restante / 60);
                const segundos = restante % 60;
                if (tiempoRestanteSpan) {
                    tiempoRestanteSpan.textContent = `Tiempo restante: ${minutos}:${segundos.toString().padStart(2, '0')}`;
                }
            }
        }
    }
    
    // Solo números en input de código
    const codigoInput = document.getElementById('codigo');
    codigoInput?.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
    });
});