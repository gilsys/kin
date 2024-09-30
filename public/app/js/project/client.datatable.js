class ClientDatatable {
    ready() {
        var routeEdit = '/app/client/form';
        var routeDelete = 'app/client/delete'
        var routeDatatable = '/app/client/datatable';

        var datatable = new CustomDatatable('#mt-client', {
            ajax: {
                url: routeDatatable
            },
            columns: [
                // Añadimos todas las columnas con las que queremos trabajar
                { data: 'id', width: 60 },
                {
                    data: 'client_type', width: 110,
                    render: function (data, type, full, meta) {
                        // Realizamos un render especial para utilizar badges y mejorar la interfaz                        
                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.client_type_color, 0.1) + '; color: ' + full.client_type_color + '">' + data + '</span>';
                    }
                },
                { data: 'client_name' },
                { data: 'total_processes' },
                {
                    data: 'phone',
                    render: function (data, type, full, meta) {
                        var phones = '';
                        if (!full.phone) {
                            return '';
                        }
                        JSON.parse(full.phone).forEach(function (item) {
                            if (type == 'export' && phones.length) {
                                phones += ', ';
                            }
                            phones += (item.name.length) ? '<span data-bs-toggle="tooltip" class="d-block" title="' + item.name + '">' + item.phone + '</span>' : '<span class="d-block">' + item.phone + '</span>';
                        });

                        return phones;
                    }
                },
                {
                    data: 'email',
                    render: function (data, type, full, meta) {
                        if (!data) {
                            return data;
                        }
                        var emails = '';
                        JSON.parse(full.email).forEach(function (item) {
                            if (type == 'export' && emails.length) {
                                emails += ', ';
                            }
                            emails += (item.name.length) ? '<span data-bs-toggle="tooltip" class="d-block" title="' + item.name + '">' + item.email + '</span>' : '<span class="d-block">' + item.email + '</span>';
                        });

                        return emails;
                    }
                },
                { data: 'country_name', 'visible': false },
                { data: 'date_created' },

                // Incluimos campos invisibles, útiles para filtros                                
                { data: 'client_type_id', 'visible': false },
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
