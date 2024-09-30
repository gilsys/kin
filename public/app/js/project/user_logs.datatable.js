class UserLogsAuthDatatable {

    constructor() {
        this.entity = 'user';
    }

    ready() {
        var userId = $('#mt-user-form[data-user-id]').attr('data-user-id');
        var routeDatatable = '/app/' + this.entity + '/logs_auth/datatable/' + userId;
        var datatable = new CustomDatatable('#mt-user-logs-auth', {
            ajax: {
                url: routeDatatable
            },
            columns: [
                // Añadimos todas las columnas con las que queremos trabajar
                {
                    data: 'message',
                    render: function (data, type, full, meta) {
                        return full.message;
                    }
                },
                {
                    data: 'translation_id',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    width: 150,
                    render: function (data, type, full, meta) {
                        var e = {
                            'app.log.action.forgot_password.sent': { class: "info", text: "app.js.info" },
                            'app.log.action.forgot_password.pin_invalid': { class: "warning", text: "app.js.warning" },
                            'app.log.action.forgot_password.success': { class: "success", text: "app.js.success" },
                            'app.log.action.auth.logout': { class: "secondary", text: "app.js.info" },
                            'app.log.action.auth.success': { class: "success", text: "app.js.success" },
                            'app.log.action.auth.user_locked': { class: "error", text: "app.js.error" },
                            'app.log.action.auth.failed': { class: "warning", text: "app.js.warning" },
                            'app.log.action.user.email_password_sent': { class: "info", text: "app.js.info" },
                            'app.log.action.enter_password.success': { class: "success", text: "app.js.success" },
                            'app.log.action.pin.sent': { class: "info", text: "app.js.info" },
                            'app.log.action.pin.not_valid': { class: "warning", text: "app.js.warning" },
                            'app.log.action.pin.valid': { class: "success", text: "app.js.success" },
                        };

                        if (type == 'export') {
                            return __(e[full.translation_id].text);
                        }

                        return '<span class="badge badge-' + e[full.translation_id].class + ' fw-lighter">' + __(e[full.translation_id].text) + '</span>';

                    }
                },
                { data: 'ip' },
                {
                    data: 'device',
                    render: function (data, type, full, meta) {
                        return __('table.device.' + full.device);
                    }
                },
                { data: 'user_agent' },
                { data: 'date_created', className: 'text-center mx-0', width: 150 },
                { data: 'variables', 'visible': false }
            ],
            initComplete: function () {
                datatable.advancedSearchForm();
            },
            order: [['date_created', 'desc']]
        });
    }

}

class UserLogsDatatable {

    constructor() {
        this.entity = 'user';
    }

    ready() {
        var userId = $('#mt-user-form[data-user-id]').attr('data-user-id');
        var routeDatatable = '/app/' + this.entity + '/logs/datatable/' + userId;
        var datatable = new CustomDatatable('#mt-user-logs', {
            ajax: {
                url: routeDatatable
            },
            columns: [
                // Añadimos todas las columnas con las que queremos trabajar
                { data: 'message', name: 'message' },
                { data: 'date_created', name: 'date_created', className: 'text-center mx-0', width: 150 },
                { data: 'variables', name: 'variables', 'visible': false }
            ],
            initComplete: function () {
                datatable.advancedSearchForm();
            },
            order: [[1, 'desc']],
            exportColumns: [1, 2]
        });
    }
}