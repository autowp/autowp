var $ = require('jquery');

require('./share.less');

module.exports = function(element) {
    var $element = $(element);
    
    $element.find('a').on('click', function(event) {
        event.preventDefault();
        
        var $this = $(this);
        
        ga('send', 'event', 'share', $this.attr('title'));
        
        window.open($this.attr('href'), null, "height=600,width=600,resizable=yes,scrollbars=no,status=no,toolbar=no,location=no,directories=no");
    });
};