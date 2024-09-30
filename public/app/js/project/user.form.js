class UserForm {
    constructor() {
    }

    ready() {

        // Atención, está reutilizado para la pantalla de logs para cargar la información de cabecera
        var mForm = $('#mt-user-form');
        var id = mForm.find("[name='id']");
        if (id.length) {
            id = id.val();
        }

        var errorMsg = '';
        $.validator.addMethod('checkNicknameRequirements', function (value, element, param) {
            errorMsg = '';

            if (value.match(/[^A-Za-z0-9]/)) {
                errorMsg = __("app.js.error.nickname_format");
            }

            if (errorMsg.length === 0) {
                var url = "/app/user/check_nickname";
                if (id.length) {
                    url += "/" + id;
                }

                var dataParams = { "nickname": value };
                $.post({
                    url: url,
                    async: false,
                    global: false,
                    data: dataParams,
                    dataType: 'json',
                    success: function (result) {
                        if (result == 1) {
                            errorMsg = __("app.js.error.nickname_exists");
                        }
                    }
                });
            }
            return !errorMsg.length;
        }, function () {
            return errorMsg;
        });


        mForm.validate({
            ignore: ":not(:visible)",
            onkeyup: false,
            rules: {
                "password": {
                    checkPasswordRequirements: PASSWORD_COMPLEX
                },
                "nickname": {
                    checkNicknameRequirements: true
                }
            }
        });

        var mAuthForm = $('#mt-user-auth-form');
        mAuthForm.validate({
            onkeyup: false,
            rules: {
                "password": {
                    checkPasswordRequirements: PASSWORD_COMPLEX
                },
                "nickname": {
                    checkNicknameRequirements: true
                }
            }
        });

        var mDeleteForm = $('#mt-user-delete-form');
        mDeleteForm.validate({
            submitHandler: function (form, event) {
                $.post($(form).attr('action'), function () {
                    window.location.reload();
                });
            }
        });

        var mRestoreForm = $('#mt-user-restore-form');
        mRestoreForm.validate({
            submitHandler: function (form, event) {
                $.post($(form).attr('action'), function () {
                    window.location.reload();
                });
            }
        });

        mForm.find('[name="user_profile_id"]').on('change', function () {
            mForm.find('[data-user-profile-id]').addClass('d-none');
            mForm.find('[data-user-profile-id*=' + $(this).val() + ']').removeClass('d-none');
        });

        mForm.find('[name="send_email_password"]').on('change', function () {
            mForm.find('[name="password"]').val('').prop('required', $(this).prop('checked')).closest('.row').toggleClass('d-none', $(this).prop('checked'));
        });

        $("[name='user_profile_id']").on("change", function () {
            if (!$(this).val()) return;
            var profileColor = $(this).find('option:selected').data('profile-color');
            profileColor = profileColor.replace('#', '');
            mForm.find("[name='color']").val(profileColor)[0].jscolor.fromString(profileColor);
        });

        if (id.length) {
            $.post('/app/user/' + id, function (data) {
                mForm.find("[name='id']").val(data.id);
                if (data.user_status_id == "V") {
                    mForm.find("[name='user_status_id']").attr('checked', 'checked');
                } else if (data.user_status_id == "Z") {
                    mForm.find("[name='user_status_id']").parent().replaceWith('<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(data.user_status_color, 0.1) + '; color: ' + data.user_status_color + '">' + __('table.user_status.' + data.user_status_id) + '</span>');
                }

                mForm.find("[name='personal_information[name]']").val(data.name);
                mForm.find("[name='personal_information[surnames]']").val(data.surnames);
                mForm.find("[name='personal_information[email]']").val(data.email);
                mForm.find("[name='personal_information[phone1]']").val(data.phone1);
                mForm.find("[name='personal_information[address]']").val(data.address);

                mForm.find("[name='user_profile_id']").val(data.user_profile_id).change();
                mForm.find("[name='color']").val(data.color)[0].jscolor.fromString(data.color);

                $("[name='personal_information[city]']").val(data.city);
                $("[name='personal_information[state]']").val(data.state);
                $("[name='personal_information[address]']").val(data.address);
                iUserHeader.info(data);

                $('#auth-data-change').click(function (e) {
                    e.preventDefault();
                    mAuthForm.find(".change-password-fields").removeClass('d-none');
                    mAuthForm.find("input").removeAttr('disabled');
                    $(this).hide();
                });

                AdminUtils.showDelayedAfterLoad('.form-container');
                mAuthForm.find("[name='nickname']").val(data.nickname);
                mAuthForm.removeClass('d-none').hide().fadeIn();

                if (data.user_status_id == 'Z') {
                    mRestoreForm.removeClass('d-none').hide().fadeIn();
                    formReadOnly(mForm);
                    formReadOnly(mAuthForm);
                } else {
                    mDeleteForm.removeClass('d-none').hide().fadeIn();
                }

                if (data.last_login == null || data.last_login == '') {
                    mAuthForm.find('.send-email-password').on('click', function () {
                        showConfirm($(this).text(), $(this).attr('data-text'), 'question', function () {
                            window.location = '/app/user/email_password/' + data.id;
                        }, false, __('app.js.common.send'), __('app.js.common.cancel'));
                    }).removeClass('d-none');
                }

                mForm.find(".mt-date-created").val(formatDateWithTime(data.date_created));
                mForm.find(".mt-date-updated").val(formatDateWithTime(data.date_updated));
                mForm.find(".date-last-login").val(formatDateWithTime(data.last_login));

                mForm.removeDisabledOptions();

                if (!userHasProfile(['A'])) {
                    mForm.find("[name='user_profile_id']").addClass('readonly-disabled');
                }
            });
        } else {
            // Valores por defecto en registros nuevos            
            mForm.find("[name='user_status_id']").attr('checked', 'checked');
            mForm.find("[name='user_profile_id']").val('').change();
            AdminUtils.showDelayedAfterLoad('.form-container');
        }
    }
}