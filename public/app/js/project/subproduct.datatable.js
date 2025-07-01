class SubproductDatatable {
    ready() {
        var routeEdit = '/app/subproduct/form';
        var routeDelete = 'app/subproduct/delete';
        var routeRestore = 'app/subproduct/restore';
        var routeDatatable = '/app/subproduct/datatable';

        var datatable = new CustomDatatable('#mt-subproduct', {
            ajax: {
                url: routeDatatable
            },
            columns: [
                // Añadimos todas las columnas con las que queremos trabajar
                { data: 'id', width: 30 },
                { data: 'name' },
                { data: 'product_name' },
                { data: 'format' },
                { data: 'reference' },
                { data: 'date_created' },
                // Incluimos campos invisibles, útiles para filtros   
                { data: 'date_updated', 'visible': false },
                { data: 'product_id', 'visible': false },
                { data: 'subproduct_status', 'visible': false },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center p-0',
                    width: 150,
                    render: function (data, type, full, meta) {
                        if(full.subproduct_status == 'Z') {
                            return '<a class="btn btn-icon btn-active-light btn-sm p-3 profile-allow profile-A" href="javascript:AdminUtils.confirmRestore(\'' + routeRestore + '\', ' + data.id + ')" title="' + __('app.js.common.restore') + '"><i class="fas fa-undo fs-1 pb-1"></i></a>';
                        }

                        var mRouteEdit = routeEdit + '/' + data.id;
                        var btnEdit = '<a class="btn btn-icon btn-active-light btn-sm p-3" href="' + mRouteEdit + '" title="' + __('app.js.common.edit') + '"><i class="fa-regular fa-pen-to-square fs-1 pb-1"></i></a>';
                        var btnDelete = '<a class="btn btn-icon btn-active-light btn-sm p-3 profile-allow profile-A" href="javascript:AdminUtils.confirmDelete(\'' + routeDelete + '\', ' + data.id + ')" title="' + __('app.js.common.delete') + '"><i class="fa-regular fa-trash-can fs-1 pb-1"></i></a>';
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
