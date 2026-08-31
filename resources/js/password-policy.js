function setRequirementState(item, complete) {
    if (!item) return;

    const icon = item.querySelector('[data-password-requirement-icon]');
    item.dataset.complete = complete ? '1' : '0';
    item.classList.toggle('text-emerald-700', complete);
    item.classList.toggle('font-semibold', complete);
    item.classList.toggle('text-slate-600', !complete);

    if (icon) {
        icon.textContent = complete ? '✓' : '○';
        icon.classList.toggle('border-emerald-300', complete);
        icon.classList.toggle('bg-emerald-100', complete);
        icon.classList.toggle('text-emerald-700', complete);
        icon.classList.toggle('border-slate-300', !complete);
    }
}

function initPolicy(root) {
    const password = document.getElementById(root.dataset.passwordInput || 'password');
    const confirmation = document.getElementById(root.dataset.confirmationInput || 'password_confirmation');
    if (!password || !confirmation) return;

    const minLength = Math.max(1, Number(root.dataset.minLength || 8));
    const requireUppercase = root.dataset.requireUppercase === '1';
    const requireNumber = root.dataset.requireNumber === '1';
    const requireSpecial = root.dataset.requireSpecial === '1';
    const specialCharacters = root.dataset.specialCharacters || '@$!%*#?&';
    const escapedSpecials = specialCharacters.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const specialRegex = new RegExp(`[${escapedSpecials}]`);
    const status = root.querySelector('[data-password-policy-status]');

    const items = {
        min_length: root.querySelector('[data-password-requirement="min_length"]'),
        uppercase: root.querySelector('[data-password-requirement="uppercase"]'),
        number: root.querySelector('[data-password-requirement="number"]'),
        special: root.querySelector('[data-password-requirement="special"]'),
        confirmation: root.querySelector('[data-password-requirement="confirmation"]'),
    };

    const update = () => {
        const value = password.value || '';
        const confirmationValue = confirmation.value || '';
        const states = {
            min_length: value.length >= minLength,
            uppercase: !requireUppercase || /[A-Z]/.test(value),
            number: !requireNumber || /[0-9]/.test(value),
            special: !requireSpecial || specialRegex.test(value),
            confirmation: confirmationValue.length > 0 && value === confirmationValue,
        };

        Object.entries(states).forEach(([key, complete]) => setRequirementState(items[key], complete));

        const requiredStates = Object.entries(states)
            .filter(([key]) => items[key] !== null)
            .map(([, complete]) => complete);
        const ready = requiredStates.length > 0 && requiredStates.every(Boolean);

        if (status) {
            status.textContent = ready ? 'Ready' : 'Not ready';
            status.classList.toggle('bg-emerald-100', ready);
            status.classList.toggle('text-emerald-700', ready);
            status.classList.toggle('bg-slate-200', !ready);
            status.classList.toggle('text-slate-600', !ready);
        }

        password.setAttribute('aria-invalid', value.length > 0 && !states.min_length ? 'true' : 'false');
        confirmation.setAttribute('aria-invalid', confirmationValue.length > 0 && !states.confirmation ? 'true' : 'false');
    };

    password.addEventListener('input', update);
    confirmation.addEventListener('input', update);
    update();
}

export function initPasswordPolicies() {
    document.querySelectorAll('[data-password-policy]').forEach(initPolicy);
}
