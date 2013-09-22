require.config({
    baseUrl: '/js',
    paths: {
        async: 'lib/requirejs-plugins/async',
        normalize: 'lib/requirejs-plugins/normalize',
        css: 'lib/requirejs-plugins/css',
        domReady: 'lib/requirejs-plugins/domReady',
        jquery: 'lib/jquery.min',
        tinymce: '/tiny_mce/jquery.tinymce.min',
        'jquery.cookie': '/js/lib/jquery.cookie'
    },
    shim: {
        'bootstrap': {
            deps: ['jquery']
        },
        'jquery.cookie': {
            deps: ['jquery']
        }
    }
});