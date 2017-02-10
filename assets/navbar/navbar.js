require("./navbar.less");

var $ = require("jquery");
var i18next = require('i18next');

module.exports = {};

module.exports = {
    init: function() {
        $('.navbar .online a').each(function() {
            var 
                $modal = null,
                $body = null,
                $btnRefresh = null,
                url = $(this).attr('href');
            
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