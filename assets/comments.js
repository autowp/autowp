var $ = require('jquery');

var Comments = function(element) {
    var self = this;
    
    var $element = $(element);
    
    $element.find('[data-toggle="tooltip"]').tooltip();
    
    $element.find('.comment-remove-button').on('click', function(e) {
        e.preventDefault();
        
        var $message = $(this).closest('.message');
        var params = {
            comment_id: $message.data('id')
        };
        $.post('/comments/delete', params, function(json) {
            if (json.ok) {
                $message.addClass('deleted');
            } else {
                console.error(json.message);
            }
        }, 'json');
    });
    
    $element.find('.comment-restore-button').on('click', function(e) {
        e.preventDefault();
        
        var $message = $(this).closest('.message');
        var params = {
            comment_id: $message.data('id')
        };
        $.post('/comments/restore', params, function(json) {
            if (json.ok) {
                $message.removeClass('deleted');
            } else {
                console.error(json.message);
            }
        }, 'json');
    });
    
    $element.find('.message .vote').each(function() {
        var $vote = $(this);
        
        function postVote(value) {
            self.postVote($vote.data('id'), value, function(data) {
                var newVote = parseInt(data.vote);
                $vote.find('.value')
                    .text((newVote > 0 ? '+' : '') + newVote)
                    .removeClass('zero');
                
                if (value > 0) {
                    $vote.find('.vote-up').addClass('active');
                    $vote.find('.vote-down').removeClass('active');
                } else {
                    $vote.find('.vote-down').addClass('active');
                    $vote.find('.vote-up').removeClass('active');
                }
            });
        }
        
        $vote.find('.vote-down').on('click', function(e) {
            e.preventDefault();
            if (!$(this).hasClass('active')) {
                postVote(-1);
            }
        });
        
        $vote.find('.vote-up').on('click', function(e) {
            e.preventDefault();
            if (!$(this).hasClass('active')) {
                postVote(+1);
            }
        });
        
        $vote.find('a.value').on('click', function(e) {
            e.preventDefault();
            
            if ($(this).hasClass('zero')) {
                return;
            }
            
            var $modal = $(
                '<div class="modal fade">' +
                    '<div class="modal-dialog">' +
                        '<div class="modal-content">' +
                            '<div class="modal-header">' +
                                '<button type="button" data-dismiss="modal" class="close">×</button>' +
                                '<h3 class="modal-title">Голоса</h3>' +
                            '</div>' +
                            '<div class="modal-body"></div>' +
                            '<div class="modal-footer">' +
                                '<button data-dismiss="modal" class="btn btn-default">Закрыть</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
            
            var $body = $modal.find('.modal-body');
            
            $modal.modal();
            $modal.on('hidden.bs.modal', function() {
                $modal.remove();
            });
            
            var $btnClose = $modal.find('.btn-default');
            
            $btnClose.button('loading');
            $.get('/comments/votes', {id: $vote.data('id')}, function(html) {
                $body.html(html);
                $btnClose.button('reset');
            });
        });
    });
    
    var $form = $element.find('form.add-message');
    var $textarea = $form.find(':input[name=message]');
    var resolveText = 'fixed';
    
    $element.on('click', '.reply', function() {
        var $msg = $(this).closest('.message');
        $msg.children('.replies').prepend($form);
        $form.find(':input[name=parent_id]').val($msg.data('id'));
        $form.find(':input[name=resolve]').val('');
        
        if ($textarea.val() == resolveText) {
            $textarea.val('');
        }
    });
    
    $element.on('click', '.resolve', function() {
        var $msg = $(this).closest('.message');
        $msg.children('.replies').prepend($form);
        $form.find(':input[name=parent_id]').val($msg.data('id'));
        $form.find(':input[name=resolve]').val('1');
        
        if ($textarea.val().length === 0) {
            $textarea.val(resolveText);
        }
    });
    
    $form.find('.cancel .btn').on('click', function(e) {
        e.preventDefault();
        $form.find(':input[name=parent_id]').val('');
        $element.find('.form-holder').append($form);
        $form.find(':input[name=resolve]').val('');
        
        if ($textarea.val() == resolveText) {
            $textarea.val('');
        }
    });
};

$.extend(Comments.prototype, {
    completeModeratorAttention: function(id, success) {
        $.post('/comments/complete', {id: id}, function() {
            if (success) {
                success();
            }
        });
    },
    postVote: function(id, value, success) {
        var params = {
            id: id,
            vote: value
        };
        
        $.post('/comments/vote', params, function(json) {
            if (json.ok) {
                if (success) {
                    success(json);
                }
            } else {
                console.error(json.error);
            }
        }, 'json');
    }
});

module.exports = function(element) {
    new Comments(element);
};
