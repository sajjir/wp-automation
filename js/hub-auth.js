jQuery(document).ready(function($) {
    var timerInterval;

    function startTimer(duration, display) {
        clearInterval(timerInterval);
        var timer = duration, minutes, seconds;
        $('#hub-btn-resend').addClass('disabled');
        
        timerInterval = setInterval(function () {
            minutes = parseInt(timer / 60, 10);
            seconds = parseInt(timer % 60, 10);
            minutes = minutes < 10 ? "0" + minutes : minutes;
            seconds = seconds < 10 ? "0" + seconds : seconds;
            display.text(minutes + ":" + seconds);

            if (--timer < 0) {
                clearInterval(timerInterval);
                display.text("00:00");
                $('#hub-btn-resend').removeClass('disabled');
            }
        }, 1000);
    }

    function showMessage(msg, type) {
        $('#hub-message').removeClass('error success').addClass(type).text(msg).fadeIn();
        setTimeout(function() { $('#hub-message').fadeOut(); }, 5000);
    }

    $('#hub-btn-send, #hub-btn-resend').on('click', function(e) {
        e.preventDefault();
        if($(this).hasClass('disabled')) return;

        var phone = $('#hub-phone').val();
        if(!phone || phone.length < 10) {
            showMessage('شماره موبایل نامعتبر است.', 'error');
            return;
        }

        var $btn = $(this);
        var originalText = $btn.text();
        $btn.text('در حال ارسال...').prop('disabled', true);

        $.post(hubAuth.ajax_url, {
            action: 'hub_send_otp',
            nonce: hubAuth.nonce,
            phone: phone
        }, function(res) {
            $btn.text(originalText).prop('disabled', false);
            if(res.success) {
                $('#hub-step-phone').removeClass('active').hide();
                $('#hub-step-verify').addClass('active').fadeIn();
                $('#hub-phone-display').text(phone);
                startTimer(hubAuth.timer_limit, $('#hub-timer'));
            } else {
                showMessage(res.data, 'error');
            }
        });
    });

    $('#hub-btn-verify').on('click', function(e) {
        e.preventDefault();
        var otp = $('#hub-otp').val();
        var phone = $('#hub-phone').val();
        var redirect_to = $('#hub-redirect-to').val();

        if(!otp || otp.length < 4) {
            showMessage('کد تایید را وارد کنید.', 'error');
            return;
        }

        var $btn = $(this);
        $btn.text('در حال بررسی...').prop('disabled', true);

        $.post(hubAuth.ajax_url, {
            action: 'hub_verify_otp',
            nonce: hubAuth.nonce,
            phone: phone,
            otp: otp,
            redirect_to: redirect_to
        }, function(res) {
            $btn.text('بررسی کد').prop('disabled', false);
            if(res.success) {
                if(res.data.action === 'login_success') {
                    showMessage(res.data.msg, 'success');
                    window.location.href = res.data.redirect;
                } else if(res.data.action === 'register_required') {
                    $('#hub-step-verify').removeClass('active').hide();
                    $('#hub-step-register').addClass('active').fadeIn();
                }
            } else {
                showMessage(res.data, 'error');
            }
        });
    });

    $('#hub-btn-register').on('click', function(e) {
        e.preventDefault();
        var phone = $('#hub-phone').val();
        var fname = $('#hub-fname').val();
        var lname = $('#hub-lname').val();
        var email = $('#hub-email').val();
        var redirect_to = $('#hub-redirect-to').val();

        var $btn = $(this);
        $btn.text('در حال ثبت...').prop('disabled', true);

        $.post(hubAuth.ajax_url, {
            action: 'hub_complete_register',
            nonce: hubAuth.nonce,
            phone: phone,
            fname: fname,
            lname: lname,
            email: email,
            redirect_to: redirect_to
        }, function(res) {
            $btn.text('ثبت اطلاعات و ورود').prop('disabled', false);
            if(res.success) {
                showMessage(res.data.msg, 'success');
                window.location.href = res.data.redirect;
            } else {
                showMessage(res.data, 'error');
            }
        });
    });

    $('#hub-btn-edit, #hub-btn-cancel-register').on('click', function(e) {
        e.preventDefault();
        $('.hub-step').removeClass('active').hide();
        $('#hub-step-phone').addClass('active').fadeIn();
        clearInterval(timerInterval);
    });
});