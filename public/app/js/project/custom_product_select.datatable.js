class CustomProductSelectDatatable {
    ready() {
        var routeAdd = '/app/custom_product/form';
        var routeDatatable = '/app/custom_product/datatable/select';

        var datatable = new CustomDatatable('#mt-custom_product_select', {
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
                        var mRouteAdd = routeAdd + '/' + data.id;
                        return '<a class="btn btn-icon btn-active-light btn-sm p-3" href="' + mRouteAdd + '" title="' + __('app.js.common.select') + '"><i class="fas fa-arrow-right fs-1 pb-1"></i></a>';
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
