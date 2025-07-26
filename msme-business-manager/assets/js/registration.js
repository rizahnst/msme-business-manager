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
                <h2 style="color: #155724; margin: 0 0 20px 0;">✅  Verifikasi Berhasil!</h2>
                <p style="font-size: 18px; color: #155724; margin: 0 0 15px 0;">
                    <strong>Verifikasi email berhasil! Pendaftaran Anda akan diproses oleh admin dalam 24 jam.</strong>
                </p>
                <div style="background: white; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #0073aa; margin: 0 0 15px 0;">Bisnis Anda:</h3>
                    <p><strong>Nama:</strong> ${data.business_name || 'N/A'}</p>
                    <p><strong>Website:</strong> ${data.subdomain || 'N/A'}.cobalah.id</p>
                </div>
                <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h4 style="color: #856404; margin: 0 0 15px 0;">▶️ Langkah Selanjutnya:</h4>
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
    if (confirm('Setujui pendaftaran ini? Bisnis akan mendapatkan akses ke website mereka.')) {
        console.log('Approving registration:', id);

        // Enhanced loading state for the clicked button
        const button = event.target;
        addLoadingState(button, 'Memproses...');
        
        // Disable all other action buttons in the same row
        const row = button.closest('tr');
        const otherButtons = row.querySelectorAll('.button');
        otherButtons.forEach(btn => {
            if (btn !== button) {
                btn.disabled = true;
                btn.style.opacity = '0.5';
            }
        });
        
        // Show loading state
        showNotification('Memproses persetujuan...', 'info');
        
        // Check if required variables exist
        if (typeof ajaxurl === 'undefined') {
            showNotification('Error: ajaxurl tidak tersedia', 'error');
            return;
        }
        
        if (typeof msme_ajax_nonce === 'undefined') {
            showNotification('Error: Security nonce tidak tersedia', 'error');
            return;
        }
        
        // Create AJAX request
        const xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxurl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        console.log('Approval response:', response);
                        
                        if (response.success) {
                            // Success - update UI
                            showNotification('Pendaftaran berhasil disetujui!', 'success');
                            
                            // Update the row in the table
                            updateRegistrationRow(id, 'approved');
                            
                            // Update statistics if present
                            updateDashboardStats();

                            // Reset button states
                            removeLoadingState(button);
                            const row = button.closest('tr');
                            const otherButtons = row.querySelectorAll('.button');
                            otherButtons.forEach(btn => {
                                btn.disabled = false;
                                btn.style.opacity = '1';
                            });
                            
                        } else {
                            // Enhanced error handling for retry system
                            if (response.data && response.data.type) {
                                if (response.data.type === 'banned') {
                                    showBanMessage(response.data);
                                } else if (response.data.type === 'cooldown') {
                                    showCooldownMessage(response.data);
                                } else {
                                    showNotification('Error: ' + response.data.message, 'error');
                                }
                            } else {
                                showNotification('Gagal: ' + (response.data.message || 'Unknown error'), 'error');
                            }

                            // Reset button states on error
                            removeLoadingState(button);
                            const row = button.closest('tr');
                            const otherButtons = row.querySelectorAll('.button');
                            otherButtons.forEach(btn => {
                                btn.disabled = false;
                                btn.style.opacity = '1';
                            });
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        showNotification('Respons server tidak valid', 'error');
                    }
                } else {
                    console.error('HTTP Error:', xhr.status);
                    showNotification('Kesalahan koneksi server (HTTP ' + xhr.status + ')', 'error');
                }
            }
        };
        
        xhr.onerror = function() {
            console.error('Network error occurred');
            showNotification('Kesalahan jaringan', 'error');
        };
        
        // Send request
        const formData = 'action=msme_approve_registration&registration_id=' + id + '&nonce=' + msme_ajax_nonce;
        xhr.send(formData);
    }
}

