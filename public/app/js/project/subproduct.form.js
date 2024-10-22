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
                console.log(data);
                mForm.find("[name='name']").val(data.name);
                mForm.find("[name='product_id']").val(data.product_id).change();
                mForm.find("[name='format']").val(data.format);
                mForm.find("[name='reference']").val(data.reference);

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
