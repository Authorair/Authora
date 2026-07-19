/**
 * Authora Login Page JavaScript
 */

jQuery(document).ready(function($) {
    
    // Initialize variables
    let loginToken;
    let countdownInterval;
    let currentMobile = '';
    
    // Hide verify form initially
    $('#authora-verify').hide();
    
    // Handle mobile number submission
    $('#authora-login').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button');
        const mobile = $form.find('input[name="mobile"]').val();
        
        // Validate mobile number
        if (!mobile || mobile.length < 10) {
            showMessage($form, 'لطفا شماره موبایل معتبر وارد کنید', 'error');
            return;
        }
        
        // Show loading state
        $button.addClass('loading');
        $button.prop('disabled', true);
        
        // Send AJAX request
        $.ajax({
            url: authora_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'authora_login',
                mobile: mobile,
                _wpnonce: authora_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Store data
                    loginToken = response.data.token;
                    currentMobile = mobile;
                    
                    // Update UI
                    $('.authora-login-result').html(
                        'کد 5 رقمی ارسال شده به شماره <strong>' + mobile + '</strong> را وارد کنید'
                    );
                    
                    // Show verify form
                    $('#authora-login').fadeOut(300, function() {
                        $('#authora-verify').fadeIn(300);
                        // Focus on first code input
                        $('#authora-verify .authora-codes input:first').focus();
                    });
                    
                    // Start countdown
                    startCountdown();
                    
                    showMessage($form, 'کد تاییدیه به شماره موبایل شما ارسال شد!', 'success');
                } else {
                    showMessage($form, response.data.message || 'خطا در ارسال کد', 'error');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'خطا در ارتباط با سرور';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                }
                showMessage($form, errorMessage, 'error');
            },
            complete: function() {
                $button.removeClass('loading');
                $button.prop('disabled', false);
            }
        });
    });
    
    // Handle OTP verification
    $('#authora-verify').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const code = getCodeFromInputs();
        
        // Validate code
        if (code.length !== 5) {
            showMessage($form, 'لطفا کد 5 رقمی را کامل وارد کنید', 'error');
            return;
        }
        
        // Show loading state
        $button.addClass('loading');
        $button.prop('disabled', true);
        
        // Send AJAX request
        $.ajax({
            url: authora_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'authora_verify',
                mobile: currentMobile,
                code: code,
                token: loginToken,
                _wpnonce: authora_ajax.nonce_verify
            },
            success: function(response) {
                if (response.success) {
                    showMessage($form, 'ورود با موفقیت انجام شد!', 'success');
                    
                    // Redirect after short delay
                    setTimeout(function() {
                        window.location.href = authora_ajax.redirect_url;
                    }, 1500);
                } else {
                    showMessage($form, response.data.message || 'کد وارد شده صحیح نیست', 'error');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'خطا در تایید کد';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                }
                showMessage($form, errorMessage, 'error');
            },
            complete: function() {
                $button.removeClass('loading');
                $button.prop('disabled', false);
            }
        });
    });
    
    // Handle resend OTP
    $('.authora-resend').on('click', function(e) {
        e.preventDefault();
        
        const $link = $(this);
        const $countdown = $('.authora-countdown');
        
        // Disable link
        $link.addClass('disabled');
        
        // Send AJAX request
        $.ajax({
            url: authora_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'authora_login',
                mobile: currentMobile,
                _wpnonce: authora_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update token
                    loginToken = response.data.token;
                    
                    // Reset countdown
                    startCountdown();
                    
                    showMessage($('#authora-verify'), 'کد جدید ارسال شد!', 'success');
                } else {
                    showMessage($('#authora-verify'), response.data.message || 'خطا در ارسال مجدد کد', 'error');
                }
            },
            error: function() {
                showMessage($('#authora-verify'), 'خطا در ارسال مجدد کد', 'error');
            },
            complete: function() {
                $link.removeClass('disabled');
            }
        });
    });
    
    // Handle edit number button
    $('#authora-edit-number').on('click', function() {
        // Clear forms
        $('#authora-login input[name="mobile"]').val('');
        clearCodeInputs();
        
        // Reset countdown
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }
        
        // Show login form
        $('#authora-verify').fadeOut(300, function() {
            $('#authora-login').fadeIn(300);
            $('#authora-login input[name="mobile"]').focus();
        });
    });
    
    // Handle code input navigation
    $('.authora-codes input').on('input', function() {
        const $current = $(this);
        const value = $current.val();
        
        // Only allow numbers
        if (!/^\d*$/.test(value)) {
            $current.val('');
            return;
        }
        
        // Move to next input if value entered
        if (value.length === 1) {
            const $next = $current.next('.authora-codes input');
            if ($next.length) {
                $next.focus();
            }
        }
        
        // Update hidden code field
        updateCodeField();
    });
    
    // Handle backspace in code inputs
    $('.authora-codes input').on('keydown', function(e) {
        if (e.key === 'Backspace' && $(this).val() === '') {
            const $prev = $(this).prev('.authora-codes input');
            if ($prev.length) {
                $prev.focus();
            }
        }
    });
    
    // Helper functions
    function getCodeFromInputs() {
        let code = '';
        $('.authora-codes input').each(function() {
            code += $(this).val();
        });
        return code;
    }
    
    function clearCodeInputs() {
        $('.authora-codes input').val('');
        updateCodeField();
    }
    
    function updateCodeField() {
        $('#authora-verify-code').val(getCodeFromInputs());
    }
    
    function startCountdown() {
        // Clear existing countdown
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }
        
        let timeLeft = 120; // 2 minutes
        const $countdown = $('.authora-countdown');
        const $resend = $('.authora-resend');
        
        // Hide resend link
        $resend.hide();
        $countdown.show();
        
        countdownInterval = setInterval(function() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            
            $countdown.text(
                (minutes < 10 ? '0' : '') + minutes + ':' + 
                (seconds < 10 ? '0' : '') + seconds
            );
            
            timeLeft--;
            
            if (timeLeft < 0) {
                clearInterval(countdownInterval);
                $countdown.hide();
                $resend.show();
            }
        }, 1000);
    }
    
    function showMessage($form, message, type) {
        const $message = $form.find('.authora-message');
        const $messageText = $message.find('span');
        
        $messageText.text(message);
        $message.removeClass('show error success');
        $message.addClass('show ' + type);
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            $message.removeClass('show');
        }, 5000);
    }
    
    // Initialize code field
    updateCodeField();
    
    // Focus on mobile input on page load
    $('#authora-login input[name="mobile"]').focus();
    
});
