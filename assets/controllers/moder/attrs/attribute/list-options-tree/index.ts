import * as angular from "angular";
import Module from 'app.module';

class AutowpModerAttrsAttributeListDirective implements ng.IDirective {
    public restrict = 'E';
    public scope = {
        items: '<'
    };
    public template = require('./template.html');
    
    constructor() {

    }

    static factory(): ng.IDirectiveFactory {
        const directive = () => new AutowpModerAttrsAttributeListDirective();
        
        return directive;
    }
    
    link = (scope: ng.IScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {

    }
}

angular.module(Module).directive('autowpModerAttrsAttributeListOptionsTree', AutowpModerAttrsAttributeListDirective.factory());
