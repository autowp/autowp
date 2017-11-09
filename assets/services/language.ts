import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'LanguageService';

angular.module(Module)
    .service(SERVICE_NAME, [function() {
        
        var language = document.documentElement.getAttribute('lang');
        
        this.getLanguage = function() {
            return language;
        };
    }]);

export default SERVICE_NAME;
