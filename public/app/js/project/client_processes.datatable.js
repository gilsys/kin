class ClientProcessesDatatable {

    constructor() {
        this.entity = 'client';
    }

    ready() {
        var clientId = $('#mt-client-form[data-client-id]').data('client-id')
        var routeDatatable = '/app/' + this.entity + '/processes/datatable/' + clientId;

        var routeEdit = '/app/process/form';
        var routeDelete = 'app/process/delete'

        var datatable = new CustomDatatable('#mt-client-processes', {
            ajax: {
                url: routeDatatable
            },
            columns: [
                // Añadimos todas las columnas con las que queremos trabajar
                { data: 'id', width: 60 },
                { data: 'name' },
                {
                    data: 'process_type',
                    render: function (data, type, full, meta) {
                        if (type == 'export') {
                            return full.process_type;
                        }

                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.process_type_color, 0.1) + '; color: ' + full.process_type_color + '">' + full.process_type + '</span>';
                    }
                },
                {
                    data: 'process_status',
                    render: function (data, type, full, meta) {
                        if (type == 'export') {
                            return full.process_status;
                        }

                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.process_status_color, 0.1) + '; color: ' + full.process_status_color + '">' + full.process_status + '</span>';
                    }
                },
                { data: 'total_tasks' },
                {
                    data: 'creator_fullname',
                    render: function (data, type, full, meta) {
                        if (type == 'export') {
                            return full.creator_fullname;
                        }

                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.creator_color, 0.1) + '; color: ' + full.creator_color + '">' + full.creator_fullname + '</span>';
                    }
                },
                { data: 'date_start' },
                { data: 'date_end' },
                { data: 'date_created' },
                { data: 'date_updated' },

                // Incluimos campos invisibles, útiles para filtros   
                { data: 'client_id', 'visible': false },
                { data: 'process_type_id', 'visible': false },
                { data: 'process_status_id', 'visible': false },
                { data: 'creator_user_id', 'visible': false },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center p-0',
                    width: 150,
                    render: function (data, type, full, meta) {
                        var mRouteEdit = routeEdit + '/' + data.id + '/' + clientId;
                        var btnEdit = '<a class="btn btn-icon btn-active-light btn-sm p-3" href="' + mRouteEdit + '" title="' + __('app.js.common.edit') + '"><i class="fa-regular fa-pen-to-square fs-1 pb-1"></i></a>';
                        var btnDelete = '<a class="btn btn-icon btn-active-light btn-sm p-3" href="javascript:AdminUtils.confirmDelete(\'' + routeDelete + '\', ' + data.id + ')" title="' + __('app.js.common.delete') + '"><i class="fa-regular fa-trash-can fs-1 pb-1"></i></a>';
                        return btnEdit + btnDelete;
                    }
                }

            ],
            initComplete: function () {
                datatable.advancedSearchForm();
            },
            order: [['id', 'desc']]
        });
    }
}
