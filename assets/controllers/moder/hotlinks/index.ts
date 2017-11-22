import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import notify from 'notify';

const CONTROLLER_NAME = 'ModerHotlinksController';
const STATE_NAME = 'moder-hotlinks';

export class ModerHotlinksController {
    static $inject = ['$scope', '$http', 'AclService'];
    
    public hosts: any[] = [];
    public canManage: boolean = false;

    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private Acl: AclService
    ) {
        this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/67/name',
            pageId: 67
        });
        
        var self = this;
        
        this.Acl.isAllowed('hotlinks', 'manage').then(function(allow) {
            self.canManage = !!allow;
        }, function() {
            self.canManage = false;
        });
        
        this.loadHosts();
    }
    
    private loadHosts() {
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/hotlinks/hosts'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.hosts = response.data.items;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    };
    
    public clearAll(host: string) {
        var self = this;
        this.$http({
            method: 'DELETE',
            url: '/api/hotlinks/hosts'
        }).then(function() {
            self.loadHosts();
        });
    }
    
    public clear(host: string) {
        var self = this;
        this.$http({
            method: 'DELETE',
            url: '/api/hotlinks/hosts/' + encodeURIComponent(host)
        }).then(function() {
            self.loadHosts();
        });
    }
    
    public addToWhitelist(host: string) {
        var self = this;
        this.$http({
            method: 'POST',
            url: '/api/hotlinks/whitelist',
            data: {
                host: host
            }
        }).then(function() {
            self.loadHosts();
        });
    }
    
    public addToWhitelistAndClear(host: string) {
        this.addToWhitelist(host);
        this.clear(host);
    }
    
    public addToBlacklist(host: string) {
        var self = this;
        this.$http({
            method: 'POST',
            url: '/api/hotlinks/blacklist',
            data: {
                host: host
            }
        }).then(function() {
            self.loadHosts();
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerHotlinksController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/hotlinks',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.isAllowed('hotlinks', 'view', 'unauthorized');
                    }]
                }
            });
        }
    ]);

