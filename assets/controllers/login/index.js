import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

import './ok';

const CONTROLLER_NAME = 'LoginController';
const STATE_NAME = 'login';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/login',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {
            
            if ($scope.$parent.user) {
                $state.go('login-ok');
                return;
            }
            
            var ctrl = this;
            
            ctrl.services = [];
            
            ctrl.form = {
                login: '',
                password: '',
                remember: false
            };
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                pageId: 79
            });
            
            $http({
                method: 'GET',
                url: '/api/login/services'
            }).then(function(response) {
                ctrl.services = response.data.items;
            }, function(response) {
                notify.response(response);
            });
            
            ctrl.submit = function() {
                $http({
                    method: 'POST',
                    url: '/api/login',
                    data: ctrl.form
                }).then(function() {
                    $http({
                        method: 'GET',
                        url: '/api/user/me'
                    }).then(function(response) {
                        $scope.$parent.user = response.data;
                        $state.go('login-ok');
                    }, function(response) {
                        notify.response(response);
                    });
                    
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
