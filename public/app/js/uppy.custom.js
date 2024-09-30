class UppyCustom {

    constructor(mForm, settings) {
        this.form = mForm;
        this.settings = $.extend({
            target: settings.target,
            inline: true,
            width: '100%',
            autoProceed: false,
            proudlyDisplayPoweredByUppy: false,
            hideCancelButton: true,
            doneButtonHandler: null,
            hideUploadButton: true,
            locale: settings.locale,
        }, settings);

        if (!$(settings.target).length) {
            // No uppy
            return;
        }

        document.querySelector('meta[name=viewport]').setAttribute('content', 'width=device-width, initial-scale=1, shrink-to-fit=no, maximum-scale=10.0, user-scalable=yes');

        this.uppy = new Uppy.Uppy()
            .use(Uppy.Dashboard, this.settings)
            .use(Uppy.XHRUpload, {
                endpoint: this.settings.uploadFileUrl, // Ruta al controlador que procesará la carga de imágenes
                fieldName: this.settings.fieldName // Nombre del campo de entrada de archivo
            });

        this.uppy.use(Uppy.ImageEditor, {target: Uppy.Dashboard});
        this.uppy.on('upload-success', (file, response) => {
            var currentVal = this.form.find('[name="file_ids"]').val().trim();
            if (currentVal !== '') {
                currentVal += ',';
            }

            currentVal += response.body.file_id;
            this.form.find('[name="file_ids"]').val(currentVal);
        });
    }

    findUppyFile(name) {
        var result = false;

        $(this.settings.target).closest('.card').find('.uppy-Dashboard-Item-name').each(function () {
            if ($(this).attr('title') == name) {
                result = $(this);
                return $(this);
            }
        });
        return result;
    }

    load(initialFiles) {
        // Añadimos campos especiales para tratar los elementos añadidos y borrados
        this.form.append('<input type="hidden" name="file_ids"><input type="hidden" name="delete_file_ids">');

        if (!initialFiles.length) {
            return;
        }

        const that = this;

        this.uppy.on('file-added', (file) => {
            if (!file.name.startsWith('(id:')) {
                return;
            }

            setTimeout(() => {
                var fileItem = this.findUppyFile(file.meta.name);
                if (fileItem) {
                    const downloadBtn = document.createElement('a');
                    downloadBtn.href = this.settings.downloadUrl + '/' + file.meta.storedId;
                    downloadBtn.innerHTML = '</br>' + __('app.js.common.download');
                    fileItem.append(downloadBtn);
                }
            }, 200);
        });

        // Configurar la función de eliminación de imágenes en Uppy
        this.uppy.on('file-removed', (file) => {
            if (typeof file.meta.storedId !== 'undefined') {
                var currentVal = this.form.find('[name="delete_file_ids"]').val().trim();
                if (currentVal !== '') {
                    currentVal += ',';
                }

                currentVal += file.meta.storedId;
                this.form.find('[name="delete_file_ids"]').val(currentVal);
            }
        });



        initialFiles.forEach(async function (file) {
            var fileData = null;

            //if (AdminUtils.isImage(file)) {
            const response = await fetch(that.settings.fetchFileUrl + '/' + file.id);
            fileData = await response.blob();
            //}

            that.uppy.addFile({
                id: file.id,
                name: "(id: " + file.id + ") - " + file.file,
                data: fileData,
                meta: {
                    storedId: file.id
                }
            });
        });
    }

    validate() {
        if (!$(this.settings.target).length) {
            this.settings.successCallback();
            return;
        }

        const that = this;
        this.uppy.off('file-removed');
        // Itera a través de los archivos en Uppy
        this.uppy.getFiles().forEach(function (file) {
            // Si el nombre del archivo comienza con "(id", remuévelo de Uppy
            if (file.name.startsWith('(id:')) {
                that.uppy.removeFile(file.id);
            }
        });
        this.uppy.upload().then((result) => {
            if (result.failed && result.failed.length > 0) {
                console.error(result.failed);
            } else {
                that.settings.successCallback();
            }
        });
    }
}