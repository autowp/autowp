import angular from 'angular';
import Module from 'app.module';
import template from './template.html';

const CONTROLLER_NAME = 'ModerItemsAlphaController';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: 'moder-cars-alpha',
                url: '/moder/cars/alpha',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http',
        function($scope, $http) {
            
            $scope.title = 'page/119/title';
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                pageId: 74
            });
            
            $http.get('/api/items/alpha').then(function(response) {
                $scope.groups = response.data.groups;
            });
            
            $scope.selectChar = function(char) {
                $scope.items = [];
                $scope.loading = true;
                $http.get('/api/items/alpha-items', {
                    params: {char: char}
                }).then(function(response) {
                    $scope.items = response.data.items;
                    $scope.loading = false;
                });
            };
        }
    ]);

export default CONTROLLER_NAME;