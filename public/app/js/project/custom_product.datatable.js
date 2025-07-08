class CustomProductDatatable {
    ready() {
        var routeEdit = '/app/custom_product/form';
        var routeDelete = 'app/custom_product/delete';
        var routeRestore = 'app/custom_product/restore';
        var routeDatatable = '/app/custom_product/datatable';

        var datatable = new CustomDatatable('#mt-custom_product', {
            ajax: {
                url: routeDatatable
            },
            columns: [
                // Añadimos todas las columnas con las que queremos trabajar
                { data: 'id', width: 30 },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    width: 150,
                    export: false,
                    render: function (data, type, full, meta) {
                        // Mostramos la imagen en la tabla
                        return '<div style="background-image: url(\'/app/image/image_es_2/' + full.id + addDateUpdatedTimestampParam(full) + '\')" class="mt-datatable-image"></div>';
                    }
                },
                { data: 'name' },
                { data: 'market_names', 'visible': false},
                { data: 'total_booklets', 'visible': false },
                { data: 'total_references', 'visible': false },
                { data: 'date_created', },
                // Incluimos campos invisibles, útiles para filtros   
                { data: 'market_ids', 'visible': false },
                { data: 'product_status', 'visible': false },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center p-0',
                    width: 150,
                    render: function (data, type, full, meta) {
                        if(full.product_status == 'Z') {
                            return '<a class="btn btn-icon btn-active-light btn-sm p-3" href="javascript:AdminUtils.confirmRestore(\'' + routeRestore + '\', ' + data.id + ')" title="' + __('app.js.common.restore') + '"><i class="fas fa-undo fs-1 pb-1"></i></a>';
                        }

                        var mRouteEdit = routeEdit + '/' + data.parent_product_id + '/' + data.id;
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
