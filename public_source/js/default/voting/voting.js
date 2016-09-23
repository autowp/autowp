define(
    'default/voting/voting',
    ['jquery', 'bootstrap'],
    function($, Bootstrap) {
        return {
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
                                '<div class="modal fade">\
                                    <div class="modal-dialog">\
                                        <div class="modal-content">\
                                            <div class="modal-header">\
                                                <button type="button" data-dismiss="modal" class="close">×</button>\
                                                <h3 class="modal-title">Online</h3>\
                                            </div>\
                                            <div class="modal-body"></div>\
                                            <div class="modal-footer">\
                                                <button class="btn btn-primary">Обновить</a>\
                                                <button data-dismiss="modal" class="btn btn-default">Закрыть</button>\
                                            </div>\
                                        </div>\
                                    </div>\
                                </div>'
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
        }
    }
);