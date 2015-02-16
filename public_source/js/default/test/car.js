define(
    'default/test/car',
    ['jquery', 'bootstrap', 'gallery2'],
    function($, Bootstrap, Gallery) {
        return {
            init: function(options) {
                
                var gallery;
                
                $('.pictures a').on('click', function(e) {
                    e.preventDefault();
                    
                    var id = $(this).data('id');
                    
                    if (!gallery) {
                        gallery = new Gallery({
                            items: options.gallery.items,
                            current: id
                        });
                        gallery.show();
                    } else {
                        gallery.show();
                        gallery.rewindToId(id);
                    }
                    
                });
            }
        }
    }
);