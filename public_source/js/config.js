require.config({
    baseUrl: '/js',
    paths: {
        requireLib: 'require',
        async: 'lib/require/async',
        domReady: 'lib/requirejs-plugins/domReady',
        jquery: 'lib/jquery',
        tinymce: '/tiny_mce/jquery.tinymce.min',
        'jquery.cookie': '/js/lib/jquery.cookie',
        typeahead: 'lib/bootstrap3-typeahead',
        raphael: 'lib/raphael'
    },
    shim: {
        'bootstrap': {
            deps: ['jquery']
        },
        'jquery.cookie': {
            deps: ['jquery']
        },
        'typeahead': {
            deps: ['bootstrap']
        }
    }
});