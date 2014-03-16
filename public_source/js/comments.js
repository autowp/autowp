define(
    'comments',
    ['jquery'],
    function($) {
        
        var Comments = function(element) {
            var self = this;
            
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
                        window.alert(json.message);
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
                        window.alert(json.message);
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
                    })
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
                        '<div class="modal fade">\
                            <div class="modal-dialog">\
                                <div class="modal-content">\
                                    <div class="modal-header">\
                                        <button type="button" data-dismiss="modal" class="close">×</button>\
                                        <h3 class="modal-title">Голоса</h3>\
                                    </div>\
                                    <div class="modal-body"></div>\
                                    <div class="modal-footer">\
                                        <button data-dismiss="modal" class="btn btn-default">Закрыть</button>\
                                    </div>\
                                </div>\
                            </div>\
                        </div>'
                    );
                    
                    var $body = $modal.find('.modal-body');
                    
                    $modal.modal();
                    $modal.on('hidden.bs.modal', function() {
                        $modal.remove();
                    });
                    
                    var $btnClose = $modal.find('.btn-default')
                    
                    $btnClose.button('loading');
                    $.get('/comments/votes/', {id: $vote.data('id')}, function(html) {
                        $body.html(html);
                        $btnClose.button('reset');
                    });
                });
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
                        window.alert(json.error);
                    }
                }, 'json');
            }
        });
        
        return function(element) {
            new Comments(element);
        };
    }
);