// Password strength indicator for registration and signin forms

document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.querySelector('input[name="password"]');
    if (!passwordInput) return;

    // Create strength indicator element
    const strengthIndicator = document.createElement('div');
    strengthIndicator.id = 'password-strength-indicator';
    strengthIndicator.style.marginTop = '8px';
    strengthIndicator.style.height = '8px';
    strengthIndicator.style.borderRadius = '4px';
    strengthIndicator.style.transition = 'background-color 0.3s ease, box-shadow 0.3s ease';
    passwordInput.parentNode.insertBefore(strengthIndicator, passwordInput.nextSibling);

    // Create message element
    const strengthMessage = document.createElement('div');
    strengthMessage.id = 'password-strength-message';
    strengthMessage.style.marginTop = '4px';
    strengthMessage.style.fontSize = '0.9rem';
    strengthMessage.style.fontWeight = '600';
    passwordInput.parentNode.insertBefore(strengthMessage, strengthIndicator.nextSibling);

    passwordInput.addEventListener('input', () => {
        const val = passwordInput.value;
        const strength = getPasswordStrength(val);

        // Update indicator color and shadow
        strengthIndicator.style.backgroundColor = strength.color;
        strengthIndicator.style.boxShadow = `0 0 8px ${strength.color}`;

        // Update message text and color
        strengthMessage.textContent = strength.message;
        strengthMessage.style.color = strength.color;
    });

    function getPasswordStrength(password) {
        let score = 0;

        if (!password) {
            return { color: '#ccc', message: '' };
        }
        if (password.length >= 8) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[a-z]/.test(password)) score++;
        if (/\d/.test(password)) score++;
        if (/[\W_]/.test(password)) score++;

        if (score <= 2) {
            return { color: '#ef4444', message: 'Weak password' };
        } else if (score === 3) {
            return { color: '#f59e0b', message: 'Moderate password' };
        } else if (score >= 4) {
            return { color: '#10b981', message: 'Strong password' };
        }
    }
});
