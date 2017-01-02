"use strict";

var gulp = require("gulp")
  , packageJSON = require("./package")
  , favicons = require("gulp-favicons")
  , gutil = require("gulp-util")
  , jshint = require('gulp-jshint')
;

gulp.task("favicon-1", function () {
    return gulp.src("./module/Application/assets/logo-bg.svg").pipe(favicons({
        appName: "WheelsAge.org",
        appDescription: "WheelsAge.org - encyclopedia of cars in the pictures",
        developerName: "Dmitry P.",
        developerURL: "https://github.com/autowp/autowp",
        background: "#ede9de",
        path: "/",
        url: "https://wheelsage.org/",
        display: "standalone",
        orientation: "portrait",
        start_url: "/",
        version: 1.0,
        logging: false,
        online: false,
        html: "favicons.html",
        pipeHTML: true,
        replace: true,
        icons: {
            android: false,             // Create Android homescreen icon. `boolean`
            appleIcon: true,            // Create Apple touch icons. `boolean` or `{ offset: offsetInPercentage }`
            appleStartup: false,        // Create Apple startup images. `boolean`
            coast: false,               // Create Opera Coast icon with offset 25%. `boolean` or `{ offset: offsetInPercentage }`
            favicons: true,             // Create regular favicons. `boolean`
            firefox: false,             // Create Firefox OS icons. `boolean` or `{ offset: offsetInPercentage }`
            windows: false,             // Create Windows 8 tile icons. `boolean`
            yandex: false               // Create Yandex browser icon. `boolean`
        }
    }))
        .on("error", gutil.log)
        .pipe(gulp.dest("./public_html"));
});

gulp.task("favicon-2", function () {
    return gulp.src("./module/Application/assets/logo.svg").pipe(favicons({
        appName: "WheelsAge.org",
        appDescription: "WheelsAge.org - encyclopedia of cars in the pictures",
        developerName: "Dmitry P.",
        developerURL: "https://github.com/autowp/autowp",
        background: "#ede9de",
        path: "/",
        url: "https://wheelsage.org/",
        display: "standalone",
        orientation: "portrait",
        start_url: "/",
        version: 1.0,
        logging: false,
        online: false,
        html: "favicons.html",
        pipeHTML: true,
        replace: true,
        icons: {
            android: true,               // Create Android homescreen icon. `boolean`
            appleIcon: false,            // Create Apple touch icons. `boolean` or `{ offset: offsetInPercentage }`
            appleStartup: true,          // Create Apple startup images. `boolean`
            coast: { offset: 25 },       // Create Opera Coast icon with offset 25%. `boolean` or `{ offset: offsetInPercentage }`
            favicons: false,             // Create regular favicons. `boolean`
            firefox: true,               // Create Firefox OS icons. `boolean` or `{ offset: offsetInPercentage }`
            windows: true,               // Create Windows 8 tile icons. `boolean`
            yandex: true                 // Create Yandex browser icon. `boolean`
        }
    }))
        .on("error", gutil.log)
        .pipe(gulp.dest("./public_html"));
});

gulp.task('favicon', ['favicon-1', 'favicon-2'], function () {
    return gulp;
});

gulp.task('lint', function() {
    return gulp.src([
        './assets/**/*.js'
    ])
        .pipe(jshint())
        .pipe(jshint.reporter('default'));
});
