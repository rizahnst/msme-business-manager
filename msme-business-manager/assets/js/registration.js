// MSME Business Manager - Registration Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    console.log('MSME Registration JavaScript loaded');
    
    // Initialize registration form functionality
    initRegistrationForm();
    
    // Auto-fill user data if available
    autoFillUserData();
});
// Add this new function
function autoFillUserData() {
    console.log('Checking for user data auto-fill...');
    
    // Check if user data is available
    if (typeof msme_ajax !== 'undefined' && msme_ajax.current_user) {
        console.log('User data found:', msme_ajax.current_user);
        
        const ownerNameField = document.getElementById('owner_name');
        const ownerEmailField = document.getElementById('owner_email');
        
        if (ownerNameField && ownerEmailField) {
            // Fill the fields
            ownerNameField.value = msme_ajax.current_user.display_name || '';
            ownerEmailField.value = msme_ajax.current_user.email || '';
            
            // Remove readonly from name field (allow editing)
            ownerNameField.removeAttribute('readonly');
            
            console.log('Auto-fill completed');
            console.log('Name:', ownerNameField.value);
            console.log('Email:', ownerEmailField.value);
        } else {
            console.log('Form fields not found yet, will retry...');
            // Retry after a short delay if fields aren't ready
            setTimeout(autoFillUserData, 500);
        }
    } else {
        console.log('No user data available or user not logged in');
    }
}

// Update the continue button function
function continueToBusinessForm() {
    console.log('Continue to business form clicked');
    
    // Auto-fill user data and show step 2
    autoFillUserData();
    showStep(2);
}

function initRegistrationForm() {
    // Subdomain availability checking
    initSubdomainCheck();
    
    // Form validation
    initFormValidation();
    
    // Step navigation
    initStepNavigation();
}

function initSubdomainCheck() {
    const subdomainInput = document.getElementById('subdomain');
    const checkDiv = document.getElementById('subdomain-check');
    const businessNameInput = document.getElementById('business_name');
    const businessAddressInput = document.getElementById('business_address');
    const suggestionsDiv = document.getElementById('subdomain-suggestions');
    
    let checkTimeout;
    let suggestionTimeout;
    
    // Listen to business name and address changes for suggestions
    if (businessNameInput && businessAddressInput) {
        businessNameInput.addEventListener('input', debounceSubdomainSuggestions);
        businessAddressInput.addEventListener('input', debounceSubdomainSuggestions);
    }
    
    // Listen to manual subdomain input
    if (subdomainInput && checkDiv) {
        subdomainInput.addEventListener('input', function() {
            clearTimeout(checkTimeout);
            const subdomain = this.value.toLowerCase().trim();
            
            // Clear previous results
            checkDiv.className = 'subdomain-check';
            checkDiv.textContent = '';
            
            if (subdomain.length < 3) {
                checkDiv.textContent = 'Minimal 3 karakter';
                checkDiv.className = 'subdomain-check';
                return;
            }
            
            // Validate format
            if (!/^[a-z0-9-]+$/.test(subdomain)) {
                checkDiv.textContent = 'Hanya huruf kecil, angka, dan tanda hubung (-)';
                checkDiv.className = 'subdomain-check unavailable';
                return;
            }
            
            // Check availability after delay
            checkTimeout = setTimeout(() => {
                checkSubdomainAvailability(subdomain);
            }, 500);
        });
    }
    
    function debounceSubdomainSuggestions() {
        clearTimeout(suggestionTimeout);
        suggestionTimeout = setTimeout(generateSubdomainSuggestions, 800);
    }
}

