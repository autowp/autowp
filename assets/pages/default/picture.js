var $ = require("jquery");
var Gallery = require("gallery").Gallery;
var share = require('share/share');
var pictureVote = require('picture-vote/picture-vote');
var subscription = require('subscription/subscription');

module.exports = {
    init: function(options) {
        
        share('.share');
        pictureVote('.picture-vote');
        
        $('.btn-share-dialog').on('click', function(e) {
            e.preventDefault();
            
            $('.share-dialog').toggle();
        });
        
        var gallery;
        
        $('.picture-preview-medium a').on('click', function(e) {
            e.preventDefault();
            
            if (! gallery) {
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
        
        subscription('.subscription');
    }
};