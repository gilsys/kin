class LoginForm {
    changeForgotStep(step) {
        $('.forgot-step').addClass('d-none');
        $('.forgot-step.' + step + '-step').removeClass('d-none');
    }

    ready() {
        $('#login-form').validate({});
        $('#login-forgot').validate({
            rules: {
                password: {
                    checkPasswordRequirements: PASSWORD_COMPLEX
                },
                password2: {
                    equalTo: '#login-forgot [name="password"]'
                },
                pin: {
                    checkPinLength: true
                }
            },
            submitHandler: (form, event) => {
                event.preventDefault();

                if ($(form).find('.login-step:visible').length) {
                    $.ajax({
                        type: "POST",
                        dataType: "serialized",
                        url: "/app/public/forgot_login",
                        data: $(form).serialize(),
                        success: (data) => {
                            if ('error' in data) {
                                showError(data.error);
                                grecaptcha.reset(captchaItems[$(form).find('.login-step .recaptcha-field > div').attr('id')]);
                            } else {
                                iLoginForm.changeForgotStep('pin');
                            }
                        }
                    });
                } else if ($(form).find('.pin-step:visible').length) {
                    $.ajax({
                        type: "POST",
                        dataType: "serialized",
                        url: "/app/public/forgot_pin",
                        data: $(form).serialize(),
                        success: (data) => {
                            if ('error' in data) {
                                showError(data.error);
                            } else {
                                iLoginForm.changeForgotStep('password');
                            }
                        }
                    });
                } else if ($(form).find('.password-step:visible').length) {
                    $.ajax({
                        type: "POST",
                        dataType: "serialized",
                        url: "/app/public/forgot_password",
                        data: $(form).serialize(),
                        success: (data) => {
                            if ('error' in data) {
                                showError(data.error);
                            } else {
                                window.location.reload();
                            }
                        }
                    });
                }
            }
        });


        $('#kt_login_forgot').click(() => {
            $('.login-forgot').removeClass('d-none');
            $('.login-signin').addClass('d-none');
        });
        $('#kt_login_forgot_cancel').click(() => {
            $('.login-forgot').addClass('d-none');
            $('.login-signin').removeClass('d-none');

            iLoginForm.changeForgotStep('login');
            $('#login-forgot .forgot-step').find('[name], .pincode-input-container input').val('');
            grecaptcha.reset(captchaItems[$('#login-forgot .login-step .recaptcha-field > div').attr('id')]);
        });

        $('[name="pin"]').pincodeInput({inputs: PIN_LENGTH, hidedigits: false, inputtype: 'text', inputclass: 'form-control form-control-solid bg-white border-primary rounded', change: (input, value, inputnumber) => {
                $(input).closest('.pincode-input-container').siblings('[name="pin"]').blur();
            }});

        iLoginForm.changeForgotStep('login');
        clearDatatableLocalStorage();
        clearOldUserLocalStorage();
    }
}