function generateSubdomainSuggestions() {
    const businessName = document.getElementById('business_name').value.trim();
    const businessAddress = document.getElementById('business_address').value.trim();
    const suggestionsDiv = document.getElementById('subdomain-suggestions');
    const suggestionsButtonsDiv = document.getElementById('suggestion-buttons');
    
    if (businessName.length < 3) {
        suggestionsDiv.style.display = 'none';
        return;
    }
    
    // Generate subdomain suggestions
    const suggestions = createSubdomainSuggestions(businessName, businessAddress);
    
    if (suggestions.length > 0) {
        // Clear previous suggestions
        suggestionsButtonsDiv.innerHTML = '';
        
        // Create suggestion buttons
        suggestions.forEach(suggestion => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'suggestion-btn checking';
            btn.textContent = suggestion + '.cobalah.id';
            btn.dataset.subdomain = suggestion;
            
            btn.addEventListener('click', function() {
                if (!this.classList.contains('unavailable')) {
                    document.getElementById('subdomain').value = this.dataset.subdomain;
                    checkSubdomainAvailability(this.dataset.subdomain);
                }
            });
            
            suggestionsButtonsDiv.appendChild(btn);
            
            // Check availability for this suggestion
            checkSuggestionAvailability(suggestion, btn);
        });
        
        suggestionsDiv.style.display = 'block';
    } else {
        suggestionsDiv.style.display = 'none';
    }
}

function createSubdomainSuggestions(businessName, address) {
    const suggestions = [];
    
    // Clean and process business name
    const cleanBusinessName = businessName
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, '') // Remove special characters
        .replace(/\s+/g, '-') // Replace spaces with hyphens
        .replace(/^-+|-+$/g, ''); // Remove leading/trailing hyphens
    
    // Clean and process address (extract street name - minimum 2 words)
    let streetName = '';
    if (address) {
        // Extract street name (remove jl./jalan, numbers, etc.)
        let cleanAddress = address
            .toLowerCase()
            .replace(/^(jl\.|jalan|jl)\s*/i, '') // Remove jl./jalan prefix
            .replace(/\s*(no\.|nomor)\s*\d+.*$/i, '') // Remove number and after
            .replace(/[^a-z0-9\s]/g, '') // Remove special characters
            .trim();
        
        // Split into words and take minimum 2 words
        let addressWords = cleanAddress.split(/\s+/).filter(word => word.length > 0);
        
        if (addressWords.length >= 2) {
            // Take minimum 2 words, maximum 3 words
            streetName = addressWords.slice(0, Math.min(3, addressWords.length)).join('-');
        } else if (addressWords.length === 1 && addressWords[0].length >= 3) {
            // If only 1 word but long enough, use it
            streetName = addressWords[0];
        }
        
        // Limit street name length
        if (streetName.length > 20) {
            streetName = streetName.substring(0, 20);
        }
    }
    
    // Generate suggestions based on different patterns
    if (cleanBusinessName) {
        // Pattern 1: Business name only
        if (cleanBusinessName.length >= 3) {
            suggestions.push(cleanBusinessName);
        }
        
        // Pattern 2: Business name + street
        if (streetName && streetName.length >= 3) {
            const combined = cleanBusinessName + '-' + streetName;
            if (combined.length <= 50) {
                suggestions.push(combined);
            }
            
            // Pattern 3: Abbreviated version if too long
            if (combined.length > 30) {
                const abbreviated = cleanBusinessName.substring(0, 20) + '-' + streetName.substring(0, 10);
                suggestions.push(abbreviated);
            }
        }
        
        // Pattern 4: Add numbers if name is very short
        if (cleanBusinessName.length < 10) {
            suggestions.push(cleanBusinessName + '-1');
            if (streetName) {
                suggestions.push(cleanBusinessName + '-' + streetName + '-1');
            }
        }
    }
    
    // Remove duplicates and ensure valid format
    return [...new Set(suggestions)]
        .filter(s => s && s.length >= 3 && s.length <= 50 && /^[a-z0-9-]+$/.test(s))
        .slice(0, 4); // Limit to 4 suggestions
}

