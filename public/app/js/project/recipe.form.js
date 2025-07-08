class RecipeForm {
    jsonEditor = [null, null, null, null];
    jsonData = [null, null, null, null];
    products = null;

    ready() {
        var that = this;
        var mForm = $('#mt-recipe-form');
        var id = mForm.find("[name='id']");
        if (id.length) {
            id = id.val();
        }

        var startStep = 1;
        if (localStorage.getItem('active_recipe_tab') != null) {
            startStep = localStorage.getItem('active_recipe_tab');
            localStorage.removeItem('active_recipe_tab');
        }

        var stepper = new KTStepper(mForm.find('#mt-recipe-stepper')[0], { startIndex: startStep });

        mForm.validate({
            ignore: '.json-form-hidden :input',
            onkeyup: false,
            invalidHandler: function (event, validator) {
                stepperInvalidFormValidationHandler(validator, stepper);
            },
            submitHandler: function (form) {
                var subproductsSelected = [];
                $(form).find('select[name*="[subproducts]"]').each(function () {
                    subproductsSelected.push($(this).val());
                });
                if (new Set(subproductsSelected).size !== subproductsSelected.length) {
                    AdminUtils.hideLoading();
                    showWarning(__('app.js.common.attention'), __('app.js.recipe.subproduct_already_selected_submit'));
                    return false;
                }

                if (jQuery.inArray($(form).attr('action').split('/').pop(), ['N', 'B']) === -1) {
                    localStorage.setItem('active_recipe_tab', stepper.getCurrentStepIndex());
                }

                var jsonDataArray = [
                    that.jsonEditor[0].getValue(),
                    that.jsonEditor[1].getValue(),
                    that.jsonEditor[2].getValue(),
                    that.jsonEditor[3].getValue(),
                ]

                $(form).find("[name='json_data']").val(JSON.stringify(jsonDataArray));

                return true;
            }
        });

        function changeStep(index, stepper) {
            var languageValid = !mForm.find("[name='main_language_id']").is(':visible') || mForm.find("[name='main_language_id']").valid();
            var marketValid = !mForm.find("[name='market_id']").is(':visible') || mForm.find("[name='market_id']").valid();

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

        mForm.find("[name='market_id'], [name='main_language_id']").on('change', function () {
            that.getProducts();
        });

        initFileVersionsList(mForm, '/app/recipe/pdf/delete/');

        if (id.length) {
            $.post('/app/recipe/' + id, function (data) {
                mForm.find("[name='name']").val(data.name);

                mForm.find("[name='creator_name']").val(data.creator_name);

                mForm.find("[name='main_language_id']").val(data.main_language_id).change();
                mForm.find("[name='qr_language_id']").val(data.qr_language_id).change();

                that.jsonData = data.json_data;

                mForm.find(".mt-date-created").val(formatDateWithTime(data.date_created));
                mForm.find(".mt-date-updated").val(formatDateWithTime(data.date_updated));

                mForm.find("[name='market_name']").val(data.market_name);
                mForm.find("[name='market_id']").val(data.market_id).change();

                if (!userHasProfile(['A'])) {
                    if (data.editable != '1') {
                        formReadOnly(mForm);
                    }
                }

                AdminUtils.showDelayedAfterLoad();
            });
        } else {
            // Valores por defecto en registros nuevos
            mForm.removeDisabledOptions();
            AdminUtils.showDelayedAfterLoad();
        }
    }

    initJsonEditor(page, reloadData = true) {
        var mForm = $('#mt-recipe-form');
        var that = this;
        var firstInit = that.jsonEditor[page] == null;

        var reloadCurrentData = null;
        if (that.jsonEditor[page] != null) {
            if (reloadData) {
                reloadCurrentData = that.jsonEditor[page].getValue();
            }

            that.jsonEditor[page].destroy();
        }

        var disableEdit = !userHasProfile(['A']);
        // Siempre permitir editar el JSON en la vista de edición
        disableEdit = false;

        mForm.find('#json-content-form-' + page).toggleClass('disable-edit', disableEdit);

        var select2options = {
            "language": __('app.js.lang.code'),
            "placeholder": __('app.js.common.select_value')
        };

        var lang = mForm.find("[name='main_language_id']").val() ?? __('app.js.lang.code');

        var iconList = [
            { id: "", name: __('app.js.common.select_value') }
        ];
        for (var i = 1; i <= 8; i++) {
            iconList.push({ id: i, name: `/app/img/receipt/ico${i}-${lang}.svg?v=${RESOURCES_VERSION}` });
        }

        var bannerList = [
            { id: "", name: __('app.js.common.select_value') }
        ];
        for (var i = 1; i <= 3; i++) {
            bannerList.push({ id: i, name: `/app/img/receipt/banner${i}-${lang}.jpg?v=${RESOURCES_VERSION}` });
        }
        // console.log(bannerList);

        const select2IconOptions = {
            language: __('app.js.lang.code'),
            placeholder: __('app.js.common.select_value'),
            templateResult: function (data) {
                if (!data.id || data.id == 0) {
                    return $('<span>' + __('app.js.common.select_value') + '</span>');
                }
                return $('<span><img class="select2-logo-image" src="' + data.text + '" /></span>');
            },
            templateSelection: function (data) {
                if (!data.id || data.id == 0) {
                    return $('<span>' + __('app.js.common.select_value') + '</span>');
                }
                return $('<span><img class="select2-logo-image" src="' + data.text + '" /></span>');
            },
            escapeMarkup: function (m) {
                return m;
            },
        };
        const select2BannerOptions = {
            language: __('app.js.lang.code'),
            placeholder: __('app.js.common.select_value'),
            templateResult: function (data) {
                if (!data.id || data.id == 0) {
                    return $('<span>' + __('app.js.common.select_value') + '</span>');
                }
                return $('<span><img class="select2-banner-image" src="' + data.text + '" /></span>');
            },
            templateSelection: function (data) {
                if (!data.id || data.id == 0) {
                    return $('<span>' + __('app.js.common.select_value') + '</span>');
                }
                return $('<span><img class="select2-banner-image" src="' + data.text + '" /></span>');
            },
            escapeMarkup: function (m) {
                return m;
            },
        };
        const select2ProductOptions = {
            language: __('app.js.lang.code'),
            placeholder: __('app.js.common.select_value'),
            templateResult: function (data) {
                if (!data.id || data.id == 0) {
                    return $('<span>' + __('app.js.common.select_value') + '</span>');
                }

                const product = that.products.products.find(p => p.id == data.id);
                const isCustom = product.is_custom == '1';
                const langCode = isCustom ? 'custom' : lang;
                const recipedIdParam = (mForm.find("[name='id']").length > 0 ? '/' + mForm.find("[name='id']").val() : '') + addDateUpdatedTimestampParam(product);

                return $('<div><img class="select2-banner-image me-4" src="/app/recipe/product_image/logo_' + langCode + '/' + data.id + recipedIdParam + '" /><img class="select2-banner-image me-4" src="/app/recipe/product_image/photo_' + langCode + '/' + data.id + recipedIdParam + '" /><div class="product-select2-info">' + data.text + '</div></div>');
            },
            templateSelection: function (data) {
                if (!data.id || data.id == 0) {
                    return $('<span>' + __('app.js.common.select_value') + '</span>');
                }

                const product = that.products.products.find(p => p.id == data.id);
                const isCustom = product.is_custom == '1';
                const langCode = isCustom ? 'custom' : lang;
                const recipedIdParam = (mForm.find("[name='id']").length > 0 ? '/' + mForm.find("[name='id']").val() : '') + addDateUpdatedTimestampParam(product);

                return $('<div><img class="select2-banner-image me-4" src="/app/recipe/product_image/logo_' + langCode + '/' + data.id + recipedIdParam + '" /><img class="select2-banner-image me-4" src="/app/recipe/product_image/photo_' + langCode + '/' + data.id + recipedIdParam + '" /><div class="product-select2-info">' + data.text + '</div></div>');
            },
            escapeMarkup: function (m) {
                return m;
            },
        };


        var properties = {
            "page": page,
            "type": "array",
            "title": __('app.js.common.group'),
            "format": "grid-strict",
            "items":
            {
                "type": "object",
                "title": __('app.js.common.group'),
                "format": "grid-strict",


                "properties": {

                    "use_image_only": {
                        "type": "boolean",
                        "title": __('app.js.recipe.show_banner_only'),
                        "format": "checkbox",
                        "default": false,
                        "options": {
                            "grid_columns": 12
                        }
                    },


                    "image_block": {
                        "type": "object",
                        "options": {
                            "containerAttributes": {
                                "class": "image-block-container"
                            }
                        },
                        "properties":
                        {
                            "banner": {
                                "title": __('app.js.recipe.banner'),
                                "type": "integer",
                                "format": "select2",
                                "enumSource": [{
                                    "source": bannerList,
                                    "title": "enumTitle",
                                    "value": "enumValue"
                                }],
                                "readonly": disableEdit,
                                "options": {
                                    "grid_columns": 6,
                                    "select2": select2BannerOptions,
                                }
                            },
                            "image": {
                                "type": "string",
                                "title": __('app.js.recipe.banner_override'),
                                "description": __('app.js.common.media_formats') + '. ' + __('app.js.common.recommended_dimensions') + ": 1760px x 360px.",
                                "format": "url",
                                "readonly": disableEdit,

                                "options": {
                                    "grid_columns": 6,
                                    "upload": {
                                        "title": __('app.js.common.upload_image'),
                                        "auto_upload": true,
                                        "upload_handler": "JSONEditorUploadHandler"
                                    },
                                    "containerAttributes": {
                                        "class": "col-md-12 image-required"
                                    }
                                },
                                "links": [
                                    {
                                        "href": "{{self}}",
                                        "mediaType": "image/*",
                                        "class": "uploaded-image"
                                    }
                                ]
                            }
                        }
                    },



                    "formdata": {
                        "format": "grid-strict",
                        "options": {
                            "containerAttributes": {
                                "class": "formdata-container"
                            }
                        },
                        "type": "object",
                        "properties":
                        {
                            "icon": {
                                "title": __('app.js.group_icon'),
                                "type": "integer",
                                "format": "select2",
                                "enumSource": [{
                                    "source": iconList,
                                    "title": "enumTitle",
                                    "value": "enumValue"
                                }],
                                "readonly": disableEdit,
                                "options": {
                                    "grid_columns": 6,
                                    "select2": select2IconOptions,

                                    /*"inputAttributes": {
                                        "required": true
                                    }*/
                                }
                            },
                            "image": {
                                "type": "string",
                                "title": __('app.js.group_icon.image_override'),
                                "description": __('app.js.common.media_formats') + '. ' + __('app.js.common.recommended_dimensions') + ": 2480px x 1754px.",
                                "format": "url",
                                "readonly": disableEdit,

                                "options": {
                                    "grid_columns": 6,
                                    "upload": {
                                        "title": __('app.js.common.upload_image'),
                                        "auto_upload": true,
                                        "upload_handler": "JSONEditorUploadHandler"
                                    },
                                    "containerAttributes": {
                                        "class": "col-md-12 image-required"
                                    },
                                    /*"inputAttributes": {
                                        "required": true
                                    }*/
                                },
                                "links": [
                                    {
                                        "href": "{{self}}",
                                        "mediaType": "image/*",
                                        "class": "uploaded-image"
                                    }
                                ]
                            },
                            "group_title": {
                                "title": __('app.js.group_title'),
                                "type": "string",
                                "readonly": disableEdit,
                                "required": true,
                                "options": {
                                    "grid_columns": 9,
                                    "inputAttributes": {
                                        "required": true
                                    },

                                }
                            },
                            "title_bg_color": {
                                "title": __('app.js.common.title_color'),
                                "type": "string",
                                "format": "color",
                                "readonly": disableEdit,
                                "options": {
                                    "grid_columns": 3,

                                    /*"inputAttributes": {
                                        "required": true
                                    }*/
                                }
                            },
                            "group_bg_color": {
                                "title": __('app.js.common.group_bg_color'),
                                "type": "string",
                                "format": "color",
                                "readonly": disableEdit,
                                "options": {
                                    "grid_columns": 3,

                                    /*"inputAttributes": {
                                        "required": true
                                    }*/
                                }
                            },
                            "products": {
                                "type": "array",
                                "minItems": 1,
                                "title": __('app.entity.products'),
                                "items": {
                                    "type": "object",
                                    "title": __('app.entity.product'),
                                    "format": "grid-strict",
                                    "properties": {
                                        "product_id": {
                                            "title": __('app.entity.product'),
                                            "type": "string",
                                            "format": "select2",
                                            "enumSource": [{
                                                "source": this.products.products,
                                                "title": "enumTitle",
                                                "value": "enumValue"
                                            }],
                                            "readonly": disableEdit,
                                            "required": true,
                                            "options": {
                                                "grid_columns": 12,
                                                "select2": select2ProductOptions,
                                                "inputAttributes": {
                                                    "required": true
                                                }
                                            }
                                        },
                                        "image": {
                                            "type": "string",
                                            "title": __('app.js.common.product_image_override'),
                                            "description": __('app.js.common.media_formats') + '. ' + __('app.js.common.recommended_dimensions') + ": 2480px x 1754px.",
                                            "format": "url",
                                            "readonly": disableEdit,
                                            "options": {
                                                "grid_columns": 12,
                                                "upload": {
                                                    "title": __('app.js.common.upload_image'),
                                                    "auto_upload": true,
                                                    "upload_handler": "JSONEditorUploadHandler"
                                                },
                                                "containerAttributes": {
                                                    "class": "col-md-12 image-required"
                                                }
                                            },
                                            "links": [
                                                {
                                                    "href": "{{self}}",
                                                    "mediaType": "image/*",
                                                    "class": "uploaded-image"
                                                }
                                            ]
                                        },

                                        "subtitle": {
                                            "type": "string",
                                            "title": __('app.js.product.subtitle'),
                                            "readonly": disableEdit,
                                            "options": {
                                                "grid_columns": 6
                                            }
                                        },
                                        "periodicity": {
                                            "type": "string",
                                            "title": __('app.js.product.periodicity'),
                                            "readonly": disableEdit,
                                            "options": {
                                                "grid_columns": 6
                                            }
                                        },

                                        "show_frequency_icons": {
                                            "type": "boolean",
                                            "format": "checkbox",
                                            "title": __('app.js.show_frequency_icons'),
                                            "default": false,
                                            "readonly": disableEdit,
                                            "options": {
                                                "grid_columns": 6
                                            }
                                        },
                                        "qr": {
                                            "type": "string",
                                            "title": __('app.js.qr_code'),
                                            "readonly": disableEdit,
                                            "options": {
                                                "grid_columns": 6,
                                            }
                                        },



                                        "group_title": {
                                            "type": "string",
                                            "title": __('app.js.product.group_title'),
                                            "readonly": disableEdit,
                                            "options": {
                                                "grid_columns": 9,
                                            }
                                        },
                                        "group_line_color": {
                                            "type": "string",
                                            "title": __('app.js.product.group_line_color'),
                                            "format": "color",
                                            "readonly": disableEdit,
                                            "options": {
                                                "grid_columns": 3
                                            }
                                        },
                                        "subproducts": {
                                            "type": "array",
                                            //"minItems": 1,
                                            "title": __('app.entity.subproducts'),
                                            "format": "table",
                                            "items": {
                                                "title": __('app.entity.subproduct'),
                                                "type": "object",

                                                "properties": {
                                                    "subproduct_id": {
                                                        "title": __('app.entity.subproduct'),
                                                        "type": "string",
                                                        "format": "select2",
                                                        "enumSource": [{
                                                            "source": this.products.subproducts,
                                                            "title": "enumTitle",
                                                            "value": "enumValue",
                                                            "filter": "filterSubproducts"
                                                        }],
                                                        "readonly": disableEdit,
                                                        "required": true,
                                                        "options": {
                                                            "select2": select2options,
                                                            "inputAttributes": {
                                                                "required": true
                                                            },
                                                        }
                                                    },
                                                    "subproduct_name": {
                                                        "type": "string",
                                                        "title": __('app.js.subproduct_name'),
                                                        "readonly": disableEdit,
                                                    }
                                                }
                                            },
                                            "options": {
                                                "grid_columns": 12,
                                                "disable_array_add": disableEdit,
                                                "disable_array_delete": disableEdit,
                                                "disable_array_delete_all_rows": disableEdit,
                                                "disable_array_delete_last_row": disableEdit,
                                                "disable_array_reorder": disableEdit
                                            }
                                        },
                                        "columns": {
                                            "type": "integer",
                                            "title": __('app.js.product.reference_columns'),
                                            "enum": [1, 2],
                                            "readonly": disableEdit,
                                            "options": {
                                                "grid_columns": 12,
                                            }
                                        },
                                    }
                                },
                                "options": {
                                    "disable_array_add": disableEdit,
                                    "disable_array_delete": disableEdit,
                                    "disable_array_delete_all_rows": disableEdit,
                                    "disable_array_delete_last_row": disableEdit,
                                    "disable_array_reorder": disableEdit
                                }
                            }
                        }
                    }
                }
            },
            "options": {
                "disable_array_add": disableEdit,
                "disable_array_delete": disableEdit,
                "disable_array_delete_all_rows": disableEdit,
                "disable_array_delete_last_row": disableEdit,
                "disable_array_reorder": disableEdit
            }
        };



        let p = {};
        p['group' + page] = properties;

        // Initialize the editor with a JSON schema
        that.jsonEditor[page] = new JSONEditor(mForm.find('#json-content-form-' + page)[0], {
            required_by_default: false,
            display_required_only: false,
            //disable_edit_json: false,
            disable_edit_json: true,
            no_additional_properties: true,
            prompt_before_delete: false,
            disable_array_delete_all_rows: true,
            disable_array_delete_last_row: true,
            schema: {
                type: "object",
                format: "grid-strict",
                properties: p
            }
        });


        that.jsonEditor[page].on('ready', () => {
            if (firstInit && this.jsonData != null && this.jsonData[page] != null) {
                that.jsonEditor[page].setValue(this.jsonData[page]);
            } else if (reloadCurrentData != null) {
                that.jsonEditor[page].setValue(reloadCurrentData);
            } else {
                mForm.find('#json-content-form-' + page).find('select[name*="[subproducts]"]').val('').change();
            }

            that.jsonEditor[page].on('addRow', editor => {
                $(editor.container).find('select[name*="[subproducts]"]').val('').change();
            });

            if (disableEdit) {
                setTimeout(() => {
                    mForm.find('#json-content-form-' + page).find('select').addClass('readonly-disabled');
                }, 0);
            }
        });

        this.jsonEditor[page].on('change', function () {
            // console.log('JSONEditor change event for page ' + page);

            mForm.find('select[name*="[subproducts]"]:not(.change-init)').each(function () {
                $(this).on('select2:selecting', function (e) {
                    var selectedValue = e.params.args.data.id;

                    var exists = mForm.find('select[name*="[subproducts]"]').filter(function () {
                        return $(this).val() === selectedValue;
                    }).length > 0;

                    if (exists) {
                        e.preventDefault();
                        showWarning(__('app.js.common.attention'), __('app.js.recipe.subproduct_already_selected'));
                    }
                });

                $(this).addClass('change-init');
            });


            // Resaltar los subproductos seleccionados en otros select2
            mForm.find('#json-content-form-' + page + ' select[name*="[subproducts]"]').on('select2:open', function () {
                setTimeout(() => {
                    var selectedValues = $('select[name*="[subproducts]"]').not($(this)).map(function () {
                        return $(this).val();
                    }).get();

                    $('.select2-results__option').each(function () {
                        if ($(this).attr('data-select2-id')) {
                            $(this).toggleClass('selected-other-select', selectedValues.includes($(this).attr('data-select2-id').split('-').pop()));
                        }
                    });
                }, 0);
            });

            // Al cambiar el producto, eliminar los subproductos seleccionados
            mForm.find('#json-content-form-' + page + ' select[name*="[product_id]"]:not(.change-init)').on('change', function (e) {
                $(this).closest('.card').find('select[name*="[subproducts]"]').each(function () {
                    $(this).closest('[data-schematype="array"]').find('.json-editor-btn-delete').each(function () {
                        $(this).trigger('click');
                    });
                });
            }).addClass('change-init');

            mForm.find('#json-content-form-' + page + ' [name*="[use_image_only]"]').each(function () {
                var container = $(this).closest('.je-object__container');
                container.find('.image-block-container').toggleClass('json-form-hidden', !$(this).prop('checked'));
                container.find('.formdata-container').toggleClass('json-form-hidden', $(this).prop('checked'));
            });
        });
    }

    getProducts() {
        var mForm = $('#mt-recipe-form');

        if (
            (mForm.find("[name='market_id']").length > 0 && (mForm.find("[name='market_id']").val() == null || mForm.find("[name='market_id']").val() == '')) ||
            (mForm.find("[name='main_language_id']").val() == null || mForm.find("[name='main_language_id']").val() == '')
        ) {
            return;
        }

        var params = {
            'id': mForm.find("[name='id']").val(),
            'market_id': mForm.find("[name='market_id']").length > 0 ? mForm.find("[name='market_id']").val() : null,
            'main_language_id': mForm.find("[name='main_language_id']").val()
        };
        var that = this;
        $.post('/app/recipe/get_products', params, data => {
            this.products = data;

            this.products.products.unshift({ id: "", name: __('app.js.common.select_value') });
            this.products.subproducts.unshift({ id: "", name: __('app.js.common.select_value') });

            if (this.jsonData != null) {

                // Init JSONEditor callbacks
                window.JSONEditor.defaults.callbacks.template = {
                    "filterSubproducts": (jseditor, e) => {
                        try {

                            const pathStr = jseditor.path;
                            const path = pathStr.split('.');
                            const groupIndex = parseInt(path[2]);   // 'group.0' → 0
                            const group = path[1];
                            const productIndex = parseInt(path[5]); // 'products.0' → 0
                            const productPath = `root.${group}.${groupIndex}.formdata.products.${productIndex}.product_id`;
                            const productEditor = jseditor.jsoneditor.getEditor(productPath);
                            return e.item.product_id == productEditor.getValue();
                        } catch (err) {
                            console.warn('Error en filterSubproducts:', err);
                            return false;
                        }
                    },
                    "enumTitle": (jseditor, e) => {
                        return e.item.name;
                    },
                    "enumValue": (jseditor, e) => {
                        return e.item.id;
                    }
                };

                this.initJsonEditor(0);
                this.initJsonEditor(1);
                this.initJsonEditor(2);
                this.initJsonEditor(3);
            }
        });
    }
}
