import * as angular from 'angular';
import Module from 'app.module';

angular.module(Module)
    .directive('autowpModerItemsItemSelectParentTree', function() {
        return {
            restirct: 'E',
            scope: {
                item: '=',
                select: '<',
                loadChilds: '<',
                disableItemId: '<'
            },
            template: require('./template.html'),
            transclude: true,
            controllerAs: 'ctrl',
            controller: function() { }
        };
    });