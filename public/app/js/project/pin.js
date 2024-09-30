class PinForm {

    request(callbackFunction, userId = null) {
        $.post('/app/pin_request', {user_id: userId}, function (data) {
            if ('error' in data) {
                showError(data.error);
            } else {
                PinForm(callbackFunction, userId);
            }
        });
    }

    form(callbackFunction, userId = null) {
        var mFormPin = $('#mt-pin-form');

        if (userId == null) {
            mFormPin.find('.pin-title').html(__('app.js.pin.title'));
            mFormPin.find('.pin-text').html(__('app.js.pin.text'));
        } else {
            mFormPin.find('.pin-title').html(__('app.js.pin.title_consumer'));
            mFormPin.find('.pin-text').html(__('app.js.pin.text_consumer'));
        }

        mFormPin.validate({
            rules: {
                pin: {
                    checkPinLength: true
                }
            },
            submitHandler: function (form, event) {
                event.preventDefault();

                $.ajax({
                    type: "POST",
                    url: '/app/pin_validate',
                    data: {pin: $(form).find('[name="pin"]').val(), user_id: userId},
                    success: function (data) {
                        if ('error' in data) {
                            showError(data.error);
                        } else {
                            callbackFunction(data.token);
                            mFormPin.parent().modal('hide');
                        }
                    }
                });
            }
        });

        mFormPin.find('[name="pin"]').pincodeInput({inputs: PIN_LENGTH, hidedigits: false, inputtype: 'text', inputclass: 'form-control form-control-solid bg-secondary bg-opacity-20', change: function (input, value, inputnumber) {
                $(input).closest('.pincode-input-container').siblings('[name="pin"]').blur();
            }});
        mFormPin.find('[name="pin"]').pincodeInput().data('plugin_pincodeInput').clear();
        mFormPin.find('[name="pin"]').siblings('label.error').remove();
        mFormPin.parent().modal('show');
    }
}
