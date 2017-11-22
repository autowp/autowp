import * as angular from 'angular';
import Module from 'app.module';
import { IpService } from 'services/ip';
import { AclService } from 'services/acl';

const CONTROLLER_NAME = 'ModerTrafficWhitelistController';
const STATE_NAME = 'moder-traffic-whitelist';

export class ModerTrafficWhitelistController {
    static $inject = ['$scope', '$http', '$state', 'IpService'];

    public items: any[];
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private $state: any,
        private IpService: IpService
    ) {
        this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/77/name',
            pageId: 77
        });
        
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/traffic/whitelist'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.items = response.data.items;
            
            /*angular.forEach($scope.items, function(item) {
                IpService.getHostByAddr(item.ip).then(function(hostname) {
                    item.hostname = hostname;
                });
            });*/
        }, function() {
            self.$state.go('error-404');
        });
    }
    
    public deleteItem(item: any) {
        var self = this;
        this.$http({
            method: 'DELETE',
            url: '/api/traffic/whitelist/' + item.ip
        }).then(function(response: ng.IHttpResponse<any>) {
            var index = self.items.indexOf(item);
            if (index !== -1) {
                self.items.splice(index, 1);
            }
            /*angular.forEach($scope.items, function(item) {
                IpService.getHostByAddr(item.ip).then(function(hostname) {
                    item.hostname = hostname;
                });
            });*/
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerTrafficWhitelistController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/traffic/whitelist',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ]);
