class ProcessTypeDatatable {

    orderUp(id) {
        $.ajax({
            global: false,
            method: 'post',
            url: '/app/process_type/order/' + id + '/1',
            success: function () {
                $("#mt-process-type").DataTable().ajax.reload();
            }
        });
    }

    orderDown(id) {
        $.ajax({
            global: false,
            method: 'post',
            url: '/app/process_type/order/' + id + '/0',
            success: function () {
                $("#mt-process-type").DataTable().ajax.reload();
            }
        });
    }

    ready() {

        var routeEdit = '/app/process_type/form';
        var routeDelete = '/app/process_type/delete'
        var routeDatatable = '/app/process_type/datatable';

        var datatable = new CustomDatatable('#mt-process-type', {
            ajax: {
                url: routeDatatable
            },
            columns: [
                // Añadimos todas las columnas con las que queremos trabajar
                { data: 'id', width: 60 },
                {
                    data: 'name',
                    render: function (data, type, full, meta) {
                        if (type == 'export') {
                            return full.name;
                        }

                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.color, 0.1) + '; color: ' + full.color + '">' + full.name + '</span>';
                    }
                },
                { data: 'date_created' },
                { data: 'date_updated' },
                {
                    data: 'custom_order', name: 'custom_order',
                    orderable: true,
                    searchable: false,
                    className: 'text-center',
                    export: false,
                    width: 150,
                    render: function (data, type, full, meta) {
                        // No permitir ordenar si no se están mostrando todos los elementos
                        if (meta.settings._iRecordsDisplay != meta.settings._iRecordsTotal) {
                            return "-";
                        }

                        // Mostramos los botones de acción del registro
                        var btnUp = '<a class="btn btn-sm btn-clean btn-icon btn-icon-md btn-up" href="javascript:iProcessTypeDatatable.orderUp(\'' + full.id + '\')" title="' + __('app.js.common.up') + '"><i class="la la-angle-up"></i></a>';
                        var btnDown = '<a class="btn btn-sm btn-clean btn-icon btn-icon-md btn-down" href="javascript:iProcessTypeDatatable.orderDown(\'' + full.id + '\')" title="' + __('app.js.common.down') + '"><i class="la la-angle-down"></i></a>';
                        return btnUp + btnDown;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center p-0',
                    width: 150,
                    render: function (data, type, full, meta) {
                        var mRouteEdit = routeEdit + '/' + data.id;
                        var btnEdit = '<a class="btn btn-icon btn-active-light btn-sm p-3" href="' + mRouteEdit + '" title="' + __('app.js.common.edit') + '"><i class="fa-regular fa-pen-to-square fs-1 pb-1"></i></a>';
                        var btnDelete = '<a class="btn btn-icon btn-active-light btn-sm p-3" href="javascript:AdminUtils.confirmDelete(\'' + routeDelete + '\', ' + data.id + ')" title="' + __('app.js.common.delete') + '"><i class="fa-regular fa-trash-can fs-1 pb-1"></i></a>';
                        return btnEdit + btnDelete;
                    }
                }

            ],
            initComplete: function () {
                datatable.advancedSearchForm();
            },
            order: [['custom_order', 'asc']]
        });
    }
}
