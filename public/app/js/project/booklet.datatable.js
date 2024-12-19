class BookletDatatable {
    ready() {
        var routeEdit = '/app/booklet/form';
        var routeDelete = 'app/booklet/delete';
        var routeDownload = '/app/booklet/pdf/file';
        var routeDatatable = '/app/booklet/datatable';

        var datatable = new CustomDatatable('#mt-booklet', {
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
                        if(data == null) {
                            return '';
                        }
                        if (type == 'export') {
                            return __(full.main_language);
                        }
                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.main_language_color, 0.1) + '; color: ' + full.main_language_color + '">' + __(full.main_language) + '</span>';
                    }
                },
                {
                    data: 'qr_language',
                    render: function (data, type, full, meta) {
                        if(data == null) {
                            return '';
                        }
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
                        var btnEdit = '<a class="btn btn-icon btn-active-light btn-sm p-3" href="' + mRouteEdit + '" title="' + __('app.js.common.edit') + '"><i class="fa-regular fa-pen-to-square fs-1 pb-1"></i></a>';
                        var btnDelete = '<a class="btn btn-icon btn-active-light btn-sm p-3" href="javascript:AdminUtils.confirmDelete(\'' + routeDelete + '\', ' + data.id + ')" title="' + __('app.js.common.delete') + '"><i class="fa-regular fa-trash-can fs-1 pb-1"></i></a>';
                        var btnDownload = data.last_file_id != null ? '<a class="btn btn-icon btn-active-light btn-sm p-3" href="' + routeDownload + '/' + data.last_file_id + '" target="_blank" title="' + __('app.js.common.download_last_pdf') + '"><i class="fa-regular fa-file fs-1 pb-1"></i></a>' : '';
                        return btnEdit + btnDelete + btnDownload;
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
