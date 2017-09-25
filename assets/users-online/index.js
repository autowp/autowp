var $ = require('jquery');
var i18next = require('i18next');

export function bind(element) {
    $(element).each(function() {
        var 
            $modal = null,
            $body = null,
            $btnRefresh = null,
            url = '/api/user/online';
        
        function reload() {
            if (!$modal) {
                $modal = $(require('./online.html'));
                
                $modal.find('.modal-title').text(i18next.t("who-online/title"));
                $modal.find('.btn-primary').text(i18next.t("who-online/refresh"));
                $modal.find('.btn-default').text(i18next.t("who-online/close"));
                
                $body = $modal.find('.modal-body');
                $btnRefresh = $modal.find('.btn-primary').on('click', function(e) {
                    e.preventDefault();
                    reload();
                });
            }
            $body.empty();
            $modal.modal();
            
            $btnRefresh.button('loading');
            $.get(url, {}, function(response) {
                $body.empty();
                $.map(response.items, function(user) {
                    var $e = $('<span class="user"><i class="fa fa-user"></i> </span>')
                        .toggleClass('muted', !!user.deleted)
                        .toggleClass('long-away', !!user.long_away)
                        .toggleClass('green-man', !!user.green);
                    
                    if (user.deleted) {
                        $e.append($('<span></span>').text(i18next.t("deleted-user")));
                    } else {
                        $e.append($('<a></a>').attr('href', user.url).text(user.name));
                    }
                    
                    $body.append([$e, ' ']);
                });
                
                $btnRefresh.button('reset');
            });
        }
        
        $(this).on('click', function(e) {
            e.preventDefault();
            reload();
        });
    });
}
