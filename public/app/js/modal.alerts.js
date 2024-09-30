window.showAlert = function (title, message) {
    swal.fire(title, message);
};

window.alert = window.showAlert;

window.showDanger = function (title, message) {
    swal.fire(title, message, 'error');
};

window.showError = function (title, message) {
    swal.fire(title, message, 'error');
};

window.showWarning = function (title, message) {
    swal.fire(title, message, 'warning');
};

window.showSuccess = function (title, message) {
    swal.fire(title, message, 'success');
};

window.showInfo = function (title, message) {
    swal.fire(title, message, 'info');
};

window.showConfirm = function (title, message, type, yesCallback, noCallback, yesLabel, noLabel) {
    swal.fire({
        title: title,
        html: message,
        icon: (typeof type != 'undefined' ? type : 'question'),
        showCancelButton: true,
        confirmButtonText: (typeof yesLabel != 'undefined' && yesLabel != null && yesLabel != '' ? yesLabel : __('app.js.common.yes')),
        cancelButtonText: (typeof noLabel != 'undefined' && noLabel != null && noLabel != '' ? noLabel : __('app.js.common.no')),
        reverseButtons: true
    }).then(function (result) {
        if (result.value && yesCallback) {
            yesCallback();
            // result.dismiss can be 'cancel', 'overlay',
            // 'close', and 'timer'
        } else if (result.dismiss === 'cancel' && noCallback) {
            noCallback();
        }
    });
};

window.showMessageInline = function (message, targetBefore) {
    $('#message-inline').removeClass('d-none').find('div').html(message);
    if (targetBefore != null) {
        $('#message-inline').insertAfter(targetBefore);                
    }

};

window.addMessageInline = function (message, targetBefore, id) {
    var messageInlineCloned = $('#message-inline').clone();
    messageInlineCloned.removeClass('d-none').find('div').html(message);    
    messageInlineCloned.attr('id', id);
    messageInlineCloned.insertAfter(targetBefore);
};

window.confirm = window.showConfirm;