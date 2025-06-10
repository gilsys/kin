class RecipeForm {
    jsonEditor = null;
    products = null;
    jsonData = null;

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
            ignore: "",
            onkeyup: false,
            invalidHandler: function (event, validator) {
                stepperInvalidFormValidationHandler(validator, stepper);
            },
            submitHandler: function (form) {
                var subproductsSelected = [];
                $(form).find('#json-content-form select[name*="[subproducts]"]').each(function () {
                    subproductsSelected.push($(this).val());
                });
                if (new Set(subproductsSelected).size !== subproductsSelected.length) {
                    AdminUtils.hideLoading();
                    showWarning(__('app.js.common.attention'), __('app.js.recipe.subproduct_already_selected_submit'));
                    return false;
                }

                var noSelectedImages = $(form).find('#json-content-form .image-required > input[type="hidden"]').filter(function () {
                    return $(this).val() == '';
                }).length > 0;
                if (noSelectedImages) {
                    AdminUtils.hideLoading();
                    showWarning(__('app.js.common.attention'), __('app.js.recipe.image_required'));
                    return false;
                }

                if (jQuery.inArray($(form).attr('action').split('/').pop(), ['N', 'B']) === -1) {
                    localStorage.setItem('active_recipe_tab', stepper.getCurrentStepIndex());
                }

                $(form).find("[name='json_data']").val(JSON.stringify(that.jsonEditor.getValue()));

                return true;
            }
        });

        function changeStep(index, stepper) {
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

        mForm.find("[name='recipe_layout_id']").on('change', function () {
            that.initJsonEditor($(this).val());
        });

        initFileVersionsList(mForm, '/app/recipe/pdf/delete/');

        if (id.length) {
            $.post('/app/recipe/' + id, function (data) {
                mForm.find("[name='name']").val(data.name);
                mForm.find("[name='creator_name']").val(data.creator_name);
                mForm.find("[name='main_language_id']").val(data.main_language_id).change();
                mForm.find("[name='qr_language_id']").val(data.qr_language_id).change();
                mForm.find("[name='recipe_layout_id']").val(data.recipe_layout_id).trigger('change.select2');

                that.jsonData = data.json_data;
                that.getProducts();

                mForm.find(".mt-date-created").val(formatDateWithTime(data.date_created));
                mForm.find(".mt-date-updated").val(formatDateWithTime(data.date_updated));

                if (!userHasProfile(['A'])) {
                    if (data.editable == '1') {
                        formReadOnly(mForm.find('[name]:not([type="hidden"]):not([name="name"]):not([name^="root"])').closest('div'));
                    } else {
                        formReadOnly(mForm);
                    }
                }

                AdminUtils.showDelayedAfterLoad();
            });
        } else {
            that.getProducts();
            mForm.removeDisabledOptions();
            AdminUtils.showDelayedAfterLoad();
        }
    }

    initJsonEditor(layoutId) {
        var that = this;
        var mForm = $('#mt-recipe-form');
        var firstInit = this.jsonEditor == null;

        if (this.jsonEditor != null) {
            this.jsonEditor.destroy();
        }

        if (layoutId == null || layoutId == '') {
            return;
        }

        var disableEdit = !userHasProfile(['A']);
        mForm.find('#json-content-form').toggleClass('disable-edit', disableEdit);

        window.JSONEditor.defaults.callbacks.template = {
            "filterSubproducts": (jseditor, e) => {
                return e.item.product_id == e.watched.productId;
            },
            "enumTitle": (jseditor, e) => {
                return e.item.name;
            },
            "enumValue": (jseditor, e) => {
                return e.item.id;
            }
        };

        var select2options = {
            "language": __('app.js.lang.code'),
            "placeholder": __('app.js.common.select_value')
        };

        if (layoutId == 1) {
            // --- ESTRUCTURA AGRUPADORES/PRODUCTOS ---
            var iconSource = [
                { id: 'icon1', name: 'Diente', iconUrl: '/img/icons/icon-tooth.svg' },
                { id: 'icon2', name: 'Encías', iconUrl: '/img/icons/icon-gums.svg' }
                // Añade más iconos si necesitas
            ];
            var productSource = (this.products && this.products.products) ? this.products.products.map(p => ({
                id: p.id,
                name: p.name
            })) : [];

            var properties = {
                groups: {
                    title: __('app.js.recipe.groups'),
                    type: "array",
                    minItems: 1,
                    format: "tabs",
                    items: {
                        type: "object",
                        format: "grid-strict",
                        properties: {
                            title: {
                                title: __('app.js.common.title'),
                                type: "string",
                                options: { grid_columns: 6 }
                            },
                            titleColor: {
                                title: __('app.js.common.color'),
                                type: "string",
                                format: "color",
                                options: { grid_columns: 3 }
                            },
                            bgColor: {
                                title: __('app.js.common.bg_color'),
                                type: "string",
                                format: "color",
                                options: { grid_columns: 3 }
                            },
                            icon: {
                                title: __('app.js.recipe.group_icon'),
                                type: "string",
                                format: "select2",
                                enumSource: [{
                                    source: iconSource,
                                    title: "name",
                                    value: "id"
                                }],
                                options: {
                                    grid_columns: 6,
                                    select2: {
                                        templateResult: function (data) {
                                            var item = iconSource.find(i => i.id == data.id);
                                            if (item) {
                                                return $('<span><img src="' + item.iconUrl + '" style="width:22px;vertical-align:middle;margin-right:6px;">' + item.name + '</span>');
                                            }
                                            return data.text;
                                        },
                                        templateSelection: function (data) {
                                            var item = iconSource.find(i => i.id == data.id);
                                            if (item) {
                                                return $('<span><img src="' + item.iconUrl + '" style="width:18px;vertical-align:middle;margin-right:5px;">' + item.name + '</span>');
                                            }
                                            return data.text;
                                        }
                                    }
                                }
                            },
                            iconPhoto: {
                                title: __('app.js.recipe.icon_photo'),
                                type: "string",
                                format: "url",
                                options: {
                                    grid_columns: 6,
                                    upload: {
                                        title: __('app.js.common.upload_image'),
                                        auto_upload: true,
                                        upload_handler: "JSONEditorUploadHandler"
                                    }
                                }
                            },
                            products: {
                                title: __('app.js.recipe.products'),
                                type: "array",
                                minItems: 1,
                                format: "tabs",
                                items: {
                                    type: "object",
                                    format: "grid-strict",
                                    properties: {
                                        productId: {
                                            title: __('app.entity.product'),
                                            type: "integer",
                                            format: "select2",
                                            enum: productSource.map(p => p.id),
                                            enumTitles: productSource.map(p => p.name),
                                            options: { grid_columns: 6 }
                                        },
                                        productPhoto: {
                                            title: __('app.js.recipe.product_photo'),
                                            type: "string",
                                            format: "url",
                                            options: {
                                                grid_columns: 6,
                                                upload: {
                                                    title: __('app.js.common.upload_image'),
                                                    auto_upload: true,
                                                    upload_handler: "JSONEditorUploadHandler"
                                                }
                                            }
                                        },
                                        productTitle: {
                                            title: __('app.js.recipe.product_title'),
                                            type: "string",
                                            options: { grid_columns: 6 }
                                        },
                                        productTitleColor: {
                                            title: __('app.js.recipe.product_title_color'),
                                            type: "string",
                                            format: "color",
                                            options: { grid_columns: 3 }
                                        },
                                        productBgColor: {
                                            title: __('app.js.recipe.product_bg_color'),
                                            type: "string",
                                            format: "color",
                                            options: { grid_columns: 3 }
                                        },
                                        subtitle: {
                                            title: __('app.js.recipe.subtitle'),
                                            type: "string",
                                            options: { grid_columns: 12 }
                                        },
                                        treatment: {
                                            title: __('app.js.recipe.treatment'),
                                            type: "string",
                                            options: { grid_columns: 6 }
                                        },
                                        showFrequencyIcons: {
                                            title: __('app.js.recipe.show_frequency_icons'),
                                            type: "boolean",
                                            options: { grid_columns: 6 }
                                        },
                                        qrImage: {
                                            title: __('app.js.recipe.qr_image'),
                                            type: "string",
                                            format: "url",
                                            options: {
                                                grid_columns: 6,
                                                upload: {
                                                    title: __('app.js.common.upload_image'),
                                                    auto_upload: true,
                                                    upload_handler: "JSONEditorUploadHandler"
                                                }
                                            }
                                        },
                                        columns: {
                                            title: __('app.js.recipe.columns'),
                                            type: "integer",
                                            enum: [1, 2],
                                            default: 1,
                                            options: { grid_columns: 6 }
                                        },
                                        references: {
                                            title: __('app.js.recipe.references'),
                                            type: "array",
                                            minItems: 0,
                                            format: "grid-strict",
                                            options: { grid_columns: 12 },
                                            items: {
                                                type: "object",
                                                format: "grid-strict",
                                                properties: {
                                                    code: { type: "string", title: __('app.js.recipe.ref_code'), options: { grid_columns: 4 } },
                                                    text: { type: "string", title: __('app.js.recipe.ref_text'), options: { grid_columns: 8 } }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            };
        } else if (layoutId == 2) {
            var properties = {
                "title": {
                    "title": __('app.js.common.title'),
                    "type": "string",
                    "readonly": disableEdit,
                    "options": {
                        "grid_columns": 6,
                        "inputAttributes": {
                            "required": true
                        }
                    },
                }
            };
        }

        this.jsonEditor = new JSONEditor(mForm.find('#json-content-form')[0], {
            required_by_default: true,
            display_required_only: false,
            disable_edit_json: false,
            no_additional_properties: true,
            prompt_before_delete: false,
            schema: {
                type: "object",
                format: "grid-strict",
                properties: properties
            }
        });

        this.jsonEditor.on('ready', () => {
            if (firstInit && this.jsonData != null) {
                this.jsonEditor.setValue(this.jsonData);
            }

            if (disableEdit) {
                setTimeout(() => {
                    mForm.find('#json-content-form').find('select').addClass('readonly-disabled');
                }, 0);
            }
        });

        this.jsonEditor.on('change', function () {
            // Hooks o lógica reactiva, si la necesitas
        });
    }

    getProducts() {
        var mForm = $('#mt-recipe-form');
        var params = {
            'id': mForm.find("[name='id']").val()
        };

        $.post('/app/recipe/get_products', params, data => {
            this.products = data;
            if (this.jsonData != null) {
                mForm.find("[name='recipe_layout_id']").change();
            }
        });
    }
}
