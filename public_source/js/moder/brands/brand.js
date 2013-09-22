define(
    'moder/brands/brand',
    ['jquery', 'tinymce'],
    function($) {
        return {
            init: function(options) {
                $('textarea#description').tinymce({
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
            }
        }
    }
);