class CustomDatatable {

    constructor(id, settings) {
        this.id = id;
        this.settings = settings;
        this.init(id, settings);

        if (typeof settings.displayHoverEffects != 'undefined' && settings.displayHoverEffects) {
            this.displayHoverEffects();
        }
        if (typeof settings.diplayFooterSearch != 'undefined' && settings.diplayFooterSearch) {
            this.diplayFooterSearch();
        }

        this.allowReload();
        this.allowReset();
    }

    init() {
        if (!$().DataTable) {
            console.warn('Warning - datatables.min.js is not loaded.');
            return;
        }

        // Setting datatable defaults
        $.extend($.fn.dataTable.defaults, {
            diplayFooterSearch: false,
            displayHoverEffects: true,
            serverSide: true,
            stateSave: true,
            responsive: true,
            autoWidth: false,
            processing: false,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, __('app.js.common.all')]],
            ajax: {
                type: 'POST'
            },
            "drawCallback": function (settings) {
                $('[data-bs-toggle="tooltip"]').tooltip();
            },
            "language":
                    {
                        "sProcessing": __('app.js.datatable.processing'),
                        "sLengthMenu": __('app.js.datatable.show_records'),
                        "sZeroRecords": __('app.js.datatable.no_records'),
                        "sEmptyTable": __('app.js.datatable.empty_table'),
                        "sInfo": __('app.js.datatable.info_records'),
                        "sInfoEmpty": __('app.js.datatable.info_records_empty'),
                        "sInfoFiltered": __('app.js.datatable.info_records_filtered'),
                        "sInfoPostFix": '',
                        "sSearch": __('app.js.datatable.search') + ': ',
                        "sUrl": '',
                        "sInfoThousands": ',',
                        "sLoadingRecords": __('app.js.datatable.loading'),
                        "oPaginate": {
                            "sFirst": __('app.js.datatable.first'),
                            "sLast": __('app.js.datatable.last'),
                            "sNext": __('app.js.datatable.next'),
                            "sPrevious": __('app.js.datatable.previous')
                        },
                        "oAria": {
                            "sSortAscending": ': ' + __('app.js.datatable.sort_ascending'),
                            "sSortDescending": ': ' + __('app.js.datatable.sort_descending')
                        },
                        "buttons": {
                            "copyTitle": __('app.js.datatable.copy_title'),
                            "copyKeys": __('app.js.datatable.copy_keys'),
                            "copySuccess": {
                                "_": __('app.js.datatable.rows_copied'),
                                "1": __('app.js.datatable.row_copied')
                            }
                        }
                    },
            dom:
                    "<'table-responsive'tr>" +
                    "<'row'" +
                    "<'col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start'li>" +
                    "<'col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end'p>" +
                    "<'col-12 d-none'B>" +
                    ">"
        });

        let associativeColumns = [];

        // Associamos el name a la columna
        for (let i in this.settings.columns) {
            const column = this.settings.columns[i];
            if (column['data']) {
                associativeColumns[column['data']] = i;
            }
        }

        // Se exportan las columnas export = true | false, si no se indica, se exportarán las visibles
        var exportColumns = [];
        if (typeof this.settings.exportColumns != 'undefined' && this.settings.exportColumns != null && this.settings.exportColumns.length) {
            exportColumns = this.settings.exportColumns;
        } else {
            for (let i in this.settings.columns) {
                const column = this.settings.columns[i];
                if ('export' in column) {
                    if (column['export']) {
                        exportColumns.push(i);
                    }
                } else if (!('visible' in column) || column['visible']) {
                    exportColumns.push(i);
                }
            }
        }

        if (exportColumns.length) {
            var that = this;
            var exportButtons = [];
            ['copyHtml5', 'csvHtml5', 'excelHtml5', 'pdfHtml5', 'print'].forEach(function (exporType) {
                exportButtons.push({
                    extend: exporType,
                    exportOptions: {
                        columns: exportColumns,
                        orthogonal: 'export',
                        format: {
                            header: function (data, columnIdx) {
                                if (typeof that.settings.exportHeader != 'undefined') {
                                    return that.settings.exportHeader(data, columnIdx);
                                }
                                return data;
                            }
                        }
                    },
                    action: function (e, dt, node, config) {
                        that.customExport(this, e, dt, node, config, exporType);
                    }
                });
            });

            this.settings.buttons = exportButtons;
            delete this.settings.exportColumns;

            $('[data-export-datatable="' + this.id.replace('#', '') + '"] [data-export-type]').click(function () {
                $(that.id + '_wrapper .buttons-' + $(this).attr('data-export-type')).click();
            });
        }

        if (this.settings.order) {
            for (var i = 0; i < this.settings.order.length; i++) {
                var field = this.settings.order[i][0];
                if (!Number.isInteger(field)) {
                    // Replace column label by index
                    this.settings.order[i][0] = associativeColumns[field];
                }
            }
        }

        this.table = $(this.id).DataTable(this.settings);

        $(this.id).on('responsive-display.dt', function (e, datatable, row, showHide, update) {
            if (showHide) {
                $(this).find('[data-bs-toggle="tooltip"]').tooltip();
            }
        });
    }

    diplayFooterSearch() {
        // Apply the search
        $(this.id + ' tfoot th').not(':last-child, .dt-custom-filter').each(function () {
            var title = $(this.id + ' thead th').eq($(this).index()).text();
            if ($(this).text().length) {
                $(this).html('<input type="text" class="form-control input-sm datatables-column-search-filter" placeholder=' + __('app.js.datatable.search') + ' ' + title + '" />');
            }
        });

        // Restore state
        var state = this.table.state.loaded();
        if (state) {
            var that = this;
            this.table.columns().eq(0).each(function (colIdx) {
                var colSearch = state.columns[colIdx].search;
                if (colSearch.search) {
                    $('input', that.table.column(colIdx).footer()).val(colSearch.search);
                }
            });
        }

        // Apply search
        this.table.columns().every(function () {
            var that = this;
            $('input', this.footer()).on('keyup change', function () {
                that.search(this.value).draw();
            });
        });
    }

    displayHoverEffects() {
        var table = this.table;

        var lastIdx = null;
        $(this.id + ' tbody').on('mouseover', 'td', function () {
            try {
                var colIdx = table.cell(this).index().column;
                if (colIdx !== lastIdx) {
                    $(table.cells().nodes()).removeClass('active');
                    $(table.column(colIdx).nodes()).addClass('active');
                }
            } catch (e) {
            }
        }).on('mouseleave', function () {
            $(table.cells().nodes()).removeClass('active');
        });
    }

    block(block) {
        // Block card
        $(block).block({
            message: '<i class="icon-spinner2 spinner"></i>',
            overlayCSS: {
                backgroundColor: '#fff',
                opacity: 0.8,
                cursor: 'wait',
                'box-shadow': '0 0 0 1px #ddd'
            },
            css: {
                border: 0,
                padding: 0,
                backgroundColor: 'none'
            }
        });
    }

    reloadData(callback = null) {
        this.table.ajax.reload(callback);
    }

    allowReload() {
        $(this.id).closest('.datatable-container').find('[data-action=reload]:not(.disabled)').on('click', function (e) {
            e.preventDefault();
            reloadData();
        });

    }

    allowReset() {
        // Reset search inputs
        var that = this;
        $(this.id).closest('.datatable-container').find('[data-action=remove]:not(.disabled)').on('click', function (e) {
            e.preventDefault();
            $(that.id).closest('.datatable-container').find('.datatables-column-search-filter:not(.datatables-column-skip-reset), .select2-selection--single:not(.datatables-column-skip-reset)').val('').change();
            that.table.search('').draw();

            $(that.id).closest('.datatable-container').find('[data-action="advanced-search"]').click();
            $(that.id).closest('.datatable-container').find('.custom-datepicker, .custom-datetimepicker').each(function () {
                //$('[data-action="advanced-search"]').click();
                //$('.custom-datepicker, .custom-datetimepicker').each(function () {                                
                if (!$(this).hasClass('datatables-column-skip-reset')) {
                    //      $(this).datepicker("clearDates");
                }
            });
        });

    }

    showSelectFilters(columnsToSkip, defaultFilterText, useSelect2) {
        var that = this;
        var state = this.table.state.loaded();
        this.table.columns().every(function (index) {
            // Skip not filtrable columns
            if (columnsToSkip.indexOf(index) === -1) {
                return;
            }

            var column = this;

            var select = $('<select class="form-control form-control-sm form-filter  filter-select select2-selection select2-selection--single" data-placeholder="' + defaultFilterText + '"><option value="">' + defaultFilterText + '</option></select>').appendTo($(column.footer()).not(':last-child, .dt-custom-filter').empty()).on('change', function () {
                var val = $.fn.dataTable.util.escapeRegex($(this).val());
                column.search(val ? '^' + val + '$' : '', true, false).draw();
            });

            if (useSelect2) {
                select.select2({
                    dropdownAutoWidth: true,
                    width: 'auto'
                });
            }


            var colSearch = state ? state.columns[index].search : null;

            column.data().unique().sort().each(function (d, j) {
                var dstr = "" + d;
                var mySelected = "";

                if (colSearch != null && colSearch.search == "^" + dstr + "$") {
                    mySelected = " selected ";
                }
                select.append('<option ' + mySelected + ' value="' + dstr.replace(/<[^>]+>/g, '') + '">' + dstr + '</option>')
            });

            // console.log(that.settings);
        });
    }

    advancedSearchForm(submitButton = null) {
        const dt = $(this.id).closest('.datatable-container');
        if (submitButton == null) {
            submitButton = dt.find('[data-action="advanced-search"]');
        }

        var that = this;
        var searchForm = submitButton.closest('form');
        submitButton.on('click', function (e) {
            e.preventDefault();
            var params = {};
            searchForm.find('[data-col-index]').each(function () {
                var i = $(this).data('col-index');
                if (params[i]) {
                    params[i] += '|' + $(this).val();
                } else {
                    params[i] = $(this).val();
                }
            });

            searchForm.find('[data-col-name]').each(function () {
                var column = $(this).data('col-name');
                for (var i = 0; i < that.settings.columns.length; i++) {
                    if (that.settings.columns[i].data == column) {
                        if (params[i]) {
                            params[i] += '|' + $(this).val();
                        } else {
                            params[i] = $(this).val();
                        }
                    }
                }
            });

            $.each(params, function (i, val) {
                // apply search params to datatable
                that.table.column(i).search(val ? val : '', false, false);
            });

            if (searchForm.find('.datatables-global-search-filter').length) {
                that.table.search(searchForm.find('.datatables-global-search-filter').val());
            }

            if (typeof that.settings.beforeDrawSearchCallback === 'function') {
                that.settings.beforeDrawSearchCallback();
            }

            that.table.draw();
        });


        var state = this.table.state.loaded();

        if (state && state.search.search != null) {
            searchForm.find('.datatables-global-search-filter').val(state.search.search);
        }

        dt.find(".button-filter").click(function () {
            $(this).removeClass('btn-light-primary').addClass('btn-light').addClass('disabled');
            dt.find(".filter-section").slideDown();

        });
        dt.find(".reset-filters").click(function () {
            dt.find(".button-filter").removeClass('btn-light').addClass('btn-light-primary').removeClass('disabled');
            dt.find(".filter-section").slideUp();
        });

        this.table.columns().every(function (index) {
            var colSearch = null;
            if (state) {
                colSearch = state.columns[index].search;
            } else if (typeof that.settings.searchCols != 'undefiend' && that.settings.searchCols != null && typeof that.settings.searchCols[index] != 'undefiend' && that.settings.searchCols[index] != null && that.settings.searchCols[index]['sSearch'] != null && that.settings.searchCols[index]['sSearch'].length) {
                colSearch = {search: that.settings.searchCols[index]['sSearch']};
            }

            if (colSearch != null && colSearch.search != null && colSearch.search.length) {

                dt.find(".button-filter").click();

                // If the target field is a date range, parse it
                var item = searchForm.find('[data-col-index=' + index + '], [data-col-name=' + that.settings.columns[index].data + ']');
                var isDateRange = item.closest('.input-daterange');

                if (isDateRange.length) {
                    var searchDates = colSearch.search.split('|');
                    $(item[0]).datepicker('update', searchDates[0]);
                    $(item[1]).datepicker('update', searchDates[1]);
                } else {
                    if (typeof item.attr('data-datatable-select-ajax') != 'undefined') {
                        $.ajax({
                            type: 'post',
                            global: true,
                            url: item.attr('data-datatable-select-ajax'),
                            data: {id: colSearch.search},
                            success: function (data) {
                                var option = new Option(data.text, data.id, false, true);
                                item.append(option).trigger('change');

                                item.trigger({
                                    type: 'select2:select',
                                    params: {
                                        data: data
                                    }
                                });
                            }
                        });
                    } else {
                        if (typeof item.attr('multiple') != 'undefined') {
                            item.val(colSearch.search.split(',')).change();
                        } else {
                            item.val(colSearch.search).change();
                        }
                    }
                }
            }
        });

        dt.css('visibility', 'visible').hide().fadeIn();
    }

    customExport(that, e, dt, node, config, exportType) {
        // Obtener página actual + num paginas
        var len = dt.page.info().length;
        var page = dt.page.info().page;
        var datatableId = dt.table().node().id;
        $('#' + datatableId + '_wrapper').fadeTo(500, 0.2);

        dt.one('draw', function () {
            $.fn.dataTable.ext.buttons[exportType].action.call(that, e, dt, node, config);

            // Recuperar página actual + num páginas
            setTimeout(function () {
                $('#' + datatableId + '_wrapper [name="' + datatableId + '_length"]').val(len).change();

                if (page != 0) {
                    // TODO Mejorar, la página debe saltar una vez cargado después de actualizar el selector
                    setTimeout(function () {
                        var iPage = page + 1;
                        $('#' + datatableId + '_wrapper').fadeTo(500, 1);
                        $('#' + datatableId + '_wrapper .paginate_button [data-dt-idx=' + iPage + ']').click();
                    }, 500);
                } else {
                    $('#' + datatableId + '_wrapper').fadeTo(500, 1);
                }
            }, 500);

        });
        dt.page.len(-1).draw();

    }

}