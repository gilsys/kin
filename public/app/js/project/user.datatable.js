class UserDatatable {

    constructor() {
        this.entity = 'user';
    }

    changeStatus(q) {
        var statusId = $(q).is(':checked') ? 'V' : 'D';
        $.post('/app/' + this.entity + '/status/' + $(q).attr('data-id') + '/' + statusId);
    }

    ready() {
        const routeEdit = '/app/' + this.entity + '/form';
        const routeDatatable = '/app/' + this.entity + '/datatable';
        var datatable = new CustomDatatable('#mt-' + this.entity, {
            order: [['id', 'desc']],
            ajax: {
                url: routeDatatable
            },
            columns: [
                // Añadimos todas las columnas con las que queremos trabajar
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    width: 70,
                    render: function (data, type, full, meta) {
                        return '<div style="background-image: url(\'/app/user/avatar/' + full.id + addDateUpdatedTimestampParam(full) + '\')" class="mt-datatable-image"></div>';
                    }
                },
                { data: 'id', width: 60 },
                { data: 'wp_id', width: 60 },
                { data: 'name' },
                { data: 'surnames' },
                { data: 'nickname' },
                {
                    data: 'market_name',
                    render: function (data, type, full, meta) {
                        if(full.market_name == null || full.market_name == '') {
                            return '';
                        }
                        if (type == 'export') {
                            return full.market_name;
                        }
                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.market_color, 0.1) + '; color: ' + full.market_color + '">' + full.market_name + '</span>';
                    }
                },
                { data: 'email' },
                {
                    data: 'profile', 'visible': true, width: 110,
                    render: function (data, type, full, meta) {
                        // Realizamos un render especial para utilizar badges y mejorar la interfaz                        
                        return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.user_profile_color, 0.1) + '; color: ' + full.user_profile_color + '">' + __('table.user_profile.' + full.user_profile_id) + '</span>';
                    }
                    ,
                },
                {
                    data: 'status', width: 60,
                    render: function (data, type, full, meta) {
                        if (type == 'export') {
                            return __('table.user_status.' + full.user_status_id);
                        }

                        if (full.user_status_id == 'Z') {
                            return '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(full.user_status_color, 0.1) + '; color: ' + full.user_status_color + '">' + __('table.user_status.' + full.user_status_id) + '</span>';
                        }

                        // Realizamos un render especial para poder cambiar directamente de estado desde el listado
                        var checked = (full.user_status_id == 'V') ? 'checked="checked"' : '';
                        return '<div class="form-check form-switch form-check-custom form-check-solid"><input onchange="javascript:iUserDatatable.changeStatus(this)" class="form-check-input h-20px w-30px" ' + checked + ' type="checkbox" data-id="' + full.id + '"/></div>';
                    }
                },
                { data: 'last_login', 'visible': true },
                { data: 'date_created', 'visible': true },

                // Incluimos campos invisibles, útiles para filtros
                { data: 'user_status_id', 'visible': false },
                { data: 'user_profile_id', 'visible': false },
                { data: 'market_id', 'visible': false },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center p-0',
                    width: 150,
                    render: function (data, type, full, meta) {
                        // Mostramos los botones de acción del registro
                        var mRouteEdit = routeEdit + '/' + data.id;
                        var btnEdit = '<a class="btn btn-icon btn-active-light btn-sm p-3" href="' + mRouteEdit + '" title="' + __('app.js.common.edit') + '"><i class="fa-regular fa-pen-to-square fs-1 pb-1"></i></a>';

                        if (full.user_status_id == 'Z') {
                            return btnEdit;
                        }

                        return btnEdit;
                    }
                }

            ],
            initComplete: function () {
                datatable.advancedSearchForm();
            }
        });
    }
}