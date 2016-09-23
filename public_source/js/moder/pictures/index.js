define(
    ['jquery', 'bootstrap'],
    function($, Bootstrap) {
        
        return {
            init: function(options) {
                
                var self = this;
                
                this.currentReason = null;
        
                this.$toolbar = $('.toolbar');
                
                this.$toolbar.on('click', '.btn-accept', function() {
                    var ids = [];
                    $('.picture-preview:has(button.active[data-toggle])').each(function() {
                        ids.push($(this).data('id'));
                    });
                    
                    if (ids.length) {
                        $.post(options.acceptUrl, {id: ids}, function(result) {
                            if (result) {
                                window.location = window.location;
                            }
                        });
                    }
                });
                
                this.$toolbar.on('click', '.dropdown-reason li a', function(e) {
                    self.currentReason = $(this).data('reason');
                    self.$toolbar.find('.dropdown-reason-toggle').text(self.currentReason);
                    
                    e.preventDefault();
                });
                
                this.$toolbar.on('click', '.btn-vote', function() {
                    if (!self.currentReason) {
                        window.alert('Выберите сообщение');
                        return;
                    }
                    
                    var ids = [];
                    $('.picture-preview:has(button.active[data-toggle])').each(function() {
                        ids.push($(this).data('id'));
                    });
                    
                    var params = {
                        id: ids,
                        vote: $(this).data('vote'), 
                        reason: self.currentReason
                    };
                    $.post(options.voteUrl, params, function(result) {
                        if (result) {
                            window.location = window.location;
                        }
                    });
                });
                
                $('.picture-preview button[data-toggle]').on('click', function() {
                    setTimeout(function() {
                        self.refreshActive();
                    }, 1);
                });
                
                this.refreshActive();
            },
            refreshActive: function() {
                var ids = [];
                $('.picture-preview:has(button.active[data-toggle])').each(function() {
                    ids.push($(this).data('id'));
                });
                
                this.$toolbar.find('button').prop('disabled', !ids.length);
            }
        };
    }
);