define(
    'moder/pictures/picture',
    ['jquery', 'bootstrap', 'tinymce'],
    function($, Bootstrap, Tinymce) {
        return {
            init: function(options) {
                
                $('textarea#copyrights').tinymce({
                    // Location of TinyMCE script
                    script_url : '/tiny_mce/tinymce.min.js',

                    plugins: [
                        "link"
                    ],

                    // General options
                    theme: "modern",

                    menubar: false,
                    toolbar: "undo redo link unlink",
                    resize: true,

                    // Example content CSS (should be your site CSS)
                    //content_css : "/css/styles.css",

                    object_resizing : false,
                    convert_fonts_to_spans : true,
                    force_p_newlines : true,
                    //remove_trailing_nbsp : true,
                    //trim_span_elements : true,
                    valid_elements : "a[href|title],p",
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
        }
    }
);