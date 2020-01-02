var $ = require('jquery');
import i18next from 'i18next';

var Widget = function (element) {

    var $element = $(element);

    $element.on('click', '.btn-cancel-vote', function (e) {
        e.preventDefault();

        $.ajax({
            method: 'DELETE',
            url: $(this).closest('[data-module]').data('url')
        }).done(function () {
            window.location = window.location;
        });
    });

    $element.on('click', '[data-vote]', function (e) {
        e.preventDefault();

        var $this = $(this),
            reason = $this.data('reason'),
            vote = $this.data('vote');

        function send(reason, vote, save, done)
        {
            $.ajax({
                method: 'PUT',
                url: $this.closest('[data-module]').data('url'),
                data: {
                    reason: reason,
                    vote: vote,
                    save: save ? 1 : 0
                }
            }).done(done);
        }

        if (reason) {
            send(reason, vote, 0, function () {
                window.location = window.location;
            });
        } else {
            var $modal = $(require('./picture-moder-vote/modal.html'));

            var $btnSend = $modal.find('.btn-send');
            var $btnCancel = $modal.find('.btn-secondary');
            var $checkboxLabel = $modal.find('.checkbox span');

            $btnSend.addClass(vote > 0 ? 'btn-success' : 'btn-danger');

            $modal.find('.modal-title').text(i18next.t("picture-moder-vote/custom/title"));
            $btnSend.attr('data-loading-text', i18next.t("picture-moder-vote/custom/sending"));
            $btnSend.text(i18next.t("picture-moder-vote/custom/send"));
            $btnCancel.text(i18next.t("picture-moder-vote/custom/cancel"));
            $checkboxLabel.text(i18next.t("picture-moder-vote/custom/save"));

            var $reason = $modal.find(':input[name=reason]');

            $modal.on('shown.bs.modal', function () {
                $reason.focus();
            });

            $btnSend.on('click', function () {
                reason = $reason.val();
                if (reason) {
                    // $(this).button('loading');
                    var save = $modal.find(':input[name=save]').val();
                    send(reason, vote, save, function () {
                        $modal.modal('hide');
                        window.location = window.location;
                    });
                }
            });

            $modal.modal({
                show: true
            });
        }
    });
};

module.exports = function (element) {
    new Widget(element);
};
