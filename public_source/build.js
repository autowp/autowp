({
    baseUrl: ".",
    appDir: "/home/autowp/autowp.ru/public_source/js/",
    paths: {
        async: 'lib/requirejs-plugins/async',
        normalize: 'lib/requirejs-plugins/normalize',
        css: 'lib/requirejs-plugins/css',
        domReady: 'lib/requirejs-plugins/domReady',
        jquery: 'lib/jquery',
        tinymce: '/tiny_mce/jquery.tinymce.min',
        'jquery.cookie': '/js/lib/jquery.cookie'
    },
    //name: "application",
    mainConfigFile: 'js/config.js',
    dir: "/home/autowp/autowp.ru/public_html/js",
    modules: [
        {
            name: "application",
            include: [
                "requireLib", "config", "brand-popover", "default/index", "message", "comments"
            ]
        }
    ]
})