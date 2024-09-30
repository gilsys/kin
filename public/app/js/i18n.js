class I18nTranslator {
    constructor(lang) {
        this.lang = lang;
        this.translations = null;
        this.getTranslations();
    }

    static getInstance(lang) {
        if (!I18nTranslator.instance) {
            I18nTranslator.instance = new I18nTranslator(lang);
        }
        return I18nTranslator.instance;
    }

    getTranslations() {
        if (this.translations == null) {
            const that = this;
            $.ajax({
                url: '/app/i18n/' + this.lang + '/js?v=' + RESOURCES_VERSION,
                success: function (result) {
                    that.translations = result;
                },
                async: false,
                cache: true,
            });
        }
        return this.translations;
    }

    translate(text, args) {
        if (typeof this.translations[text] === 'undefined') {
            console.error('Missing JS translation: ' + text);
            return "¡¡¡" + text + "!!!";
        }
        return vsprintf(this.translations[text], args);
    }
}

function __(text) {
    const lang = $('html').attr('lang');
    const i18nTranslator = I18nTranslator.getInstance(lang);

    const args = Array.from(arguments);
    args.shift();
    return i18nTranslator.translate(text, args);
}