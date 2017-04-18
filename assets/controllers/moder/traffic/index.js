import angular from 'angular';
import Module from 'app.module';
import template from './template.html';

const CONTROLLER_NAME = 'ModerTrafficController';
const STATE_NAME = 'moder-traffic';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/traffic',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http',
        function($scope, $http) {
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                pageId: 77
            });
            
            $http({
                method: 'GET',
                url: '/api/traffic'
            }).then(function(response) {
                $scope.items = response.data.items;
                
                angular.forEach($scope.items, function(item) {
                    $http({
                        method: 'GET',
                        url: '/api/ip/' + item.ip
                    }).then(function(response) {
                        item.hostname = response.data.host;
                    });
                });
            }, function() {
                $state.go('error-404');
            });
        }
    ]);

export default CONTROLLER_NAME;
