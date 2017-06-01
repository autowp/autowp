import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';

const CONTROLLER_NAME = 'ModerCommentsController';
const STATE_NAME = 'moder-comments';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/comments?user&moderator_attention&item_id&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                params: { 
                    user: { dynamic: true },
                    moderator_attention: { dynamic: true },
                    item_id: { dynamic: true },
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
        '$scope', '$http', '$state', '$q',
        function($scope, $http, $state, $q) {
            
            $scope.title = 'page/119/title';
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                pageId: 110
            });
            
            $scope.comments = [];
            $scope.paginator = null;
            $scope.selectedUser = $state.params.user ? {
                id: $state.params.user,
                name: '#' + $state.params.user
            } : null;
            $scope.moderator_attention = $state.params.moderator_attention;
            $scope.selectedItem = $state.params.item_id ? {
                id: $state.params.item_id,
                name: '#' + $state.params.item_id
            } : null;
            $scope.page = $state.params.page;
            
            $scope.load = function() {
                $scope.loading = true;
                
                var params = {
                    user: $scope.selectedUser ? $scope.selectedUser.id : null,
                    moderator_attention: $scope.moderator_attention,
                    item_id: $scope.selectedItem ? $scope.selectedItem.id : null,
                    page: $scope.page
                };
                
                $state.go(STATE_NAME, params, {
                    notify: false,
                    reload: false,
                    location: 'replace'
                });
                
                $http({
                    method: 'GET',
                    url: '/api/comment',
                    params: params
                }).then(function(response) {
                    $scope.comments = response.data.comments;
                    $scope.paginator = response.data.paginator;
                    $scope.loading = false;
                });
            };
            
            $scope.queryUserName = function(query) { 
                var deferred = $q.defer();
                
                var params = {
                    limit: 10
                };
                if (query.substring(0, 1) == '#') {
                    params.id = query.substring(1);
                } else {
                    params.search = query;
                }
                
                $http({
                    method: 'GET',
                    url: '/api/user',
                    params: params
                }).then(function(response) {
                    deferred.resolve(response.data.items);
                }, function() {
                    deferred.reject(null);
                });
                return deferred.promise;
            };
            
            $scope.queryItemName = function(query) {
                var deferred = $q.defer();
                
                var params = {
                    limit: 10,
                    fields: 'name_text'
                };
                if (query.substring(0, 1) == '#') {
                    params.id = query.substring(1);
                } else {
                    params.search = query;
                }
                
                $http({
                    method: 'GET',
                    url: '/api/item',
                    params: params
                }).then(function(response) {
                    deferred.resolve(response.data.items);
                }, function() {
                    deferred.reject(null);
                });
                return deferred.promise;
            };
            
            $scope.load();
        }
    ]);

export default CONTROLLER_NAME;
