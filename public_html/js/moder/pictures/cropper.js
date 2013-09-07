define(
    'moder/pictures/cropper',
    ['jquery', 'jcrop'],
    function($) {
        return {
            init: function(options) {
                
                var crop = options.crop;
                $('#picture-to-crop').Jcrop({
                    onSelect: function(c) {
                        crop = c;
                    },
                    setSelect: [
                        options.crop.x,
                        options.crop.y,
                        options.crop.x + options.crop.w,
                        options.crop.y + options.crop.h
                    ],
                    aspectRatio: 4 / 3,
                    minSize: [400, 300],
                    boxWidth: options.width / 2,
                    boxHeight: options.height / 2,
                    trueSize: [options.width, options.height]
                });
                $('#save-crop').click(function() {
                    var $btn = $(this).button('loading');
                    $.post(options.saveUrl, crop, function() {
                        $btn.button('reset');
                    });
                });
                
            }
        }
    }
);