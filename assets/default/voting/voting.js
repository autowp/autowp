var $ = require('jquery');

module.exports = {
    init: function() {
        
        $('.who-vote').each(function() {
            var 
                $a = $(this),
                $modal = null,
                $body = null,
                $btnRefresh = null,
                url = $a.attr('href');
            
            function reload() {
                if (!$modal) {
                    $modal = $(
                        require('html!./who-vote.html')
                    );
                    $modal.find('.modal-title').text($a.text());
                    $body = $modal.find('.modal-body');
                    $btnRefresh = $modal.find('.btn-primary').on('click', function(e) {
                        e.preventDefault();
                        reload();
                    });
                }
                $body.empty();
                $modal.modal();
                
                $btnRefresh.button('loading');
                $.get(url, {}, function(html) {
                    $body.html(html);
                    $btnRefresh.button('reset');
                });
            }
            
            $(this).on('click', function(e) {
                e.preventDefault();
                reload();
            });
        });
        
    }
};
