import * as angular from "angular";
import Module from 'app.module';

function replaceArgs(str: string, args: any) {
    angular.forEach(args, function(value, key) {
        str = str.replace(key, ''+value);
    });
    return str;
}

interface IAutowpSpecificationsEditorInputDirectiveScope extends ng.IScope {
    items: any[];
}


class AutowpSpecificationsEditorInputDirective implements ng.IDirective {
    restrict = 'E';
    scope = {
        typeId: '<',
        unit: '<',
        value: '=',
        options: '<',
        multiple: '<'
    };
    transclude = true;
    template = require('./template.html');

    link = (scope: IAutowpSpecificationsEditorInputDirectiveScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {
      
        
    }

    static factory(): ng.IDirectiveFactory {
        const directive = () => new AutowpSpecificationsEditorInputDirective();
        directive.$inject = [];
        return directive;
    }
}

angular.module(Module).directive('autowpSpecificationsEditorInput', AutowpSpecificationsEditorInputDirective.factory());
