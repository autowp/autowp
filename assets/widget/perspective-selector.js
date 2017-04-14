var $ = require('jquery');
        
var PerspectiveSelector = function(element) {
    $(element).find(':input[name="perspective_id"]').on('change', function() {
        $.ajax({
            method: 'put',
            url: $(this.form).attr('action'),
            data: {perspective_id: $(this).val()}
        });
    });
};

module.exports = function(element) {
    new PerspectiveSelector(element);
};
