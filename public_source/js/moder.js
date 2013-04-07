function pictureModerPage(options) {
    $('textarea#copyrights').tinymce({
        // Location of TinyMCE script
        script_url : '/tiny_mce/tiny_mce_jquery.js',

        // General options
        theme : "advanced",
        plugins : "inlinepopups",

        // Theme options
        theme_advanced_buttons1 : "undo,redo,link,unlink",
        theme_advanced_buttons2 : "",
        theme_advanced_buttons3 : "",
        theme_advanced_buttons4 : "",
        theme_advanced_toolbar_location : "top",
        theme_advanced_toolbar_align : "left",
        theme_advanced_statusbar_location : "bottom",
        theme_advanced_resizing : true,

        // Example content CSS (should be your site CSS)
        content_css : "/css/styles.css",

        object_resizing : false,
        convert_fonts_to_spans : true,
        force_p_newlines : true,
        remove_trailing_nbsp : true,
        trim_span_elements : true,
        extended_valid_elements : "a[href|title]",
        language: "en"
    });
    
    $('.normalize').on('click', function() {
        var $btn = $(this),
            msg = 'Внимание! Качество картинки может пострадать! Вы уверены?';
        if (window.confirm(msg)) {
            $btn.button('loading');
            $.post(options.normalizeUrl, {}, function(json) {
                $btn.button('reset');
                window.location = window.location;
            }, 'json');
        }
    });
    
    $('.flop').on('click', function() {
        var $btn = $(this),
            msg = 'Внимание! Качество картинки может пострадать! Вы уверены?';
        if (window.confirm(msg)) {
            $btn.button('loading');
            $.post(options.flopUrl, {}, function(json) {
                $btn.button('reset');
                window.location = window.location;
            }, 'json');
        }
    });
    
    $('.files-repair').on('click', function() {
        var $btn = $(this);
        $btn.button('loading');
        $.post(options.filesRepairUrl, {}, function(json) {
            $btn.button('reset');
            window.location = window.location;
        }, 'json');
    });
    
    $('.files-correct-names').on('click', function() {
        var $btn = $(this);
        $btn.button('loading');
        $.post(options.filesCorrectNamesUrl, {}, function(json) {
            $btn.button('reset');
            window.location = window.location;
        }, 'json');
    });
}