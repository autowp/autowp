import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';

const STATE_NAME = 'moder-cars-alpha';
const CONTROLLER_NAME = 'ModerItemsAlphaController';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/cars/alpha?char&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                resolve: {
                    access: [ACL_SERVICE_NAME, function (Acl) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {
            
            $scope.title = 'page/119/title';
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                pageId: 74
            });
            
            $scope.paginator = null;
            $scope.page = $state.params.page;
            
            $http.get('/api/item/alpha').then(function(response) {
                $scope.groups = response.data.groups;
            });
            
            function loadChar(char) {
                $scope.paginator = null;
                $scope.items = [];
                $scope.loading = true;
                $http.get('/api/item', {
                    params: {
                        search: char,
                        page: $scope.page,
                        limit: 500
                    }
                }).then(function(response) {
                    $scope.paginator = response.data.paginator;
                    $scope.items = response.data.items;
                    $scope.loading = false;
                });
            }
            
            $scope.selectChar = function(char) {
                
                $scope.page = null;
                
                $state.go(STATE_NAME, {
                    char: char,
                    page: $scope.page
                }, {
                    notify: false,
                    reload: false,
                    location: 'replace'
                });
                
                loadChar(char);
            };
            
            if ($state.params.char) {
                loadChar($state.params.char);
            }
        }
    ]);

export default STATE_NAME;