function checkSuggestionAvailability(subdomain, buttonElement) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', msme_ajax.ajax_url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    if (response.data.available) {
                        buttonElement.className = 'suggestion-btn available';
                        buttonElement.title = 'Tersedia - klik untuk pilih';
                    } else {
                        buttonElement.className = 'suggestion-btn unavailable';
                        buttonElement.title = 'Sudah digunakan';
                    }
                } else {
                    buttonElement.className = 'suggestion-btn';
                    buttonElement.title = 'Error checking availability';
                }
            } catch (e) {
                buttonElement.className = 'suggestion-btn';
                buttonElement.title = 'Error checking availability';
            }
        }
    };
    
    xhr.send('action=check_subdomain_availability&subdomain=' + encodeURIComponent(subdomain) + '&nonce=' + msme_ajax.nonce);
}

function initFormValidation() {
    const form = document.getElementById('business-registration-form');
    const submitBtn = document.getElementById('submit-registration');
    const termsCheckbox = document.getElementById('terms_agree');
    
    if (form && submitBtn && termsCheckbox) {
        // Enable/disable submit button based on terms checkbox
        termsCheckbox.addEventListener('change', function() {
            submitBtn.disabled = !this.checked;
        });
        
        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitRegistrationForm();
        });
    }
    // OTP verification form
    const otpForm = document.getElementById('email-verification-form');
    if (otpForm) {
        otpForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitOTPVerification();
        });
    }
}

function initStepNavigation() {
    // Step navigation will be implemented when we add Google OAuth integration
    console.log('Step navigation initialized');
}

function submitRegistrationForm() {
    const submitBtn = document.getElementById('submit-registration');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');
    
    // Show loading state
    btnText.style.display = 'none';
    btnLoading.style.display = 'inline';
    submitBtn.disabled = true;
    
    // Get form data
    const formData = new FormData(document.getElementById('business-registration-form'));
    formData.append('action', 'submit_business_registration');
    formData.append('nonce', msme_ajax.nonce);
    
    // AJAX submission
    const xhr = new XMLHttpRequest();
    xhr.open('POST', msme_ajax.ajax_url, true);
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            // Reset button state
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
            submitBtn.disabled = false;
            
            try {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    // Move to step 3 (email verification)
                    showStep(3);
                    console.log('Registration successful:', response.data);
                } else {
                    alert('Error: ' + response.data.message);
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Server response:', xhr.responseText);
                alert('Server response error. Check console for details.');
            }
        }
    };
    
    xhr.onerror = function() {
        // Reset button state
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
        submitBtn.disabled = false;
        
        alert('Network error occurred');
    };
    
    xhr.send(formData);
}

function showStep(stepNumber) {
    // Hide all steps
    document.querySelectorAll('.form-step').forEach(step => {
        step.style.display = 'none';
    });
    
    // Show target step
    const targetStep = document.getElementById('form-step-' + stepNumber);
    if (targetStep) {
        targetStep.style.display = 'block';
    }
    
    // Update step indicators
    document.querySelectorAll('.step').forEach((step, index) => {
        step.classList.remove('active', 'completed');
        if (index + 1 < stepNumber) {
            step.classList.add('completed');
        } else if (index + 1 === stepNumber) {
            step.classList.add('active');
        }
    });
    
    // Update URL without reload
    if (stepNumber > 1) {
        window.history.pushState({}, '', '/daftar-bisnis?step=' + stepNumber);
    }
}

// Continue to business form after Google login
function continueToBusinessForm() {
    // Pre-fill user data and show step 2
    const currentUser = msme_ajax.current_user;
    if (currentUser) {
        document.getElementById('owner_name').value = currentUser.display_name;
        document.getElementById('owner_email').value = currentUser.email;
    }
    
    showStep(2);
}

