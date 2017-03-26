import angular from 'angular';
import Module from 'app.module';
import template from './template.html';

const CONTROLLER_NAME = 'ModerIndexStatController';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: 'moder-index-stat',
                url: '/moder/index/stat',
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
                pageId: 119
            });
            
            $http.get('/api/stat/global-summary').then(function(response) {
                $scope.items = response.data.items;
            });
        }
    ]);

export default CONTROLLER_NAME;
