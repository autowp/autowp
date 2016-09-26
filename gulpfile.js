"use strict";

var gulp = require("gulp")
  , argv = require('yargs').argv
  , gulpif = require('gulp-if')
  , addsrc = require('gulp-add-src')
  , gzip = require('gulp-gzip')
  , concat = require("gulp-concat")
  , less = require("gulp-less")
  , amdOptimize = require('gulp-amd-optimizer')
  , gulpCopy = require("gulp-copy")
  , cleanCSS = require('gulp-clean-css')
  , uglify = require('gulp-uglify')
//  , imagemin = require('gulp-imagemin')
  , shell = require("gulp-shell")
  , urlAdjuster = require('gulp-css-url-adjuster')
  , embed = require('gulp-image-embed')
  , packageJSON = require("./package")
;

gulp.task("build.css", ["copy.jcrop", 'copy.flags'], function () {
    return gulp.src([
        './public_source/less/styles.less'
    ])
        .pipe(less())
        .pipe(addsrc.append([
            './node_modules/jcrop-0.9.12/css/jquery.Jcrop.css'
        ]))
        .pipe(addsrc.append([
            './public_html/css/brands.css'
        ]))
        .pipe(concat("styles.css"))
        .pipe(urlAdjuster({
            replace: ['Jcrop.gif', '/img/vendor/Jcrop.gif']
        }))
        .pipe(embed({
            asset: './public_html',
            extension: ['svg'],
            include: [/flag-icon-css/]
        }))
        .pipe(gulpif(!argv.fast, cleanCSS({
            keepSpecialComments: 1
        })))
        .pipe(gulp.dest('./public_html/css'))
        .pipe(gulpif(!argv.fast, gzip({
            append: true,
            gzipOptions: { level: 9 } 
        })))
        .pipe(gulpif(!argv.fast, gulp.dest('./public_html/css')));
});

gulp.task('build.js', shell.task([
    './node_modules/requirejs/bin/r.js -o ./public_source/build.js generateSourceMaps=false preserveLicenseComments=1 optimize=uglify2',
]));

gulp.task('build.js.gz', ['build.js'], function () {
    return gulp.src([
        './public_html/js/**/*'
    ])
        .pipe(gulpif(!argv.fast, gzip({
            append: true,
            gzipOptions: { level: 9 } 
        })))
        .pipe(gulpif(!argv.fast, gulp.dest('./public_html/js')));
});

gulp.task("build.js.fast", shell.task([
    'r.js -o ./public_source/build.js preserveLicenseComments=false optimize=none'
    //generateSourceMaps=1
]));

gulp.task("copy.fonts", function () {
    return gulp.src([
        './node_modules/bootstrap/fonts/*',
        './node_modules/font-awesome/fonts/*',
    ])
        .pipe(gulpCopy("./public_html/fonts", {prefix: 3}));
});

gulp.task("copy.jcrop", function () {
    return gulp.src([
        './node_modules/jcrop-0.9.12/css/Jcrop.gif'
    ])
        .pipe(gulpCopy("./public_html/img/vendor", {prefix: 3}));
});

gulp.task("copy.flags", function () {
    return gulp.src([
        './node_modules/flag-icon-css/flags/4x3/cn.svg',
        './node_modules/flag-icon-css/flags/4x3/ru.svg',
        './node_modules/flag-icon-css/flags/4x3/gb.svg',
        './node_modules/flag-icon-css/flags/4x3/fr.svg'
    ])
        .pipe(gulpCopy("./public_html/img/vendor/flag-icon-css", {prefix: 4}));
});

/*gulp.task("minify.brands.png", function () {
    return gulp.src([
         './public_html/img/brands.png'
    ])
      .pipe(imagemin())
      .pipe('./public_html/img/');
  });*/

gulp.task("build", ['build.css', 'build.js.gz', 'copy.fonts']);
