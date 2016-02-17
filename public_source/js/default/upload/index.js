define(
    ['jquery', 'bootstrap', 'crop-dialog'],
    function($, Bootstrap, CropDialog) {
        return {
            init: function(options) {
                
                var self = this;
                
                this.cropMsg = options.cropMsg;
                this.croppedToMsg = options.croppedToMsg;
                this.cropSaveUrl = options.cropSaveUrl;
                
                var $progress = $('.progress-area');
                this.$pictures = $('.pictures');
                $('form:not(.disable-ajax)').each(function() {
                    var $form = $(this);
                    $form.on('submit', function(e) {

                        e.preventDefault();

                        $progress.empty();

                        $form.hide();

                        var xhrs = [];

                        $form.find(':file').each(function() {
                            var $file = $(this),
                                name = $file.attr('name');
                            $.map(this.files, function(file) {

                                var formData = new FormData();

                                formData.append(name, file);

                                $form.find('textarea').each(function() {
                                    formData.append($(this).attr('name'), $(this).val());
                                });

                                var $bar = $(
                                    '<div class="progress">' +
                                        '<div class="progress-bar progress-bar-info" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">' +
                                            '<span class="file-name"></span> <span class="percentage"></span>' +
                                        '</div>' +
                                    '</div>'
                                );

                                $bar.appendTo($progress);
                                $bar.find('.file-name').text(file.fileName || file.name);

                                var UPLOAD_PART = 70,
                                    DOWNLOAD_PART = 30;

                                var originalXhr = $.ajaxSettings.xhr;
                                xhrs.push($.ajax({
                                    url: $form.attr('action'),
                                    data: formData,
                                    type: 'POST',
                                    contentType: false,
                                    processData: false,
                                    progress: function(e) {
                                        if (e.lengthComputable) {
                                            var value = UPLOAD_PART + Math.round(e.loaded / e.total * DOWNLOAD_PART);
                                            var percent = value + '%';
                                            $bar.find('.progress-bar').width(percent).attr('aria-valuenow', value);
                                            $bar.find('.percentage').text(percent);
                                        }
                                    },
                                    progressUpload: function(e) {
                                        if (e.lengthComputable) {
                                            var value = Math.round(e.loaded / e.total * UPLOAD_PART);
                                            var percent = value + '%';
                                            $bar.find('.progress-bar').width(percent).attr('aria-valuenow', value);
                                            $bar.find('.percentage').text(percent);
                                        }
                                    },
                                    success: function(data) {
                                        $bar.find('.progress-bar')
                                            .removeClass('progress-bar-info')
                                            .addClass('progress-bar-success')
                                            .width('100%')
                                            .attr('aria-valuenow', '100');

                                        if (data) {
                                            var $container = $bar.find('.percentage').empty();
                                            /*$.map(data, function(url) {
                                                $('<p />')
                                                    .append(
                                                        $('<a />').attr('href', url).text(url)
                                                    )
                                                    .insertAfter($bar);
                                            });*/
                                            
                                            $bar.fadeOut(function() {
                                                $(this).remove();
                                            });
                                            
                                            $.map(data, function(picture) {
                                                self.insertPicture(picture);
                                            });
                                        }
                                    },
                                    error :function(data) {
                                        $bar.find('.progress-bar')
                                            .removeClass('progress-bar-info')
                                            .addClass('progress-bar-danger')
                                            .width('100%');
                                        var errorMessage;
                                        if (data.responseJSON) {
                                            var errors = [];
                                            $.map(data.responseJSON, function(field) {
                                                $.map(field, function(error) {
                                                    errors.push(error);
                                                });
                                            });
                                            errorMessage = errors.join("\n");
                                        } else {
                                            errorMessage = data.statusText;
                                        }
                                        $bar.find('.percentage').text("Error: " + errorMessage);
                                    },
                                    xhr: function() {
                                        var req = originalXhr(), that = this;
                                        if (req) {
                                            if (typeof req.addEventListener == "function" && that.progress !== undefined) {
                                                req.addEventListener("progress", function(evt) {
                                                    that.progress(evt);
                                                }, false);
                                            }
                                            if (typeof req.upload == "object" && that.progressUpload !== undefined) {
                                                req.upload.addEventListener("progress", function(evt) {
                                                    that.progressUpload(evt);
                                                }, false);
                                            }
                                        }
                                        return req;
                                    }
                                }));
                            });
                        });

                        $.when.apply($, xhrs).then(function() {
                            $form.show();
                            $form[0].reset();
                        }, function() {
                            $form.show();
                            $form[0].reset();
                        })
                    });
                });
            },
            insertPicture: function(picture) {
                var self = this;
                var $picture = $(picture.html);
                
                $picture.addClass('col-lg-2 col-md-2 col-sm-2');
                
                var $row = this.$pictures.find('.row').filter(function() {
                    return $(this).find('.picture-preview').length < 6;
                }).first();
                
                if ($row.length <= 0) {
                    $row = $('<div class="row"></div>').appendTo(this.$pictures);
                }
                
                $row.append($picture);
                
                var cropDialog;
                
                var $cropBtn = $('<a href="#"></a>')
                    .text(this.cropMsg)
                    .prepend('<i class="fa fa-crop"></i> ')
                    .on('click', function(e) {
                        e.preventDefault();
                        
                        if (!cropDialog) {
                            cropDialog = new CropDialog({
                                sourceUrl: picture.src,
                                crop: {
                                    x: 0, 
                                    y: 0, 
                                    w: picture.width, 
                                    h: picture.height
                                },
                                width: picture.width,
                                height: picture.height,
                                onSave: function(crop, callback) {
                                    var params = $.extend(crop, {
                                        id: picture.id
                                    });
                                    $.post(self.cropSaveUrl, params, function(json) {
                                        var cropStr = Math.round(crop.w) + '×' 
                                                    + Math.round(crop.h) + '+' 
                                                    + Math.round(crop.x) + '+' 
                                                    + Math.round(crop.y);
                                        
                                        $cropBtn
                                            .text(self.croppedToMsg.replace('%s', cropStr))
                                            .prepend('<i class="fa fa-crop"></i> ');
                                        
                                        $picture.find('img').attr('src', json.src + '?' + Math.random());
                                        
                                        cropDialog.hide();
                                        
                                        callback();
                                    });
                                }
                            });
                        }
                        
                        cropDialog.show();
                    });
                
                $picture.append($cropBtn);
            }
        }
    }
);