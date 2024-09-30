class TaskDatatable {

    constructor() {
        this.processEntity = 'process';
    }

    ready() {

        var routeEdit = '/app/task/form';
        var routeDelete = 'app/task/delete'
        var routeDatatable = '/app/task/datatable';
        var userProfile = $('body').data('profile-id');

        if ($('.process-tasks').length) {
            var processId = $('.process-tasks[data-process-id]').data('process-id')
            routeDatatable += '/' + processId;
            $('.btn-add').attr('href', '/app/task/form/process/' + processId)
        }

        var datatable = new CustomDatatable('#mt-task', {
            ajax: {
                url: routeDatatable
            },
            columns: [
                // Añadimos todas las columnas con las que queremos trabajar
                { data: 'id', width: 60 },
                { data: 'process_name' },
                {
                    data: 'tags',
                    render: function (data, type, full, meta) {
                        if (type == 'export') {
                            return full.tags;
                        }
                        if (!data) return;
                        var tags = data.split(', ');
                        for (var i = 0; i < tags.length; i++) {
                            tags[i] = '<span class="badge badge-light fw-normal">' + tags[i] + '</span>'
                        }

                        return tags.join('&nbsp');
                    }
                },
                { data: 'description', 'visible': false },
                {
                    data: 'task_type',
                    render: function (data, type, full, meta) {
                        if (type == 'export') {
                            return full.task_type;
                        }

                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.task_type_color, 0.1) + '; color: ' + full.task_type_color + '">' + full.task_type + '</span>';
                    }
                },
                {
                    data: 'task_status',
                    render: function (data, type, full, meta) {
                        if (type == 'export') {
                            return full.task_status;
                        }

                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.task_status_color, 0.1) + '; color: ' + full.task_status_color + '">' + full.task_status + '</span>';
                    }
                },
                {
                    data: 'payment_status',
                    render: function (data, type, full, meta) {
                        if (!data) return '';
                        if (type == 'export') {
                            return full.payment_status;
                        }

                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.payment_status_color, 0.1) + '; color: ' + full.payment_status_color + '">' + full.payment_status + '</span>';
                    },
                    'defaultContent': '',
                    'visible': userProfile == 'A',
                },
                { data: 'hours' },
                {
                    data: 'creator_fullname',
                    render: function (data, type, full, meta) {
                        if (type == 'export') {
                            return full.creator_fullname;
                        }

                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.creator_color, 0.1) + '; color: ' + full.creator_color + '">' + full.creator_fullname + '</span>';
                    }
                },
                { data: 'date_task' },
                { data: 'date_created' },

                // Incluimos campos invisibles, útiles para filtros   
                { data: 'process_id', 'visible': false },
                { data: 'task_type_id', 'visible': false },
                { data: 'task_status_id', 'visible': false },
                { data: 'payment_status_id', 'visible': false, 'defaultContent': '' },
                { data: 'creator_user_id', 'visible': false },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center p-0',
                    width: 150,
                    render: function (data, type, full, meta) {
                        var mRouteEdit = routeEdit + '/' + data.id;
                        if (typeof processId !== "undefined") {
                            mRouteEdit += '/' + processId;
                        }
                        var btnEdit = '<a class="btn btn-icon btn-active-light btn-sm p-3" href="' + mRouteEdit + '" title="' + __('app.js.common.edit') + '"><i class="fa-regular fa-pen-to-square fs-1 pb-1"></i></a>';
                        var btnDelete = '<a class="btn btn-icon btn-active-light btn-sm p-3" href="javascript:AdminUtils.confirmDelete(\'' + routeDelete + '\', ' + data.id + ')" title="' + __('app.js.common.delete') + '"><i class="fa-regular fa-trash-can fs-1 pb-1"></i></a>';
                        return btnEdit + btnDelete;
                    }
                }

            ],
            initComplete: function () {
                datatable.advancedSearchForm();
            },
            order: [['id', 'desc']],
            exportColumns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
        });
    }
}
