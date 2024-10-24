var jqueryNativeAjax;
var unloadingState = false;

$(document).ready(function () {
    $("body").css("overflow", "auto");
    $(window).bind("beforeunload", function () {
        unloadingState = true;
    });
    if ($('html.framed').length) {
        setInterval(function () {
            adjustIframe();
        }, 500);
    }

    jqueryNativeAjax = $.ajax;
    $.ajax = function () {
        var data = {};
        data[$('#csrf-name').attr('name')] = $('#csrf-name').attr('content');
        data[$('#csrf-value').attr('name')] = $('#csrf-value').attr('content');
        if (arguments[0].dataType == 'serialized') {
            delete arguments[0].dataType;
        } else if (arguments[0].enctype == 'multipart/form-data') {
            arguments[0].data.append($('#csrf-name').attr('name'), $('#csrf-name').attr('content'));
            arguments[0].data.append($('#csrf-value').attr('name'), $('#csrf-value').attr('content'));
        } else if (typeof arguments[0].data == "undefined") {
            arguments[0].data = data;
        } else {
            arguments[0].data = $.extend(true, data, arguments[0].data);
        }
        return jqueryNativeAjax.apply($, arguments);
    };
    CSRFToForm();
    $(document).ajaxError(function (e, xhr, settings, exception) {
        if (!unloadingState) {
            showError(__('app.js.error.ajax'));
        }
    });
    // Loading
    $(document).ajaxStop(function () {
        AdminUtils.hideLoading();
    });
    $(document).ajaxStart(function () {
        AdminUtils.showLoading();
    });
    $('.show-loading').click(function () {
        AdminUtils.showLoading();
    });
    $('form').submit(function () {
        AdminUtils.showLoading();
    }).onSubmitAddFieldWithEmptyImage();
    jQuery.validator.setDefaults({
        lang: __('app.js.lang.code'),
        scrollToTopOnError: false,
        invalidHandler: defaultFormValidationHandler,
        errorPlacement: function (error, element) {
            if (element.closest('.radio-inline, .radio-list, .form-check').length) {
                error.appendTo(element.closest('.form-item'));
            } else if (element.closest('.image-input-outline').length) {
                error.insertAfter(element.closest('.image-input-outline').parent().find('.form-text'));
            } else if (element.parent('.form-item').length) {
                error.appendTo(element.parent());
            } else if (element.parent('.input-icon-container, .input-group').length) {
                error.insertAfter(element.parent());
            } else if (element.is('.select2, .kt-select2, [data-select2-id]')) {
                error.insertAfter(element.parent().find('.select2-container'));
            } else if (element.is('.summernote, .summernote-air, summernote-content')) {
                error.insertAfter(element.parent().find('.note-editor'));
            } else {
                error.insertAfter(element);
            }
        }
    });
    $.validator.addMethod('money', function (value, element) {
        errorMsg = '';
        if (/^\d{0,10}(\.\d{0,2})?$/.test(value) === false) {
            errorMsg = __('app.js.error.money');
        }
        return !errorMsg.length;
    }, function () {
        return errorMsg;
    });
    $.validator.addMethod("greaterThan", function (value, element, min) {
        return value == null || value.length == 0 || parseInt(value) > parseInt($(min).val());
    }, __('app.js.error.greater_than')
    );
    $.validator.addMethod('checkPasswordRequirements', function (value, element, param) {
        errorMsg = '';
        if (value.length < 8) {
            errorMsg = __('app.js.error.password_length');
        } else if (!value.match(/[a-z]/)) {
            errorMsg = __('app.js.error.password_lower_case');
        } else if (!value.match(/[A-Z]/)) {
            errorMsg = __('app.js.error.password_upper_case');
        } else if (!value.match(/[0-9]/)) {
            errorMsg = __('app.js.error.password_number');
        } else if (!value.match(/[^a-zA-Z0-9]/)) {
            errorMsg = __('app.js.error.password_special_character');
        }

        return !errorMsg.length;
    }, function () {
        return errorMsg;
    });

    $.validator.addMethod('checkPinLength', function (value, element, param) {
        errorMsg = '';
        if (value.length != PIN_LENGTH) {
            errorMsg = __('app.js.error.pin_length');
        }

        return !errorMsg.length;
    }, function () {
        return errorMsg;
    });

    $.validator.addMethod("nifCifES", function (value, element) {
        if ($(element).attr('data-allow-dash') != null && value === '-') {
            return true;
        }

        if (this.optional(element)) {
            return true;
        }

        var PASSPORT_REGEX = /^[a-z]{1,3}[0-9]{6,8}[a-z]{0,2}?$/i;
        var DNI_REGEX = /^(\d{8})([A-Z])$/;
        var CIF_REGEX = /^([ABCDEFGHJKLMNPQRSUVW])(\d{7})([0-9A-J])$/;
        var NIE_REGEX = /^[XYZ]\d{7,8}[A-Z]$/;

        var sanitize = function (str) {
            return str.toUpperCase().replace(/\s/g, '').replace(/-/g, '');
        };

        var validateSpanishId = function (str) {
            str = sanitize(str);
            var valid = false;
            var type = spainIdType(str);
            switch (type) {
                case 'dni':
                    valid = validDNI(str);
                    break;
                case 'passport':
                    return true;
                    break;
                case 'cif':
                    valid = validCIF(str);
                    break;
                case 'nie':
                    valid = validNIE(str)
                    break;
            }
            return valid;
        };

        var spainIdType = function (str) {
            str = sanitize(str);
            if (str.match(DNI_REGEX)) {
                return 'dni';
            }
            if (str.match(CIF_REGEX)) {
                return 'cif';
            }
            if (str.match(NIE_REGEX)) {
                return 'nie';
            }
            if (str.match(PASSPORT_REGEX)) {
                return 'passport';
            }
        };

        var validDNI = function (str) {
            str = sanitize(str);
            var dniLetters = 'TRWAGMYFPDXBNJZSQVHLCKE';
            var letter = dniLetters.charAt(parseInt(str, 10) % 23);
            return letter === str.charAt(8);
        };

        var validNIE = function (nie) {
            var nie_prefix = nie.charAt(0);
            switch (nie_prefix) {
                case 'X':
                    nie_prefix = 0;
                    break;
                case 'Y':
                    nie_prefix = 1;
                    break;
                case 'Z':
                    nie_prefix = 2;
                    break;
            }
            return validDNI(nie_prefix + nie.substr(1));
        };

        var validCIF = function (str) {
            str = sanitize(str);
            if (!str || str.length !== 9) {
                return false;
            }
            var letters = ['J', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
            var digits = str.substr(1, str.length - 2);
            var letter = str.substr(0, 1);
            var control = str.substr(str.length - 1);
            var sum = 0;
            var i;
            var digit;
            if (!letter.match(/[A-Z]/)) {
                return false;
            }
            for (i = 0; i < digits.length; ++i) {
                digit = parseInt(digits[i]);
                if (isNaN(digit)) {
                    return false;
                }
                if (i % 2 === 0) {
                    digit *= 2;
                    if (digit > 9) {
                        digit = Math.floor(digit / 10) + (digit % 10);
                    }
                    sum += digit;
                } else {
                    sum += digit;
                }
            }
            sum %= 10;
            if (sum !== 0) {
                digit = 10 - sum;
            } else {
                digit = sum;
            }
            if (letter.match(/[ABEH]/)) {
                return String(digit) === control;
            }
            if (letter.match(/[NPQRSW]/)) {
                return letters[digit] === control;
            }
            return String(digit) === control || letters[digit] === control;
        };
        return validateSpanishId(value);
    }, __("app.js.error.nifCif"));

    $.validator.addMethod('checkIBANRequirements', function (value, element, param) {
        errorMsg = '';
        if (this.optional(element)) {
            return true;
        }

        var iban = value.replace(/ /g, "").toUpperCase(),
            ibancheckdigits = "",
            leadingZeroes = true,
            cRest = "",
            cOperator = "",
            countrycode, ibancheck, charAt, cChar, bbanpattern, bbancountrypatterns, ibanregexp, i, p;
        var minimalIBANlength = 5;
        if (iban.length < minimalIBANlength) {
            return false;
        }

        countrycode = iban.substring(0, 2);
        bbancountrypatterns = {
            "AL": "\\d{8}[\\dA-Z]{16}",
            "AD": "\\d{8}[\\dA-Z]{12}",
            "AT": "\\d{16}",
            "AZ": "[\\dA-Z]{4}\\d{20}",
            "BE": "\\d{12}",
            "BH": "[A-Z]{4}[\\dA-Z]{14}",
            "BA": "\\d{16}",
            "BR": "\\d{23}[A-Z][\\dA-Z]",
            "BG": "[A-Z]{4}\\d{6}[\\dA-Z]{8}",
            "CR": "\\d{17}",
            "HR": "\\d{17}",
            "CY": "\\d{8}[\\dA-Z]{16}",
            "CZ": "\\d{20}",
            "DK": "\\d{14}",
            "DO": "[A-Z]{4}\\d{20}",
            "EE": "\\d{16}",
            "FO": "\\d{14}",
            "FI": "\\d{14}",
            "FR": "\\d{10}[\\dA-Z]{11}\\d{2}",
            "GE": "[\\dA-Z]{2}\\d{16}",
            "DE": "\\d{18}",
            "GI": "[A-Z]{4}[\\dA-Z]{15}",
            "GR": "\\d{7}[\\dA-Z]{16}",
            "GL": "\\d{14}",
            "GT": "[\\dA-Z]{4}[\\dA-Z]{20}",
            "HU": "\\d{24}",
            "IS": "\\d{22}",
            "IE": "[\\dA-Z]{4}\\d{14}",
            "IL": "\\d{19}",
            "IT": "[A-Z]\\d{10}[\\dA-Z]{12}",
            "KZ": "\\d{3}[\\dA-Z]{13}",
            "KW": "[A-Z]{4}[\\dA-Z]{22}",
            "LV": "[A-Z]{4}[\\dA-Z]{13}",
            "LB": "\\d{4}[\\dA-Z]{20}",
            "LI": "\\d{5}[\\dA-Z]{12}",
            "LT": "\\d{16}",
            "LU": "\\d{3}[\\dA-Z]{13}",
            "MK": "\\d{3}[\\dA-Z]{10}\\d{2}",
            "MT": "[A-Z]{4}\\d{5}[\\dA-Z]{18}",
            "MR": "\\d{23}",
            "MU": "[A-Z]{4}\\d{19}[A-Z]{3}",
            "MC": "\\d{10}[\\dA-Z]{11}\\d{2}",
            "MD": "[\\dA-Z]{2}\\d{18}",
            "ME": "\\d{18}",
            "NL": "[A-Z]{4}\\d{10}",
            "NO": "\\d{11}",
            "PK": "[\\dA-Z]{4}\\d{16}",
            "PS": "[\\dA-Z]{4}\\d{21}",
            "PL": "\\d{24}",
            "PT": "\\d{21}",
            "RO": "[A-Z]{4}[\\dA-Z]{16}",
            "SM": "[A-Z]\\d{10}[\\dA-Z]{12}",
            "SA": "\\d{2}[\\dA-Z]{18}",
            "RS": "\\d{18}",
            "SK": "\\d{20}",
            "SI": "\\d{15}",
            "ES": "\\d{20}",
            "SE": "\\d{20}",
            "CH": "\\d{5}[\\dA-Z]{12}",
            "TN": "\\d{20}",
            "TR": "\\d{5}[\\dA-Z]{17}",
            "AE": "\\d{3}\\d{16}",
            "GB": "[A-Z]{4}\\d{14}",
            "VG": "[\\dA-Z]{4}\\d{16}"
        };
        bbanpattern = bbancountrypatterns[countrycode];
        if (typeof bbanpattern !== "undefined") {
            ibanregexp = new RegExp("^[A-Z]{2}\\d{2}" + bbanpattern + "$", "");
            if (!(ibanregexp.test(iban))) {
                errorMsg = __("app.js.error.iban_exists");
                return false;
            }
        }

        ibancheck = iban.substring(4, iban.length) + iban.substring(0, 4);
        for (i = 0; i < ibancheck.length; i++) {
            charAt = ibancheck.charAt(i);
            if (charAt !== "0") {
                leadingZeroes = false;
            }
            if (!leadingZeroes) {
                ibancheckdigits += "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ".indexOf(charAt);
            }
        }

        for (p = 0; p < ibancheckdigits.length; p++) {
            cChar = ibancheckdigits.charAt(p);
            cOperator = "" + cRest + "" + cChar;
            cRest = cOperator % 97;
        }
        return !errorMsg.length;
    }, function () {
        errorMsg = __("app.js.error.iban");
        return errorMsg;
    });
    $.validator.addMethod('maxFileSize', function (value, element, param) {
        errorMsg = '';
        if (element.files.length && (element.files[0].size / 1024 / 1024) > param) {
            errorMsg = __('app.js.error.videoMaxFileSize', [param + 'MB']);
        }
        return !errorMsg.length;
    }, function () {
        return errorMsg;
    });
    //$('.custom-datepicker input[required]').attr('readonly', 'readonly');

    if ($('.jscolor').length) {
        $('.jscolor').val('#999999')[0].jscolor.fromString('#999999');
    }

    $('.custom-datepicker').datepicker({
        language: 'i18n',
        templates: {
            leftArrow: '<i class="la la-angle-left"></i>',
            rightArrow: '<i class="la la-angle-right"></i>'
        },
    });
    $('.birth-datepicker').datepicker({
        format: "dd/mm/yyyy",
        language: 'i18n',
        templates: {
            leftArrow: '<i class="la la-angle-left"></i>',
            rightArrow: '<i class="la la-angle-right"></i>'
        },
        endDate: "-18Y",
        startDate: "-122Y"
    });
    $('.today-datepicker').datepicker({
        language: 'i18n',
        templates: {
            leftArrow: '<i class="la la-angle-left"></i>',
            rightArrow: '<i class="la la-angle-right"></i>'
        },
        endDate: "0d"
    });
    $('.custom-datetimepicker').datetimepicker({
        format: "dd/mm/yyyy HH:ii",
        autoclose: true,
        pickerPosition: 'bottom-left',
        language: 'i18n',
        weekStart: 1,
        fontAwesome: 'font-awesome'
    });
    $('textarea.textarea-autosize').each(function () {
        autosize($(this));
    });
    ShowPassword();
    InitSummernote();
    $('.kt-select2').each(function () {
        initSelect2($(this));
    });
    $('[data-modal-iframed]').each(function () {
        $(this).on('click', function () {
            var modal = $('#modal-iframe');
            modal.find('.modal-title').text($(this).text());
            modal.find('iframe').attr('src', '/app/page/' + $(this).attr('data-modal-iframed'))
            modal.modal('show');
        });
    });
    $('form').find('input[type="checkbox"], input[type="radio"]').each(function () {
        $(this).attr('tabindex', '-1');
    });
    $('.datatables-global-search-filter').closest('form').find('input[type="text"]').keypress(function (e) {
        if (e.which == 13) {
            e.preventDefault();
            e.stopPropagation();
            $(this).closest('form').find('.btn[data-action="advanced-search"]').click();
        }
    });
    InitClamp();
    $('[data-js]').each(function () {
        const className = $(this).attr('data-js');
        const classInstance = "i" + className;
        eval(`window.${classInstance} = new ${className}()`);
        eval(`window.${classInstance}.ready()`);
    });
    $('[data-datatable-select-ajax]').each(function () {
        select2AjaxSearch($(this), $(this).attr('data-datatable-select-ajax'));
    });

    JSONEditor.defaults.options.theme = 'bootstrap5';
    if (__('app.js.lang.code') != 'en') {
        JSONEditor.defaults.language = 'i18n';
    }
    JSONEditor.defaults.options.iconlib = "fontawesome5";
    JSONEditor.defaults.callbacks.upload = {
        "JSONEditorUploadHandler": function (jseditor, type, file, cbs) {
            // Obtener el field
            var field = type.split('.').slice(-1).pop();
            var fd = new FormData();
            fd.append('file', file);
            fd.append('field', field);
            AdminUtils.showLoading();
            $.ajax({
                url: '/app/file/upload_file',
                type: 'post',
                enctype: 'multipart/form-data',
                data: fd,
                contentType: false,
                processData: false,
                success: function (response) {
                    cbs.updateProgress(100);
                    cbs.success('/app/file/get/' + response.filename);
                    AdminUtils.hideLoading();
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    cbs.failure(textStatus);
                    AdminUtils.hideLoading();
                }
            });
        }
    }
});
function InitClamp() {
    ReloadClamp();
    $(window).resize(function () {
        ReloadClamp();
    });
}

function ReloadClamp() {
    $('.clamp-readmore').off('click').click(function () {
        var clampContent = $(this).parent().find('.clamp-content');
        if (clampContent.hasClass('clamp-content-expanded')) {
            $(this).text(__('app.js.common.readmore'));
            clampContent.removeClass('clamp-content-expanded');
        } else {
            $(this).text(__('app.js.common.readless'));
            clampContent.addClass('clamp-content-expanded');
        }
    });
    $('.clamp-content').each(function () {
        var element = $(this)[0];
        // Requiere botón leer más
        if ((element.offsetHeight < element.scrollHeight) || (element.offsetWidth < element.scrollWidth)) {
            $(this).parent().find('.clamp-readmore').addClass('d-inline-block').text(__('app.js.common.readmore'));
        } else {
            $(this).removeClass('clamp-content-expanded');
            $(this).parent().find('.clamp-readmore').removeClass('d-inline-block');
        }
    });
}



function InitSummernote(selector = null) {
    if (selector == null) {
        selector = '.summernote-air';
    }

    if ($(selector).length) {
        $(selector).each(function () {
            $(this).summernote({
                airMode: false,
                lang: __('app.js.lang.code_extended'),
                disableDragAndDrop: true,
                callbacks: {
                    onPaste: function (e) {
                        summernoteRemoveHtml(e);
                    }
                },
                toolbar: [
                    ['style', ['bold', 'italic', 'underline', 'clear']],
                    ['font', ['strikethrough', 'superscript', 'subscript']],
                    ['para', ['ul', 'paragraph']],
                    ['view', ['codeview']],
                ]
            });
        });
        if ($(this).attr('data-summernote-height') != 'undefined') {
            $(this).siblings('.note-editor').find('> .note-editing-area .note-editable').css('min-height', $(this).attr('data-summernote-height') + 'px');
        }
    }

    if ($('.summernote-content').length) {
        $('.summernote-content').each(function () {
            var colors = [
                ['#6babe5', '#94ccaa', '#4b4b4d', '#000000', '#ffffff', '#ffff00', '#ff0000', '#e3ecf9']
            ];
            $(this).summernote({
                airMode: false,
                lang: __('app.js.lang.code_extended'),
                disableDragAndDrop: true,
                callbacks: {
                    onPaste: function (e) {
                        summernoteRemoveHtml(e);
                    }
                },
                toolbar: [
                    ['style', ['style', 'bold', 'italic', 'underline', 'clear']],
                    ['font', ['strikethrough', 'superscript', 'subscript']],
                    ['color', ['color']],
                    ['para', ['ul', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link']],
                    ['view', ['codeview']]
                ],
                styleTags: ['p', 'h1', 'h2', 'h3', 'h4', 'h5',
                    { title: __('app.js.summernote.small'), tag: 'div', className: 'small-text', value: 'div' },
                    { title: __('app.js.summernote.important_text_1'), tag: 'div', className: 'important-text-1', value: 'div' },
                    { title: __('app.js.summernote.important_text_2'), tag: 'div', className: 'important-text-2', value: 'div' },
                ],
                colors: colors,
                colorsName: colors
            });
            if ($(this).attr('data-summernote-height') != 'undefined') {
                $(this).siblings('.note-editor').find('> .note-editing-area .note-editable').css('min-height', $(this).attr('data-summernote-height') + 'px');
            }
        });
    }


    if ($('.summernote').length) {
        $('.summernote').each(function () {
            $(this).summernote({
                dialogsInBody: true,
                lang: __('app.js.lang.code_extended'),
                height: 100,
                disableDragAndDrop: true,
                callbacks: {
                    onPaste: function (e) {
                        summernoteRemoveHtml(e);
                    }
                },
                toolbar: [
                    ['style', ['style', 'bold', 'italic', 'underline', 'clear']],
                    ['font', ['strikethrough', 'superscript', 'subscript']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol']],
                    ['insert', ['link']],
                    ['view', ['codeview']],
                ]
            });
        });
    }

    $('.summernote, .summernote-air, .summernote-content').siblings('.note-editor').on('drop', function () {
        return false;
    });
    $('.card-breadcumb').closest('.card').first().addClass('sticky-card');
    $(window).scroll(function () {
        if ($('html.framed').length) {
            return;
        }

        if ($(window).scrollTop() + $(window).height() == $(document).height()) {
            $('body').addClass('scroll-bottom');
        } else {
            $('body').removeClass('scroll-bottom');
        }

        if ($(window).width() > 500) {
            if ($('.sticky-card:not(.is-pinned)').outerHeight() > ($(document).height() - $(window).height() - $(window).scrollTop())) {
                return;
            }
            $('.sticky-card').toggleClass('is-pinned', $(window).scrollTop() > $('.app-header').height());
        }

    }).scroll();

}

function initSelect2(item) {
    var settings = {
        language: __('app.js.lang.code'),
        placeholder: item.attr('data-placeholder'),
    }

    if (item.find('option[value=""]').length) {
        settings.allowClear = true;
    }

    if (item.closest('.modal').length) {
        settings.dropdownParent = item.closest('.modal');
    }

    item.select2(settings);
}

function ShowPassword() {
    $('.show-password').on('click', function () {
        var input = $(this).siblings('input');
        $(this).find('i').addClass('d-none');
        if (input.attr('type') == 'password') {
            input.attr('type', 'text');
            $(this).find('.hide-icon').removeClass('d-none');
        } else {
            input.attr('type', 'password');
            $(this).find('.show-icon').removeClass('d-none');
        }
    });
}

function CSRFToForm() {
    $('form[method="post"]').each(function () {
        $(this).find('[name="' + $('#csrf-name').attr('name') + '"]').remove();
        $(this).find('[name="' + $('#csrf-value').attr('name') + '"]').remove();
        $(this).prepend('<input type="hidden" name="' + $('#csrf-name').attr('name') + '" value="' + $('#csrf-name').attr('content') + '">');
        $(this).prepend('<input type="hidden" name="' + $('#csrf-value').attr('name') + '" value="' + $('#csrf-value').attr('content') + '">');
    });
}



function formatFileSize(fileSize) {
    var units = ['Kb', 'Mb', 'Gb'];
    var s = fileSize;
    for (var i = 0; i < units.length; i++) {
        s = s / 1024;
        if (((s / 1024) < 1) || (i == (units.length - 1))) {
            return s.toFixed(2) + units[i];
        }
    }
}

function addDateUpdatedTimestampParam(data) {
    return '?t=' + getDateTimestamp(data.date_updated);
}

function formatDate(date) {
    if (date == null || !date.length) {
        return '';
    }
    return moment(date, 'YYYY-MM-DD HH:mm:ss').format('DD/MM/YYYY');
}

function getDateTimestamp(date) {
    if (date == null || !date.length) {
        return '';
    }
    return moment(date, 'YYYY-MM-DD HH:mm:ss').format('X');
}

function formatDateWithTime(date) {
    if (date == null || !date.length) {
        return '';
    }
    return moment(date, 'YYYY-MM-DD HH:mm:ss').format('DD/MM/YYYY HH:mm');
}

function calculateAge(birthday) {
    var start = new Date(moment(birthday, "DD/MM/YYYY"));
    var diff_ms = Date.now() - start.getTime();
    var age_dt = new Date(diff_ms);

    return Math.abs(age_dt.getUTCFullYear() - 1970);
}


function testShowLoading() {
    $.post('testloading', function (data) {
        console.log(data);
    });
}

function textareaAutosize() {
    autosize.update($('textarea'));
    $('[data-toggle="tab"]').on('shown.bs.tab', function () {
        autosize.update($('textarea'));
    });
}

function adjustIframe(p) {
    if (window.frameElement === null) {
        return;
    }

    var iframeInParent = $(window.parent.document).find('#' + window.frameElement.id);

    if (iframeInParent[0].contentWindow.document.getElementById('kt_content_container') != null) {
        var height = iframeInParent[0].contentWindow.document.getElementById('kt_content_container').scrollHeight + 40;
    } else {
        var height = iframeInParent[0].contentWindow.document.documentElement.scrollHeight;
    }

    if (height != iframeInParent.height() && height != 0) {
        iframeInParent.height(height);
    }
}

function summernoteRemoveHtml(e) {
    var bufferText = ((e.originalEvent || e).clipboardData || window.clipboardData).getData('Text');
    e.preventDefault();
    setTimeout(function () {
        document.execCommand('insertText', false, bufferText);
    }, 10);
}

function defaultFormValidationHandler(event, validator) {
    AdminUtils.hideLoading();
    if (validator.numberOfInvalids()) {
        showWarning(__('app.js.common.attention'), __('app.js.common.form_errors'));
    }
}

function stepperInvalidFormValidationHandler(validator, stepper) {
    AdminUtils.hideLoading();
    if (validator.numberOfInvalids()) {
        showWarning(__('app.js.common.attention'), __('app.js.common.form_errors'));
        var stepIndex = $(validator.errorList[0].element).closest('[data-kt-stepper-element="content"]').index();
        stepper.goTo(stepIndex + 1);
    }
}

function addJsonEditorRemoveUploadBtn(editor, uploadFields = null) {
    var deleteFileBtn = '<button type="button" class="btn btn-sm btn-reset-upload json-editor-btn- btn-outline-secondary"><i class="fas fa-times-circle"></i></button>';
    if (uploadFields === null) {
        uploadFields = $('.form-control-file').closest('[data-schemapath]');
    }
    uploadFields.each(function () {
        $(this).find('.input-group-append').append(deleteFileBtn);
    });
    $('.btn-reset-upload').click(function () {
        var schemePath = $(this).closest('[data-schemapath]').attr('data-schemapath');
        editor.getEditor(schemePath).setValue('');
        editor.getEditor(schemePath).onChange(true);
        var parent = $(this).closest('[data-schemapath]');
        parent.find('a.d-inline-block').text('');
        parent.find('.je-upload-preview').remove();
    });
}


function hexToRgbA(hex, alpha) {
    if (hex == null) {
        return;
    }
    var c;
    if (/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)) {
        c = hex.substring(1).split('');
        if (c.length == 3) {
            c = [c[0], c[0], c[1], c[1], c[2], c[2]];
        }
        c = '0x' + c.join('');
        return 'rgba(' + [(c >> 16) & 255, (c >> 8) & 255, c & 255].join(',') + ',' + alpha + ')';
    }
    throw new Error('Bad Hex');
}

function formReadOnly(form, remove = true) {
    form.addClass('form-readonly');
    form.find('.hide-read-only').hide();
    form.find('.checkbox input').off();
    form.find('.form-switch').addClass('pe-none').find('input').on('click', function (e) {
        e.preventDefault();
        return false;
    });
    form.find('.form-button-label').addClass('readonly-disabled');
    form.find('.kt-select2').addClass('readonly-disabled');
    form.find('input[type="text"], input[type="number"], input[type="email"], select, textarea').prop('disabled', true).prop('readonly', true).addClass('readonly-disabled');
    $('.summernote-air, .summernote, .summernote-air-lazy, summernote-content').each(function () {
        $(this).summernote('disable');
        $(this).siblings('.note-editor').find('.note-toolbar .note-btn-group:not(.note-view)').remove();
        $(this).siblings('.note-editor').find('.btn-codeview').prop('disabled', false);
    });
    form.find('input[type="file"]').hide();
    if (remove) {
        form.find('[data-submit-mode]').closest('.dropdown').remove();
        form.find('.card-footer, .image-input [data-kt-image-input-action="cancel"], .image-input [data-kt-image-input-action="change"], [data-submit-mode], .btn-delete-file').remove();
    } else {
        form.find('[data-submit-mode]').closest('.dropdown').hide();
        form.find('.card-footer, .image-input [data-kt-image-input-action="cancel"], .image-input [data-kt-image-input-action="change"], [data-submit-mode], .btn-delete-file').hide();
    }
}

function formEditable(form) {
    form.removeClass('form-readonly');
    form.find('.hide-read-only').show();

    form.find('.form-button-label').removeClass('readonly-disabled');
    form.find('.kt-select2').removeClass('readonly-disabled');
    form.find('input[type="text"], input[type="number"], input[type="email"], select, textarea').prop('disabled', false).prop('readonly', false).removeClass('readonly-disabled');

    form.find('input[type="file"]').show();
    form.find('[data-submit-mode]').closest('.dropdown').show();
    form.find('.card-footer, .image-input [data-kt-image-input-action="cancel"], .image-input [data-kt-image-input-action="change"], [data-submit-mode]').show();
}


(function ($) {
    $.fn.setRandomId = function () {
        var randomId = 'r' + Math.floor((Math.random() * 1000000));
        $(this).attr('id', randomId);
    };
}(jQuery));
(function ($) {
    $('[data-kt-image-input-action="view"]').click(function () {
        var inputWrapper = $(this).closest('.image-input').find('.image-input-wrapper');
        var image = inputWrapper.css('background-image');
        var title = inputWrapper.attr('data-media-viewer-title').length ? inputWrapper.attr('data-media-viewer-title') : '';
        var imageSrc = image.substring(5, image.length - 2);
        $('#media-viewer #media-viewer-title').text(title);
        $('#media-viewer #media-viewer-image').attr('src', imageSrc);
        $('#media-viewer #media-viewer-download').click(function () {
            window.open(imageSrc, '_blank');
        });
        $('#media-viewer').modal('show');
    });
    $.fn.setImageUploaded = function (url, required) {
        var outline = $(this).removeAttr('required').closest('.image-input-outline');
        outline.find('.image-input-wrapper').css('background-image', "url('" + url + "')");
        if (!required) {
            outline.addClass('loaded-image').addClass('image-input-changed');
        }
        return $(this);
    };
    $.fn.setFileUploaded = function (url, required) {
        var outline = $(this).removeAttr('required').closest('.image-input-outline');
        if (outline.find('video').length) {
            outline.find('video').attr('src', url);
            outline.find('video').removeClass('d-none')[0].load();
        } else if (outline.is('[data-kt-image-input-uploaded]')) {
            outline.find('.image-input-wrapper').css('background-image', "url(" + outline.attr('data-kt-image-input-uploaded') + ")");
        } else {
            outline.find('.image-input-wrapper').css('background-image', "url(/app/img/uploaded200.jpg)");
        }

        if (!required) {
            outline.addClass('loaded-image').addClass('image-input-changed');
        }
        outline.find('.btn-media-download').off('click').show().click(function () {
            window.open(url, '_blank');
        });
        return $(this);
    };
}(jQuery));
(function ($) {
    $.fn.setDecimal = function (value, currency = '') {
        $(this).val(parseDecimal(value).replace('.', ','));
        if ($(this).val().length == 0 && value != null && String(value).length) {
            $(this).val(parseDecimal(value));
        }
        if (currency.length) {
            $(this).val($(this).val() + ' ' + currency);
        }
        return $(this);
    };
    $.fn.setDecimalHtml = function (value, currency = '') {
        $(this).html(parseDecimal(value).replace('.', ',') + ' ' + currency);
        return $(this);
    };
}(jQuery));
(function ($) {
    $.fn.removeDisabledOptions = function (disabledStatus) {
        if (disabledStatus == null) {
            disabledStatus = 'D';
        }
        $(this).find('[data-option-status="' + disabledStatus + '"]:not(:selected)').remove();
        return $(this);
    };
}(jQuery));
(function ($) {
    $.fn.disableDisabledOptions = function (disabledStatus) {
        if (disabledStatus == null) {
            disabledStatus = 'D';
        }
        $(this).find('[data-option-status="' + disabledStatus + '"]').each(function () {
            $(this).prop('disabled', !$(this).prop('selected'));
        });
        return $(this);
    };
}(jQuery));
(function ($) {
    $.fn.enableDisabledOptions = function (disabledStatus) {
        if (disabledStatus == null) {
            disabledStatus = 'D';
        }
        $(this).find('[data-option-status="' + disabledStatus + '"]').prop('disabled', false);
        return $(this);
    };
}(jQuery));

(function ($) {
    $.fn.fullResetForm = function (resetCheckboxes = true) {
        $(this).find('select').val('').change();
        $(this).find('.custom-datepicker, .custom-datetimepicker').each(function () {
            $(this).datepicker("clearDates");
        });

        if (resetCheckboxes) {
            $(this).find('[type="checkbox"]').removeAttr('checked').change();
        }

        $(this)[0].reset();
        return $(this);
    };
}(jQuery));

function parseDecimal(elm) {
    return parseFloat(elm).toFixed(2);
}

function removeDecimalRounded(elm) {
    return Math.round(elm);
}

function removeDecimalRoundedUp(elm) {
    return Math.ceil(elm);
}

function formatNumberWithDecimals(number, decimals = 2) {
    var decpoint = ',';
    let thousand = '.';

    var n = Math.abs(number).toFixed(decimals).split('.');
    n[0] = n[0].split('').reverse().map((c, i, a) =>
        i > 0 && i < a.length && i % 3 == 0 ? c + thousand : c
    ).reverse().join('');
    return (Math.sign(number) < 0 ? '-' : '') + n.join(decpoint);
}

function formatCurrency(number) {
    return formatNumberWithDecimals(number) + ' €';
}

(function ($) {
    $.fn.onSubmitAddFieldWithEmptyImage = function () {
        var mForm = $(this);
        $(this).on('submit', function () {
            $(this).find('.loaded-image [data-kt-image-input-action="cancel"]:not(:visible)').siblings('.image-input-wrapper').each(function () {
                if ($(this).css('background-image').includes('app/img')) {
                    var fieldName = $(this).closest('.image-input').find('input[type="file"]').attr('name');
                    if (fieldName.endsWith(']')) {
                        fieldName = fieldName.slice(0, -1) + '_removeemptyimage]';
                    } else {
                        fieldName += '_removeemptyimage';
                    }

                    mForm.append($('<input>', { type: 'hidden', name: fieldName, value: '1' }));
                }
            });
        });
    };
}(jQuery));
(function ($) {
    $.fn.uncheckableRadio = function () {
        this.each(function () {
            var id = $(this).attr('id');
            var that = $(this);
            $("label[for='" + id + "']").mousedown(function () {
                $(this).data('wasChecked', that.is(':checked'));
            })
                .click(function (event) {
                    if ($(this).data('wasChecked')) {
                        that.prop('checked', false);
                        event.preventDefault();
                    }
                });
        });
        return this.each(function () {
            $(this).mousedown(function () {
                $(this).data('wasChecked', this.checked);
            });
            $(this).click(function () {
                if ($(this).data('wasChecked'))
                    this.checked = false;
            });
        });
    };
})(jQuery);
function getFloatValue(value) {
    value = typeof value != 'undefined' && value != null ? value.toString() : '';
    if (value.length) {
        value = parseFloat(value.replace(',', '.'));
        if (!isNaN(value)) {
            return value;
        }
    }

    return 0;
}

function getIntValue(value) {
    value = typeof value != 'undefined' && value != null ? value.toString() : '';
    if (value.length) {
        value = parseInt(value);
        if (!isNaN(value)) {
            return value;
        }
    }

    return 0;
}

function clearDatatableLocalStorage() {
    for (key in localStorage) {
        if (key.startsWith('DataTables_')) {
            localStorage.removeItem(key);
        }
    }
}


function clearOldUserLocalStorage() {
    if (localStorage.getItem('web-push-token') != null) {
        $.post({
            url: '/app/delete_old_notification_token',
            global: false,
            data: {token: localStorage.getItem('web-push-token')},
            success: function (result) {
                localStorage.removeItem('web-push-token');
            }
        });
    }
}


function getValueOrEmpty(value, emptyValue = '') {
    return typeof value != 'undefined' && value != null && value != '' ? value : emptyValue;
}

function removeDisabledOptionsJson(result, selectedId) {
    selectedId = getValueOrEmpty(selectedId, '-1');

    result.forEach(function (item) {
        if (typeof item.children != 'undefined' && item.children.length) {
            item.children = item.children.filter(function (child) {
                return child.enabled == '1' || child.id == selectedId;
            });
        }
    });

    return result.filter(function (item) {
        var children = typeof item.children != 'undefined' && item.children.length ? item.children.map(function (c) {
            return c.id;
        }) : [];

        return item.enabled == '1' || (selectedId != '-1' && (item.id == selectedId || children.includes(selectedId)));
    });
}

function nl2br(str, is_xhtml) {
    var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br ' + '/>' : '<br>'; // Adjust comment to avoid issue on phpjs.org display
    return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
}


function userHasProfile(profiles) {
    var allowed = false;
    profiles.forEach(function (p) {
        if ($('body').hasClass('profile-' + p)) {
            allowed = true;
        }
    });
    return allowed;
}

function select2AjaxSearch(field, url, value, mapFunction, select2TemplateResult, select2TemplateSelection, minimumInputLength = 3, delay = 250) {
    if (typeof select2TemplateResult == 'undefined' || select2TemplateResult == null) {
        select2TemplateResult = function (data) {
            return data.text;
        };
    }

    if (typeof select2TemplateSelection == 'undefined' || select2TemplateSelection == null) {
        select2TemplateSelection = select2TemplateResult;
    }

    $(field).each(function () {
        var selectItem = $(this);
        selectItem.select2({
            ajax: {
                url: url,
                global: false,
                type: 'post',
                dataType: 'json',
                delay: delay,
                data: function (params) {
                    return {
                        searchTerm: params.term
                    };
                },
                processResults: function (response) {
                    if (typeof mapFunction != 'undefined' && mapFunction != null) {
                        mapFunction(response);
                    }

                    return {
                        results: response
                    };
                }
            },
            minimumInputLength: minimumInputLength,
            language: __('app.js.lang.code'),
            allowClear: selectItem.find('option[value=""]').length,
            templateResult: select2TemplateResult,
            templateSelection: select2TemplateSelection,
            escapeMarkup: function (m) {
                return m;
            },
            dropdownParent: selectItem.closest('.modal').length ? selectItem.closest('.modal') : $(document.body)
        });

        if (typeof value != 'undefined' && value != null && value != '') {
            $.ajax({
                type: 'post',
                global: true,
                url: url,
                data: { id: value },
                success: function (data) {
                    var option = new Option(select2TemplateSelection(data), data.id, false, true);
                    selectItem.append(option).trigger('change');

                    selectItem.trigger({
                        type: 'select2:select',
                        params: {
                            data: data
                        }
                    });
                }
            });
        }
    });
}

function select2Clients(field, url, value, ajaxSearch = false) {
    function select2TemplateResult(data) {
        if (typeof data.name == 'undefined') {
            return data.text;
        }

        block = '<div>' + data.client_name;
        if (data.name && data.surnames) block += '<strong class="d-block">' + data.name + " " + data.surnames + '</strong>';
        return block += '</div>';
    }

    var mapFunction = function (select2Data) {
        $.map(select2Data, function (obj) {
            obj.text = obj.client_name;
            return obj;
        });
    };

    if (ajaxSearch) {
        select2AjaxSearch(field, url, value, mapFunction, select2TemplateResult);
    } else {
        $.post(url, function (select2Data) {
            mapFunction(select2Data);
            select2Ajax(field, select2TemplateResult, select2Data, value);
        });
    }
}

function select2Users(field, url, value, notUnselectCurrentUser = true) {
    function select2Template(data) {
        if (typeof data.name == 'undefined') {
            return data.text;
        }

        return '<div class="select2-option-img">' +
            '<div><img src="/app/user/avatar/' + data.id + addDateUpdatedTimestampParam(data) + '"></div>' +
            '<div>' + data.name + ' ' + data.surnames + '</div>' +
            '</div>';
    }

    $.post(url, function (select2Data) {
        $.map(select2Data, function (obj) {
            obj.text = obj.name + ' ' + obj.surnames;
            return obj;
        });
        select2Ajax(field, select2Template, select2Data, value);

        if (notUnselectCurrentUser) {
            $(field).on('select2:unselecting', function (e) {
                if (!userHasProfile(['A']) && $('body').attr('data-user-id') == e.params.args.data.id) {
                    e.preventDefault();
                }
            });
        }
    });
}

function select2Ajax(field, select2TemplateResult, select2Data, value, select2TemplateSelection) {
    if (typeof select2TemplateSelection == 'undefined' || select2TemplateSelection == null) {
        select2TemplateSelection = select2TemplateResult;
    }

    $(field).each(function () {
        $(this).select2({
            data: select2Data,
            language: __('app.js.lang.code'),
            placeholder: $(this).attr('data-placeholder'),
            allowClear: $(this).find('option[value=""]').length,
            templateResult: select2TemplateResult,
            templateSelection: select2TemplateSelection,
            escapeMarkup: function (m) {
                return m;
            },
            dropdownParent: $(this).closest('.modal').length ? $(this).closest('.modal') : $(document.body)
        });

        if (typeof value != 'undefined' && value != null && value != '') {
            $(this).val(value).change();
        }
    });
}

function allowSaveInvalidCheckEmpty(input) {
    if (input.is('[type="radio"]') || input.is('[type="checkbox"]')) {
        input.toggleClass('empty-value', !input.prop('checked'));
    } else {
        input.toggleClass('empty-value', !input.val().length);
    }
}

const select2Badge = (item) => {
    if (!item.id) {
        return item.text;
    }

    var span = document.createElement('span');
    var badgeColor = $(item.element).data('badge-color');
    template = '<span class="badge fw-lighter" style="background-color: ' + hexToRgbA(badgeColor, 0.1) + '; color: ' + badgeColor + '">' + item.text + '</span>';
    span.innerHTML = template;

    return $(span);
}

function initFileVersionsList(mForm, deleteUrl) {
    mForm.find('.file-info-container .btn-delete-file').on('click', function () {
        var fileContainer = $(this).closest('.file-container');
        var fileId = $(this).attr('data-file-id');
        
        showConfirm(__('app.js.utils.delete_record'), __('app.js.utils.delete_record_text'), 'question', function () {
            $.post(deleteUrl + fileId, function (data) {
                if (typeof data.success != 'undefined' && data.success == '1') {
                    var fileInfoContainer = fileContainer.closest('.file-info-container');
                    fileContainer.remove();
                    if (!fileInfoContainer.find('.btn-delete-file').length) {
                        fileInfoContainer.find('.historical-title').remove();
                    }
                }
            });
        });
    });
}