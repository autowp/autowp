import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import IP_SERVICE_NAME from 'services/ip';
import ACL_SERVICE_NAME from 'services/acl';

const CONTROLLER_NAME = 'ModerTrafficWhitelistController';
const STATE_NAME = 'moder-traffic-whitelist';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/traffic/whitelist',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                resolve: {
                    access: [ACL_SERVICE_NAME, function (Acl) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', IP_SERVICE_NAME,
        function($scope, $http, IpService) {
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                pageId: 77
            });
            
            $http({
                method: 'GET',
                url: '/api/traffic/whitelist'
            }).then(function(response) {
                $scope.items = response.data.items;
                
                /*angular.forEach($scope.items, function(item) {
                    IpService.getHostByAddr(item.ip).then(function(hostname) {
                        item.hostname = hostname;
                    });
                });*/
            }, function() {
                $state.go('error-404');
            });
            
            $scope.deleteItem = function(item) {
                $http({
                    method: 'DELETE',
                    url: '/api/traffic/whitelist/' + item.ip
                }).then(function(response) {
                    var index = $scope.items.indexOf(item);
                    if (index !== -1) {
                        $scope.items.splice(index, 1);
                    }
                    /*angular.forEach($scope.items, function(item) {
                        IpService.getHostByAddr(item.ip).then(function(hostname) {
                            item.hostname = hostname;
                        });
                    });*/
                });
            };
        }
    ]);

export default CONTROLLER_NAME;
