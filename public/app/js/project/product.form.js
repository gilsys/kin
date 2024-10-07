class ProductForm {

    ready() {
        var mForm = $('#mt-product-form');
        var id = mForm.find("[name='id']");
        if (id.length) {
            id = id.val();
        }

        mForm.validate({
            ignore: ":not(:visible)",
            onkeyup: false
        });

        if (id.length) {
            $.post('/app/product/' + id, function (data) {    

                mForm.find("[name='name']").val(data.name);
                mForm.find("[name='area_id']").val(data.area_id).change();             

                mForm.find(".mt-date-created").val(formatDateWithTime(data.date_created));
                mForm.find(".mt-date-updated").val(formatDateWithTime(data.date_updated));
  
                $('#file-image_es_2 input[type="file"]').setImageUploaded('/app/image/image_es_2/' + data.id + addDateUpdatedTimestampParam(data), false);
                $('#file-image_es_3 input[type="file"]').setImageUploaded('/app/image/image_es_3/' + data.id + addDateUpdatedTimestampParam(data), false);
                $('#file-image_es_6 input[type="file"]').setImageUploaded('/app/image/image_es_6/' + data.id + addDateUpdatedTimestampParam(data), false);
                $('#file-image_en_2 input[type="file"]').setImageUploaded('/app/image/image_en_2/' + data.id + addDateUpdatedTimestampParam(data), false);
                $('#file-image_en_3 input[type="file"]').setImageUploaded('/app/image/image_en_3/' + data.id + addDateUpdatedTimestampParam(data), false);
                $('#file-image_en_6 input[type="file"]').setImageUploaded('/app/image/image_en_6/' + data.id + addDateUpdatedTimestampParam(data), false);
                $('#file-image_fr_2 input[type="file"]').setImageUploaded('/app/image/image_fr_2/' + data.id + addDateUpdatedTimestampParam(data), false);
                $('#file-image_fr_3 input[type="file"]').setImageUploaded('/app/image/image_fr_3/' + data.id + addDateUpdatedTimestampParam(data), false);
                $('#file-image_fr_6 input[type="file"]').setImageUploaded('/app/image/image_fr_6/' + data.id + addDateUpdatedTimestampParam(data), false);
                
                
                AdminUtils.showDelayedAfterLoad();
            });
        } else {
            // Valores por defecto en registros nuevos            
            mForm.removeDisabledOptions();
            AdminUtils.showDelayedAfterLoad();
        }
    }
}
