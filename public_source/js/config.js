require.config({
    baseUrl: '/js',
    paths: {
        requireLib: 'require',
        async: 'lib/require/async',
        domReady: 'lib/requirejs-plugins/domReady',
        jquery: 'lib/jquery',
        tinymce: '/tiny_mce/jquery.tinymce.min',
        'jquery.cookie': '/js/lib/jquery.cookie',
        raphael: 'lib/raphael',
        css: 'lib/requirejs-plugins/css',
        markdown: 'lib/markdown.min',
        moment: 'lib/moment',
        Chart: 'lib/Chart',
        typeahead: 'lib/typeahead'
    },
    shim: {
        'bootstrap': {
            deps: ['jquery']
        },
        'jquery.cookie': {
            deps: ['jquery']
        },
        markdown: {
            exports: 'markdown'
        }
    }
});