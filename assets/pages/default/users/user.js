define(
    ['jquery'],
    function($) {
        return {
            init: function(options) {
                $('.btn-contact').on('click', function() {
                    var $btn = $(this);
                    var value = $btn.hasClass('in-contacts');
                    
                    $.ajax({
                        url: options.contactApiUrl,
                        method: value ? 'DELETE' : 'PUT',
                        success: function(data, textStatus, jqXHR) {
                            switch (jqXHR.status) {
                                case 204:
                                    $btn.removeClass('in-contacts');
                                    break;
                                case 200:
                                    $btn.addClass('in-contacts');
                                    break;
                            }
                        }
                    });
                });
                
                $('.btn-delete-user').on('click', function() {
                    
                    if (! window.confirm("Are you sure?")) {
                        return;
                    }
                    
                    var $btn = $(this);
                    var id = $btn.data('id');
                    
                    $.ajax({
                        url: '/api/user/'+id,
                        method: 'PUT',
                        data: {
                            deleted: true
                        },
                        success: function() {
                            window.location = window.location;
                        }
                    });
                });
                
                $('.btn-delete-photo').on('click', function() {
                    
                    if (! window.confirm("Are you sure?")) {
                        return;
                    }
                    
                    var $btn = $(this);
                    var id = $btn.data('id');
                    
                    $.ajax({
                        url: '/api/user/'+id+'/photo',
                        method: 'DELETE',
                        success: function() {
                            window.location = window.location;
                        }
                    });
                });
            }
        };
    }
);