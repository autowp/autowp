define(
    ['jquery', 'bootstrap', 'specifications'],
    function($, Bootstrap, Specs) {
        return {
            init: function(options) {
                
                console.log(options);
                
                if (!options.disabled) {
                    Specs.init($('[data-specs]'));
                }
                
            }
        };
    }
);