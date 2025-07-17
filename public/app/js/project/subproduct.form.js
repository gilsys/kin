class SubProductForm {

    ready() {
        var mForm = $('#mt-subproduct-form');
        var id = mForm.find("[name='id']");
        if (id.length) {
            id = id.val();
        }

        mForm.validate({
            ignore: ":not(:visible)",
            onkeyup: false
        });

        if (id.length) {
            $.post('/app/subproduct/' + id, function (data) {
                mForm.find("[name='product_id']").val(data.product_id).change();

                ['es', 'en', 'fr'].forEach(function (lang) {
                    mForm.find("[name='name[" + lang + "]']").val(data.name[lang]);
                    mForm.find("[name='reference[" + lang + "]']").val(data.reference[lang]);
                });

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
