var $ = require('jquery');
        
var PerspectiveSelector = function(element) {
    $(element).find(':input[name="perspective_id"]').on('change', function() {
        $.post($(this.form).attr('action'), {perspective_id: $(this).val()}, function(json) {
        }, 'json');
    });
};

module.exports = function(element) {
    new PerspectiveSelector(element);
};
