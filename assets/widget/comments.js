var $ = require('jquery');

var Comments = function(element) {
    var self = this;

    var $element = $(element);

    $element.find('[data-toggle="tooltip"]').tooltip();

    $element.find('.comment-remove-button').on('click', function(e) {
        e.preventDefault();

        var $message = $(this).closest('.message');
        $.ajax({
            url: '/api/comment/' + $message.data('id'),
            method: 'PUT',
            data: {
                deleted: 1
            }
        }).then(function() {
            $message.addClass('deleted');
        });
    });

    $element.find('.comment-restore-button').on('click', function(e) {
        e.preventDefault();

        var $message = $(this).closest('.message');
        $.ajax({
            url: '/api/comment/' + $message.data('id'),
            method: 'PUT',
            data: {
                deleted: 0
            }
        }).then(function() {
            $message.removeClass('deleted');
        });
    });

    $element.find('.message .vote').each(function() {
        var $vote = $(this);

        function postVote(value) {
            self.postVote($vote.data('id'), value, function(newVote) {
                newVote = parseInt(newVote);
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

                ga('send', 'event', 'comment-vote', value > 0 ? 'like' : 'dislike');
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

            var $modal = $(require('./comments/votes.html'));

            var $body = $modal.find('.modal-body');

            $modal.modal();
            $modal.on('hidden.bs.modal', function() {
                $modal.remove();
            });

            // var $btnClose = $modal.find('.btn-secondary');

            // $btnClose.button('loading');
            $.get('/comments/votes', {id: $vote.data('id')}, function(html) {
                $body.html(html);
                // $btnClose.button('reset');
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

        if ($textarea.val() === resolveText) {
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

        if ($textarea.val() === resolveText) {
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
        $.ajax({
            method: 'PUT',
            url: '/api/comment/' + id,
            data: {
                user_vote: value
            }
        }).then(function() {
            $.getJSON('/api/comment/' + id, {fields: 'vote'}, function(json) {
                success(json.vote);
            });
        });
    }
});

module.exports = function(element) {
    new Comments(element);
};
