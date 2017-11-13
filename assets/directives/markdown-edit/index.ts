import * as angular from "angular";
import Module from 'app.module';
import * as autosize from 'autosize';

interface IMarkdownEditDirectiveScope extends ng.IScope {
    past: boolean;
    date: string;
}

class MarkdownEditDirective implements ng.IDirective {
    restrict = 'E';
    scope = {
        text: '=',
        save: '='
    };
    template = require('./template.html');
    private past: boolean;

    constructor() {
    }

    link = (scope: IMarkdownEditDirectiveScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {
        autosize(element.find('textarea'));
    }

    static factory(): ng.IDirectiveFactory {
        const directive = () => new MarkdownEditDirective();
        return directive;
    }
}

angular.module(Module).directive('autowpMarkdownEdit', MarkdownEditDirective.factory());