define(
    ['jquery', 'bootstrap', 'crop-dialog'],
    function($, Bootstrap, CropDialog) {
        return {
            init: function(options) {
                
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
                
                var cropDialog;
                $('.crop').on('click', function() {
                    if (!cropDialog) {
                        cropDialog = new CropDialog({
                            sourceUrl: options.sourceUrl,
                            crop: options.crop,
                            width: options.width,
                            height: options.height,
                            onSave: function(crop, callback) {
                                $.post(options.cropSaveUrl, crop, callback);
                            }
                        });
                    }
                    
                    cropDialog.show();
                });
            }
        }
    }
);