function rejectRegistration(id) {
    const reason = prompt('Alasan penolakan (opsional):');
    if (reason !== null) { // User didn't cancel
        console.log('Rejecting registration:', id, 'Reason:', reason);

        // Enhanced loading state for the clicked button
        const button = event.target;
        addLoadingState(button, 'Memproses...');
        
        // Disable all other action buttons in the same row
        const row = button.closest('tr');
        const otherButtons = row.querySelectorAll('.button');
        otherButtons.forEach(btn => {
            if (btn !== button) {
                btn.disabled = true;
                btn.style.opacity = '0.5';
            }
        });
        
        // Show loading state
        showNotification('Memproses penolakan...', 'info');
        
        // Check if required variables exist
        if (typeof ajaxurl === 'undefined') {
            showNotification('Error: ajaxurl tidak tersedia', 'error');
            return;
        }
        
        if (typeof msme_ajax_nonce === 'undefined') {
            showNotification('Error: Security nonce tidak tersedia', 'error');
            return;
        }
        
        // Create AJAX request
        const xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxurl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        console.log('Rejection response:', response);
                        
                        if (response.success) {
                            // Success - update UI
                            showNotification('Pendaftaran berhasil ditolak!', 'success');
                            
                            // Update the row in the table
                            updateRegistrationRow(id, 'rejected');
                            
                            // Update statistics if present
                            updateDashboardStats();

                            // Reset button states
                            removeLoadingState(button);
                            const row = button.closest('tr');
                            const otherButtons = row.querySelectorAll('.button');
                            otherButtons.forEach(btn => {
                                btn.disabled = false;
                                btn.style.opacity = '1';
                            });
                            
                        } else {
                            // Error response
                            showNotification('Gagal menolak pendaftaran: ' + (response.data || 'Unknown error'), 'error');
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        showNotification('Respons server tidak valid', 'error');
                    }
                } else {
                    console.error('HTTP Error:', xhr.status);
                    showNotification('Kesalahan koneksi server (HTTP ' + xhr.status + ')', 'error');
                }

                // Reset button states on error
                removeLoadingState(button);
                const row = button.closest('tr');
                const otherButtons = row.querySelectorAll('.button');
                otherButtons.forEach(btn => {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                });
            }
        };
        
        xhr.onerror = function() {
            console.error('Network error occurred');
            showNotification('Kesalahan jaringan', 'error');
        };
        
        // Send request
        const formData = 'action=msme_reject_registration&registration_id=' + id + '&reason=' + encodeURIComponent(reason) + '&nonce=' + msme_ajax_nonce;
        xhr.send(formData);
    }
}

