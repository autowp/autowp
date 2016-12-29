define(
    ['jquery', 'message', 'domReady!'],
    function($, Message) {
        return {
            init: function() {
                $('[data-trigger=delete-pm]').on('click', function(e) {
                    e.preventDefault();
                    
                    var $element = $(this);
                    Message.deleteMessage($element.data('id'), function() {
                        $element.closest('.message').remove();
                    });
                });
            }
        };
    }
);