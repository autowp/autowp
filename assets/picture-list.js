var $ = require('jquery');
        
var PictureList = function(element) {
    $('[data-toggle="tooltip"]', element).tooltip();
};

module.exports = function(element) {
    new PictureList(element);
};
