define(
    'default/picture',
    ['jquery', 'bootstrap', 'gallery', 'gallery2'],
    function($, Bootstrap, Gallery, Gallery2) {
        return {
            init: function(options) {
                
                var gallery;
                
                $('.picture-preview-medium a').on('click', function(e) {
                    e.preventDefault();
                    
                    if (options.gallery2) {
                        
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
                        
                    } else {
                    
                        if (!gallery) {
                            $.get(options.galleryUrl, function(data) {
                                gallery = new Gallery({
                                    current: options.gallery.current,
                                    items: data
                                });
                                gallery.show();
                            });
                        } else {
                            gallery.show();
                            gallery.rewindToId(options.gallery.current);
                        }
                        
                    }
                    
                });
            }
        }
    }
);