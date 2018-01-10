import * as angular from 'angular';
import '@uirouter/angularjs';
import 'angular-animate';
import 'angular-aria';
import 'angular-messages';
import 'angular-translate';
import 'angular-translate-interpolation-messageformat';
import 'angular-sanitize';
import 'angular-simple-logger';
import 'angular-filesize-filter/angular-filesize-filter';
import 'ng-tags-input';
import 'ng-tags-input/build/ng-tags-input.css';
import 'ng-tags-input/build/ng-tags-input.bootstrap.css';
import 'ui-leaflet';
import 'leaflet/dist/leaflet.css';
import 'angular-moment';
import 'moment/locale/fr.js';
import 'moment/locale/ru.js';
import 'moment/locale/zh-cn.js';
import 'moment/locale/de.js';
import 'moment/locale/be.js';
import 'moment/locale/pt-br.js';
import 'moment/locale/uk.js';
import 'angular-recaptcha';
import 'ng-file-upload';
import 'ng-rollbar';

const MODULE_NAME = 'App';

declare global {
    interface Window { opt: any; }
}

angular.module(MODULE_NAME, ['ngAnimate', 'ngAria', 'ui.router', 'pascalprecht.translate', 'nemLogging', 'ngSanitize', "ngFilesizeFilter", 'ngTagsInput', 'ui-leaflet', 'angularMoment', 'vcRecaptcha', 'ngFileUpload', 'tandibar/ng-rollbar'])
    .config(['$urlRouterProvider', '$locationProvider', '$translateProvider', 'RollbarProvider',
        function config($urlRouterProvider: any, $locationProvider: ng.ILocationProvider, $translateProvider: any, RollbarProvider: any) {
            $locationProvider.html5Mode(true).hashPrefix('!');
    
            //$urlRouterProvider.when('', '/');
            $urlRouterProvider.otherwise('/');

            $translateProvider.useSanitizeValueStrategy('escape');
            $translateProvider.translations('en', require('./languages/en.json'));
            $translateProvider.translations('zh', require('./languages/zh.json'));
            $translateProvider.translations('ru', require('./languages/ru.json'));
            $translateProvider.translations('fr', require('./languages/fr.json'));
            $translateProvider.translations('be', require('./languages/be.json'));
            $translateProvider.translations('uk', require('./languages/uk.json'));
            $translateProvider.translations('pt-br', require('./languages/pt-br.json'));
            
            var lang = document.documentElement.getAttribute('lang');
            
            $translateProvider.useMessageFormatInterpolation();
            $translateProvider.fallbackLanguage('en');
            $translateProvider.preferredLanguage(lang);
            $translateProvider.use(lang);
            
            if (window.opt.rollbar.access_token) {
                RollbarProvider.init({
                    accessToken: window.opt.rollbar.access_token,
                    captureUncaught: true,
                    payload: {
                        environment: window.opt.rollbar.environment
                    }
                });
            }
        }
    ])
    .run(['amMoment', function(amMoment: any) {
        var lang = document.documentElement.getAttribute('lang');
        if (lang) {
            var map: any = {
                ru: 'ru',
                en: 'en',
                fr: 'fr',
                zh: 'zh-cn',
                be: 'be',
                'pt-br': 'pt-br'
            };
            amMoment.changeLocale(map[lang]);
        }
    }])
    .constant('amTimeAgoConfig', {
        fullDateThreshold: 2,
        fullDateFormat: 'lll',
        titleFormat: 'LLL'
    });
    //.constant('moment', require('moment-timezone'));


export default MODULE_NAME;
