define(
    'moder-vote-reason',
    ['jquery'],
    function($) {
        
        var Reason = function(element) {
            
            var $element = $(element);
            var $select = $element.find('select');
            var $text = $element.find(':text');
            
            $select.on('change', function() {
                if ($(this).val() == 'своя') {
                    $(this).hide();
                    $text
                        .val('')
                        .show()
                        .focus();
                } else {
                    $text.val($(this).val());
                }
            });
        };
        
        return function(element) {
            new Reason(element);
        };
    }
);