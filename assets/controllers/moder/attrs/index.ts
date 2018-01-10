import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import { AttrsService } from 'services/attrs';
import notify from 'notify';

import './attribute';
import './attribute-list';
import './zone';

const CONTROLLER_NAME = 'ModerAttrsController';
const STATE_NAME = 'moder-attrs';

export class ModerAttrsController {
    static $inject = ['$scope', '$http', '$state', 'AttrsService'];
    
    public attributes: any[];
    public zones: any[];
    public moveUp: Function;
    public moveDown: Function;

    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private $state: any,
        private attrsService: AttrsService
    ) {

        this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/100/name',
            pageId: 100
        });
        
        
        let self = this;
        
        this.attrsService.getZones().then(function(zones: autowp.IAttrZone[]) {
            self.zones = zones;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
        
        this.loadAttributes();
        
        this.moveUp = function(id: number) {
            self.$http({
                method: 'PATCH',
                url: '/api/attr/attribute/' + id,
                data: {
                    move: 'up'
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.loadAttributes();
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
        };
        
        this.moveDown = function(id: number) {
            self.$http({
                method: 'PATCH',
                url: '/api/attr/attribute/' + id,
                data: {
                    move: 'down'
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.loadAttributes();
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
        };
    }
    
    private loadAttributes()
    {
        let self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/attr/attribute',
            params: {
                recursive: 1
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.attributes = response.data.items;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerAttrsController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/attrs',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.isAllowed('attrs', 'edit', 'unauthorized');
                    }]
                }
            });
        }
    ]);
