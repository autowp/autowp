import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

import './ok';

const CONTROLLER_NAME = 'SignupController';
const STATE_NAME = 'signup';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/signup',
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
                name: '',
                password: '',
                password_confirm: '',
                captcha: ''
            };
            
            ctrl.recaptchaKey = null;
            ctrl.showCaptcha = true;
            
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
                    needRight: true
                },
                name: 'page/52/name',
                pageId: 52
            });
            
            ctrl.submit = function(id) {
                $http({
                    method: 'POST',
                    url: '/api/user',
                    data: ctrl.form
                }).then(function() {
                    $state.go('signup-ok');
                }, function(response) {
                    if (response.status == 400) {
                        ctrl.invalidParams = response.data.invalid_params;
                        
                        ctrl.showCaptcha = response.data.invalid_params.captcha;
                    } else {
                        notify.response(response);
                    }
                });
            };
        }
    ]);

export default CONTROLLER_NAME;
