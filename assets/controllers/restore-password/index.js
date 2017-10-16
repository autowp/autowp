import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

import './new';
import './sent';

const CONTROLLER_NAME = 'RestorePasswordController';
const STATE_NAME = 'restore-password';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/restore-password',
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
            
            ctrl.form = {
                email: '',
                captcha: ''
            };
            
            ctrl.recaptchaKey = null;
            ctrl.showCaptcha = true;
            ctrl.failure = false;
            
            $http({
                method: 'GET',
                url: '/api/recaptcha',
                data: ctrl.form
            }).then(function(response) {
                ctrl.recaptchaKey = response.data.publicKey;
                ctrl.showCaptcha = !response.data.success;
            }, function(response) {
                notify.response(response);
            });
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/60/name',
                pageId: 60
            });
            
            ctrl.submit = function(id) {
                $http({
                    method: 'POST',
                    url: '/api/restore-password/request',
                    data: ctrl.form
                }).then(function() {
                    $state.go('restore-password-sent');
                }, function(response) {
                    ctrl.failure = response.status == 404;
                    if (response.status == 400) {
                        ctrl.invalidParams = response.data.invalid_params;
                        
                        ctrl.showCaptcha = response.data.invalid_params.captcha;
                    } else if (response.status == 404) {
                        
                    } else {
                        notify.response(response);
                    }
                });
            };
        }
    ]);

export default CONTROLLER_NAME;
