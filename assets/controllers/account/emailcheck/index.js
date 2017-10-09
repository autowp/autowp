import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

const CONTROLLER_NAME = 'AccountEmailcheckController';
const STATE_NAME = 'account-emailcheck';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/emailcheck/:code',
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
            
            ctrl.success = false;
            ctrl.failure = false;
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                pageId: 54
            });
            
            $http({
                method: 'POST',
                url: '/api/user/emailcheck',
                data: {
                    code: $state.params.code
                }
            }).then(function() {
                
                ctrl.success = true;
                
            }, function(response) {
                ctrl.failure = true;
            });
        }
    ]);

export default CONTROLLER_NAME;
