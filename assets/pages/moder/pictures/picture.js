define(
    ['jquery', 'crop-dialog'],
    function($, CropDialog) {
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
                
                
                $('.pick-area').on('click', function() {
                    var areaDialog;
                    var $btn = $(this);
                    var data = $btn.data('area');
                    if (!areaDialog) {
                        areaDialog = new CropDialog(
                            $.extend(data, {
                                minSize: [100, 100],
                                onSave: function(crop, callback) {
                                    data.crop = crop;
                                    $.post(data.saveUrl, crop, callback);
                                }
                            })
                        );
                    }
                    
                    areaDialog.show();
                });
                
                $('.perspective-selector').on('change', function() {
                    $.ajax({
                        method: 'PUT',
                        url: $(this).data('url'), 
                        data: {
                            perspective_id: $(this).val()
                        }
                    });
                });
            }
        };
    }
);