var $ = require('jquery');

require('./subscription.less');

module.exports = function(element) {
    $(element).on('click', function(event) {
        event.preventDefault();
            
        var $this = $(this);
        $this.button('loading');
        
        var subscribed = $this.hasClass('subscribed');
        
        $.ajax({
            method: subscribed ? 'DELETE' : 'PUT',
            url: $this.data('url')
        }).done(function() {
            $this.toggleClass('subscribed', !subscribed);
            $this.button('reset');
        });
    });
};
