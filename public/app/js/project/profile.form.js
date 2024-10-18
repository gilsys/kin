class ProfileForm {
    ready() {
        // Atención, está reutilizado para la pantalla de logs para cargar la información de cabecera
        var mForm = $('#mt-profile-form');
        var id = mForm.find('[name="id"]').val()
        
        var errorMsg = '';
        $.validator.addMethod('checkPasswordCurrent', function (value, element, param) {
            errorMsg = '';

            var dataParams = {"password": value};
            if (value.length == 0) {
                return true;
            }

            var url = "/app/user/check_password_current";
            $.post({
                url: url,
                async: false,
                global: false,
                data: dataParams,
                dataType: 'json',
                success: function (result) {
                    if (result == 0) {
                        errorMsg = __("app.js.error.password_current");
                    }
                }
            });
            return !errorMsg.length;
        }, function () {
            return errorMsg;
        });


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

                var dataParams = {"nickname": value};
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
            onkeyup: false,
            rules: {
                "password_current": {
                    checkPasswordCurrent: true
                },
                "password": {
                    checkPasswordRequirements: PASSWORD_COMPLEX
                },
                "nickname": {
                    checkNicknameRequirements: true
                }
            }
        });


        $.post('/app/profile', function (data) {
            mForm.find("[name='personal_information[name]']").val(data.name);
            mForm.find("[name='personal_information[surnames]']").val(data.surnames);
            mForm.find("[name='personal_information[email]']").val(data.email);
            mForm.find("[name='personal_information[phone1]']").val(data.phone1);

            mForm.find("[name='color']").val(data.color)[0].jscolor.fromString(data.color);

            if ('avatar' in data && data.avatar.length) {
                $('#avatar-image-holder input[type="file"]').setImageUploaded('/app/file/user/avatar/' + data.id + addDateUpdatedTimestampParam(data), false);
            }

            mForm.find("#breadcumb-name, #user-fullname").text(data.name + " " + data.surnames);
            mForm.find("#user-email").text(data.email).attr('href', 'mailto:' + data.email);
            mForm.find("#user-profile").text(mForm.find("[name='user_profile_id'] option:selected").text().trim()).css('background-color', hexToRgbA(data.user_profile_color, 0.1)).css('color', data.user_profile_color);
            mForm.find("#user-avatar").attr('src', '/app/user/avatar/' + data.id + addDateUpdatedTimestampParam(data));

            $('#auth-data-change').click(function (e) {
                e.preventDefault();
                mForm.find("[name='password_current']").val('');
                mForm.find(".change-password-fields").removeClass('d-none');
                mForm.find("input").removeAttr('disabled');
                $(this).hide();
            });

            mForm.find("[name='nickname']").val(data.nickname);
            mForm.find("[name='password_current']").val('***********');
            AdminUtils.showDelayedAfterLoad();
        });

    }
}