import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';
import notify from 'notify';

const CONTROLLER_NAME = 'ModerHotlinksController';
const STATE_NAME = 'moder-hotlinks';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/hotlinks',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                resolve: {
                    access: [ACL_SERVICE_NAME, function (Acl) {
                        return Acl.isAllowed('hotlinks', 'view', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', ACL_SERVICE_NAME,
        function($scope, $http, Acl) {
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                pageId: 67
            });
            
            var ctrl = this;
            ctrl.hosts = [];
            ctrl.canManage = false;
            Acl.isAllowed('hotlinks', 'manage').then(function() {
                ctrl.canManage = true;
            }, function() {
                ctrl.canManage = false;
            });
            
            var loadHosts = function() {
                $http({
                    method: 'GET',
                    url: '/api/hotlinks/hosts'
                }).then(function(response) {
                    ctrl.hosts = response.data.items;
                }, function(response) {
                    notify.response(response);
                });
            };
            
            loadHosts();
            
            ctrl.clearAll = function(host) {
                $http({
                    method: 'DELETE',
                    url: '/api/hotlinks/hosts'
                }).then(function() {
                    loadHosts();
                });
            };
            
            ctrl.clear = function(host) {
                $http({
                    method: 'DELETE',
                    url: '/api/hotlinks/hosts/' + encodeURIComponent(host)
                }).then(function() {
                    loadHosts();
                });
            };
            
            ctrl.addToWhitelist = function(host) {
                $http({
                    method: 'POST',
                    url: '/api/hotlinks/whitelist',
                    data: {
                        host: host
                    }
                }).then(function() {
                    loadHosts();
                });
            };
            
            ctrl.addToWhitelistAndClear = function(host) {
                ctrl.addToWhitelist(host);
                ctrl.clear(host);
            };
            
            ctrl.addToBlacklist = function(host) {
                $http({
                    method: 'POST',
                    url: '/api/hotlinks/blacklist',
                    data: {
                        host: host
                    }
                }).then(function() {
                    loadHosts();
                });
            };
        }
    ]);

export default CONTROLLER_NAME;
