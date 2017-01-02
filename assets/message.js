var $ = require("jquery");

var markup = require('html!./message/modal.html');

module.exports = {
    showDialog: function(userId, message, sentCallback, cancelCallback) {
        var self = this;
        
        var $modal = $(markup);
        
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
};