// Enhanced SMTP Test with verbose logging
function testSMTPEmail() {
    console.log('Step 1: SMTP Test button clicked');
    
    const resultDiv = document.getElementById('smtp-test-result');
    resultDiv.innerHTML = '<div style="background: #f0f0f0; padding: 10px; border-radius: 5px;"><strong>Testing SMTP Configuration...</strong><br><div id="smtp-log"></div></div>';
    
    const logDiv = document.getElementById('smtp-log');
    
    function addLog(message) {
        console.log('SMTP Test: ' + message);
        logDiv.innerHTML += '<div style="margin: 5px 0; font-size: 12px;">' + message + '</div>';
    }
    
    addLog('Step 2: Preparing AJAX request...');
    
    const xhr = new XMLHttpRequest();
    xhr.open('POST', ajaxurl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        addLog('Step 3: XMLHttpRequest state: ' + xhr.readyState);
        
        if (xhr.readyState === 4) {
            addLog('Step 4: Request completed with status: ' + xhr.status);
            
            if (xhr.status === 200) {
                addLog('Step 5: Server response received');
                console.log('Server response:', xhr.responseText);
                
                if (xhr.responseText.trim()) {
                    resultDiv.innerHTML = xhr.responseText;
                } else {
                    resultDiv.innerHTML = '<div style="color: red;">❌ Empty response from server</div>';
                }
            } else {
                addLog('❌ HTTP Error: ' + xhr.status);
                resultDiv.innerHTML = '<div style="color: red;">❌ HTTP Error: ' + xhr.status + '</div>';
            }
        }
    };
    
    xhr.onerror = function() {
        addLog('❌ Network error occurred');
        resultDiv.innerHTML = '<div style="color: red;">❌ Network error occurred</div>';
    };
    
    addLog('Step 6: Sending AJAX request...');
    xhr.send('action=test_smtp_email');
    addLog('Step 7: AJAX request sent successfully');
}

function checkSubdomainAvailability(subdomain) {
    const checkDiv = document.getElementById('subdomain-check');
    
    if (subdomain.length < 3) {
        checkDiv.textContent = 'Minimal 3 karakter';
        checkDiv.className = 'subdomain-check';
        return;
    }
    
    // Validate format
    if (!/^[a-z0-9-]+$/.test(subdomain)) {
        checkDiv.textContent = 'Hanya huruf kecil, angka, dan tanda hubung (-)';
        checkDiv.className = 'subdomain-check unavailable';
        return;
    }
    
    checkDiv.textContent = 'Memeriksa ketersediaan...';
    checkDiv.className = 'subdomain-check checking';
    
    const xhr = new XMLHttpRequest();
    xhr.open('POST', msme_ajax.ajax_url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    if (response.data.available) {
                        checkDiv.textContent = '✓ Tersedia';
                        checkDiv.className = 'subdomain-check available';
                    } else {
                        checkDiv.textContent = '✗ Sudah digunakan';
                        checkDiv.className = 'subdomain-check unavailable';
                    }
                } else {
                    checkDiv.textContent = 'Error: ' + response.data.message;
                    checkDiv.className = 'subdomain-check unavailable';
                }
            } catch (e) {
                checkDiv.textContent = 'Error checking availability';
                checkDiv.className = 'subdomain-check unavailable';
            }
        }
    };
    
    xhr.send('action=check_subdomain_availability&subdomain=' + encodeURIComponent(subdomain) + '&nonce=' + msme_ajax.nonce);
}

function submitOTPVerification() {
    const otpInput = document.getElementById('otp_code');
    const submitBtn = document.querySelector('#email-verification-form .btn-submit');
    const otpCode = otpInput.value.trim();
    
    // Validate OTP format
    if (!/^\d{6}$/.test(otpCode)) {
        alert('Kode OTP harus berupa 6 angka');
        otpInput.focus();
        return;
    }
    
    // Get email from previous form or current user
    let email = '';
    const emailField = document.getElementById('owner_email');
    if (emailField) {
        email = emailField.value;
    } else if (typeof msme_ajax !== 'undefined' && msme_ajax.current_user) {
        email = msme_ajax.current_user.email;
    }
    
    if (!email) {
        alert('Error: Email tidak ditemukan. Silakan mulai ulang pendaftaran.');
        return;
    }
    
    // Show loading state
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Memverifikasi...';
    submitBtn.disabled = true;
    otpInput.disabled = true;
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', 'verify_otp_code');
    formData.append('otp_code', otpCode);
    formData.append('email', email);
    formData.append('nonce', msme_ajax.nonce);
    
    // AJAX submission
    const xhr = new XMLHttpRequest();
    xhr.open('POST', msme_ajax.ajax_url, true);
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            // Reset button state
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
            otpInput.disabled = false;
            
            try {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    // Show success message
                    showVerificationSuccess(response.data);
                } else {
                    // Show error message
                    showVerificationError(response.data);
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                alert('Server response error. Check console for details.');
            }
        }
    };
    
    xhr.onerror = function() {
        // Reset button state
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        otpInput.disabled = false;
        
        alert('Network error occurred');
    };
    
    xhr.send(formData);
}

