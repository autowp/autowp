import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

import './ok';

const CONTROLLER_NAME = 'RestorePasswordNewController';
const STATE_NAME = 'restore-password-new';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/restore-password/new?code',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {
            
            var ctrl = this;
            
            $http({
                method: 'GET',
                url: '/api/restore-password/new',
                params: {
                    code: $state.params.code
                }
            }).then(function() {
                
            }, function(response) {
                $state.go('error-404');
            });
            
            
            ctrl.form = {
                code: $state.params.code,
                password: '',
                password_confirm: ''
            };
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/134/name',
                pageId: 134
            });
            
            ctrl.submit = function(id) {
                $http({
                    method: 'POST',
                    url: '/api/restore-password/new',
                    data: ctrl.form
                }).then(function() {
                    $state.go('restore-password-new-ok');
                }, function(response) {
                    ctrl.failure = response.status == 404;
                    if (response.status == 400) {
                        ctrl.invalidParams = response.data.invalid_params;
                    } else if (response.status == 404) {
                        
                    } else {
                        notify.response(response);
                    }
                });
            };
        }
    ]);

export default CONTROLLER_NAME;
