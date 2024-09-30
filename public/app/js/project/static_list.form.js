class StaticListForm {
    ready() {
        var mForm = $('#mt-static-list-form');
        var id = mForm.find("[name='id']");
        if (id.length) {
            id = id.val();
        }

        mForm.validate();
        if (id.length) {
            $.post('/app/static_list/' + mForm.attr('data-list') + '/' + id, function (data) {
                mForm.find("[name='id']").val(data.id);
                // Tratar nombres no editables
                if (data.name.startsWith('table.')) {
                    mForm.find("[name='name']").attr('disabled', 'disabled').val(__(data.name));
                    mForm.find("#breadcumb-name").text(__(data.name));
                } else {
                    mForm.find("[name='name']").val(data.name);
                    mForm.find("#breadcumb-name").text(data.name);
                }

                mForm.find("[name='color']").val(data.color)[0].jscolor.fromString(data.color);
                mForm.find(".mt-date-created").val(formatDateWithTime(data.date_created));
                mForm.find(".mt-date-updated").val(formatDateWithTime(data.date_updated));

                AdminUtils.showDelayedAfterLoad();
            });
        } else {
            AdminUtils.showDelayedAfterLoad();
        }
    }
}