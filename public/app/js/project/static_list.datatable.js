class StaticListDatatable {
    constructor() {
        this.list = $('#mt-static-list').attr('data-list');
    }

    orderUp(id) {
        $.ajax({
            global: false,
            method: 'post',
            url: '/app/static_list/order/' + this.list + '/' + id + '/1',
            success: function () {
                $("#mt-static-list").DataTable().ajax.reload();
            }
        });
    }
    
    orderDown(id) {
        $.ajax({
            global: false,
            method: 'post',
            url: '/app/static_list/order/' + this.list + '/' + id + '/0',
            success: function () {
                $("#mt-static-list").DataTable().ajax.reload();
            }
        });
    }

    ready() {
        var routeEdit = '/app/static_list/form/' + this.list;
        var routeDatatable = '/app/static_list/datatable/' + this.list;
        var routeDelete = '/app/static_list/delete/' + this.list;

        var datatable = new CustomDatatable('#mt-static-list', {
            ajax: {
                url: routeDatatable
            },
            columns: [
                // Añadimos todas las columnas con las que queremos trabajar
                {data: 'id', width: 60},
                {data: 'name',
                    render: function (data, type, full, meta) {
                        // Traducir en caso de ser datos de tabla
                        if (full.name_translated != null) {
                            full.name = full.name_translated;
                        }

                        if (type == 'export') {
                            return full.name;
                        }
                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.color, 0.1) + '; color: ' + full.color + '">' + full.name + '</span>';
                    }
                },
                {data: 'custom_order', name: 'custom_order',
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
                        var btnUp = '<a class="btn btn-sm btn-clean btn-icon btn-icon-md btn-up" href="javascript:iStaticListDatatable.orderUp(\'' + full.id + '\')" title="' + __('app.js.common.up') + '"><i class="la la-angle-up"></i></a>';
                        var btnDown = '<a class="btn btn-sm btn-clean btn-icon btn-icon-md btn-down" href="javascript:iStaticListDatatable.orderDown(\'' + full.id + '\')" title="' + __('app.js.common.down') + '"><i class="la la-angle-down"></i></a>';
                        return btnUp + btnDown;
                    }
                },
                {data: 'name_translated', name: 'name_translated', visible: false},
                {data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center p-0',
                    width: 150,
                    render: function (data, type, full, meta) {
                        // Mostramos los botones de acción del registro
                        var mRouteEdit = routeEdit + '/' + data.id;
                        var btnEdit = '<a class="btn btn-icon btn-active-light btn-sm p-3" href="' + mRouteEdit + '" title="' + __('app.js.common.edit') + '"><i class="fa fa-edit fs-2 pb-1"></i></a>';
                        var btnDelete = '<a class="btn btn-icon btn-active-light btn-sm p-3" href="javascript:AdminUtils.confirmDelete(\'' + routeDelete + '\', ' + data.id + ')" title="' + __('app.js.common.delete') + '"><i class="fa fa-trash fs-2 pb-1"></i></a>';
                        return btnEdit + btnDelete;
                    }
                }

            ],
            initComplete: function () {
                datatable.advancedSearchForm();
            }
        });
    }
}