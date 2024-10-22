class RecipeForm {
    jsonEditor = null;

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

                $(form).find("[name='json_data']").val(JSON.stringify(that.jsonEditor.getValue()));

                return true;
            }
        });

        function changeStep(index, stepper) {
            var languageValid = !mForm.find("[name='main_language_id']").is(':visible') || mForm.find("[name='main_language_id']").valid();

            if (!languageValid) {
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

                mForm.find("[name='recipe_layout_id']").val(data.recipe_layout_id).change();

                that.jsonEditor.on('ready', function () {
                    that.jsonEditor.setValue(data.json_data);
                });

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

    initJsonEditor(layoutId) {
        if (this.jsonEditor != null) {
            this.jsonEditor.destroy();
        }

        if (layoutId == 1) {
            var properties = {
                "product": {
                    "title": "Product",
                    "type": "integer",
                    "enum": [1, 2, 3],
                    "options": {
                        "enum_titles": ["Product 1", "Product 2", "Product 3"],
                        "grid_columns": 6
                    }
                },
                "subproduct1": {
                    "title": "Subproduct",
                    "type": "integer",
                    "enum": [1, 2, 3],
                    "options": {
                        "enum_titles": ["Product 1 - 1", "Product 1 - 2", "Product 1 - 3"],
                        "dependencies": {
                            "product": 1
                        },
                        "grid_columns": 6
                    }
                },
                "subproduct2": {
                    "title": "Subproduct",
                    "type": "integer",
                    "enum": [4, 5, 6],
                    "options": {
                        "enum_titles": ["Product 2 - 1", "Product 2 - 2", "Product 2 - 3"],
                        "dependencies": {
                            "product": 2
                        },
                        "grid_columns": 6
                    }
                },
                "subproduct3": {
                    "title": "Subproduct",
                    "type": "integer",
                    "enum": [7, 8, 9],
                    "options": {
                        "enum_titles": ["Product 3 - 1", "Product 3 - 2", "Product 3 - 3"],
                        "dependencies": {
                            "product": 3
                        },
                        "grid_columns": 6
                    }
                },
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
            "schema": {
                "type": "object",
                "format": "grid-strict",
                "properties": properties
            }
        });
    }
}
