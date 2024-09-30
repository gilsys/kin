function AdminUtils() {}
;

AdminUtils.blockUI = null;

AdminUtils.doFormAction = function (url, id) {
    // Create a form and send the data by post
    var cForm = $('<form action="' + url + '" method="post"><input type="hidden" name="id" value="' + id + '"></form>').appendTo($('body'));
    CSRFToForm();
    AdminUtils.showLoading();
    cForm.submit();
};

AdminUtils.confirmDelete = function (url, id) {
    showConfirm(__('app.js.utils.delete_record'), __('app.js.utils.delete_record_text'), 'question', function () {
        // Create a form and send the data by post
        var cForm = $('<form action="' + url + '" method="post"><input type="hidden" name="id" value="' + id + '"></form>').appendTo($('body'));
        CSRFToForm();
        AdminUtils.showLoading();
        cForm.submit();
    });
};

AdminUtils.confirmDuplicate = function (url, id) {
    showConfirm(__('app.js.utils.duplicate_record'), __('app.js.utils.duplicate_record_text'), 'question', function () {
        // Create a form and send the data by post
        var cForm = $('<form action="' + url + '" method="post"><input type="hidden" name="id" value="' + id + '"></form>').appendTo($('body'));
        CSRFToForm();
        AdminUtils.showLoading();
        cForm.submit();
    });
};

AdminUtils.confirmAction = function (url, id, title, message) {
    showConfirm(title, message, 'question', function () {
        // Create a form and send the data by post
        var cForm = $('<form action="' + url + '" method="post"><input type="hidden" name="id" value="' + id + '"></form>').appendTo($('body'));
        CSRFToForm();
        AdminUtils.showLoading();
        cForm.submit();
    });
};

AdminUtils.sendPostForm = function (url, id) {
    // Create a form and send the data by post
    var cForm = $('<form action="' + url + '" method="post"><input type="hidden" name="id" value="' + id + '"></form>').appendTo($('body'));
    CSRFToForm();
    cForm.submit();
};

AdminUtils.showImage = function (object, image, imageType) {
    if (object !== null && image !== null) {
        var img = new Image();
        img.src = "data:" + imageType + ";charset=utf-8;base64, " + image;
        object.css("background-image", "url('" + img.src + "')")
    } else {
        object.css("background-image", "none")
    }
};

AdminUtils.swapClasses = function (object, class1, class2) {
    var element = $(object);
    if (element.hasClass(class1)) {
        element.removeClass(class1).addClass(class2);
    } else {
        element.removeClass(class2).addClass(class1);
    }
}

AdminUtils.setPublicImage = function (mForm, field, folder, data) {
    mForm.find("[name='" + field + "']").closest('.mt_filename').find('.kt-avatar__holder img').attr('src', '/upload/' + folder + '/' + field + '_' + data.id + "." + data[field].split('.').pop() + '?t=' + (new Date().getTime()));
}

AdminUtils.setStreamImage = function (mForm, field, folder, data) {
    mForm.find("[name='" + field + "']").closest('.mt_filename').find('.kt-avatar__holder img').attr('src', '/app/' + folder + '/' + field + '/' + data.id + '?t=' + (new Date().getTime()));
}

AdminUtils.showLoading = function () {
    if (AdminUtils.blockUI == null) {
        AdminUtils.blockUI = new KTBlockUI($('body')[0], {
            overlayColor: "#000000",
            message: '<div class="blockui-message"><span class="spinner-border text-primary"></span>' + __('app.js.common.loading') + '</div>'
        });
    }

    if (!AdminUtils.blockUI.isBlocked()) {
        AdminUtils.blockUI.block();
    }
}

AdminUtils.hideLoading = function () {
    if (AdminUtils.blockUI != null && AdminUtils.blockUI.isBlocked()) {
        AdminUtils.blockUI.release();
    }
}

AdminUtils.showDelayedAfterLoad = function (ElementClass = '.data-form') {
    setTimeout(function () {
        $(ElementClass).removeClass('d-none').hide().fadeIn();
    }, 500);
}

AdminUtils.saveBtnNoOptions = function (btn) {
    btn.removeClass('btn-rounded-grouped').removeClass('pe-2').next().remove();
}

AdminUtils.createCssClass = function (className, styleCode) {
    if ($('#' + className).length) {
        return;
    }
    var style = document.createElement('style');
    style.type = 'text/css';
    style.id = className;
    style.innerHTML = '.' + className + ' { ' + styleCode + ' }';
    document.getElementsByTagName('head')[0].appendChild(style);
}

AdminUtils.isImage = function (fileName){    
    return /\.(jpg|jpeg|png|gif|bmp|webp|tiff|svg)$/i.test(fileName);    
}

$(document).ready(function () {
    $('[data-submit-mode]').click(function () {
        var mForm = $(this).closest('form');
        if ($(this).attr('data-validate-all')) {
            mForm.addClass('data-validate-all');
        } else {
            mForm.removeClass('data-validate-all');
        }

        var action = mForm.attr('form-action');
        if (action == null) {
            action = mForm.attr('action');
            mForm.attr('form-action', action);
        }

        action = action.substring(0, action.lastIndexOf('/') + 1) + $(this).attr('data-submit-mode');
        mForm.attr('action', action);
        mForm.submit();
    });

    $('[data-submit]').click(function () {
        $(this).closest('form').submit();
    });

    $('[data-preview-url]').on('click', function () {
        var mForm = $(this).closest('form');
        mForm.attr('data-preview', 1);
        var oldAction = mForm.attr('action');

        mForm.attr('action', $(this).attr('data-preview-url'));
        mForm.attr('target', '_blank');
        mForm.submit();

        mForm.attr('action', oldAction);
        mForm.removeAttr('data-preview', '');
        mForm.removeAttr('target');
        AdminUtils.hideLoading();
    });
});


