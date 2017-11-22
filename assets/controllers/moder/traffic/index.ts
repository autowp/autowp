import * as angular from 'angular';
import Module from 'app.module';
import "./whitelist";
import { IpService } from 'services/ip';
import { AclService } from 'services/acl';

const CONTROLLER_NAME = 'ModerTrafficController';
const STATE_NAME = 'moder-traffic';

export class ModerTrafficController {
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
        this.load();
    }
    
    private load() {
        var self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/traffic'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.items = response.data.items;
            
            angular.forEach(self.items, function(item: any) {
                self.IpService.getHostByAddr(item.ip).then(function(hostname: string) {
                    item.hostname = hostname;
                });
            });
        }, function() {
            self.$state.go('error-404');
        });
    }
    
    public addToWhitelist(ip: string) {
        var self = this;
        this.$http({
            method: 'POST',
            url: '/api/traffic/whitelist',
            data: {
                ip: ip
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.load();
        });
    }
    
    public addToBlacklist(ip: string) {
        var self = this;
        this.$http({
            method: 'POST',
            url: '/api/traffic/blacklist',
            data: {
                ip: ip,
                period: 240,
                reason: ''
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.load();
        });
    }
    
    public removeFromBlacklist(ip: string) {
        var self = this;
        this.$http({
            method: 'DELETE',
            url: '/api/traffic/blacklist/' + ip
        }).then(function(response: ng.IHttpResponse<any>) {
            self.load();
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerTrafficController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/traffic',
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

