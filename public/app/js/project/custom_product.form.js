class CustomProductForm {

    ready() {
        var mForm = $('#mt-product-form');
        var id = mForm.find("[name='id']");
        if (id.length) {
            id = id.val();
        } else {
            var parentProductId = mForm.find("[name='parent_product_id']").val();
        }

        mForm.validate({
            ignore: ":not(:visible)",
            onkeyup: false
        });

        function loadForm(data, isNew) {
            mForm.find("[name='name']").val(data.name);
            mForm.find("[name='market_ids[]']").val(data.market_ids).change();
            mForm.find("[name='slug']").val(data.slug);

            ['custom', 'es', 'en', 'fr'].forEach(function (lang) {
                if (isNew && lang == 'custom') {
                    return;
                }

                mForm.find("[name='subtitle_" + lang + "']").val(data['subtitle_' + lang]);
                mForm.find("[name='periodicity_" + lang + "']").val(data['periodicity_' + lang]);

                mForm.find('#logo_' + lang + ' input[type="file"]').setImageUploaded('/app/image/logo_' + lang + '/' + data.id + addDateUpdatedTimestampParam(data), false);
                mForm.find('#photo_' + lang + ' input[type="file"]').setImageUploaded('/app/image/photo_' + lang + '/' + data.id + addDateUpdatedTimestampParam(data), false);

                mForm.find('#file-image_' + lang + '_2 input[type="file"]').setImageUploaded('/app/image/image_' + lang + '_2/' + data.id + addDateUpdatedTimestampParam(data), false);
                mForm.find('#file-image_' + lang + '_3 input[type="file"]').setImageUploaded('/app/image/image_' + lang + '_3/' + data.id + addDateUpdatedTimestampParam(data), false);
                mForm.find('#file-image_' + lang + '_6 input[type="file"]').setImageUploaded('/app/image/image_' + lang + '_6/' + data.id + addDateUpdatedTimestampParam(data), false);

                if (lang != 'custom') {
                    if (data['zip_' + lang]) {
                        $('#zip_' + lang + ' input[type="file"]').setFileUploaded('/app/image/zip_' + lang + '/' + data.id + addDateUpdatedTimestampParam(data), false);
                    }

                    formReadOnly(mForm.find('#file-image_' + lang + '_2').closest('.card'));
                }
            });

            mForm.find(".mt-date-created").val(formatDateWithTime(data.date_created));
            mForm.find(".mt-date-updated").val(formatDateWithTime(data.date_updated));

            AdminUtils.showDelayedAfterLoad();
        }

        if (id.length) {
            $.post('/app/custom_product/' + id, function (data) {
                loadForm(data, false);
            });
        } else {
            $.post('/app/product/' + parentProductId, function (data) {
                loadForm(data, true);
            });
        }
    }
}
