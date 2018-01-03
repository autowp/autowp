import * as angular from "angular";
import Module from 'app.module';

class AutowpModerAttrsAttributeListDirective implements ng.IDirective {
    public restrict = 'E';
    public scope = {
        attributes: '<',
        moveUp: '<',
        moveDown: '<'
    };
    public template = require('./template.html');
    
    constructor(
        private $http: ng.IHttpService
    ) {

    }

    static factory(): ng.IDirectiveFactory {
        const directive = ($http: ng.IHttpService) => new AutowpModerAttrsAttributeListDirective($http);
        directive.$inject = ['$http'];
        return directive;
    }
    
    link = (scope: ng.IScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {

    }
}

angular.module(Module).directive('autowpModerAttrsAttributeList', AutowpModerAttrsAttributeListDirective.factory());
