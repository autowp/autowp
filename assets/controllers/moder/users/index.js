import angular from 'angular';
import Module from 'app.module';
import template from './template.html';

const CONTROLLER_NAME = 'ModerUsersController';
const STATE_NAME = 'moder-users';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/users?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {
            
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                pageId: 203
            });
            
            $scope.paginator = null;
            $scope.users = [];
            $scope.page = $state.params.page;
            
            $scope.load = function() {
                $scope.loading = true;
                $scope.users = [];
                
                var params = {
                    page: $scope.page,
                    limit: 30,
                    fields: 'image,reg_date,last_online,email,login'
                };
                
                $state.go(STATE_NAME, params, {
                    notify: false,
                    reload: false,
                    location: 'replace'
                });
                
                $http({
                    method: 'GET',
                    url: '/api/user',
                    params: params
                }).then(function(response) {
                    $scope.users = response.data.items;
                    $scope.paginator = response.data.paginator;
                    $scope.loading = false;
                });
            };
            
            $scope.load();
        }
    ]);

export default CONTROLLER_NAME;
