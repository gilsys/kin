class LoginEnterPasswordForm {
    ready() {
        $('#login-enter-password').validate({
            rules: {
                password: {
                    checkPasswordRequirements: PASSWORD_COMPLEX
                },
                password2: {
                    equalTo: '#login-enter-password [name="password"]'
                }
            }
        });
    }
}