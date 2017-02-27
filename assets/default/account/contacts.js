var $ = require('jquery');

module.exports = {
    init: function() {
        $('.media-list .close').on('click', function(e) {
            e.preventDefault();
            
            var $this = $(this);
            
            $.ajax({
                url: $this.data('url'),
                method: 'DELETE',
                success: function(data, textStatus, jqXHR) {
                    $this.closest('.media').remove();
                }
            });
        });
    }
};
