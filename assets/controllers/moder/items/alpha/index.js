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
                params: { 
                    char: { dynamic: true },
                    page: { dynamic: true }
                },
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
            
            var ctrl = this;
            
            ctrl.loading = 0;
            
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                name: 'page/74/name',
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
                ctrl.loading++;
                $http.get('/api/item', {
                    params: {
                        name: char + '%',
                        page: $scope.page,
                        limit: 500,
                        fields: 'name_html'
                    }
                }).then(function(response) {
                    $scope.paginator = response.data.paginator;
                    $scope.items = response.data.items;
                    ctrl.loading--;
                }, function() {
                    ctrl.loading--;
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
