// MSME Business Manager - Registration Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    console.log('MSME Registration JavaScript loaded');
    
    // Initialize registration form functionality
    initRegistrationForm();
});

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
    
    // Clean and process address (extract street name)
    let streetName = '';
    if (address) {
        // Extract street name (remove jl./jalan, numbers, etc.)
        streetName = address
            .toLowerCase()
            .replace(/^(jl\.|jalan|jl)\s*/i, '') // Remove jl./jalan prefix
            .replace(/\s*(no\.|nomor)\s*\d+.*$/i, '') // Remove number and after
            .replace(/[^a-z0-9\s]/g, '') // Remove special characters
            .replace(/\s+/g, '-') // Replace spaces with hyphens
            .split('-')[0] // Take first part only
            .replace(/^-+|-+$/g, ''); // Remove leading/trailing hyphens
        
        // Limit street name length
        if (streetName.length > 15) {
            streetName = streetName.substring(0, 15);
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
            const response = JSON.parse(xhr.responseText);
            
            // Reset button state
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
            submitBtn.disabled = false;
            
            if (response.success) {
                // Move to step 3 (email verification)
                showStep(3);
            } else {
                alert('Error: ' + response.data.message);
            }
        }
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

