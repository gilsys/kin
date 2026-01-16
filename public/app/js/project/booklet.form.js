class BookletForm {
    tableConfigurations = {
        1: [
            ['2'],
            ['2']
        ],
        2: [
            ['3'],
            ['3'],
            ['3']
        ],
        3: [
            ['6', '6'],
            ['3'],
            ['3']
        ],
        4: [
            ['6', '6'],
            ['6', '6'],
            ['3']
        ],
        5: [
            ['6', '6'],
            ['3'],
            ['6', '6']
        ],
        6: [
            ['6', '6'],
            ['6', '6'],
            ['6', '6']
        ],
        7: [
            ['3'],
            ['6', '6'],
            ['3']
        ],
        8: [
            ['3'],
            ['6', '6'],
            ['6', '6']
        ],
        9: [
            ['3'],
            ['3'],
            ['6', '6']
        ]
    };

    products = [];
    selectedProducts = {};

    ready() {
        var that = this;
        var mForm = $('#mt-booklet-form');
        var id = mForm.find("[name='id']");
        if (id.length) {
            id = id.val();
        }

        var startStep = 1;
        if (localStorage.getItem('active_booklet_tab') != null) {
            startStep = localStorage.getItem('active_booklet_tab');
            localStorage.removeItem('active_booklet_tab');
        }

        var stepper = new KTStepper(mForm.find('#mt-booklet-stepper')[0], { startIndex: startStep });

        mForm.validate({
            ignore: "form:not(.data-validate-all) [allow-save-invalid].empty-value, .form-disabled-section :input",
            onkeyup: false,
            rules: {
                cover_file_id: {
                    required: true
                },
                cover_type: {
                    required: true
                },
                'page_type[2]': {
                    required: true
                }
            },
            messages: {
                cover_file_id: {
                    required: __('app.js.image_required')
                }
            },
            invalidHandler: function (event, validator) {
                stepperInvalidFormValidationHandler(validator, stepper);
            },
            submitHandler: function (form) {
                if (jQuery.inArray($(form).attr('action').split('/').pop(), ['N', 'B']) === -1) {
                    localStorage.setItem('active_booklet_tab', stepper.getCurrentStepIndex());
                }
                return true;
            }
        });

        mForm.find('[allow-save-invalid]').change(function () {
            allowSaveInvalidCheckEmpty($(this));
        });

        function changeStep(index, stepper) {
            mForm.addClass('data-validate-all');
            var languageValid = !mForm.find("[name='main_language_id']").is(':visible') || mForm.find("[name='main_language_id']").valid();
            var marketValid = !mForm.find("[name='market_id']").is(':visible') || mForm.find("[name='market_id']").valid();
            mForm.removeClass('data-validate-all');

            if (!languageValid || !marketValid) {
                return;
            }

            stepper.goTo(index);
        }

        stepper.on("kt.stepper.click", function (stepper) {
            changeStep(stepper.getClickedStepIndex(), stepper);
        });
        stepper.on("kt.stepper.next", function (stepper) {
            changeStep(stepper.getNextStepIndex(), stepper);
        });
        stepper.on("kt.stepper.previous", function (stepper) {
            changeStep(stepper.getPreviousStepIndex(), stepper);
        });

        mForm.find("[name='market_id']").on('change', function () {
            that.getProducts();
        });

        mForm.find("[name='main_language_id']").on('change', function () {
            mForm.find('select.booklet-product-select').trigger('change.select2');

            if (mForm.find("[name='main_language_id']").val() == null || mForm.find("[name='main_language_id']").val() == '') {
                return;
            }

            mForm.find('[name="cover_file_id"]').each(function () {
                $(this).closest('.image-select').find('label').html('<img src="/app/booklet/cover/' + $(this).val() + '/' + mForm.find("[name='main_language_id']").val() + '?v=' + RESOURCES_VERSION + '" class="rounded">');
            });

            mForm.find('.cover-source-download').attr('href', '/app/booklet/cover_file' + '/' + $(this).val());
        });

        mForm.find('select.booklet-layout-select').on('change', function () {
            var tableContainer = $(this).closest('[data-booklet-page]').find('.booklet-table-container');
            var page = $(this).closest('[data-booklet-page]').attr('data-booklet-page');
            $(this).parent().find('.booklet-layouts [data-booklet-layout]').removeClass('active');
            if ($(this).val() != null && $(this).val() != '') {
                that.generateTable(page, $(this).val(), tableContainer);
                that.selectProducts(page);
                $(this).parent().find('.booklet-layouts [data-booklet-layout="' + $(this).val() + '"]').addClass('active');
            } else {
                tableContainer.html('');
            }
        });

        mForm.find('.booklet-layouts [data-booklet-layout]').on('click', function () {
            var bookletLayouts = $(this).closest('.booklet-layouts');

            if ($(this).hasClass('active')) {
                $(this).removeClass('active');
            } else {
                bookletLayouts.find('.active').removeClass('active');
                $(this).addClass('active');
            }

            var currentLayout = bookletLayouts.find('.active').length > 0 ? bookletLayouts.find('.active').attr('data-booklet-layout') : '';
            bookletLayouts.parent().find('select.booklet-layout-select').val(currentLayout).change();
        });

        mForm.find('[name="cover_type"]').on('change', function () {
            var optionSelected = $('[name="cover_type"]:checked').val() ?? '';
            mForm.find('.cover-select-container').toggleClass('form-disabled-section', optionSelected != 'select');
            mForm.find('.cover-upload-container').toggleClass('form-disabled-section', optionSelected != 'upload');
        });

        mForm.find('[name^="page_type"]').on('change', function () {
            var optionSelected = $(this).closest('.form-item').find('[name^="page_type"]:checked').val() ?? '';
            $(this).closest('[data-booklet-page').find('.page-cover-container').toggleClass('form-disabled-section', optionSelected != 'cover');
            $(this).closest('[data-booklet-page').find('.page-products-container').toggleClass('form-disabled-section', optionSelected != 'products');
        });

        initFileVersionsList(mForm, '/app/booklet/pdf/delete/');

        if (id.length) {
            $.post('/app/booklet/' + id, function (data) {
                data.booklet_products.forEach(function (product) {
                    if (typeof that.selectedProducts[product.page] == 'undefined') {
                        that.selectedProducts[product.page] = [];
                    }
                    that.selectedProducts[product.page].push({ id: product.product_id, order: product.custom_order });
                });

                mForm.find("[name='name']").val(data.name);

                $('#kt_app_toolbar_container h2').text(data.name);

                mForm.find("[name='market_name']").val(data.market_name);
                mForm.find("[name='creator_name']").val(data.creator_name);

                mForm.find("[name='main_language_id']").val(data.main_language_id).change();
                mForm.find("[name='qr_language_id']").val(data.qr_language_id).change();

                if (mForm.find("[name='page_type[2]']").length > 0) {
                    if (data.page2_booklet_layout_id) {
                        mForm.find("[name='page_type[2]'][value='products']").prop('checked', true).change();
                    } else if(data.cover_file_id) {
                        mForm.find("[name='page_type[2]'][value='cover']").prop('checked', true).change();
                    } else {
                        mForm.find("[name='page_type[2]']").change();
                    }
                }

                mForm.find("[name='page2_booklet_layout_id']").val(data.page2_booklet_layout_id).trigger('change.select2');
                mForm.find("[name='page3_booklet_layout_id']").val(data.page3_booklet_layout_id).trigger('change.select2');
                mForm.find("[name='page4_booklet_layout_id']").val(data.page4_booklet_layout_id).trigger('change.select2');

                mForm.find("[name='market_id']").val(data.market_id).change();

                if (data.cover_file_id != null) {
                    var coverType = data.cover_file_type_id == 'BU' ? 'upload' : 'select';
                    mForm.find('[name="cover_type"][value="' + coverType + '"]').prop('checked', true).change();

                    if (coverType == 'select') {
                        mForm.find('[name="cover_file_id"][value="' + data.cover_file_id + '"]').prop('checked', true).change();
                    } else {
                        mForm.find('#cover-upload input[type="file"]').setImageUploaded('/app/booklet/cover/' + data.id + addDateUpdatedTimestampParam(data), false);
                    }
                } else {
                    mForm.find('[name="cover_type"]').change();
                }

                if (!userHasProfile(['A'])) {
                    that.getProducts();
                }

                mForm.find(".mt-date-created").val(formatDateWithTime(data.date_created));
                mForm.find(".mt-date-updated").val(formatDateWithTime(data.date_updated));

                mForm.find("[allow-save-invalid]:not([name='market_id']):not([name='main_language_id']):not([name='qr_language_id']):not(.booklet-layout-select):not(.booklet-product-select)").change();

                AdminUtils.showDelayedAfterLoad();
            });
        } else {
            if (mForm.attr('data-default-market-id') != '') {
                $.post('/app/market/' + mForm.attr('data-default-market-id'), function (data) {
                    mForm.find("[name='market_name']").val(data.name);
                    mForm.find("[name='main_language_id']").val(data.main_language_id).change();
                    mForm.find("[name='qr_language_id']").val(data.qr_language_id).change();

                    mForm.removeDisabledOptions();
                    mForm.find('[allow-save-invalid]').change();
                    AdminUtils.showDelayedAfterLoad();
                });
            } else {
                mForm.removeDisabledOptions();
                mForm.find('[allow-save-invalid]').change();
                AdminUtils.showDelayedAfterLoad();
            }

            // Valores por defecto en registros nuevos        
            if (!userHasProfile(['A'])) {
                that.getProducts();
            }

            mForm.find('[name="cover_type"]').change();
            mForm.find('[name^="page_type"]').change();
        }
    }

    generateTable(page, layoutId, container) {
        var config = this.tableConfigurations[layoutId];
        var tableHTML = '<table class="table table-bordered"><tbody>';

        var order = 0;
        var trHeight = 100 / config.length;
        config.forEach(row => {
            tableHTML += '<tr style="height: ' + trHeight + '%">';
            if (row.length === 1) {
                order++;
                tableHTML += `<td colspan="2">` + this.getSelectProductHtml(page, order, row[0]) + `</td>`;
            } else {
                row.forEach(col => {
                    order++;
                    tableHTML += `<td>` + this.getSelectProductHtml(page, order, col) + `</td>`;
                });
            }
            tableHTML += '</tr>';
        });

        tableHTML += '</tbody></table>';

        container.html(tableHTML);

        function templateSelect2(data) {
            if (!data.id) {
                return data.text;
            }

            var customHtml = '';
            if (that.products['_' + data.id].is_custom == '1') {
                customHtml = '<span class="badge badge-primary fw-lighter ms-2">' + __('app.js.product.custom') + '</span>';
            }
            return $('<span>' + data.text + customHtml + '</span>');
        }

        function initSelect2Products(item) {
            var settings = {
                language: __('app.js.lang.code'),
                placeholder: item.attr('data-placeholder'),
                allowClear: true,
                templateResult: templateSelect2,
                templateSelection: templateSelect2
            }

            item.select2(settings);
        }

        var that = this;
        container.find('select.booklet-product-select').each(function () {
            initSelect2Products($(this));

            $(this).on('select2:selecting', function (e) {
                var mForm = $('#mt-booklet-form');
                var selectedValue = e.params.args.data.id;

                var exists = mForm.find('select.booklet-product-select').filter(function () {
                    return $(this).val() === selectedValue;
                }).length > 0;

                if (exists && selectedValue != EMPTY_PRODUCT) {
                    e.preventDefault();
                    showWarning(__('app.js.common.attention'), __('app.js.booklet.product_already_selected'));
                }
            });

            $(this).on('change.select2', function () {
                var mForm = $('#mt-booklet-form');
                var languageId = mForm.find("[name='main_language_id']").val();
                var imageContainer = $(this).closest('td').find('.booklet-product-image');

                if ($(this).val() != null && $(this).val() != '') {
                    var product = that.products['_' + $(this).val()];
                    imageContainer.css('background-image', 'url("/app/image/image_' + (product.is_custom == '1' ? 'custom' : languageId) + '_' + $(this).attr('data-display-mode') + '/' + product.id + addDateUpdatedTimestampParam(product) + '")');
                } else {
                    imageContainer.css('background-image', 'none');
                }
            });

            $(this).on('change', function () {
                var page = $(this).closest('[data-booklet-page]').attr('data-booklet-page');
                that.selectedProducts[page] = [];
                $(this).closest('[data-booklet-page]').find('select.booklet-product-select').each(function () {
                    that.selectedProducts[page].push({ id: $(this).val(), order: $(this).attr('data-order') });
                });
            });

            $(this).on('select2:open', function () {
                setTimeout(() => {
                    var mForm = $('#mt-booklet-form');
                    var selectedValues = mForm.find('select.booklet-product-select').not($(this)).map(function () {
                        return $(this).val();
                    }).get();

                    $('.select2-results__option').each(function () {
                        var optionValue = $(this).attr('data-select2-id').split('-').pop();
                        $(this).toggleClass('selected-other-select', selectedValues.includes(optionValue) && optionValue != EMPTY_PRODUCT);
                    });
                }, 0);
            });
        });

        container.find('[allow-save-invalid]').each(function () {
            $(this).on('change', function () {
                allowSaveInvalidCheckEmpty($(this));
            });
            allowSaveInvalidCheckEmpty($(this));
        })
    }

    getSelectProductHtml(page, order, displayMode) {
        var html = `<div class="d-flex flex-column h-100"><select name="booklet_product[` + page + `][` + order + `][` + displayMode + `]" data-order="` + order + `" data-display-mode="` + displayMode + `" data-control="select2" data-placeholder="` + __('app.js.common.select_value') + `" class="form-select kt-select2 booklet-product-select" required allow-save-invalid>
                        <option disabled selected value>` + __('app.js.common.select_value') + `</option>`;
        Object.values(this.products).forEach(product => {
            html += `<option value="` + product.id + `">` + product.name + `</option>`;
        });
        html += `</select><div class="booklet-product-image mt-3 flex-grow-1"></div></div>`;
        return html;
    }

    getProducts() {
        var mForm = $('#mt-booklet-form');

        var params = {
            'id': mForm.find("[name='id']").val(),
            'market_id': mForm.find("[name='market_id']").length > 0 ? mForm.find("[name='market_id']").val() : null
        };

        $.post('/app/booklet/get_products', params, data => {
            this.products = {};
            data.forEach((item) => {
                this.products['_' + item.id] = item;
            });
            mForm.find('select.booklet-layout-select').change();
        });
    }

    selectProducts(page) {
        var mForm = $('#mt-booklet-form');

        if (typeof this.selectedProducts[page] == 'undefined') {
            return;
        }

        this.selectedProducts[page].forEach(product => {
            if (typeof this.products['_' + product.id] == 'undefined') {
                return;
            }

            mForm.find('[name^="booklet_product[' + page + '][' + product.order + ']"]').val(product.id).trigger('change.select2');
        });
    }
}
