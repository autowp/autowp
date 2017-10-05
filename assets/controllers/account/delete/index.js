import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

import './deleted';

const CONTROLLER_NAME = 'AccountDeleteController';
const STATE_NAME = 'account-delete';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/delete',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {
            
            if (! $scope.user) {
                $state.go('login');
                return;
            }
            
            var ctrl = this;
            
            ctrl.form = {
                password_old: ''
            };
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                pageId: 137
            });
            
            ctrl.submit = function() {
                $http({
                    method: 'PUT',
                    url: '/api/user/me',
                    data: {
                        password_old: ctrl.form.password_old,
                        deleted: 1
                    }
                }).then(function() {
                    $scope.setUser(null);
                    $state.go('account-deleted');
                }, function(response) {
                    if (response.status == 400) {
                        ctrl.invalidParams = response.data.invalid_params;
                    } else {
                        notify.response(response);
                    }
                });
            };
        }
    ]);

export default CONTROLLER_NAME;
