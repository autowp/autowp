define(
    ['jquery', 'markdown', 'bootstrap'], 
    function($, Markdown) {
        return function(element) {
            $(element).find('#preview-tab').on('shown.bs.tab', function(e) {
                var markdown = $(element).find('textarea').val();
                var html = Markdown.toHTML(markdown);
                $(element).find('#markdown-preview').html(html);
            });
        };
    }
);