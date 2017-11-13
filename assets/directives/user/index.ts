import * as angular from "angular";
import Module from '../../app.module';

interface IUserDirectiveScope extends ng.IScope {
    user: any;
    isModer: boolean;
}

class AutowpUserDirective implements ng.IDirective {
    restrict = 'E';
    scope = {
        user: '=',
        isModer: '='
    };
    template = require('./template.html');

    constructor() {
    }

    link = (scope: IUserDirectiveScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {
    }

    static factory(): ng.IDirectiveFactory {
        const directive = () => new AutowpUserDirective();
        return directive;
    }
}

angular.module(Module).directive('autowpUser', AutowpUserDirective.factory());