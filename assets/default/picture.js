var $ = require("jquery");
var Gallery = require("gallery/gallery");

module.exports = {
    init: function(options) {
        
        var gallery;
        
        $('.picture-preview-medium a').on('click', function(e) {
            e.preventDefault();
            
            if (!gallery) {
                gallery = new Gallery({
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