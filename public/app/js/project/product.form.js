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
                mForm.find("[name='market_ids[]']").val(data.market_ids).change();
                mForm.find("[name='slug']").val(data.slug);

                mForm.find("[name='subtitle_es']").val(data.subtitle_es);
                mForm.find("[name='subtitle_en']").val(data.subtitle_en);
                mForm.find("[name='subtitle_fr']").val(data.subtitle_fr);
                mForm.find("[name='periodicity_es']").val(data.periodicity_es);
                mForm.find("[name='periodicity_en']").val(data.periodicity_en);
                mForm.find("[name='periodicity_fr']").val(data.periodicity_fr);

                $('#logo_es input[type="file"]').setImageUploaded('/app/image/logo_es/' + data.id + addDateUpdatedTimestampParam(data), false);
                $('#photo_es input[type="file"]').setImageUploaded('/app/image/photo_es/' + data.id + addDateUpdatedTimestampParam(data), false);
                $('#logo_fr input[type="file"]').setImageUploaded('/app/image/logo_fr/' + data.id + addDateUpdatedTimestampParam(data), false);
                $('#photo_fr input[type="file"]').setImageUploaded('/app/image/photo_fr/' + data.id + addDateUpdatedTimestampParam(data), false);
                $('#logo_en input[type="file"]').setImageUploaded('/app/image/logo_en/' + data.id + addDateUpdatedTimestampParam(data), false);
                $('#photo_en input[type="file"]').setImageUploaded('/app/image/photo_en/' + data.id + addDateUpdatedTimestampParam(data), false);

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

                ['es', 'fr', 'en'].forEach(function (lang) {
                    if (data['zip_' + lang]) {
                        $('#zip_' + lang + ' input[type="file"]').setFileUploaded('/app/image/zip_' + lang + '/' + data.id + addDateUpdatedTimestampParam(data), false);
                    }
                });

                if (data.id == EMPTY_PRODUCT) {
                    mForm.find('[name="market_ids[]"], [name="slug"]').closest('.row > div').addClass('d-none');
                }

                AdminUtils.showDelayedAfterLoad();
            });
        } else {
            // Valores por defecto en registros nuevos            
            mForm.removeDisabledOptions();
            AdminUtils.showDelayedAfterLoad();
        }
    }
}
