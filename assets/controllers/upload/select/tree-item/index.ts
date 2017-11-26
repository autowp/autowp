import * as angular from 'angular';
import Module from 'app.module';


interface IAutowpUploadSelectTreeItemDirectiveScope extends ng.IScope {
    disableItemId: number;
    isDisabled: (item: any) => boolean
}

class AutowpUploadSelectTreeItemDirective implements ng.IDirective {
    restrict = 'E';
    scope = {
        item: '=',
        loadChilds: '<',
        typeId: '<'
    };
    template = require('./template.html');

    link = (scope: IAutowpUploadSelectTreeItemDirectiveScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {
    }

    static factory(): ng.IDirectiveFactory {
        return () => new AutowpUploadSelectTreeItemDirective();
    }
}

angular.module(Module).directive('autowpUploadSelectTreeItem', AutowpUploadSelectTreeItemDirective.factory());
