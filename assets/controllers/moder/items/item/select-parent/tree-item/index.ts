import * as angular from 'angular';
import Module from 'app.module';


interface IAutowpModerItemsItemSelectParentTreeItemDirectiveScope extends ng.IScope {
    disableItemId: number;
    isDisabled: (item: any) => boolean
}

class AutowpModerItemsItemSelectParentTreeItemDirective implements ng.IDirective {
    restrict = 'E';
    scope = {
        item: '=',
        select: '<',
        loadChilds: '<',
        disableItemId: '<',
        typeId: '<'
    };
    template = require('./template.html');

    link = (scope: IAutowpModerItemsItemSelectParentTreeItemDirectiveScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {
      
        scope.isDisabled = function(item: any): boolean {
            return item.id == scope.disableItemId;
        };
    }

    static factory(): ng.IDirectiveFactory {
        return () => new AutowpModerItemsItemSelectParentTreeItemDirective();
    }
}

angular.module(Module).directive('autowpModerItemsItemSelectParentTreeItem', AutowpModerItemsItemSelectParentTreeItemDirective.factory());
