define(
    ['jquery', 'bootstrap'],
    function($, Bootstrap) {
        var modal = 
            '<div class="modal fade">' +
                '<div class="modal-dialog">' +
                    '<form action="/account/send-personal-message" class="modal-content" method="post">' +
                        '<div class="modal-header">' +
                            '<a class="close">×</a>' +
                            '<h3 class="modal-title">Характеристика</h3>' +
                        '</div>' +
                        '<div class="modal-body">' +
                        '</div>' +
                        '<div class="modal-footer">' +
                            '<button class="btn btn-primary" data-loading-text="отправляется ..." data-complete-text="отправлено" data-send-text="отправить">отправить</button>' +
                            '<button class="btn btn-default cancel">отменить</button>' +
                        '</div>' +
                    '</form>' +
                '</div>' +
            '</div>';
            
        return {
            init: function(element) {
                var $element = $(element);
                
                $element.each(function() {
                    $(this).on('click', '[data-specs-attr]', function() {
                        var $this = $(this);
                        console.log('click');
                        var $modal = $(modal);
                        $modal.modal('show');
                        
                        var params = {
                            attr: $this.data('specsAttr')
                        };
                        
                        $.get('/cars/edit-value', params, function(html) {
                            $modal.find('.modal-body').html(html);
                        });
                    });
                });
                
            }
        };
    }
);