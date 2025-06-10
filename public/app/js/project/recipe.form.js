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
            // Valores por defecto en registros nuevos  
            that.getProducts();
            mForm.removeDisabledOptions();
            AdminUtils.showDelayedAfterLoad();
        }
    }

    initJsonEditor(layoutId) {
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

        var properties = {
            "group": {
                "type": "array",
                "title": __('app.js.common.group'),
                "items": {
                    "type": "object",
                    "title": __('app.js.common.add_group_item'),
                    "format": "grid",
                    "properties": {
                        "icon": {
                            "title": __('app.js.group_icon'),
                            "type": "integer",
                            "format": "select2",
                            "enumSource": [{
                                "source": [
                                    { id: 1, name: "Icono 1" },
                                    { id: 2, name: "Icono 2" },
                                    { id: 3, name: "Icono 3" }
                                ],
                                "title": "enumTitle",
                                "value": "enumValue"
                            }],
                            "readonly": disableEdit,
                            "options": {
                                "grid_columns": 6,
                                "select2": select2options,
                                "inputAttributes": {
                                    "required": true
                                }
                            }
                        },
                        "group_title": {
                            "title": __('app.js.group_title'),
                            "type": "string",
                            "readonly": disableEdit,
                            "options": {
                                "grid_columns": 6,
                                "inputAttributes": {
                                    "required": true
                                }
                            }
                        },
                        "image": {
                            "type": "string",
                            "title": __('app.js.common.image'),
                            "description": __('app.js.common.media_formats') + '. ' + __('app.js.common.recommended_dimensions') + ": 2480px x 1754px.",
                            "format": "url",
                            "readonly": disableEdit,
                            "options": {
                                "upload": {
                                    "title": __('app.js.common.upload_image'),
                                    "auto_upload": true,
                                    "upload_handler": "JSONEditorUploadHandler"
                                },
                                "containerAttributes": {
                                    "class": "col-md-12 image-required"
                                },
                                "inputAttributes": {
                                    "required": true
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
                        "title_bg_color": {
                            "title": __('app.js.common.title_color'),
                            "type": "string",
                            "format": "color",
                            "readonly": disableEdit,
                            "options": {
                                "grid_columns": 2,
                                "inputAttributes": {
                                    "required": true
                                }
                            }
                        },
                        "group_bg_color": {
                            "title": __('app.js.common.group_bg_color'),
                            "type": "string",
                            "format": "color",
                            "readonly": disableEdit,
                            "options": {
                                "grid_columns": 2,
                                "inputAttributes": {
                                    "required": true
                                }
                            }
                        },
                        "products": {
                            "type": "array",
                            "title": __('app.entity.products'),
                            "items": {
                                "type": "object",
                                "title": __('app.entity.product'),
                                "format": "grid",
                                "properties": {
                                    "product": {
                                        "title": __('app.entity.product'),
                                        "type": "integer",
                                        "format": "select2",
                                        "enumSource": [{
                                            "source": this.products.products,
                                            "title": "enumTitle",
                                            "value": "enumValue"
                                        }],
                                        "readonly": disableEdit,
                                        "options": {
                                            "grid_columns": 4,
                                            "select2": select2options,
                                            "inputAttributes": {
                                                "required": true
                                            }
                                        }
                                    },
                                    "subproducts": {
                                        "type": "array",
                                        "title": __('app.entity.subproducts'),
                                        "format": "grid",
                                        "items": {
                                            "type": "object",
                                            "properties": {
                                                "active": {
                                                    "type": "boolean",
                                                    "format": "checkbox",
                                                    "default": true,
                                                    "options": {
                                                        "hidden": !disableEdit
                                                    }
                                                },
                                                "subproduct_name": {
                                                    "type": "string",
                                                    "title": __('app.js.subproduct_name'),
                                                    "readonly": disableEdit,
                                                    "options": {
                                                        "grid_columns": 6,
                                                        "inputAttributes": {
                                                            "required": false
                                                        }
                                                    }
                                                },
                                                "id": {
                                                    "title": __('app.entity.subproduct'),
                                                    "type": "integer",
                                                    "format": "select2",
                                                    "watch": {
                                                        "productId": "product"
                                                    },
                                                    "enumSource": [{
                                                        "source": this.products.subproducts,
                                                        "title": "enumTitle",
                                                        "value": "enumValue",
                                                        "filter": "filterSubproducts"
                                                    }],
                                                    "readonly": disableEdit,
                                                    "options": {
                                                        "select2": select2options,
                                                        "inputAttributes": {
                                                            "required": true
                                                        }
                                                    }
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
                                        "title": __('app.js.columns'),
                                        "enum": [1, 2],
                                        "readonly": disableEdit,
                                        "options": {
                                            "grid_columns": 5,
                                            "inputAttributes": {
                                                "required": true
                                            }
                                        }
                                    },
                                    "product_image": {
                                        "type": "string",
                                        "title": __('app.js.common.product_image'),
                                        "description": __('app.js.common.media_formats') + '. ' + __('app.js.common.recommended_dimensions') + ": 2480px x 1754px.",
                                        "format": "url",
                                        "readonly": disableEdit,
                                        "options": {
                                            "upload": {
                                                "title": __('app.js.common.upload_image'),
                                                "auto_upload": true,
                                                "upload_handler": "JSONEditorUploadHandler"
                                            },
                                            "containerAttributes": {
                                                "class": "col-md-12 image-required"
                                            },
                                            "inputAttributes": {
                                                "required": true
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
                                    "qr": {
                                        "type": "string",
                                        "title": __('app.js.qr_code'),
                                        "readonly": disableEdit,
                                        "options": {
                                            "grid_columns": 5,
                                            "inputAttributes": {
                                                "required": false
                                            }
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
                                    "treatment": {
                                        "type": "string",
                                        "title": __('app.js.treatment'),
                                        "readonly": disableEdit,
                                        "options": {
                                            "grid_columns": 5,
                                            "inputAttributes": {
                                                "required": false
                                            }
                                        }
                                    },
                                    "group_title": {
                                        "type": "string",
                                        "title": __('app.js.group_title'),
                                        "readonly": disableEdit,
                                        "options": {
                                            "grid_columns": 6,
                                            "inputAttributes": {
                                                "required": false
                                            }
                                        }
                                    },
                                    "group_line_color": {
                                        "type": "string",
                                        "title": __('app.js.group_line_color'),
                                        "format": "color",
                                        "readonly": disableEdit,
                                        "options": {
                                            "grid_columns": 5
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
            }
        };

        // Initialize the editor with a JSON schema
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
            } else {
                mForm.find('select[name*="[subproducts]"]').val('').change();
            }

            this.jsonEditor.on('addRow', editor => {
                $(editor.container).find('select[name*="[subproducts]"]').val('').change();
            });

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