function showVerificationSuccess(data) {
    // Replace Step 3 content with success message
    const step3 = document.getElementById('form-step-3');
    step3.innerHTML = `
        <div style="text-align: center; padding: 40px 20px;">
            <div style="background: #d4edda; border: 2px solid #28a745; border-radius: 15px; padding: 30px; margin: 20px 0;">
                <h2 style="color: #155724; margin: 0 0 20px 0;">[CHECK] Verifikasi Berhasil!</h2>
                <p style="font-size: 18px; color: #155724; margin: 0 0 15px 0;">
                    <strong>Verifikasi email berhasil! Pendaftaran Anda akan diproses oleh admin dalam 24 jam.</strong>
                </p>
                <div style="background: white; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #0073aa; margin: 0 0 15px 0;">Bisnis Anda:</h3>
                    <p><strong>Nama:</strong> ${data.business_name || 'N/A'}</p>
                    <p><strong>Website:</strong> ${data.subdomain || 'N/A'}.cobalah.id</p>
                </div>
                <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h4 style="color: #856404; margin: 0 0 15px 0;">[LIST] Langkah Selanjutnya:</h4>
                    <ul style="text-align: left; color: #856404;">
                        <li>Tim admin akan mengulas pendaftaran Anda</li>
                        <li>Anda akan menerima email konfirmasi dalam 24 jam</li>
                        <li>Setelah disetujui, website bisnis Anda akan aktif</li>
                        <li>Anda dapat mulai mengelola konten bisnis Anda</li>
                    </ul>
                </div>
                <div style="margin-top: 30px;">
                    <p style="color: #666; font-size: 14px;">
                        Terima kasih telah bergabung dengan Cobalah.id<br>
                        <strong>Website Gratis untuk UMKM Indonesia</strong>
                    </p>
                </div>
            </div>
        </div>
    `;
}

function showVerificationError(data) {
    const errorDiv = document.createElement('div');
    errorDiv.style.cssText = 'background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; padding: 15px; margin: 15px 0; color: #721c24;';
    errorDiv.innerHTML = `<strong>❌ Error:</strong> ${data.message}`;
    
    // Insert error message before the form
    const form = document.getElementById('email-verification-form');
    form.parentNode.insertBefore(errorDiv, form);
    
    // Remove error after 5 seconds
    setTimeout(() => {
        errorDiv.remove();
    }, 5000);
    
    // Focus back to OTP input
    document.getElementById('otp_code').focus();
}

// Handle OTP resend functionality
function resendOTPCode() {
    const email = document.getElementById('verification-email').value;
    const resendBtn = document.querySelector('.resend-otp-btn');
    
    if (!email) {
        showMessage('Email tidak ditemukan. Silakan daftar ulang.', 'error');
        return;
    }
    
    // Show loading state
    const originalText = resendBtn.textContent;
    resendBtn.textContent = 'Mengirim...';
    resendBtn.disabled = true;
    
    // Send AJAX request
    fetch(msme_ajax.ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'resend_otp_code',
            email: email,
            nonce: msme_ajax.nonce
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            showMessage(data.data.message, 'success');
            
            // Disable button for 60 seconds with countdown
            startResendCountdown(resendBtn);
            
        } else {
            // Show error message
            showMessage(data.data.message, 'error');
            
            // If rate limited, start countdown
            if (data.data.wait_time) {
                startResendCountdown(resendBtn, data.data.wait_time);
            } else {
                // Reset button
                resendBtn.textContent = originalText;
                resendBtn.disabled = false;
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Terjadi kesalahan sistem. Silakan coba lagi.', 'error');
        
        // Reset button
        resendBtn.textContent = originalText;
        resendBtn.disabled = false;
    });
}

