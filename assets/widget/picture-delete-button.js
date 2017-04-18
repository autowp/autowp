var $ = require('jquery');

var Widget = function(element) {
    var url = $(element).data('url');
    
    $(element).on('click', function() {
        $.ajax({
            method: 'PUT',
            url: url,
            data: {
                status: 'removing'
            }
        }).then(function() {
            window.location = window.location;
        });
    });
};

module.exports = function(element) {
    new Widget(element);
};
