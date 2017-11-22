import * as angular from 'angular';
import Module from 'app.module';

class AutowpRightRolesTreeDirective implements ng.IDirective {
    restrict = 'E';
    scope = {
        roles: '='
    };
    template = require('./template.html');

    static factory(): ng.IDirectiveFactory {
        return () => new AutowpRightRolesTreeDirective();
    }
}

angular.module(Module).directive('autowpRolesTree', AutowpRightRolesTreeDirective.factory());