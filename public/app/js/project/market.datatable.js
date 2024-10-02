class MarketDatatable {
    ready() {
        var routeEdit = '/app/market/form';
        var routeDelete = 'app/market/delete'
        var routeDatatable = '/app/market/datatable';

        var datatable = new CustomDatatable('#mt-market', {
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
                {
                    data: 'area_names',
                    render: function (data, type, full, meta) {
                        if (full.area_names === null) {
                            return '';
                        }
                        var result = '';
                        
                        if (type == 'export') {
                            if (full.area_names != null) {
                                for (let area of full.area_names.split(", ")) {
                                    let areaInfo = area.split("|");
                                    result += areaInfo[0] + ", ";
                                }                                
                                if (result.length > 0) {
                                    result = result.slice(0, -2);
                                }
                                return result;
                            }
                        }

                        
                        if (full.area_names != null) {
                            for (var area of full.area_names.split(", ")) {
                                var areaInfo = area.split("|");
                                result += '<span class="badge fw-lighter my-2 mx-1" style="background-color: ' + hexToRgbA(areaInfo[1], 0.1) + '; color: ' + areaInfo[1] + '">' + areaInfo[0] + '</span>';
                            }
                        }
                        return result;
                    }
                },
                {
                    data: 'main_language',
                    render: function (data, type, full, meta) {
                        if (type == 'export') {
                            return full.main_language;
                        }
                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.main_language_color, 0.1) + '; color: ' + full.main_language_color + '">' + full.main_language + '</span>';
                    }
                },
                {
                    data: 'qr_language',
                    render: function (data, type, full, meta) {
                        if (type == 'export') {
                            return full.qr_language;
                        }
                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.qr_language_color, 0.1) + '; color: ' + full.qr_language_color + '">' + full.qr_language + '</span>';
                    }
                },
                { data: 'total_users' },
                { data: 'date_created' },

                // Incluimos campos invisibles, útiles para filtros   
                { data: 'area_ids', 'visible': false },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center p-0',
                    width: 150,
                    render: function (data, type, full, meta) {
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