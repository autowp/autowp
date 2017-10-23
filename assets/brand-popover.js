var $ = require('jquery');

module.exports = {
    apply: function(selector) {
        
        var $doc = $(document);
        
        $doc.find(selector).data({
            loaded: false,
            over: false
        });
        
        $doc.on('click', selector, function() {
            e.preventDefault();
        });
        
        $doc.on('mouseover', selector, function() {
            var $self = $(this);
            $self.data('over', true);
            
            if ($self.data('loaded')) {
                $self.popover('show');
            } else {
                $.get($self.data('href'), {}, function(html) {
                    
                    $self.popover({
                        trigger: 'manual',
                        content: html,
                        html: true,
                        placement: 'bottom',
                    });
                    $self.data('loaded', true);
                    if ($self.data('over')) {
                        $self.popover('show');
                    }
                });
            }
        });
        
        $doc.on('mouseout', selector, function() {
            var $self = $(this);
            $self.data('over', false);
            if ($self.data('loaded')) {
                $self.popover('hide');
            }
        });
    }
};
