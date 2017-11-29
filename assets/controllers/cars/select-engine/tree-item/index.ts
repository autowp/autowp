import * as angular from 'angular';
import Module from 'app.module';


interface IAutowpCarsSelectEngineTreeItemDirectiveScope extends ng.IScope {
    disableItemId: number;
    isDisabled: (item: any) => boolean
}

class AutowpCarsSelectEngineTreeItemDirective implements ng.IDirective {
    restrict = 'E';
    scope = {
        item: '=',
        loadChilds: '<',
        selectEngine: '<'
    };
    template = require('./template.html');

    link = (scope: IAutowpCarsSelectEngineTreeItemDirectiveScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {
    }

    static factory(): ng.IDirectiveFactory {
        return () => new AutowpCarsSelectEngineTreeItemDirective();
    }
}

angular.module(Module).directive('autowpCarsSelectEngineTreeItem', AutowpCarsSelectEngineTreeItemDirective.factory());
