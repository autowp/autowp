var $ = require('jquery');
        
var CarList = function(element) {
    $('[data-toggle="tooltip"]', element).tooltip();
};

module.exports = function(element) {
    new CarList(element);
};
