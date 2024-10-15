class BookletForm {
    tableConfigurations = {
        1: [
            ['1/2'],
            ['1/2']
        ],
        2: [
            ['1/3'],
            ['1/3'],
            ['1/3']
        ],
        3: [
            ['1/6', '1/6'],
            ['1/3'],
            ['1/3']
        ],
        4: [
            ['1/6', '1/6'],
            ['1/6', '1/6'],
            ['1/3']
        ],
        5: [
            ['1/6', '1/6'],
            ['1/3'],
            ['1/6', '1/6']
        ],
        6: [
            ['1/6', '1/6'],
            ['1/6', '1/6'],
            ['1/6', '1/6']
        ],
        7: [
            ['1/3'],
            ['1/6', '1/6'],
            ['1/3']
        ],
        8: [
            ['1/3'],
            ['1/6', '1/6'],
            ['1/6', '1/6']
        ],
        9: [
            ['1/3'],
            ['1/3'],
            ['1/6', '1/6']
        ]
    };

    products = [];

    ready() {
        var that = this;
        var mForm = $('#mt-booklet-form');
        var id = mForm.find("[name='id']");
        if (id.length) {
            id = id.val();
        }

        mForm.validate({
            ignore: ":not(:visible)",
            onkeyup: false
        });

        var stepper = new KTStepper(mForm.find('#mt-booklet-stepper')[0]);
        stepper.on("kt.stepper.click", function (stepper) {
            stepper.goTo(stepper.getClickedStepIndex());
        });

        $('.booklet-layout-select').on('change', function () {
            var tableContainer = $(this).closest('[data-booklet-page]').find('.booklet-table-container');
            if ($(this).val() != null && $(this).val() != '') {
                tableContainer.html(that.generateTable($(this).val()));
                that.getProducts();
            } else {
                tableContainer.html('');
            }
        })

        if (id.length) {
            $.post('/app/booklet/' + id, function (data) {

                mForm.find("[name='name']").val(data.name);

                mForm.find("[name='market_name']").val(data.market_name);
                mForm.find("[name='creator_name']").val(data.creator_name);

                mForm.find("[name='main_language_id']").val(data.main_language_id).change();
                mForm.find("[name='qr_language_id']").val(data.qr_language_id).change();
                mForm.find("[name='market_id']").val(data.market_id).change();

                mForm.find(".mt-date-created").val(formatDateWithTime(data.date_created));
                mForm.find(".mt-date-updated").val(formatDateWithTime(data.date_updated));

                AdminUtils.showDelayedAfterLoad();
            });
        } else {
            // Valores por defecto en registros nuevos            
            mForm.removeDisabledOptions();
            AdminUtils.showDelayedAfterLoad();
        }
    }

    generateTable(layoutId) {
        var mForm = $('#mt-booklet-form');
        var languageId = mForm.find("[name='main_language_id']").val();

        var config = this.tableConfigurations[layoutId];
        var tableHTML = '<table class="table table-bordered"><tbody>';

        config.forEach(row => {
            tableHTML += '<tr>';
            // Verificar si hay una sola columna en la fila, para aplicar colspan=2
            if (row.length === 1) {
                tableHTML += `<td colspan="2">
                                <select class="product-select form-control" required>
                                    <option value="">Seleccionar producto</option>`;
                products.forEach(product => {
                    tableHTML += `<option value="${product.name}" data-bg="${product.image[row[0]]}">${product.name}</option>`;
                });
                tableHTML += '</select></td>';
            } else {
                // En caso de múltiples celdas en la fila (sin colspan)
                row.forEach(col => {
                    tableHTML += `<td><select class="product-select form-control" required><option value="">Seleccionar producto</option>`;
                    products.forEach(product => {
                        tableHTML += `<option value="${product.name}" data-bg="${product.image[col]}">${product.name}</option>`;
                    });
                    tableHTML += '</select></td>';
                });
            }
            tableHTML += '</tr>';
        });

        tableHTML += '</tbody></table>';
        return tableHTML;
    }


    getProducts() {
        var mForm = $('#mt-booklet-form');

        var params = {
            'id': mForm.find("[name='id']").val(),
            'market_id': mForm.find("[name='market_id']").length > 0 ? mForm.find("[name='market_id']").val() : null
        };

        $.post('/app/booklet/get_products', params, function (data) {
            this.products = data;
        });
    }


}
