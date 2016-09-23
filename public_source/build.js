({
    baseUrl: ".",
    appDir: "/home/autowp/autowp.ru/public_source/js/",
    //name: "application",
    mainConfigFile: 'js/config.js',
    dir: "/home/autowp/autowp.ru/public_html/js",
    modules: [
        {
            name: "application",
            include: [
                "requireLib", "config", "brand-popover", "default/index", 
                "message", "comments", "inline-picture", "moder-vote-reason",
                "perspective-selector", "default/picture", "car-list"
            ]
        }
    ]
})