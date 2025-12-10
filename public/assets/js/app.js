document.addEventListener('DOMContentLoaded', function() {
    const authUrl = window.AUTH_URL || '/middleware/auth.php';
    const appRoot = window.APP_ROOT || '';

    const roleMap = {
        1: 'superadmin-dashboard',
        2: 'admin-dashboard',
        3: 'staff-dashboard',
        4: 'user-dashboard'
    };

    const setButtonLoading = (btn, isLoading, text = 'Please wait...') => {
        if (!btn) return;
        if (isLoading) {
            btn.dataset.originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + text;
        } else {
            btn.disabled = false;
            if (btn.dataset.originalText) {
                btn.innerHTML = btn.dataset.originalText;
            }
        }
    };

    const showAlert = (el, type, msg) => {
        if (!el) return;
        el.innerHTML = `<div class="alert alert-${type}">${msg}</div>`;
    };

    const buildDashboardUrl = (role) => {
        const nav = roleMap[role] || 'user-dashboard';
        if (appRoot && appRoot !== '/') return `${appRoot}/index.php?nav=${nav}`;
        return `/index.php?nav=${nav}`;
    };

    // Login form handling
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        const loginAlert = document.getElementById('loginAlert');
        const loginBtn = loginForm.querySelector('button[type="submit"]');
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            loginAlert.innerHTML = '';

            const username = (loginForm.querySelector('[name="username"]').value || '').trim();
            const password = (loginForm.querySelector('[name="password"]').value || '').trim();
            if (!username || !password) {
                showAlert(loginAlert, 'danger', 'Please enter both username and password.');
                return;
            }

            const formData = new FormData(loginForm);
            formData.set('action', 'login');
            setButtonLoading(loginBtn, true, 'Signing in...');

            fetch(authUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(json => {
                if (json.success) {
                    showAlert(loginAlert, 'success', json.message || 'Logged in successfully');
                    setTimeout(() => {
                        window.location.href = buildDashboardUrl(json.role);
                    }, 600);
                } else {
                    showAlert(loginAlert, 'danger', json.message || 'Login failed');
                }
            })
            .catch(err => {
                console.error('Login fetch error:', err);
                showAlert(loginAlert, 'danger', 'Network error: ' + err.message);
            })
            .finally(() => setButtonLoading(loginBtn, false));
        });
    }

    // Register form handling
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        const registerAlert = document.getElementById('registerAlert');
        const registerBtn = registerForm.querySelector('button[type="submit"]');
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            registerAlert.innerHTML = '';

            const requiredFields = ['first_name', 'last_name', 'username', 'password', 'password_confirm'];
            const missing = requiredFields.filter(name => !(registerForm.querySelector(`[name="${name}"]`).value || '').trim());
            if (missing.length) {
                showAlert(registerAlert, 'danger', 'Please fill all required fields.');
                return;
            }

            const password = registerForm.querySelector('[name="password"]').value;
            const confirm = registerForm.querySelector('[name="password_confirm"]').value;
            if (password !== confirm) {
                showAlert(registerAlert, 'danger', 'Passwords do not match.');
                return;
            }

            const formData = new FormData(registerForm);
            formData.set('action', 'register');
            setButtonLoading(registerBtn, true, 'Creating account...');

            fetch(authUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(json => {
                if (json.success) {
                    showAlert(registerAlert, 'success', json.message || 'Registered successfully');
                    registerForm.reset();

                    const registerModalEl = document.getElementById('registerModal');
                    const loginModalEl = document.getElementById('loginModal');
                    const showLoginAfterHide = () => {
                        registerModalEl.removeEventListener('hidden.bs.modal', showLoginAfterHide);
                        const loginModal = new bootstrap.Modal(loginModalEl);
                        loginModal.show();
                    };

                    if (document.activeElement) document.activeElement.blur();

                    const registerModal = bootstrap.Modal.getInstance(registerModalEl);
                    if (registerModal) {
                        registerModalEl.addEventListener('hidden.bs.modal', showLoginAfterHide, { once: true });
                        registerModal.hide();
                    } else {
                        showLoginAfterHide();
                    }
                } else {
                    showAlert(registerAlert, 'danger', json.message || 'Registration failed');
                }
            })
            .catch(err => {
                console.error('Register fetch error:', err);
                showAlert(registerAlert, 'danger', 'Network error: ' + err.message);
            })
            .finally(() => setButtonLoading(registerBtn, false));
        });
    }

    // Clear alerts when modals open
    const loginModalEl = document.getElementById('loginModal');
    const registerModalEl = document.getElementById('registerModal');
    if (loginModalEl) {
        loginModalEl.addEventListener('show.bs.modal', () => {
            const alertEl = document.getElementById('loginAlert');
            if (alertEl) alertEl.innerHTML = '';
        });
    }
    if (registerModalEl) {
        registerModalEl.addEventListener('show.bs.modal', () => {
            const alertEl = document.getElementById('registerAlert');
            if (alertEl) alertEl.innerHTML = '';
        });
    }

    // Switch between login/register modals via link
    document.querySelectorAll('[data-switch-target]').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = link.getAttribute('data-switch-target');
            const targetEl = document.getElementById(targetId);
            if (!targetEl) return;

            // Hide any open modal then show target
            const openModal = bootstrap.Modal.getInstance(document.querySelector('.modal.show'));
            const showTarget = () => {
                const modal = new bootstrap.Modal(targetEl);
                modal.show();
            };
            if (openModal) {
                document.querySelector('.modal.show').addEventListener('hidden.bs.modal', showTarget, { once: true });
                openModal.hide();
            } else {
                showTarget();
            }
        });
    });

    // Highlight active nav link based on current page
    const urlParams = new URLSearchParams(window.location.search);
    const currentNav = urlParams.get('nav');
    if (currentNav) {
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
        navLinks.forEach(link => {
            if (link.getAttribute('href') && link.getAttribute('href').includes('nav=' + currentNav)) {
                link.classList.add('active');
            }
        });
    }
});
