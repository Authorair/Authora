jQuery(document).ready(function ($) {

    let authoraSecureMobile = '';
    let authoraSecureToken = '';

    function close_modal() {
        $('.authora-modal-container').removeClass('open');
    }

    function open_modal(e) {
        e.preventDefault();
        $('.authora-modal-container').addClass('open');
    }

    $('.authora-close').click(close_modal);

    $('#authora-login').submit(function (e) {

        e.preventDefault();

        let data = $(this).serialize();
        let _this = $(this);
        let _message = $(this).find('.authora-message');
        let _btn = $(this).find('button');
        let _resend = $('.authora-resend');

        $.ajax({
            type: 'POST',
            url: authora.ajax_url,
            data: data,
            beforeSend: function () {
                $(_this).addClass('loading');
                $(_message).removeClass('active');
                $(_btn).attr('disabled', true);
                $(_resend).text(authora.i18n.sending).removeClass('active');
            },
            success: function (result) {
                if (result.success) {
                    countdown = result.data.duration;
                    set_time(countdown);
                    $(".authora-login-result").text(result.data.message);
                    $('.authora-modal').addClass('verify');
                    $(".authora-codes input").eq(0).focus();

                    authoraSecureMobile = result.data.mobile;
                    authoraSecureToken = result.data.token;

                    $("#authora-verify input[name='mobile']").val(authoraSecureMobile);

                } else {
                    let message = authora.i18n.error_occurred;
                    if (result && result.data && result.data.message) {
                        message = result.data.message;
                    }
                    $(_message).addClass('active').find('span').text(message);
                }
            },
            complete: function () {
                $(_this).removeClass('loading');
                $(_btn).attr('disabled', false);
                $(_resend).text(authora.i18n.resend);
            },
            error: function (xhr) {
                let result = xhr.responseJSON;
                let message = authora.i18n.error_occurred;

                if (result && result.data && result.data.message) {
                    message = result.data.message;
                } else if (xhr.status === 0) {
                    message = authora.i18n.connection_error;
                }

                $(_message).addClass('active').find('span').text(message);
            },
        });

    });

    function toggle_form() {
        $('.authora-modal').toggleClass('verify');
    }

    $('#authora-edit-number').click(toggle_form);

    var countdown = 1000;
    function countdown_handle() {

        if (countdown == 0) {
            $('.authora-resend').addClass('active');
        }

        countdown--;

        set_time(countdown);

    }
    setInterval(countdown_handle, 1000);

    function set_time(countdown) {

        let remain = countdown >= 0 ? countdown : 0;

        let minute = Math.floor(remain / 60);
        minute = minute < 10 ? '0' + minute : minute;

        let second = remain % 60;
        second = second < 10 ? '0' + second : second;

        let time = `${minute}:${second}`;

        $('.authora-countdown').text(time);

    }

    $(document).on('focus', '.authora-codes input', function (e) {
        $(this).select();
    });

    $(document).on('input', '.authora-codes input', function (e) {
        let code = $(this).val().trim();
        if (code.length) {
            if ($(this).next()) {
                $(this).next().focus();
            }
            if ($(this).index() >= $('.authora-codes input').length - 1) {
                $('#authora-verify').submit();
            }
        }
    });

    $(document).on('click', 'a.authora-resend.active', function (e) {
        e.preventDefault();
        if (countdown <= 0) {
            authoraSecureToken = ''; 
            $('#authora-login').submit();
        }
    });

    $(document).on('submit', '#authora-verify', function (e) {

        e.preventDefault();

        let code = '';
        $(this).find('.authora-codes input').each(function () {
            code += $(this).val();
        });
        $("#authora-verify input[name='code']").val(code);

        let data = $(this).serialize();
        data += '&token=' + encodeURIComponent(authoraSecureToken);
        
        let _this = $(this);
        let _message = $(this).find('.authora-message');
        let _message_s = $(this).find('.authora-success');
        let _btn = $(this).find('button');

        $.ajax({
            type: 'POST',
            url: authora.ajax_url,
            data: data,
            beforeSend: function () {
                $(_this).addClass('loading');
                $(_btn).attr('disabled', true);
            },
            success: function (result) {
                if (result.success) {
                    $(_message_s).text(result.data.message).slideDown(500);
                    location.reload();
                } else {
                    let message = authora.i18n.error_occurred;
                    if (result && result.data && result.data.message) {
                        message = result.data.message;
                    }
                    $(_message).addClass('active').find('span').text(message);
                }
            },
            complete: function () {
                $(_this).removeClass('loading');
                $(_btn).attr('disabled', false);
            },
            error: function (xhr) {
                let result = xhr.responseJSON;
                let message = authora.i18n.error_occurred;

                if (result && result.data && result.data.message) {
                    message = result.data.message;
                } else if (xhr.status === 0) {
                    message = authora.i18n.connection_error;
                }

                $(_message).addClass('active').find('span').text(message);
            },
        });

    });

    function process_otp(otp) {

        let code = otp.code;

        let code_array = code.split('');
        for (let i = 0; i < code_array.length; i++) {
            $('.authora-codes input').eq(i).val(code_array[i]);
        }

        $('#authora-verify-code').val(code);

        $('#authora-verify').submit();

    }

    if ('OTPCredential' in window) {

        const ac = new AbortController();
        $('#authora-verify').submit(function (e) {
            ac.abort();
        });

        navigator.credentials.get({
            otp: { transport: ['sms'] },
            signal: ac.signal
        }).then(process_otp);

    }

    $(document).on('click', "a[href*='wp-login.php']", open_modal);

});
