define(
    ['jquery', 'bootstrap', 'gallery', 'gallery2'],
    function($, Bootstrap, Gallery, Gallery2) {
        return {
            init: function(options) {
                
                var gallery;
                
                $('.picture-preview-medium a').on('click', function(e) {
                    e.preventDefault();
                    
                    if (!gallery) {
                        gallery = new Gallery2({
                            url: options.galleryUrl,
                            current: options.gallery.current
                        });
                        gallery.show();
                    } else {
                        gallery.show();
                        gallery.rewindToId(options.gallery.current);
                    }
                });
            }
        };
    }
);