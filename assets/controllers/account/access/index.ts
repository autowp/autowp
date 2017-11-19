import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { IAutowpControllerScope } from 'declarations.d.ts';

const CONTROLLER_NAME = 'AccountAccessController';
const STATE_NAME = 'account-access';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/access',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', '$translate',
        function($scope: IAutowpControllerScope, $http: ng.IHttpService, $state: any, $translate: ng.translate.ITranslateService) {
            
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
                name: 'page/133/name',
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
