define(
    ['jquery'], 
    function($) {
        
        var PictureList = function(element) {
            $('[data-toggle="tooltip"]', element).tooltip();
        };
        
        return function(element) {
            new PictureList(element);
        };
    }
);