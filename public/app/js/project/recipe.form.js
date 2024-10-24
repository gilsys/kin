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
                if (jQuery.inArray($(form).attr('action').split('/').pop(), ['N', 'B']) === -1) {
                    localStorage.setItem('active_recipe_tab', stepper.getCurrentStepIndex());
                }

                //console.log(that.jsonEditor.validate()); return false;

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
        var firstInit = this.jsonEditor == null;

        if (this.jsonEditor != null) {
            this.jsonEditor.destroy();
        }

        if (layoutId == null || layoutId == '') {
            return;
        }

        var disableEdit = !userHasProfile(['A']);

        $('#json-content-form').toggleClass('disable-edit', disableEdit);

        window.JSONEditor.defaults.callbacks.template = {
            "filterSubproducts": (jseditor, e) => {
                return e.item.product_id == e.watched.productId;
            }
        };

        if (layoutId == 1) {
            var properties = {
                "title": {
                    "title": __('app.js.common.title'),
                    "type": "string",
                    "readonly": disableEdit,
                    "options": {
                        "grid_columns": 6
                    },
                },
                "color": {
                    "title": __('app.js.common.color'),
                    "type": "string",
                    "format": "color",
                    "readonly": disableEdit,
                    "options": {
                        "grid_columns": 6
                    }
                },
                "image": {
                    "type": "string",
                    "title": __('app.js.common.image'),
                    "description": "<em>(" + __('app.js.common.recommended_dimensions') + ": 2480px x 1754px)</em>",
                    "format": "url",
                    "readonly": disableEdit,
                    "options": {
                        "upload": {
                            "title": __('app.js.common.upload_image'),
                            "auto_upload": true,
                            "upload_handler": "JSONEditorUploadHandler"
                        },
                        "containerAttributes": {
                            "class": "col-md-12"
                        }
                    },
                    "links": [
                        {
                            "href": "{{self}}"
                        }
                    ]
                },
                "product": {
                    "title": __('app.entity.product'),
                    "type": "integer",
                    "format": "select2",
                    "enumSource": [{
                        "source": this.products.products,
                        "title": "{{item.name}}",
                        "value": "{{item.id}}"
                    }],
                    "readonly": disableEdit,
                    "options": {
                        "grid_columns": 4
                    }
                },
                "subproducts": {
                    "type": "array",
                    "minItems": 1,
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
                            "id": {
                                "title": __('app.entity.subproduct'),
                                "type": "integer",
                                "format": "select2",
                                "watch": {
                                    "productId": "product"
                                },
                                "enumSource": [{
                                    "source": this.products.subproducts,
                                    "title": "{{item.name}}",
                                    "value": "{{item.id}}",
                                    "filter": "filterSubproducts"
                                }],
                                "readonly": disableEdit
                            }
                        }
                    },
                    "options": {
                        "grid_columns": 8,
                        "disable_array_add": disableEdit,
                        "disable_array_delete": disableEdit,
                        "disable_array_delete_all_rows": disableEdit,
                        "disable_array_delete_last_row": disableEdit,
                        "disable_array_reorder": disableEdit,
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
                        "grid_columns": 6
                    },
                }
            };
        }

        // Initialize the editor with a JSON schema
        this.jsonEditor = new JSONEditor(document.getElementById('json-content-form'), {
            // Se añade para que en caso de ampliar el JSON, se carguen los campos que no estaban en la versión anterior. 
            // Realmente los campos NO son required.
            "required_by_default": true,
            "disable_edit_json": true,
            //"disable_properties": true,
            "no_additional_properties": true,
            //"show_errors": "always",
            "disable_array_delete_all_rows": true,
            "disable_array_delete_last_row": true,
            "schema": {
                "type": "object",
                "format": "grid-strict",
                "properties": properties
            }
        });

        this.jsonEditor.on('ready', () => {
            if (firstInit && this.jsonData != null) {
                this.jsonEditor.setValue(this.jsonData);
            }

            if (disableEdit) {
                setTimeout(() => {
                    $('#json-content-form').find('select').addClass('readonly-disabled');
                }, 0);
            }
        });
    }

    getProducts() {
        var mForm = $('#mt-recipe-form');

        $.post('/app/recipe/get_products', data => {
            this.products = data;

            if (this.jsonData != null) {
                mForm.find("[name='recipe_layout_id']").change();
            }
        });
    }
}
