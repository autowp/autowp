({
    baseUrl: ".",
    appDir: "/home/autowp/autowp.ru/public_source/js/",
    paths: {
        async: 'lib/require/async',
        domReady: 'lib/requirejs-plugins/domReady',
        jquery: 'lib/jquery',
        'jquery.cookie': 'lib/jquery.cookie',
        raphael: 'lib/raphael',
        css: 'lib/requirejs-plugins/css'
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