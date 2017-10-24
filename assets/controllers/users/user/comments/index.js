import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

const CONTROLLER_NAME = 'UsersUserCommentsController';
const STATE_NAME = 'users-user-comments';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/users/:identity/comments?order&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                params: {
                    order: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    }
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {
            
            var ctrl = this;
            
            ctrl.orders = {
                date_desc: 'users/comments/order/new',
                date_asc: 'users/comments/order/old',
                vote_desc: 'users/comments/order/positive',
                vote_asc: 'users/comments/order/negative'
            };
            
            ctrl.order = $state.params.order || 'date_desc';
            
            function init() {
                $scope.pageEnv({
                    layout: {
                        blankPage: false,
                        needRight: false
                    },
                    name: 'page/205/name',
                    pageId: 205,
                    args: {
                        USER_NAME: ctrl.user.name,
                        USER_IDENTITY: ctrl.user.identity ? ctrl.user.identity : 'user' + ctrl.user.id
                    }
                });
                
                var params = {
                    user_id: ctrl.user.id,
                    page: $state.params.page,
                    limit: 30,
                    order: ctrl.order,
                    fields: 'preview,url,vote'
                };
                
                $http({
                    method: 'GET',
                    url: '/api/comment',
                    params: params
                }).then(function(response) {
                    ctrl.comments = response.data.items;
                    ctrl.paginator = response.data.paginator;
                    ctrl.loading--;
                }, function(response) {
                    notify.response(response);
                    ctrl.loading--;
                });
            }
            
            var result = $state.params.identity.match(/^user([0-9]+)$/);
            
            if (result) {
                $http({
                    method: 'GET',
                    url: '/api/user/' + result[1],
                    fields: 'identity'
                }).then(function(response) {
                    ctrl.user = response.data;
                    init();
                }, function(response) {
                    notify.response(response);
                });
                
            } else {
                $http({
                    method: 'GET',
                    url: '/api/user',
                    params: {
                        identity: $state.params.identity,
                        limit: 1,
                        fields: 'identity'
                    }
                }).then(function(response) {
                    if (response.data.items.length <= 0) {
                        $state.go('error-404');
                    }
                    ctrl.user = response.data.items[0];
                    init();
                }, function(response) {
                    notify.response(response);
                });
            }

        }
    ]);

export default CONTROLLER_NAME;
