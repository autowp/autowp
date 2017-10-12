import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

const CONTROLLER_NAME = 'AccountAccountsController';
const STATE_NAME = 'account-accounts';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/accounts',
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
            
            ctrl.service = null;
            ctrl.accounts = [];
            ctrl.connectFailed = false;
            ctrl.disconnectFailed = false;
            
            ctrl.services = {
                facebook: 'Facebook',
                vk: 'VK',
                'google-plus': 'Google+',
                twitter: 'Twitter',
                github: 'Github',
                linkedin: 'Linkedin'
            };
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/123/name',
                pageId: 123
            });
            
            ctrl.load = function() {
                $http({
                    method: 'GET',
                    url: '/api/account'
                }).then(function(response) {
                    ctrl.accounts = response.data.items;
                }, function(response) {
                    notify.response(response);
                });
            };
            
            ctrl.load();
            
            ctrl.start = function() {
                
                if (! ctrl.service) {
                    return;
                }
                
                $http({
                    method: 'POST',
                    url: '/api/account/start',
                    data: {
                        service: ctrl.service
                    }
                }).then(function(response) {
                    window.location = response.data.url;
                }, function(response) {
                    notify.response(response);
                });
            };
            
            ctrl.remove = function(account) {
                $http({
                    method: 'DELETE',
                    url: '/api/account/' + account.id
                }).then(function(response) {
                    
                    $translate('account/accounts/removed').then(function(translation) {
                        notify({
                            icon: 'fa fa-check',
                            message: translation
                        }, {
                            type: 'success'
                        });
                    });
                    
                    ctrl.load();
                }, function(response) {
                    ctrl.disconnectFailed = true;
                    notify.response(response);
                });
            };
        }
    ]);

export default CONTROLLER_NAME;
