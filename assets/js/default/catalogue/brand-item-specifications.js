define(
    ['jquery', 'bootstrap', 'specifications'],
    function($, Bootstrap, Specs) {
        return {
            init: function(options) {
                
                if (!options.disabled) {
                    Specs.init($('[data-specs]'));
                }
                
            }
        };
    }
);