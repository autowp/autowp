import angular from 'angular';
import Module from 'app.module';
import template from './template.html';

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
                template: template
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
                pageId: 110
            });
            
            $scope.comments = [];
            $scope.paginator = null;
            $scope.user = $state.params.user;
            $scope.moderator_attention = $state.params.moderator_attention;
            $scope.item_id = $state.params.item_id;
            $scope.page = $state.params.page;
            
            $scope.load = function() {
                $scope.loading = true;
                
                var params = {
                    user: $scope.user,
                    moderator_attention: $scope.moderator_attention,
                    item_id: $scope.item_id,
                    page: $scope.page
                };
                
                $state.go(STATE_NAME, params, {
                    notify: false,
                    reload: false,
                    location: 'replace'
                });
                
                $http({
                    method: 'GET',
                    url: '/api/comments',
                    params: params
                }).then(function(response) {
                    $scope.comments = response.data.comments;
                    $scope.paginator = response.data.paginator;
                    $scope.loading = false;
                });
            };
            
            $scope.load();
        }
    ]);

export default CONTROLLER_NAME;
