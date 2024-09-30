class LogDatatable {

    ready() {
        var entityId = $('#mt-log-form[data-entity-id]').data('entity-id');
        var entity = $('#mt-log-form[data-entity]').data('entity');
        var routeDatatable = '/app/' + entity + '/logs/datatable/' + entityId;
        var datatable = new CustomDatatable('#mt-log', {
            ajax: {
                url: routeDatatable
            },
            columns: [
                // Añadimos todas las columnas con las que queremos trabajar
                { data: 'message' },
                { data: 'user_fullname' },
                { data: 'date_created', className: 'text-center mx-0', width: 150 },
                { data: 'variables', 'visible': false }
            ],
            initComplete: function () {
                datatable.advancedSearchForm();
            },
            order: [[1, 'desc']],
            exportColumns: [1, 2]
        });
    }
}