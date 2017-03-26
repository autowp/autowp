import angular from 'angular';
import angularRouter from 'angular-ui-router';
import angularMaterial from 'angular-material';
import angularAnimate from 'angular-animate';
import angularAria from 'angular-aria';
import angularMessages from 'angular-messages';
import angularTranslate from 'angular-translate';
import angularTranslateInterpolationMessageformat from 'angular-translate-interpolation-messageformat';
import angularSanitize from 'angular-sanitize';
import angularMarkdown from 'angular-markdown-directive';
import materialCss from 'angular-material/angular-material.css';

const MODULE_NAME = 'App';

angular.module(MODULE_NAME, [angularMaterial, angularAnimate, angularAria, angularRouter, angularTranslate, 'btford.markdown', angularSanitize])
    .config(['$urlRouterProvider', '$locationProvider', '$translateProvider',
        function config($urlRouterProvider, $locationProvider, $translateProvider) {
            $locationProvider.html5Mode(true).hashPrefix('!');
    
            //$urlRouterProvider.when('', '/');
            $urlRouterProvider.otherwise('/');

            $translateProvider.useSanitizeValueStrategy('escape');
            $translateProvider.translations('en', require('./languages/en.json'));
            $translateProvider.translations('zh', require('./languages/zh.json'));
            $translateProvider.translations('de', require('./languages/de.json'));
            $translateProvider.translations('ru', require('./languages/ru.json'));
            $translateProvider.translations('fr', require('./languages/fr.json'));
            
            var lang = document.documentElement.getAttribute('lang');
            
            $translateProvider.useMessageFormatInterpolation();
            $translateProvider.fallbackLanguage('en');
            $translateProvider.preferredLanguage(lang);
            $translateProvider.use(lang);
        }
    ]);


export default MODULE_NAME;
