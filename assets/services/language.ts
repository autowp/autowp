import * as angular from "angular";
import Module from 'app.module';

const SERVICE_NAME = 'LanguageService';

export class LanguageService {
    static $inject: string[] = [];
    private language = document.documentElement.getAttribute('lang');
  
    constructor(){}
  
    public getLanguage() {
        return this.language;
    };
};

angular.module(Module).service(SERVICE_NAME, LanguageService);
