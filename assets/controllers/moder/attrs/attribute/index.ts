import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import { AttrsService } from 'services/attrs';
import notify from 'notify';

const CONTROLLER_NAME = 'ModerAttrsAttributeController';
const STATE_NAME = 'moder-attrs-attribute';

export class ModerAttrsAttributeController {
    static $inject = ['$scope', '$http', '$state', 'AttrsService', '$translate'];
    
    public attribute: autowp.IAttrAttribute;
    public attributes: any[];
    public loading: number = 0;
    
    public typeOptionsDefaults = [{id: null, name: '-'}];
    public typeOptions: any[] = [];
    public typeMap: any = {};
    
    public unitOptionsDefaults = [{id: null, name: '-'}];
    public unitOptions: any[] = [];

    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private $state: any,
        private attrsService: AttrsService,
        private $translate: ng.translate.ITranslateService
    ) {
        let self = this;
        this.attrsService.getAttribute(this.$state.params.id).then(function(attribute: autowp.IAttrAttribute) {
            self.attribute = attribute;
            
            self.$translate(self.attribute.name).then(function(translation: string) {
                self.$scope.pageEnv({
                    layout: {
                        isAdminPage: true,
                        blankPage: false,
                        needRight: false
                    },
                    name: 'page/101/name',
                    pageId: 101,
                    args: {
                        ATTR_NAME: translation
                    }
                });
            }, function() {
                self.$scope.pageEnv({
                    layout: {
                        isAdminPage: true,
                        blankPage: false,
                        needRight: false
                    },
                    name: 'page/101/name',
                    pageId: 101,
                    args: {
                        ATTR_NAME: self.attribute.name
                    }
                });
            });
            
            self.attrsService.getAttributeTypes().then(function(types: autowp.IAttrAttributeType[]) {
                self.typeOptions = self.typeOptionsDefaults;
                for (let item of types) {
                    self.typeMap[item.id] = item.name;
                    self.typeOptions.push({
                        id: item.id,
                        name: item.name
                    });
                }
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
            
            self.attrsService.getUnits().then(function(items: autowp.IAttrUnit[]) {
                self.unitOptions = self.unitOptionsDefaults;
                for (let item of items) {
                    self.unitOptions.push({
                        id: item.id,
                        name: item.name
                    });
                }
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
            
            self.$http({
                method: 'GET',
                url: '/api/attr/attribute',
                params: {
                    parent_id: self.attribute.id,
                    recursive: 0,
                    fields: 'unit'
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.attributes = response.data.items;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
            
        }, function(response: ng.IHttpResponse<any>) {
            self.$state.go('error-404');
        });

    }
    
    public saveAttribute()
    {
        let self = this;
        
        this.loading++;
        this.$http({
            method: 'PATCH',
            url: '/api/attr/attribute/' + this.attribute.id,
            data: self.attribute
        }).then(function(response: ng.IHttpResponse<any>) {
            self.loading--;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
            self.loading--;
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerAttrsAttributeController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/attrs/attribute/{id}',
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
