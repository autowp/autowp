import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

const CONTROLLER_NAME = 'AccountAccessController';
const STATE_NAME = 'account-access';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/access',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', '$translate',
        function($scope, $http, $state, $translate) {
            
            if (! $scope.user) {
                $state.go('login');
                return;
            }
            
            var ctrl = this;
            
            ctrl.form = {
                password_old: null,
                password: null,
                password_confirm: null
            };
            
            ctrl.invalidParams = {};
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                pageId: 133
            });
            
            ctrl.submit = function() {
                ctrl.invalidParams = {};
                
                $http({
                    method: 'PUT',
                    url: '/api/user/me',
                    data: ctrl.form
                }).then(function() {
                    
                    ctrl.form = {
                        password_old: null,
                        password: null,
                        password_confirm: null
                    };
                    
                    $translate('account/access/change-password/saved').then(function(translation) {
                        notify({
                            icon: 'fa fa-check',
                            message: translation
                        }, {
                            type: 'success'
                        });
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
