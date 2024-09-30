class ClientForm {
    ready() {
        var mForm = $('#mt-client-form');
        var id = mForm.find("[name='id']");
        if (id.length) {
            id = id.val();
        }

        mForm.validate({
            ignore: ":not(:visible)",
            onkeyup: false
        });


        var emailRepeater = mForm.find('.email-repeater').repeater({
            initEmpty: false,
            isFirstItemUndeletable: true,
            show: function () {
                $(this).slideDown();
            },
            hide: function (deleteElement) {
                $(this).slideUp(deleteElement);
            }
        });
        var phoneRepeater = mForm.find('.phone-repeater').repeater({
            initEmpty: false,
            isFirstItemUndeletable: true,
            show: function () {
                $(this).slideDown();
            },
            hide: function (deleteElement) {
                $(this).slideUp(deleteElement);
            }
        });

        if (id.length) {
            $.post('/app/client/' + id, function (data) {
                mForm.find("[name='id']").val(data.id);
                mForm.find("[name='information[name]']").val(data.name);
                mForm.find("[name='information[surnames]']").val(data.surnames);
                mForm.find("[name='information[entity]']").val(data.entity);

                mForm.find("[name='client_type_id']").val(data.client_type_id).change();
                mForm.find("[name='information[address]']").val(data.address);
                mForm.find("[name='information[city]']").val(data.city);
                mForm.find("[name='information[province]']").val(data.province);
                mForm.find("[name='information[postalcode]']").val(data.postalcode);
                mForm.find("[name='information[country_id]']").val(data.country_id).trigger('change');

                emailRepeater.setList(data.email);
                phoneRepeater.setList(data.phone);

                mForm.find(".mt-date-created").val(formatDateWithTime(data.date_created));
                mForm.find(".mt-date-updated").val(formatDateWithTime(data.date_updated));
                AdminUtils.showDelayedAfterLoad();
            });
        } else {
            // Valores por defecto en registros nuevos            
            mForm.removeDisabledOptions();
            AdminUtils.showDelayedAfterLoad();
        }
    }
}
