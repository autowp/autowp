import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import { AttrsService } from 'services/attrs';
import notify from 'notify';

import './attribute-list';

const CONTROLLER_NAME = 'ModerAttrsZoneController';
const STATE_NAME = 'moder-attrs-zone';

export class ModerAttrsZoneController {
    static $inject = ['$scope', '$http', '$state', 'AttrsService'];
    
    public zone: autowp.IAttrZone;
    public attributes: any[];
    public zoneAttribute: any = {};
    public change: Function;

    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private $state: any,
        private attrsService: AttrsService
    ) {
        let self = this;
        this.attrsService.getZone(this.$state.params.id).then(function(zone: autowp.IAttrZone) {
            self.zone = zone;
            
            self.$scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                name: 'page/142/name',
                pageId: 142,
                args: {
                    ZONE_NAME: self.zone.name,
                    ZONE_ID: self.zone.id
                }
            });
            
            self.loadAttributes();
            
            self.$http({
                method: 'GET',
                url: '/api/attr/zone-attribute',
                params: {
                    zone_id: self.zone.id
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                for (let item of response.data.items) {
                    self.zoneAttribute[item.attribute_id] = true;
                }
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            })
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });

        this.change = function(id: number, value: boolean) {
            
            if (value) {
                self.$http({
                    method: 'POST',
                    url: '/api/attr/zone-attribute',
                    data: {
                        zone_id: self.zone.id,
                        attribute_id: id
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
            } else {
                self.$http({
                    method: 'DELETE',
                    url: '/api/attr/zone-attribute/' + self.zone.id + '/' + id
                }).then(function(response: ng.IHttpResponse<any>) {
                    
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
            }
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
    .controller(CONTROLLER_NAME, ModerAttrsZoneController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/attrs/zone/{id}',
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
