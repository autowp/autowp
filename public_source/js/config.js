require.config({
    baseUrl: '/js',
    paths: {
        requireLib: 'require',
        async: 'lib/requirejs-plugins/async',
        normalize: 'lib/requirejs-plugins/normalize',
        css: 'lib/requirejs-plugins/css',
        domReady: 'lib/requirejs-plugins/domReady',
        jquery: 'lib/jquery.min',
        tinymce: '/tiny_mce/jquery.tinymce.min',
        'jquery.cookie': '/js/lib/jquery.cookie',
        typeahead: 'lib/bootstrap3-typeahead',
        raphael: 'lib/raphael',
        dracula: 'lib/dracula',
        dracula_algorithms: 'lib/dracula_algorithms',
        dracula_graffle: 'lib/dracula_graffle',
        dracula_graph: 'lib/dracula_graph',
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
        },
        'raphael': {
            deps: ['jquery']
        },
        'dracula_algorithms': {
            deps: ['raphael']
        },
        'dracula_graffle': {
            deps: ['raphael']
        },
        'dracula_graph': {
            deps: ['raphael']
        },
    }
});