define(
    ['jquery', 'specifications'],
    function($, Specs) {
        return {
            init: function(options) {
                
                if (!options.disabled) {
                    Specs.init($('[data-specs]'));
                }
                
            }
        };
    }
);