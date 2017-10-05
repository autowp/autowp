import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

const CONTROLLER_NAME = 'AccountEmailController';
const STATE_NAME = 'account-email';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/email',
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
            
            ctrl.email = null;
            ctrl.newEmail = null;
            
            ctrl.invalidParams = {};
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                pageId: 55
            });
            
            $http({
                method: 'GET',
                url: '/api/user/me',
                params: {
                    fields: 'email'
                }
            }).then(function(response) {
                ctrl.email = response.data.email;
                ctrl.newEmail = response.data.email;
            }, function(response) {
                notify.response(response);
            });
            
            ctrl.submit = function() {
                ctrl.invalidParams = {};
                
                $http({
                    method: 'PUT',
                    url: '/api/user/me',
                    data: {
                        email: ctrl.newEmail
                    }
                }).then(function() {
                    
                    ctrl.sent = true;
                    
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