// Utility Functions
function showNotification(message, type = 'info') {
    // Remove any existing notifications
    const existingNotices = document.querySelectorAll('.msme-admin-notice');
    existingNotices.forEach(notice => notice.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notice notice-${type} is-dismissible msme-admin-notice`;
    notification.innerHTML = `
        <p><strong>${message}</strong></p>
        <button type="button" class="notice-dismiss" onclick="this.parentElement.remove();">
            <span class="screen-reader-text">Dismiss this notice.</span>
        </button>
    `;
    
    // Insert at top of content
    const contentWrap = document.querySelector('.wrap') || document.querySelector('body');
    if (contentWrap) {
        contentWrap.insertBefore(notification, contentWrap.firstChild);
        
        // Auto-dismiss after 5 seconds for non-error messages
        if (type !== 'error') {
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
    }
}

function updateRegistrationRow(id, newStatus) {
    // Find the row by registration ID
    const row = document.querySelector(`tr[data-registration-id="${id}"]`);
    if (!row) {
        console.log('Could not find row for registration ID:', id);
        return;
    }
    
    // Find the status cell (5th column - index 4)
    const cells = row.querySelectorAll('td');
    if (cells.length >= 6) {
        const statusCell = cells[4]; // Status column
        const actionCell = cells[6]; // Action column
        
        // Update status cell
        if (newStatus === 'approved') {
            statusCell.innerHTML = '<span style="color: #46b450; font-weight: bold;">Disetujui</span>';
            actionCell.innerHTML = '<span style="color: #46b450;">Disetujui</span>';
        } else if (newStatus === 'rejected') {
            statusCell.innerHTML = '<span style="color: #dc3232; font-weight: bold;">Ditolak</span>';
            actionCell.innerHTML = '<span style="color: #dc3232;">Ditolak</span>';
        }
        
        // Add a brief highlight effect
        row.style.backgroundColor = '#f0f8ff';
        setTimeout(() => {
            row.style.backgroundColor = '';
        }, 2000);
    }
}

function updateDashboardStats() {
    // Check if required variables exist
    if (typeof ajaxurl === 'undefined' || typeof msme_ajax_nonce === 'undefined') {
        console.log('Cannot update stats: missing required variables');
        return;
    }
    
    // Reload stats section via AJAX
    const xhr = new XMLHttpRequest();
    xhr.open('POST', ajaxurl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success && response.data) {
                    // Update stats display
                    const stats = response.data;
                    const totalEl = document.querySelector('#stat-total');
                    const pendingEl = document.querySelector('#stat-pending');
                    const approvedEl = document.querySelector('#stat-approved');
                    const rejectedEl = document.querySelector('#stat-rejected');
                    
                    if (totalEl) totalEl.textContent = stats.total;
                    if (pendingEl) pendingEl.textContent = stats.pending;
                    if (approvedEl) approvedEl.textContent = stats.approved;
                    if (rejectedEl) rejectedEl.textContent = stats.rejected;
                }
            } catch (e) {
                console.log('Failed to update stats:', e);
            }
        }
    };
    
    const formData = 'action=msme_get_registration_stats&nonce=' + msme_ajax_nonce;
    xhr.send(formData);
}

// Debug function to check if required variables exist
function checkAjaxVariables() {
    console.log('=== AJAX Variables Check ===');
    console.log('ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'NOT DEFINED');
    console.log('msme_ajax_nonce:', typeof msme_ajax_nonce !== 'undefined' ? msme_ajax_nonce : 'NOT DEFINED');
    console.log('==========================');
}

// Call debug function when page loads
document.addEventListener('DOMContentLoaded', function() {
    checkAjaxVariables();
});

function showBanMessage(data) {
    const container = document.querySelector('.msme-registration-container') || document.body;
    
    const banMessage = document.createElement('div');
    banMessage.style.cssText = 'background: #ffebee; border: 2px solid #f44336; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;';
    banMessage.innerHTML = `
        <h3 style="color: #d32f2f; margin: 0 0 15px 0;">🚫 Akun Dibatasi Sementara</h3>
        <p style="margin: 10px 0;">${data.message}</p>
        <p style="color: #666; font-size: 14px; margin: 10px 0;">
            ℹ️ Periode pembatasan ini memberikan waktu untuk meninjau persyaratan pendaftaran
        </p>
    `;
    
    container.insertBefore(banMessage, container.firstChild);
}

function showCooldownMessage(data) {
    const container = document.querySelector('.msme-registration-container') || document.body;
    
    const cooldownMessage = document.createElement('div');
    cooldownMessage.style.cssText = 'background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;';
    cooldownMessage.innerHTML = `
        <h3 style="color: #856404; margin: 0 0 15px 0;">⏰ Periode Tunggu Aktif</h3>
        <p style="margin: 10px 0;">${data.message}</p>
        <div id="countdown-timer" style="font-size: 24px; font-weight: bold; color: #856404; margin: 15px 0;">
            ${Math.ceil(data.remaining_seconds / 60)}:${String(data.remaining_seconds % 60).padStart(2, '0')}
        </div>
        <p style="color: #666; font-size: 14px;">
            ℹ️ Percobaan ${data.retry_count} dari 3 | Gunakan waktu ini untuk memperbaiki informasi bisnis
        </p>
    `;
    
    container.insertBefore(cooldownMessage, container.firstChild);
    
    // Start countdown timer
    startCountdownTimer(data.remaining_seconds);
}

function startCountdownTimer(seconds) {
    const timerElement = document.getElementById('countdown-timer');
    if (!timerElement) return;
    
    const updateTimer = () => {
        if (seconds <= 0) {
            timerElement.textContent = '[✓] Waktu tunggu selesai - silakan refresh halaman';
            return;
        }
        
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        timerElement.textContent = `${minutes}:${String(remainingSeconds).padStart(2, '0')}`;
        
        seconds--;
        setTimeout(updateTimer, 1000);
    };
    
    updateTimer();
}

function viewRegistrationDetails(id) {
    console.log('Viewing details for registration:', id);
    
    // Check if required variables exist
    if (typeof ajaxurl === 'undefined' || typeof msme_ajax_nonce === 'undefined') {
        showNotification('Error: AJAX variables tidak tersedia', 'error');
        return;
    }
    
    // Show loading
    showNotification('Memuat detail...', 'info');
    
    // Create AJAX request to get registration details
    const xhr = new XMLHttpRequest();
    xhr.open('POST', ajaxurl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    console.log('Details response:', response);
                    
                    if (response.success) {
                        showRegistrationDetailsModal(response.data);
                    } else {
                        showNotification('❌ Gagal memuat detail: ' + (response.data || 'Unknown error'), 'error');
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    showNotification('❌ Respons server tidak valid', 'error');
                }
            } else {
                console.error('HTTP Error:', xhr.status);
                showNotification('❌ Kesalahan koneksi server (HTTP ' + xhr.status + ')', 'error');
            }
        }
    };
    
    xhr.onerror = function() {
        console.error('Network error occurred');
        showNotification('❌ Kesalahan jaringan', 'error');
    };
    
    // Send request
    const formData = 'action=msme_get_registration_details&registration_id=' + id + '&nonce=' + msme_ajax_nonce;
    xhr.send(formData);
}

function showRegistrationDetailsModal(data) {
    // Remove existing modal if any
    const existingModal = document.getElementById('registration-details-modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create modal
    const modal = document.createElement('div');
    modal.id = 'registration-details-modal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    modal.innerHTML = `
        <div style="background: white; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80%; overflow-y: auto; padding: 30px; position: relative;">
            <button onclick="this.closest('#registration-details-modal').remove()" style="position: absolute; top: 15px; right: 15px; background: #dc3232; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer;">&times;</button>
            
            <h2 style="margin: 0 0 20px 0; color: #0073aa;">Detail Pendaftaran</h2>
            
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="background: #f8f9fa;">
                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">ID Registrasi</td>
                    <td style="padding: 10px; border: 1px solid #ddd;">${data.id}</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Nama Bisnis</td>
                    <td style="padding: 10px; border: 1px solid #ddd;">${data.business_name}</td>
                </tr>
                <tr style="background: #f8f9fa;">
                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Email</td>
                    <td style="padding: 10px; border: 1px solid #ddd;">${data.email}</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Kategori</td>
                    <td style="padding: 10px; border: 1px solid #ddd;">${data.business_category}</td>
                </tr>
                <tr style="background: #f8f9fa;">
                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Subdomain</td>
                    <td style="padding: 10px; border: 1px solid #ddd;">${data.subdomain}.cobalah.id</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Telefon</td>
                    <td style="padding: 10px; border: 1px solid #ddd;">${data.phone_number || 'Tidak disediakan'}</td>
                </tr>
                <tr style="background: #f8f9fa;">
                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Alamat</td>
                    <td style="padding: 10px; border: 1px solid #ddd;">${data.business_address || 'Tidak disediakan'}</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Status</td>
                    <td style="padding: 10px; border: 1px solid #ddd;">
                        <span style="color: ${data.status === 'approved' ? '#46b450' : data.status === 'rejected' ? '#dc3232' : '#856404'}; font-weight: bold;">
                            ${data.status === 'approved' ? 'Disetujui' : data.status === 'rejected' ? '✗ Ditolak' : data.status === 'verified' ? 'Terverifikasi' : 'Menunggu'}
                        </span>
                    </td>
                </tr>
                <tr style="background: #f8f9fa;">
                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Tanggal Daftar</td>
                    <td style="padding: 10px; border: 1px solid #ddd;">${data.created_date}</td>
                </tr>
                ${data.approved_date ? `
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Tanggal Diproses</td>
                    <td style="padding: 10px; border: 1px solid #ddd;">${data.approved_date}</td>
                </tr>
                ` : ''}
                ${data.admin_notes ? `
                <tr style="background: #f8f9fa;">
                    <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Catatan Admin</td>
                    <td style="padding: 10px; border: 1px solid #ddd;">${data.admin_notes}</td>
                </tr>
                ` : ''}
            </table>
            
            <div style="text-align: center; margin-top: 20px;">
                <button onclick="this.closest('#registration-details-modal').remove()" style="background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                    Tutup
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// =====================================
// CSV Export Functions for Admin
// =====================================

function showExportModal() {
    document.getElementById('export-modal').style.display = 'block';
    document.getElementById('export-overlay').style.display = 'block';
    
    // Set default dates (last 30 days)
    const today = new Date();
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);
    
    document.getElementById('export-date-to').value = today.toISOString().split('T')[0];
    document.getElementById('export-date-from').value = thirtyDaysAgo.toISOString().split('T')[0];
}

function hideExportModal() {
    document.getElementById('export-modal').style.display = 'none';
    document.getElementById('export-overlay').style.display = 'none';
}

function executeCSVExport() {
    const status = document.getElementById('export-status').value;
    const dateFrom = document.getElementById('export-date-from').value;
    const dateTo = document.getElementById('export-date-to').value;
    
    // Show loading
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = 'Mengunduh...';
    button.disabled = true;
    
    // Check if required variables exist
    if (typeof ajaxurl === 'undefined') {
        showNotification('Error: ajaxurl tidak tersedia', 'error');
        button.innerHTML = originalText;
        button.disabled = false;
        return;
    }
    
    if (typeof msme_ajax_nonce === 'undefined') {
        showNotification('Error: Security nonce tidak tersedia', 'error');
        button.innerHTML = originalText;
        button.disabled = false;
        return;
    }
    
    // Create download link
    const params = new URLSearchParams({
        action: 'msme_export_csv',
        nonce: msme_ajax_nonce,
        status: status,
        date_from: dateFrom,
        date_to: dateTo
    });
    
    const downloadUrl = ajaxurl + '?' + params.toString();
    
    // Create temporary link and trigger download
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = 'registrasi-bisnis-' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Show success message
    showNotification('CSV berhasil diunduh!', 'success');
    
    // Reset button after 2 seconds
    setTimeout(() => {
        button.innerHTML = originalText;
        button.disabled = false;
        hideExportModal();
    }, 2000);
}

// ====== ENHANCED UI INTERACTIONS ======

// Add loading states to buttons
function addLoadingState(button, text = 'Memproses...') {
    if (button) {
        button.classList.add('msme-loading');
        button.setAttribute('data-original-text', button.textContent);
        button.textContent = text;
        button.disabled = true;
    }
}

function removeLoadingState(button) {
    if (button) {
        button.classList.remove('msme-loading');
        const originalText = button.getAttribute('data-original-text');
        if (originalText) {
            button.textContent = originalText;
        }
        button.disabled = false;
    }
}

// Enhanced table interactions
document.addEventListener('DOMContentLoaded', function() {
    // Add data labels for mobile table view
    const table = document.querySelector('.wp-list-table');
    if (table) {
        const headers = table.querySelectorAll('th');
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            cells.forEach((cell, index) => {
                if (headers[index]) {
                    cell.setAttribute('data-label', headers[index].textContent.trim());
                }
            });
        });
    }
    
    // Enhanced button feedback
    document.querySelectorAll('.button').forEach(button => {
        button.addEventListener('click', function(e) {
            // Create ripple effect
            const rect = this.getBoundingClientRect();
            const ripple = document.createElement('span');
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(255,255,255,0.3);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s ease-out;
            `;
            
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });
    });
    
    // Add print functionality
    const printCSS = `
        <style id="print-styles">
            @media print {
                body * { visibility: hidden; }
                .wrap, .wrap * { visibility: visible; }
                .wrap { position: absolute; left: 0; top: 0; width: 100%; }
            }
        </style>
    `;
    
});

// Real-time statistics updates
function updateDashboardStats() {
    // Add subtle animation to updated stats
    const statsElement = document.querySelector('.msme-admin-stats');
    if (statsElement) {
        statsElement.style.transform = 'scale(1.02)';
        setTimeout(() => {
            statsElement.style.transform = 'scale(1)';
        }, 200);
    }
}

// Enhanced notification system with better animations
function showNotification(message, type = 'info') {
    // Remove existing notifications
    document.querySelectorAll('.msme-admin-notice').forEach(notice => notice.remove());
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `notice notice-${type} is-dismissible msme-admin-notice`;
    notification.style.cssText = `
        animation: msme-notification-slide-in 0.3s ease;
        margin-bottom: 20px;
    `;
    
    notification.innerHTML = `
        <p><strong>${message}</strong></p>
        <button type="button" class="notice-dismiss" onclick="this.parentElement.remove();">
            <span class="screen-reader-text">Dismiss this notice.</span>
        </button>
    `;
    
    // Insert notification
    const contentWrap = document.querySelector('.wrap') || document.querySelector('body');
    if (contentWrap) {
        contentWrap.insertBefore(notification, contentWrap.firstChild);
        
        // Auto-dismiss success/info messages
        if (type !== 'error') {
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.animation = 'msme-notification-slide-out 0.3s ease';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }
    }
}

// Add CSS animations for notifications
const notificationCSS = `
    <style>
        @keyframes msme-notification-slide-in {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes msme-notification-slide-out {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
        
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    </style>
`;

document.head.insertAdjacentHTML('beforeend', notificationCSS);

// Enhanced accessibility functions - add to registration.js

// Initialize accessibility features
document.addEventListener('DOMContentLoaded', function() {
    initializeAccessibilityFeatures();
    enhanceKeyboardNavigation();
    addLoadingStateAria();
});

function initializeAccessibilityFeatures() {
    // Add print date for accessibility
    const wrapper = document.querySelector('.wrap');
    if (wrapper) {
        wrapper.setAttribute('data-print-date', new Date().toLocaleDateString('id-ID'));
    }
    
    // Enhance table navigation
    const table = document.querySelector('.wp-list-table');
    if (table) {
        // Add table navigation helpers
        table.addEventListener('keydown', handleTableKeyNavigation);
        
        // Add row selection feedback
        const checkboxes = table.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectionAria);
        });
    }
}

function handleTableKeyNavigation(event) {
    const focusableElements = Array.from(this.querySelectorAll(
        'input, button, select, a, [tabindex]:not([tabindex="-1"])'
    ));
    
    const currentIndex = focusableElements.indexOf(document.activeElement);
    
    switch(event.key) {
        case 'Home':
            if (event.ctrlKey) {
                event.preventDefault();
                focusableElements[0]?.focus();
            }
            break;
        case 'End':
            if (event.ctrlKey) {
                event.preventDefault();
                focusableElements[focusableElements.length - 1]?.focus();
            }
            break;
    }
}

function updateSelectionAria() {
    const checkboxes = document.querySelectorAll('input[name="registration_ids[]"]');
    const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
    const totalCount = checkboxes.length;
    
    // Update bulk actions accessibility
    const bulkContainer = document.querySelector('.bulk-actions-container');
    if (bulkContainer) {
        bulkContainer.setAttribute('aria-label', 
            `Bulk actions - ${checkedCount} of ${totalCount} registrations selected`
        );
    }
    
    // Update select all checkbox
    const selectAll = document.getElementById('select-all-registrations');
    if (selectAll) {
        selectAll.setAttribute('aria-description', 
            `${checkedCount} of ${totalCount} registrations currently selected`
        );
    }
}

function addLoadingStateAria() {
    // Enhance existing button functions with ARIA states
    const originalApprove = window.approveRegistration;
    const originalReject = window.rejectRegistration;
    
    window.approveRegistration = function(id) {
        const button = event.target;
        button.setAttribute('aria-busy', 'true');
        button.setAttribute('aria-describedby', 'processing-message');
        
        // Create or update processing message
        let message = document.getElementById('processing-message');
        if (!message) {
            message = document.createElement('div');
            message.id = 'processing-message';
            message.className = 'sr-only';
            document.body.appendChild(message);
        }
        message.textContent = 'Processing approval request, please wait...';
        
        // Call original function
        if (originalApprove) originalApprove.call(this, id);
    };
    
    window.rejectRegistration = function(id) {
        const button = event.target;
        button.setAttribute('aria-busy', 'true');
        button.setAttribute('aria-describedby', 'processing-message');
        
        let message = document.getElementById('processing-message');
        if (!message) {
            message = document.createElement('div');
            message.id = 'processing-message';
            message.className = 'sr-only';
            document.body.appendChild(message);
        }
        message.textContent = 'Processing rejection request, please wait...';
        
        // Call original function  
        if (originalReject) originalReject.call(this, id);
    };
}

// Enhanced notification function with ARIA live regions
function showNotification(message, type = 'success') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notice.msme-notification');
    existingNotifications.forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `notice notice-${type} is-dismissible msme-notification`;
    notification.setAttribute('role', type === 'error' ? 'alert' : 'status');
    notification.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
    notification.innerHTML = `
        <p><strong>${message}</strong></p>
        <button type="button" class="notice-dismiss" onclick="this.parentElement.remove();" aria-label="Dismiss this notice">
            <span class="screen-reader-text">Dismiss this notice.</span>
        </button>
    `;
    
    const contentWrap = document.querySelector('.wrap') || document.querySelector('body');
    if (contentWrap) {
        contentWrap.insertBefore(notification, contentWrap.firstChild);
        
        // Auto-dismiss success messages
        if (type !== 'error') {
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
    }
}

function printRegistrationReport() {
    const printCSS = `
        <style id="print-styles">
            @media print {
                body * { visibility: hidden; }
                .wrap, .wrap * { visibility: visible; }
                .wrap { position: absolute; left: 0; top: 0; width: 100%; }
                .msme-export-btn, .button, .tablenav, .notice { display: none !important; }
            }
        </style>
    `;
    
    const head = document.head;
    head.insertAdjacentHTML('beforeend', printCSS);
    
    // Add print date
    document.querySelector('.wrap').setAttribute('data-print-date', new Date().toLocaleString());
    
    window.print();
    
    // Remove print styles after printing
    setTimeout(() => {
        const printStyles = document.getElementById('print-styles');
        if (printStyles) printStyles.remove();
    }, 1000);
}