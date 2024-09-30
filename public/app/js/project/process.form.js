class ProcessForm {

    ready() {
        var mForm = $('#mt-process-form');
        var id = mForm.find("[name='id']");
        if (id.length) {
            id = id.val();
        }

        if (mForm.data('client-id')) {
            var clientId = mForm.data('client-id');
            $('.btn-back').attr('href', '/app/client/processes/' + clientId)
        }

        $.validator.addMethod("greaterThanDate", function (value, element, min) {
            if (value == null || value.length == 0 || $(min).val() == null || $(min).val().length == 0) {
                return true;
            }

            var date1 = moment($(min).val(), 'DD/MM/YYYY HH:mm');
            var date2 = moment(value, 'DD/MM/YYYY HH:mm');
            return date2.isAfter(date1);
        }, __('app.js.error.date_end_not_greater_than_date_start'));

        var uppy = new UppyCustom(mForm, {
            target: '#file-uploader',
            uploadFileUrl: '/app/process/upload_file',
            fetchFileUrl: '/app/process/file',
            fieldName: 'process-file',
            locale: Uppy.locales.es_ES,
            downloadUrl: '/app/process/download_file',
            successCallback: function () {
                mForm[0].submit();
            }
        });

        mForm.validate({
            ignore: ":not(:visible)",
            onkeyup: false,
            rules: {
                "date_end": {
                    greaterThanDate: '[name="date_start"]'
                }
            },

            submitHandler: function (form, e) {
                e.preventDefault();
                uppy.validate();
                return false;
            }

        });

        mForm.find(".select2-badge").each(function () {
            $(this).select2({
                templateResult: select2Badge,
                templateSelection: select2Badge,
            })
        });

        if (id.length) {
            $.post('/app/process/' + id, function (data) {
                mForm.find("[name='id']").val(data.id);
                mForm.find("[name='name']").val(data.name);
                select2Clients(mForm.find("[name='client_id']"), '/app/client/selector', data.client_id);
                mForm.find("[name='process_type_id']").val(data.process_type_id).change();
                mForm.find("[name='process_status_id']").val(data.process_status_id).change();
                mForm.find("[name='description']").val(data.description);
                mForm.find("[name='date_start']").datetimepicker('update', data.date_start);
                mForm.find("[name='date_end']").datetimepicker('update', data.date_end);

                $(".mt-date-created").val(formatDateWithTime(data.date_created));
                $(".mt-date-updated").val(formatDateWithTime(data.date_updated));
                $("[name='fullname']").val(data.fullname);

                mForm.find("[name='process_type_id']").addClass('readonly-disabled');
                mForm.find("[name='client_id']").addClass('readonly-disabled');

                uppy.load(data.files);

                AdminUtils.showDelayedAfterLoad('.form-container');
            });
        } else {
            // Valores por defecto en registros nuevos            
            mForm.removeDisabledOptions();
            if (clientId) {
                select2Clients(mForm.find("[name='client_id']"), '/app/client/selector', clientId);
                mForm.find("[name='client_id']").addClass('readonly-disabled');

            } else {
                select2Clients(mForm.find("[name='client_id']"), '/app/client/selector', null, true);
                mForm.find("[name='client_id']").removeClass('readonly-disabled');
            }

            AdminUtils.showDelayedAfterLoad('.form-container');
        }
    }
}
