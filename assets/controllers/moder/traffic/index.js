import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import "./whitelist";
import { IpService } from 'services/ip';
import { AclService } from 'services/acl';

const CONTROLLER_NAME = 'ModerTrafficController';
const STATE_NAME = 'moder-traffic';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/traffic',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                resolve: {
                    access: ['AclService', function (Acl) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', 'IpService',
        function($scope, $http, IpService) {
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                name: 'page/77/name',
                pageId: 77
            });
            
            var load = function() {
                $http({
                    method: 'GET',
                    url: '/api/traffic'
                }).then(function(response) {
                    $scope.items = response.data.items;
                    
                    angular.forEach($scope.items, function(item) {
                        IpService.getHostByAddr(item.ip).then(function(hostname) {
                            item.hostname = hostname;
                        });
                    });
                }, function() {
                    $state.go('error-404');
                });
            };
            
            load();
            
            $scope.addToWhitelist = function(ip) {
                $http({
                    method: 'POST',
                    url: '/api/traffic/whitelist',
                    data: {
                        ip: ip
                    }
                }).then(function(response) {
                    load();
                });
            };
            
            $scope.addToBlacklist = function(ip) {
                $http({
                    method: 'POST',
                    url: '/api/traffic/blacklist',
                    data: {
                        ip: ip,
                        period: 240,
                        reason: ''
                    }
                }).then(function(response) {
                    load();
                });
            };
            
            $scope.removeFromBlacklist = function(ip) {
                $http({
                    method: 'DELETE',
                    url: '/api/traffic/blacklist/' + ip
                }).then(function(response) {
                    load();
                });
            };
        }
    ]);

export default CONTROLLER_NAME;