// Countdown timer for resend button
function startResendCountdown(button, initialSeconds = 60) {
    let seconds = initialSeconds;
    
    const updateButton = () => {
        if (seconds > 0) {
            button.textContent = `Tunggu ${seconds}s`;
            button.disabled = true;
            seconds--;
            setTimeout(updateButton, 1000);
        } else {
            button.textContent = 'Kirim Ulang Kode Verifikasi';
            button.disabled = false;
        }
    };
    
    updateButton();
}

// Add event listener for resend button
document.addEventListener('DOMContentLoaded', function() {
    const resendBtn = document.querySelector('.resend-otp-btn');
    if (resendBtn) {
        resendBtn.addEventListener('click', function(e) {
            e.preventDefault();
            resendOTPCode();
        });
    }
});

// ====== RESEND OTP FUNCTIONALITY ======

// Handle OTP resend functionality
function resendOTPCode() {
    const email = document.getElementById('verification-email')?.value || 
                  document.getElementById('owner_email')?.value;
    const resendBtn = document.querySelector('.resend-otp-btn');
    
    if (!email) {
        alert('Email tidak ditemukan. Silakan daftar ulang.');
        return;
    }
    
    // Show loading state
    const originalText = resendBtn.textContent;
    resendBtn.textContent = 'Mengirim...';
    resendBtn.disabled = true;
    resendBtn.classList.add('loading');
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', 'resend_otp_code');
    formData.append('email', email);
    formData.append('nonce', msme_ajax.nonce);
    
    // Send AJAX request
    const xhr = new XMLHttpRequest();
    xhr.open('POST', msme_ajax.ajax_url, true);
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    // Show success message
                    alert(response.data.message);
                    
                    // Start countdown (60 seconds)
                    startResendCountdown(resendBtn);
                    
                } else {
                    // Show error message
                    alert(response.data.message);
                    
                    // If rate limited, start countdown
                    if (response.data.wait_time) {
                        startResendCountdown(resendBtn, response.data.wait_time);
                    } else {
                        // Reset button
                        resendBtn.textContent = originalText;
                        resendBtn.disabled = false;
                        resendBtn.classList.remove('loading');
                    }
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                alert('Terjadi kesalahan sistem. Silakan coba lagi.');
                
                // Reset button
                resendBtn.textContent = originalText;
                resendBtn.disabled = false;
                resendBtn.classList.remove('loading');
            }
        }
    };
    
    xhr.onerror = function() {
        alert('Network error occurred');
        
        // Reset button
        resendBtn.textContent = originalText;
        resendBtn.disabled = false;
        resendBtn.classList.remove('loading');
    };
    
    xhr.send(formData);
}

// Countdown timer for resend button
function startResendCountdown(button, initialSeconds = 60) {
    let seconds = initialSeconds;
    button.classList.remove('loading');
    
    const updateButton = () => {
        if (seconds > 0) {
            button.textContent = `Tunggu ${seconds}s`;
            button.disabled = true;
            button.classList.add('resend-countdown');
            seconds--;
            setTimeout(updateButton, 1000);
        } else {
            button.textContent = 'Kirim Ulang Kode Verifikasi';
            button.disabled = false;
            button.classList.remove('resend-countdown');
        }
    };
    
    updateButton();
}

// Add event listener when page loads
document.addEventListener('DOMContentLoaded', function() {
    const resendBtn = document.querySelector('.resend-otp-btn');
    if (resendBtn) {
        resendBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Resend button clicked!'); // Debug log
            resendOTPCode();
        });
    }
});

// Admin Registration Management Functions
function approveRegistration(id) {
    if (confirm('Setujui pendaftaran ini?')) {
        console.log('Approve registration:', id);
        // TODO: Implement approval AJAX call
    }
}

function rejectRegistration(id) {
    if (confirm('Tolak pendaftaran ini?')) {
        console.log('Reject registration:', id);
        // TODO: Implement rejection AJAX call
    }
}

