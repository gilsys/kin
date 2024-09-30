class TaskForm {
    ready() {
        var mForm = $('#mt-task-form');
        var id = mForm.find("[name='id']");
        if (id.length) {
            id = id.val();
        }

        if (mForm.data('process-id')) {
            var processId = mForm.data('process-id');
            $('.btn-back').attr('href', '/app/process/form/' + processId)
        }

        mForm.validate({
            ignore: ":not(:visible)",
            onkeyup: false,
        });

        mForm.find(".select2-badge").each(function () {
            $(this).select2({
                templateResult: select2Badge,
                templateSelection: select2Badge,
            })
        });

        mForm.find("[name='task_is_extra']").change(function () {
            if ($(this).is(':checked')) {
                $(".payment-container").removeClass('d-none');
                $("[name='payment_status_id']").addClass('required');
                $(".payment-label").addClass('required');
            } else {
                $(".payment-container").addClass('d-none');
                $("[name='payment_status_id']").removeClass('required').val(null).change();
                $(".payment-label").removeClass('required');
            }
        });

        mForm.find("[name='date_task']").prop('readonly', true);

        var tagInput = document.querySelector("[name='tags']");
        let tagInputTagify = new Tagify(tagInput, {
            maxTags: 10,
            dropdown: {
                maxItems: 999999,           // <- mixumum allowed rendered suggestions
                classname: "tagify__inline__suggestions", // <- custom classname for this dropdown, so it could be targeted
                enabled: 0,             // <- show suggestions on focus
                closeOnSelect: false    // <- do not hide the suggestions dropdown once an item has been selected
            }
        });

        // Cambiar tags sugeridos al seleccionar proceso
        $('[name="process_id"]').on("change", function () {
            $.post('/app/task/tag_whitelist/' + $(this).val(), function (data) {
                tagInputTagify.whitelist = data;
            })
        });


        if (id.length) {
            $.post('/app/task/' + id, function (data) {
                mForm.find("[name='id']").val(data.id);

                tagInputTagify.whitelist = data.suggestedTags;
                tagInputTagify.addTags(data.tags);

                mForm.find("[name='task_type_id']").val(data.task_type_id).change();
                mForm.find("[name='task_status_id']").val(data.task_status_id).change();
                if (data.payment_status_id) {
                    mForm.find("[name='task_is_extra']").prop('checked', true).change();
                }
                mForm.find("[name='payment_status_id']").val(data.payment_status_id).change();
                mForm.find("[name='process_id']").val(data.process_id).change();
                mForm.find("[name='hours']").val(data.hours);
                mForm.find("[name='description']").val(data.description);
                mForm.find("[name='date_task']").datetimepicker('update', data.date_task);
                mForm.find("[name='fullname']").val(data.fullname);

                mForm.find(".mt-date-created").val(formatDateWithTime(data.date_created));
                mForm.find(".mt-date-updated").val(formatDateWithTime(data.date_updated));

                mForm.find("[name='task_type_id']").addClass('readonly-disabled');
                mForm.find("[name='process_id']").addClass('readonly-disabled');

                AdminUtils.showDelayedAfterLoad('.form-container');
            });
        } else {
            // Valores por defecto en registros nuevos     
            if (processId) {
                mForm.find("[name='process_id']").val(processId).change();
                mForm.find("[name='process_id']").addClass('readonly-disabled');

            } else {
                mForm.find("[name='process_id']").removeClass('readonly-disabled');
            }
            mForm.removeDisabledOptions();
            AdminUtils.showDelayedAfterLoad('.form-container');
        }
    }
}
