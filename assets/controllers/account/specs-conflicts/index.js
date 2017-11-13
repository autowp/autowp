import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';
import { UserService } from 'services/user';

const CONTROLLER_NAME = 'AccountSpecsConflictsController';
const STATE_NAME = 'account-specs-conflicts';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/specs-conflicts?filter&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', 'UserService',
        function($scope, $http, $state, UserService) {
            
            if (! $scope.user) {
                $state.go('login');
                return;
            }
            
            var ctrl = this;
            
            ctrl.filter = $state.params.filter || '0';
            ctrl.conflicts = [];
            ctrl.paginator = null;
            ctrl.weight = null;
            ctrl.page = $state.params.page;
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/188/name',
                pageId: 188
            });
            
            $http({
                method: 'GET',
                url: '/api/user/me',
                params: {
                    fields: 'specs_weight'
                }
            }).then(function(response) {
                ctrl.weight = response.data.specs_weight;
            }, function(response) {
                notify.response(response);
            });
            
            $http({
                method: 'GET',
                url: '/api/attr/conflict?fields=values',
                params: {
                    filter: ctrl.filter,
                    page: ctrl.page
                }
            }).then(function(response) {
                ctrl.conflicts = response.data.items;
                angular.forEach(ctrl.conflicts, function(conflict) {
                    angular.forEach(conflict.values, function(value) {
                        if ($scope.user.id != value.user_id) {
                            UserService.getUser(value.user_id).then(function(user) {
                                value.user = user;
                            });
                        }
                    });
                });
                ctrl.paginator = response.data.paginator;
            }, function(response) {
                notify.response(response);
            });
        }
    ]);

export default CONTROLLER_NAME;
