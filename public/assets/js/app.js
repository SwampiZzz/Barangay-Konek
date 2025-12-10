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
        el.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${msg}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
        
        // Scroll to alert if it's an error
        if (type === 'danger') {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
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

            const requiredFields = ['first_name', 'last_name', 'sex_id', 'username', 'password', 'password_confirm', 'email', 'barangay_id', 'birthdate'];
            const missing = requiredFields.filter(name => !(registerForm.querySelector(`[name="${name}"]`).value || '').trim());
            if (missing.length) {
                showAlert(registerAlert, 'danger', 'Please fill all required fields.');
                return;
            }

            // Validate age (must be 18+)
            const birthdateInput = registerForm.querySelector('[name="birthdate"]').value;
            if (birthdateInput) {
                const birthDate = new Date(birthdateInput);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                if (age < 18) {
                    showAlert(registerAlert, 'danger', 'You must be at least 18 years old to register.');
                    return;
                }
            }

            // Validate contact number format (09xx-xxx-xxxx)
            const contactNumber = (registerForm.querySelector('[name="contact_number"]').value || '').trim();
            if (contactNumber && !/^09\d{2}-\d{3}-\d{4}$/.test(contactNumber)) {
                showAlert(registerAlert, 'danger', 'Contact number must follow format: 09XX-XXX-XXXX');
                return;
            }

            const password = registerForm.querySelector('[name="password"]').value;
            const confirm = registerForm.querySelector('[name="password_confirm"]').value;
            if (password !== confirm) {
                showAlert(registerAlert, 'danger', 'Passwords do not match.');
                return;
            }

            // Check email uniqueness
            const email = (registerForm.querySelector('[name="email"]').value || '').trim();
            if (email) {
                fetch(`index.php?nav=register-api&action=check_email&email=${encodeURIComponent(email)}`, {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                .then(r => {
                    if (!r.ok) {
                        throw new Error(`HTTP ${r.status}`);
                    }
                    return r.text();
                })
                .then(text => {
                    if (!text) {
                        console.error('Empty response from email check');
                        submitRegisterForm();
                        return;
                    }
                    const data = JSON.parse(text);
                    if (data.exists) {
                        showAlert(registerAlert, 'danger', 'This email is already registered.');
                        return;
                    }
                    // Email is unique, proceed with registration
                    submitRegisterForm();
                })
                .catch(err => {
                    console.error('Email check error:', err);
                    // Continue with registration if check fails
                    submitRegisterForm();
                });
                return;
            }

            function submitRegisterForm() {
                const formData = new FormData(registerForm);
            formData.set('action', 'register');
            setButtonLoading(registerBtn, true, 'Creating account...');

            fetch(authUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.text())
            .then(text => {
                if (!text) {
                    showAlert(registerAlert, 'danger', 'Empty response from server');
                    setButtonLoading(registerBtn, false);
                    return;
                }
                const json = JSON.parse(text);
                if (json.success) {
                    showAlert(registerAlert, 'success', json.message || 'Registered successfully');
                    registerForm.reset();

                    const registerModalEl = document.getElementById('registerModal');
                    const loginModalEl = document.getElementById('loginModal');
                    const loginAlert = document.getElementById('loginAlert');
                    const showLoginAfterHide = () => {
                        registerModalEl.removeEventListener('hidden.bs.modal', showLoginAfterHide);
                        
                        const loginModal = new bootstrap.Modal(loginModalEl);
                        loginModal.show();
                        
                        // Show detailed success message in login modal - after modal is shown
                        setTimeout(() => {
                            if (loginAlert) {
                                const successHTML = `
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        <div style="display: flex; gap: 1rem; align-items: flex-start;">
                                            <i class="fas fa-check-circle" style="color: #28a745; font-size: 1.5rem; flex-shrink: 0; margin-top: 0.2rem;"></i>
                                            <div style="padding-right: 2rem;">
                                                <strong style="font-size: 1.1rem; color: #155724;">✓ Registration Successful!</strong>
                                                <p style="margin: 0.75rem 0 0 0; font-size: 0.95rem; color: #155724;">Your account has been created successfully. You can now log in with your username and password below.</p>
                                                <p style="margin: 0.75rem 0 0 0; font-size: 0.9rem; color: #155724; border-left: 3px solid #28a745; padding-left: 1rem;">
                                                    <i class="fas fa-info-circle me-1"></i><strong>Next Steps:</strong> After logging in, your account will need to be verified by the barangay administrator before you can submit requests or complaints.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                `;
                                loginAlert.innerHTML = successHTML;
                            }
                        }, 100);
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
            }
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

    // Register modal: load provinces on modal show
    if (registerModalEl) {
        registerModalEl.addEventListener('show.bs.modal', function() {
            const provinceSelect = document.getElementById('provinceSelect');
            if (provinceSelect && provinceSelect.options.length === 1) {
                // Load provinces
                fetch('index.php?nav=register-api&action=get_provinces', {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.provinces) {
                        data.provinces.forEach(p => {
                            const opt = document.createElement('option');
                            opt.value = p.id;
                            opt.textContent = p.name;
                            provinceSelect.appendChild(opt);
                        });
                    }
                })
                .catch(err => console.error('Failed to load provinces:', err));
            }
        });

        // Province change: load cities
        document.getElementById('provinceSelect').addEventListener('change', function() {
            const provinceId = this.value;
            const citySelect = document.getElementById('citySelect');
            const barangaySelect = document.getElementById('barangaySelect');
            
            citySelect.innerHTML = '<option value="">-- Select City --</option>';
            barangaySelect.innerHTML = '<option value="">-- Select Barangay --</option>';
            
            if (!provinceId) return;
            
            fetch(`index.php?nav=register-api&action=get_cities&province_id=${provinceId}`, {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.cities) {
                    data.cities.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.name;
                        citySelect.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error('Failed to load cities:', err));
        });

        // City change: load barangays
        document.getElementById('citySelect').addEventListener('change', function() {
            const cityId = this.value;
            const barangaySelect = document.getElementById('barangaySelect');
            
            barangaySelect.innerHTML = '<option value="">-- Select Barangay --</option>';
            
            if (!cityId) return;
            
            fetch(`index.php?nav=register-api&action=get_barangays&city_id=${cityId}`, {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.barangays) {
                    data.barangays.forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.id;
                        opt.textContent = b.name;
                        barangaySelect.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error('Failed to load barangays:', err));
        });
    }

    // Toggle password visibility in register form
    const toggleRegisterPassword = document.getElementById('toggleRegisterPassword');
    if (toggleRegisterPassword) {
        toggleRegisterPassword.addEventListener('click', function() {
            const passwordInput = document.getElementById('registerPassword');
            const passwordIcon = document.getElementById('registerPasswordIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        });
    }
});
