import * as angular from "angular";
import Module from 'app.module';

import './styles.less';

interface IPastTimeIndicatorDirectiveScope extends ng.IScope {
    past: boolean;
    date: string;
}

class PastTimeIndicatorDirective implements ng.IDirective {
    restrict = 'E';
    scope = {
        date: '<'
    };
    template = require('./template.html');
    private past: boolean;

    constructor() {
    }

    link = (scope: IPastTimeIndicatorDirectiveScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {
        this.past = (new Date(scope.date)).getTime() < ((new Date()).getTime() - 86400*1000);
    }

    static factory(): ng.IDirectiveFactory {
        const directive = () => new PastTimeIndicatorDirective();
        return directive;
    }
}

angular.module(Module).directive('autowpPastTimeIndicator', PastTimeIndicatorDirective.factory());