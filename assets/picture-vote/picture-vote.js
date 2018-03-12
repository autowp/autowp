var $ = require('jquery');

require('./picture-vote.scss');

module.exports = function(element) {
    var $element = $(element);

    var pictureId = $element.data('id');

    var $voteUp = $element.find('.vote-up');
    var $voteDown = $element.find('.vote-down');

    $voteUp.on('click', function(e) {
        e.preventDefault();

        vote(1);
    });

    $voteDown.on('click', function(e) {
        e.preventDefault();

        vote(-1);
    });

    function vote(value) {
        $.ajax({
            method: 'PUT',
            url: '/api/picture-vote/' + pictureId,
            data: {
                value: value
            }
        }).done(function(data) {

            ga('send', 'event', 'vote', value > 0 ? 'like' : 'dislike');

            $element.find('.positive').text(data.positive).toggleClass('zero', data.positive <= 0);
            $element.find('.negative').text(data.negative).toggleClass('zero', data.negative <= 0);
            $voteUp.toggleClass('fa-thumbs-up', data.value > 0);
            $voteUp.toggleClass('fa-thumbs-o-up', data.value <= 0);
            $voteDown.toggleClass('fa-thumbs-down', data.value < 0);
            $voteDown.toggleClass('fa-thumbs-o-down', data.value >= 0);
        });
    }
};
