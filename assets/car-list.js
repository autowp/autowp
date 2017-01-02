define(
    ['jquery'], 
    function($) {
        
        var PerspectiveSelector = function(element) {
            $('[data-toggle="tooltip"]', element).tooltip();
        };
        
        return function(element) {
            new PerspectiveSelector(element);
        };
    }
);