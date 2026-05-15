// ============================================================
// NexusGear - Real-time Form Validation
// ============================================================

// ---- Password strength calculation ----
function calcStrength(password) {
    let score = 0;
    if (password.length >= 8)  score++;
    if (password.length >= 12) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    return score; // 0-6
}

function updateStrengthBar(input) {
    const wrapper = input.closest('.password-strength-wrapper') || input.parentElement.parentElement;
    const bar  = wrapper ? wrapper.querySelector('.strength-bar-fill') : null;
    const text = wrapper ? wrapper.querySelector('.strength-text') : null;
    if (!bar || !text) return;

    const score = calcStrength(input.value);
    const levels = [
        { w: '0%',   color: '',        label: '' },
        { w: '17%',  color: '#ff2d78', label: 'Muy débil' },
        { w: '33%',  color: '#ff6b35', label: 'Débil' },
        { w: '50%',  color: '#ffa500', label: 'Regular' },
        { w: '67%',  color: '#c8ff00', label: 'Buena' },
        { w: '83%',  color: '#00e5ff', label: 'Fuerte' },
        { w: '100%', color: '#00f5ff', label: '¡Excelente!' },
    ];

    const level = levels[Math.min(score, 6)];
    bar.style.width           = level.w;
    bar.style.backgroundColor = level.color;
    text.textContent          = level.label;
    text.style.color          = level.color;
}

// ---- Generic field validators ----
function validateField(input) {
    const name  = input.name || input.id;
    const value = input.value.trim();
    let error   = '';

    // Required
    if (input.hasAttribute('required') && !value) {
        error = 'Este campo es obligatorio.';
    }
    // Email
    else if (input.type === 'email' || name === 'correo') {
        if (!value) {
            error = 'Este campo es obligatorio.';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            error = 'Correo electrónico inválido.';
        }
    }
    // Password (on register / change-password pages)
    else if (name === 'contrasena' || name === 'nueva_contrasena') {
        if (!value) {
            error = 'Este campo es obligatorio.';
        } else if (value.length < 8) {
            error = 'Mínimo 8 caracteres.';
        } else if (!/[A-Z]/.test(value)) {
            error = 'Debe contener al menos una mayúscula.';
        } else if (!/[0-9]/.test(value)) {
            error = 'Debe contener al menos un número.';
        }
    }
    // Confirm password
    else if (name === 'confirmar_contrasena' || name === 'confirmar_nueva') {
        const passId   = name === 'confirmar_contrasena' ? 'contrasena' : 'nueva_contrasena';
        const passInput = document.querySelector(`[name="${passId}"]`);
        if (!value) {
            error = 'Este campo es obligatorio.';
        } else if (passInput && value !== passInput.value) {
            error = 'Las contraseñas no coinciden.';
        }
    }
    // Nombre
    else if (name === 'nombre') {
        if (!value) {
            error = 'El nombre es obligatorio.';
        } else if (value.length < 2) {
            error = 'El nombre debe tener al menos 2 caracteres.';
        }
    }

    setFieldState(input, error);
    return !error;
}

function setFieldState(input, errorMsg) {
    // Find or create the error element
    let errorEl = input.parentElement.querySelector('.invalid-feedback');
    if (!errorEl) {
        // Check one level up for password-toggle wrappers
        errorEl = input.closest('.password-toggle')
            ? input.closest('.password-toggle').parentElement.querySelector('.invalid-feedback')
            : null;
    }

    if (errorMsg) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        if (errorEl) errorEl.textContent = errorMsg;
    } else if (input.value.trim()) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        if (errorEl) errorEl.textContent = '';
    } else {
        input.classList.remove('is-invalid', 'is-valid');
        if (errorEl) errorEl.textContent = '';
    }
}

// ---- Initialize validation on a form ----
function initFormValidation(formSelector) {
    const form = document.querySelector(formSelector);
    if (!form) return;

    const inputs = form.querySelectorAll('input, textarea, select');

    inputs.forEach(input => {
        // On blur: validate
        input.addEventListener('blur', () => validateField(input));

        // On input: update strength for password, live recheck confirm
        input.addEventListener('input', () => {
            const name = input.name || input.id;
            if (name === 'contrasena' || name === 'nueva_contrasena') {
                updateStrengthBar(input);
                // Also re-validate confirm field if it has a value
                const confirm = form.querySelector('[name="confirmar_contrasena"], [name="confirmar_nueva"]');
                if (confirm && confirm.value) validateField(confirm);
            }
            if (input.classList.contains('is-invalid')) validateField(input);
        });
    });

    // Prevent submit if errors exist
    form.addEventListener('submit', function (e) {
        let valid = true;
        inputs.forEach(input => {
            if (input.type !== 'hidden' && input.type !== 'submit') {
                if (!validateField(input)) valid = false;
            }
        });
        if (!valid) {
            e.preventDefault();
            showToast('Por favor corrige los errores antes de continuar.', 'error');
        }
    });
}

// ---- Init on DOM ready ----
document.addEventListener('DOMContentLoaded', function () {
    // Register / login / profile forms
    initFormValidation('#form-register');
    initFormValidation('#form-login');
    initFormValidation('#form-perfil');
    initFormValidation('#form-change-password');

    // Password show/hide toggles
    document.querySelectorAll('.toggle-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const targetId  = this.dataset.target;
            const input     = document.getElementById(targetId);
            if (!input) return;
            input.type      = input.type === 'password' ? 'text' : 'password';
            this.textContent = input.type === 'password' ? '👁️' : '🙈';
        });
    });

    // Card number formatter in checkout
    const cardInput = document.getElementById('card-number');
    if (cardInput) {
        cardInput.addEventListener('input', function () {
            let val = this.value.replace(/\D/g, '').slice(0, 16);
            this.value = val.replace(/(.{4})/g, '$1 ').trim();
            const display = document.getElementById('card-number-display');
            if (display) display.textContent = this.value || '•••• •••• •••• ••••';
        });
    }

    const cardName = document.getElementById('card-name');
    if (cardName) {
        cardName.addEventListener('input', function () {
            const display = document.getElementById('card-name-display');
            if (display) display.textContent = this.value.toUpperCase() || 'TU NOMBRE';
        });
    }
});
