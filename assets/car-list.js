var $ = require('jquery');
        
var PerspectiveSelector = function(element) {
    $('[data-toggle="tooltip"]', element).tooltip();
};

module.exports = function(element) {
    new PerspectiveSelector(element);
};
