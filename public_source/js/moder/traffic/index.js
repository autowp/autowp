define(
    'moder/traffic/index',
    ['jquery'],
    function($) {
        return {
            init: function(options) {
                $('.host-name').each(function() {
                    var $this = $(this);
                    $.getJSON(options.hostByAddrUrl, {ip: $this.text()}, function(json) {
                        $this.text(json.host);
                    })
                });
            }
        }
    }
);