define(
    'message',
    ['jquery', 'bootstrap'],
    function($, Bootstrap) {
        return {
            showDialog: function(userId, message, sentCallback, cancelCallback) {
                var self = this;
                
                var $modal = $(
                    '<div class="modal fade">\
                        <div class="modal-dialog">\
                            <form action="/account/send-personal-message" class="modal-content" method="post">\
                                <div class="modal-header">\
                                    <a class="close">×</a>\
                                    <h3 class="modal-title">Отправить личное сообщение</h3>\
                                </div>\
                                <div class="modal-body">\
                                    <textarea cols="65" rows="5" name="contents" placeholder="Текст сообщения" class="form-control"></textarea>\
                                </div>\
                                <div class="modal-footer">\
                                    <button class="btn btn-primary" data-loading-text="отправляется ..." data-complete-text="отправлено" data-send-text="отправить">отправить</button>\
                                    <button class="btn btn-default cancel">отменить</button>\
                                </div>\
                            </form>\
                        </div>\
                    </div>'
                );
                
                var $form = $modal.find('form');
                
                var $btnSend = $form.find('.btn-primary').button();
                var $btnCancel = $form.find('.cancel').button();
                var $textarea = $form.find('textarea');
                
                if (message) {
                    $textarea.val(message);
                }
                
                $modal.modal({
                    show: true
                });
                             
                $modal.on('hidden.bs.modal', function () {
                    $modal.remove();
                    if (cancelCallback) {
                        cancelCallback();
                    }
                });
                $modal.on('shown.bs.modal', function () {
                    $textarea.focus();
                });
                
                
                $textarea.bind('change keyup click', function() {
                    $textarea.parent().removeClass('error');
                    $btnSend.text('отправить').removeClass('btn-success').prop('disabled', $(this).val().length <= 0);
                }).triggerHandler('change');
                
                $form.find('button.cancel, a.close').on('click', function(e) {
                    e.preventDefault();
                    $modal.modal('hide');
                });
                
                $form.submit(function(e) {
                    e.preventDefault();
                    
                    var text = $textarea.val();
                    
                    if (text.length <= 0) {
                        $textarea.parent().addClass('error');
                    } else {
                        $btnSend.button('loading');
                        $btnCancel.prop("disabled", 1);
                        $textarea.prop("disabled", 1);
                        
                        self.sendMessage(userId, text, function() {
                            $textarea.val('');
                            
                            $btnSend.button('reset').button('complete').addClass('btn-success disabled').prop("disabled", 1);
                            
                            $textarea.prop("disabled", 0);
                            $btnCancel.prop("disabled", 0);
                            
                            if (sentCallback) {
                                sentCallback();
                            }
                        });
                    }
                });
            },
            sendMessage: function(userId, text, success) {
                $.post('/account/send-personal-message', {user_id: userId, message: text}, function() {
                    if (success) {
                        success();
                    }
                }, 'json');
            },
            deleteMessage: function(id, success) {
                $.post('/account/delete-personal-message', {id: id}, function() {
                    if (success) {
                        success();
                    }
                });
            }
        }
    }
);