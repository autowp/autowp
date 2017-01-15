var $ = require('jquery');

module.exports = {
    init: function() {
        $('.permanent-link').on('click', function() {
            var offset = $(this).offset();

            var div = $(
                require('./permalink.html')
            );

            //TODO: extract url to options
            $('input', div).val(
                'http://www.autowp.ru' + $(this).attr('href')
            );

            $('.close', div).on('click', function(e) {
                e.preventDefault();
                $(div).remove();
            });

            $(div).css({
                position: 'absolute',
                backgroundColor: 'white',
                padding: '5px',
                left: offset.left,
                top: offset.top + $(this).height(),
                border: 'border: 1px solid #cccccc',
                width: '230px'
            });

            $(document.body).append(div);
            return false;
        });
    }
};
