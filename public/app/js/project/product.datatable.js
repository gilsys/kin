class ProductDatatable {
    ready() {
        var routeEdit = '/app/product/form';
        var routeDelete = 'app/product/delete';
        var routeRestore = 'app/product/restore';
        var routeDatatable = '/app/product/datatable';

        var datatable = new CustomDatatable('#mt-product', {
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
                        return '<div style="background-image: url(\'/app/image/image_' + __('app.js.lang.code') + '_2/' + full.id + addDateUpdatedTimestampParam(full) + '\')" class="mt-datatable-image"></div>';
                    }
                },
                { data: 'name' },
                {
                    data: 'market_names',
                    orderable: false,
                    render: function (data, type, full, meta) {
                        var result = '';
                        for (var i = 0; i < data.length; i++) {
                            if (type == 'export') {
                                result += (result.length ? ', ' : '') + data[i];
                            } else {
                                result += '<span class="badge fw-lighter me-1 mb-1" style="background-color: ' + hexToRgbA(full.market_colors[i], 0.1) + '; color: ' + full.market_colors[i] + '">' + data[i] + '</span>';
                            }
                        }
                        return result;
                    }
                },
                { data: 'total_booklets', },
                { data: 'total_references', },
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
                            return '<a class="btn btn-icon btn-active-light btn-sm p-3 profile-allow profile-A" href="javascript:AdminUtils.confirmRestore(\'' + routeRestore + '\', ' + data.id + ')" title="' + __('app.js.common.restore') + '"><i class="fas fa-undo fs-1 pb-1"></i></a>';
                        }

                        var mRouteEdit = routeEdit + '/' + data.id;
                        var btnEdit = '<a class="btn btn-icon btn-active-light btn-sm p-3" href="' + mRouteEdit + '" title="' + __('app.js.common.edit') + '"><i class="fa-regular fa-pen-to-square fs-1 pb-1"></i></a>';
                        var btnDelete = '<a class="btn btn-icon btn-active-light btn-sm p-3 profile-allow profile-A" href="javascript:AdminUtils.confirmDelete(\'' + routeDelete + '\', ' + data.id + ')" title="' + __('app.js.common.delete') + '"><i class="fa-regular fa-trash-can fs-1 pb-1"></i></a>';

                        if (full.id == EMPTY_PRODUCT) {
                            btnDelete = '';
                        }

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
