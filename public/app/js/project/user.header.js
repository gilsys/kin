class UserHeader {
    ready() {
        // Si estamos en la ficha de usuario, no es necesario cargar de nuevo la información        
        if ($('[data-js="UserForm"]').length) {
            return;
        }

        var id = $("#user-header[data-user-id]");
        if (id.length) {
            id = id.attr('data-user-id');
            $.post('/app/user/' + id, function (data) {
                iUserHeader.info(data);
                $('#mt-user-form').removeClass('d-none').hide().fadeIn();
            });
        }
    }

    info(data) {
        var mForm = $('#user-header');

        if ('avatar' in data && data.avatar.length) {
            $('#avatar-image-holder input[type="file"]').setImageUploaded('/app/file/user/avatar/' + data.id + addDateUpdatedTimestampParam(data), false);
        }

        $("#breadcumb-name, #user-header #user-fullname").text(data.name + " " + data.surnames);
        $("#breadcumb-name").removeClass('d-none').prev().removeClass('d-none').prev().removeClass('text-dark').addClass('text-muted');
        mForm.find("#user-email").text(data.email).attr('href', 'mailto:' + data.email);
        mForm.find("#user-location").text(data.address + " " + data.postalcode + " " + data.city + " " + data.state);
        mForm.find("#user-profile").text(__(data.user_profile)).css('background-color', hexToRgbA(data.user_profile_color, 0.1)).css('color', data.user_profile_color);
        mForm.find("#user-avatar").attr('src', '/app/user/avatar/' + data.id + addDateUpdatedTimestampParam(data));
    }
}