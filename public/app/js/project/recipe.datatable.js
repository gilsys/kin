class RecipeDatatable {
    ready() {
        var routeEdit = '/app/recipe/form';
        var routeDelete = 'app/recipe/delete';
        var routeDuplicate = 'app/recipe/duplicate';
        var routeDownload = '/app/recipe/pdf/file';
        var routeDatatable = '/app/recipe/datatable';

        var datatable = new CustomDatatable('#mt-recipe', {
            ajax: {
                url: routeDatatable
            },
            columns: [
                // Añadimos todas las columnas con las que queremos trabajar
                { data: 'id', width: 30 },
                { data: 'name' },
                { data: 'creator_name', 'visible': userHasProfile(['A']) },
                {
                    data: 'market_name',
                    render: function (data, type, full, meta) {
                        if(data == null) {
                            return '';
                        }
                        if (type == 'export') {
                            return full.market_name;
                        }
                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.market_color, 0.1) + '; color: ' + full.market_color + '">' + full.market_name + '</span>';
                    }
                },
                {
                    data: 'main_language',
                    render: function (data, type, full, meta) {
                        if (type == 'export') {
                            return __(full.main_language);
                        }
                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.main_language_color, 0.1) + '; color: ' + full.main_language_color + '">' + __(full.main_language) + '</span>';
                    }
                },
                {
                    data: 'qr_language',
                    render: function (data, type, full, meta) {
                        if (type == 'export') {
                            return __(full.qr_language);
                        }
                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.qr_language_color, 0.1) + '; color: ' + full.qr_language_color + '">' + __(full.qr_language) + '</span>';
                    }
                },
                { data: 'date_created' },
                // Incluimos campos invisibles, útiles para filtros
                { data: 'main_language_id', 'visible': false },
                { data: 'qr_language_id', 'visible': false },
                { data: 'market_id', 'visible': false },
                { data: 'creator_user_id', 'visible': false },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center p-0',
                    width: 150,
                    render: function (data, type, full, meta) {
                        var mRouteEdit = routeEdit + '/' + data.id;
                        var btnEdit = '<a class="btn btn-icon btn-active-light btn-sm p-3" href="' + mRouteEdit + '" title="' + (data.editable == 1 ? __('app.js.common.edit') : __('app.js.common.view')) + '"><i class="fa-regular ' + (data.editable == 1 ? 'fa-pen-to-square' : 'fa-eye') + ' fs-1 pb-1"></i></a>';
                        var btnDelete = data.editable == 1 ? '<a class="btn btn-icon btn-active-light btn-sm p-3" href="javascript:AdminUtils.confirmDelete(\'' + routeDelete + '\', ' + data.id + ')" title="' + __('app.js.common.delete') + '"><i class="fa-regular fa-trash-can fs-1 pb-1"></i></a>' : '';
                        var btnDuplicate = '<a class="btn btn-icon btn-active-light btn-sm p-3" href="javascript:AdminUtils.confirmDuplicate(\'' + routeDuplicate + '\', ' + data.id + ')" title="' + __('app.js.common.duplicate') + '"><i class="fa-regular fa-copy fs-1 pb-1"></i></a>';
                        var btnDownload = data.last_file_id != null ? '<a class="btn btn-icon btn-active-light btn-sm p-3" href="' + routeDownload + '/' + data.last_file_id + '" target="_blank" title="' + __('app.js.common.download_last_pdf') + '"><i class="fa-regular fa-file fs-1 pb-1"></i></a>' : '';
                        return btnEdit + btnDelete + btnDuplicate + btnDownload;
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
