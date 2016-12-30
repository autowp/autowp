({
    baseUrl: ".",
    appDir: "../public_source/js/",
    //name: "application",
    mainConfigFile: 'js/config.js',
    dir: "../public_html/js",
    modules: [
        {
            name: "application",
            include: [
                "requireLib", "config", "brand-popover", "default/index", 
                "message", "comments", "inline-picture", "moder-vote-reason",
                "perspective-selector", "default/picture", "car-list", 
                'default/account/pm', 'default/most', 'moder/pictures/index',
                'default/map/index'
            ]
        }
    ]
})