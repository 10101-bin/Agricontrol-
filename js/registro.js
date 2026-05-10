/**
 * Módulo de Registro para Agricontrol
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página de registro cargada');
    
    const form = document.getElementById('registroForm');
    const btnSubmit = document.getElementById('btnRegistrar');
    
    // Elementos del formulario
    const nombreInput = document.getElementById('nombre');
    const apellidoInput = document.getElementById('apellido');
    const cedulaInput = document.getElementById('cedula');
    const telefonoInput = document.getElementById('telefono');
    const correoInput = document.getElementById('correo');
    const sexoSelect = document.getElementById('sexo');
    const passwordInput = document.getElementById('contrasenia');
    const confirmInput = document.getElementById('confirmar_contrasenia');
    
    // Crear panel de requisitos de contraseña
    const passwordGroup = passwordInput?.parentElement;
    const requirementsDiv = document.createElement('div');
    requirementsDiv.className = 'password-requirements';
    requirementsDiv.innerHTML = `
        <strong>Requisitos de contraseña segura:</strong>
        <ul>
            <li id="req-length">✓ Mínimo 8 caracteres</li>
            <li id="req-uppercase">✓ Al menos una mayúscula</li>
            <li id="req-lowercase">✓ Al menos una minúscula</li>
            <li id="req-number">✓ Al menos un número</li>
            <li id="req-special">✓ Al menos un carácter especial (@$!%*?&)</li>
        </ul>
    `;
    if (passwordGroup) passwordGroup.appendChild(requirementsDiv);
    
    // Funciones de utilidad
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
    
    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = type === 'success' ? 'success-message' : 'error-message-global';
        alertDiv.textContent = message;
        const card = document.querySelector('.card');
        const existingAlert = card.querySelector('.success-message, .error-message-global');
        if (existingAlert) existingAlert.remove();
        card.insertBefore(alertDiv, card.firstChild);
        setTimeout(() => alertDiv.remove(), 5000);
    }
    
    // Validaciones
    function validateNombre() {
        const nombre = nombreInput.value.trim();
        const regex = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]{2,50}$/;
        const isValid = regex.test(nombre);
        if (!isValid && nombre) showError(nombreInput, 'Nombre debe tener 2-50 caracteres y solo letras');
        else if (!nombre) showError(nombreInput, 'El nombre es requerido');
        else clearError(nombreInput);
        return isValid && nombre !== '';
    }
    
    function validateApellido() {
        const apellido = apellidoInput.value.trim();
        const regex = /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]{2,50}$/;
        const isValid = regex.test(apellido);
        if (!isValid && apellido) showError(apellidoInput, 'Apellido debe tener 2-50 caracteres y solo letras');
        else if (!apellido) showError(apellidoInput, 'El apellido es requerido');
        else clearError(apellidoInput);
        return isValid && apellido !== '';
    }
    
    function validateCedula() {
        const cedula = cedulaInput.value.trim();
        const regex = /^[0-9]{6,10}$/;
        const isValid = regex.test(cedula);
        if (!isValid && cedula) showError(cedulaInput, 'Cédula debe tener entre 6 y 10 dígitos');
        else if (!cedula) showError(cedulaInput, 'La cédula es requerida');
        else clearError(cedulaInput);
        return isValid && cedula !== '';
    }
    
    function validateTelefono() {
        const telefono = telefonoInput.value.trim();
        const regex = /^[0-9]{7,10}$/;
        const isValid = regex.test(telefono);
        if (!isValid && telefono) showError(telefonoInput, 'Teléfono debe tener 7-10 dígitos');
        else if (!telefono) showError(telefonoInput, 'El teléfono es requerido');
        else clearError(telefonoInput);
        return isValid && telefono !== '';
    }
    
    
    function validateEmail() {
        const email = correoInput.value.trim();
        const isValid = email !== '';  // Solo verifica que no esté vacío
        if (!isValid) showError(correoInput, 'El correo es requerido');
        else clearError(correoInput);
        return isValid;
    }
    
    function validatePassword() {
        const password = passwordInput.value;
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[@$!%*?&]/.test(password)
        };
        
        const reqElements = {
            length: document.getElementById('req-length'),
            uppercase: document.getElementById('req-uppercase'),
            lowercase: document.getElementById('req-lowercase'),
            number: document.getElementById('req-number'),
            special: document.getElementById('req-special')
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
        
        if (password.length > 0 && !allValid) {
            requirementsDiv.classList.add('show');
        } else {
            requirementsDiv.classList.remove('show');
        }
        
        if (!allValid && password) showError(passwordInput, 'La contraseña no cumple los requisitos');
        else if (!password) showError(passwordInput, 'La contraseña es requerida');
        else clearError(passwordInput);
        
        return allValid && password !== '';
    }
    
    function validateConfirmPassword() {
        const password = passwordInput.value;
        const confirm = confirmInput.value;
        const isValid = password === confirm;
        if (!isValid && confirm) showError(confirmInput, 'Las contraseñas no coinciden');
        else if (!confirm) showError(confirmInput, 'Confirme su contraseña');
        else clearError(confirmInput);
        return isValid && confirm !== '';
    }
    
    // Verificaciones de unicidad
    let debounceTimer;
    
    async function checkEmailUniqueness() {
        const email = correoInput.value.trim();
        if (!email || !validateEmail()) return;
        
        try {
            const formData = new FormData();
            formData.append('correo', email);
            formData.append('tipo', 'email');
            
            const response = await fetch('php/verificar_correo_existe.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.exists) {
                showError(correoInput, 'Este correo ya está registrado');
            }
        } catch (error) {
            console.error('Error verificando email:', error);
        }
    }
    
    async function checkCedulaUniqueness() {
        const cedula = cedulaInput.value.trim();
        if (!cedula || !validateCedula()) return;
        
        try {
            const formData = new FormData();
            formData.append('cedula', cedula);
            formData.append('tipo', 'cedula');
            
            const response = await fetch('php/verificar_correo_existe.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.exists) {
                showError(cedulaInput, 'Esta cédula ya está registrada');
            }
        } catch (error) {
            console.error('Error verificando cédula:', error);
        }
    }
    
    async function checkTelefonoUniqueness() {
        const telefono = telefonoInput.value.trim();
        if (!telefono || !validateTelefono()) return;
        
        try {
            const formData = new FormData();
            formData.append('telefono', telefono);
            formData.append('tipo', 'telefono');
            
            const response = await fetch('php/verificar_correo_existe.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.exists) {
                showError(telefonoInput, 'Este teléfono ya está registrado');
            }
        } catch (error) {
            console.error('Error verificando teléfono:', error);
        }
    }
    
    // Eventos de validación
    nombreInput?.addEventListener('input', validateNombre);
    apellidoInput?.addEventListener('input', validateApellido);
    cedulaInput?.addEventListener('input', validateCedula);
    telefonoInput?.addEventListener('input', validateTelefono);
    correoInput?.addEventListener('input', validateEmail);
    passwordInput?.addEventListener('input', validatePassword);
    confirmInput?.addEventListener('input', validateConfirmPassword);
    
    // Eventos de unicidad con debounce
    correoInput?.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(checkEmailUniqueness, 500);
    });
    
    cedulaInput?.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(checkCedulaUniqueness, 500);
    });
    
    telefonoInput?.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(checkTelefonoUniqueness, 500);
    });
    
    // Envío del formulario
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const validations = [
            validateNombre(),
            validateApellido(),
            validateCedula(),
            validateTelefono(),
            validateEmail(),
            validatePassword(),
            validateConfirmPassword()
        ];
        
        if (!validations.every(v => v === true)) {
            showAlert('Por favor complete todos los campos correctamente', 'error');
            return;
        }
        
        const originalText = btnSubmit.innerHTML;
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = 'Registrando... <span class="loader"></span>';
        
        try {
            const formData = new FormData(form);
            
            const response = await fetch('php/procesar_registro.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showAlert(result.message, 'success');
                sessionStorage.setItem('correo_verificar', correoInput.value.trim());
                setTimeout(() => {
                    window.location.href = 'verificar-pin.html';
                }, 2000);
            } else {
                showAlert(result.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Error de conexión con el servidor', 'error');
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = originalText;
        }
    });
});