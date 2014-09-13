define(
    'default/picture',
    ['jquery', 'bootstrap', 'gallery'],
    function($, Bootstrap, Gallery) {
        return {
            init: function(options) {
                
                var gallery;
                
                if (options.gallery && options.gallery.items && options.gallery.items.length) {
                    
                    $('.picture-preview-medium a').on('click', function(e) {
                        e.preventDefault();
                        
                        if (!gallery) {
                            gallery = new Gallery(options.gallery);
                            gallery.show();
                        } else {
                            gallery.show();
                            gallery.rewindToId(options.gallery.current);
                        }
                        
                    });
                    
                } else {
                    $('.picture-preview-medium a').on('click', function() {
                        window.open($(this).attr('href'), '_blank');
                        return false;
                    });
                }
            }
        }
    }
);