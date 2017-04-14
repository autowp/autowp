import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import './styles.less';

angular.module(Module)
    .directive('autowpModerPictureMoveItem', ['$http', function($http) {
        return {
            restirct: 'E',
            scope: {
                item: '=',
                selectItem: '='
            },
            template: template,
            controller: ['$scope', function($scope) {
                
                $scope.childs = [];
                $scope.loading = false;
                
                $scope.toggleItem = function(item) {
                    item.expanded = !item.expanded;
                    
                    if (item.expanded) {
                        $scope.loading = true;
                        $http({
                            method: 'GET',
                            url: '/api/item-parent',
                            params: {
                                type_id: 1,
                                parent_id: item.item_id,
                                fields: 'item.name_html,item.childs_count',
                                limit: 500
                            }
                        }).then(function(response) {
                            $scope.loading = false;
                            $scope.childs = response.data.items;
                        });
                    }
                };
            }]
        };
    }]);