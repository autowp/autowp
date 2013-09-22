define(
    'comments',
    ['jquery'],
    function($) {
        
        var Comments = function(element) {
            
            var $element = $(element);
            
            var itemId = $element.data('commentsItemId');
            var type = $element.data('commentsType');
            
            $element.find('.moderator-attention-sign').each(function() {
                var $block = $(this);
                var id = $block.data('id');
                var $btn = $('.btn', this);
                
                $btn.on('click', function() {
                    
                    $btn.button('loading');
                    
                    var authorId = $block.data('authorId');
                    
                    if (authorId) {
                        require(['message'], function(Message) {
                            var message = window.location + "\nfixed";
                            
                            Message.showDialog(authorId, message, function() {
                                $.post('/comments/complete', {id: id}, function() {
                                    $block.remove();
                                    $btn.button('reset');
                                });
                            }, function() {
                                $btn.button('reset');
                            });
                        });
                    } else {
                        $.post('/comments/complete', {id: id}, function() {
                            $btn.button('reset');
                            $block.remove();
                        });
                    }
                });
            });
            
            $element.find('.remove-all-comments').on('click', function() {
                var self = this;
                $.post('/comments/delete-all', {item_id: itemId, type: type}, function(json) {
                    $(self).hide();
                    $element.find('.message').remove()
                }, 'json');
            });
            
            $element.find('.comment-remove-button').on('click', function() {
                var node = this; 
                var id = parseInt($(node).attr('id').replace('comment-remove-button-', ''));
                $.post('/comments/delete', {comment_id: id}, function(json) {
                    if (json.ok) {
                        $(node).parents('.message:first').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        window.alert(json.message);
                    }
                }, 'json');
                return false; 
            });
        };
        
        $.extend(Comments.prototype, {
            completeModeratorAttention: function(id, success) {
                $.post('/comments/complete', {id: id}, function() {
                    if (success) {
                        success();
                    }
                });
            }
        });
        
        return function(element) {
            new Comments(element);
        };
    }
);