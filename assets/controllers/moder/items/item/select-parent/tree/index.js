import angular from 'angular';
import Module from 'app.module';
import template from './template.html';

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
            template: template,
            transclude: true,
            controllerAs: 'ctrl',
            controller: ['$scope', 
                function($scope) {
                
                    var cltr = this;
                    
                    cltr.isDisabled = function(item) {
                        return item.id == $scope.disableItemId;
                    };
                    
                }
            ]
        };
    });