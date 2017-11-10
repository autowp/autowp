import * as angular from "angular";
import Module from 'app.module';

let leftPad = require('left-pad');

interface IPaginatorDirectiveScope extends ng.IScope {
    data: any;
    padd: (page: number) => string;
}

class AutowpPaginatorDirective implements ng.IDirective {
    restrict = 'E';
    scope = {
        data: '='
    };
    template = require('./paginator.html');

    constructor(private $timeout: ng.ITimeoutService) {
    }

    link = (scope: IPaginatorDirectiveScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {
        scope.padd = function(page: number): string {
            var size = Math.max(2, scope.data.pageCount.toString().length);
            return leftPad(page, size, '0');
        };
    }

    static factory(): ng.IDirectiveFactory {
        const directive = ($timeout: ng.ITimeoutService) => new AutowpPaginatorDirective($timeout);
        directive.$inject = ['$timeout'];
        return directive;
    }
}

angular.module(Module).directive('autowpPaginator', AutowpPaginatorDirective.factory());
