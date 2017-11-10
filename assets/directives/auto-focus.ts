import * as angular from "angular";
import Module from 'app.module';

interface IAutoFocusDirectiveScope extends ng.IScope {
    $timeout: ng.ITimeoutService;
}

class AutoFocusDirective implements ng.IDirective {
    restrict = 'AC';

    constructor(private $timeout: ng.ITimeoutService) {
    }

    link = (scope: IAutoFocusDirectiveScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {
        this.$timeout(function() {
            element[0].focus();
        }, 0);
    }

    static factory(): ng.IDirectiveFactory {
        const directive = ($timeout: ng.ITimeoutService) => new AutoFocusDirective($timeout);
        directive.$inject = ['$timeout'];
        return directive;
    }
}

angular.module(Module).directive('autoFocus', AutoFocusDirective.factory());