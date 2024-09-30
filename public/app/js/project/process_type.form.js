class ProcessTypeForm {
    ready() {
        var mForm = $('#mt-process_type-form');
        var id = mForm.find("[name='id']");
        if (id.length) {
            id = id.val();
        }

        mForm.validate({
            ignore: ":not(:visible)",
            onkeyup: false,
        });

        var taskRepeater = mForm.find('.task-repeater').repeater({
            initEmpty: true,
            isFirstItemUndeletable: true,
            show: function () {
                $(this).slideDown();
            },
            hide: function (deleteElement) {
                $(this).slideUp(deleteElement);
            }
        });

        if (id.length) {
            $.post('/app/process_type/' + id, function (data) {
                mForm.find("[name='id']").val(data.id);

                mForm.find("[name='name']").val(data.name);
                mForm.find("[name='color']").val(data.color)[0].jscolor.fromString(data.color);

                taskRepeater.setList(data.json_data);


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
