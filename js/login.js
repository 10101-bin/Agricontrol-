document.addEventListener("DOMContentLoaded", function () {
  console.log("Página de login cargada");

  const form = document.getElementById("loginForm");
  const correoInput = document.getElementById("correo");
  const passwordInput = document.getElementById("contrasenia");
  const btnLogin = document.getElementById("btnLogin");

  // Funciones de utilidad
  function showError(input, message) {
    input.classList.add("error");
    input.classList.remove("valid");
    let errorDiv = input.parentElement.querySelector(".error-message");
    if (!errorDiv) {
      errorDiv = document.createElement("div");
      errorDiv.className = "error-message";
      input.parentElement.appendChild(errorDiv);
    }
    errorDiv.textContent = message;
    errorDiv.classList.add("show");
  }

  function clearError(input) {
    input.classList.remove("error");
    input.classList.add("valid");
    const errorDiv = input.parentElement.querySelector(".error-message");
    if (errorDiv) {
      errorDiv.classList.remove("show");
    }
  }

  function showAlert(message, type) {
    const alertDiv = document.createElement("div");
    alertDiv.className = type === "success" ? "success-message" : "error-message-global";
    alertDiv.textContent = message;
    const card = document.querySelector(".card");
    const existingAlert = card.querySelector(".success-message, .error-message-global");
    if (existingAlert) existingAlert.remove();
    card.insertBefore(alertDiv, card.firstChild);
    setTimeout(() => alertDiv.remove(), 5000);
  }

  // Validaciones
  function validateEmail() {
    const email = correoInput.value.trim();
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const isValid = regex.test(email);
    if (!isValid && email) showError(correoInput, "Ingrese un correo válido");
    else if (!email) showError(correoInput, "El correo es requerido");
    else clearError(correoInput);
    return isValid && email !== "";
  }

  function validatePassword() {
    const password = passwordInput.value;
    const isValid = password.length > 0;
    if (!isValid) showError(passwordInput, "La contraseña es requerida");
    else clearError(passwordInput);
    return isValid;
  }

  // Eventos
  correoInput?.addEventListener("input", validateEmail);
  correoInput?.addEventListener("blur", validateEmail);
  passwordInput?.addEventListener("input", validatePassword);

  // Envío del formulario
  form.addEventListener("submit", async function (e) {
    e.preventDefault();

    const isEmailValid = validateEmail();
    const isPasswordValid = validatePassword();

    if (!isEmailValid || !isPasswordValid) {
      showAlert("Por favor complete todos los campos correctamente", "error");
      return;
    }

    const originalText = btnLogin.innerHTML;
    btnLogin.disabled = true;
    btnLogin.innerHTML = 'Iniciando sesión... <span class="loader"></span>';

    try {
      const formData = new FormData();
      formData.append("correo", correoInput.value.trim());
      formData.append("contrasenia", passwordInput.value);

      const response = await fetch("php/procesar_login.php", {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        showAlert(result.message, 'success');
        setTimeout(() => {
            window.location.href = 'admin/usuario/index.php';
        }, 1500);
      } else {
        showAlert(result.message, "error");
        passwordInput.value = "";
        passwordInput.focus();
      }
    } catch (error) {
      console.error("Error:", error);
      showAlert("Error de conexión con el servidor", "error");
    } finally {
      btnLogin.disabled = false;
      btnLogin.innerHTML = originalText;
    }
  });
});
