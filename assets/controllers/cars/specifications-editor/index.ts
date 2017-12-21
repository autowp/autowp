import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { ItemService } from 'services/item';
import { AclService } from 'services/acl';
import * as $ from 'jquery';

const CONTROLLER_NAME = 'CarsSpecificationsEditorController';
const STATE_NAME = 'cars-specifications-editor';

function toPlain(options: any[], deep: number): any[] {
    var result: any[] = [];
    angular.forEach(options, function(item) {
        item.deep = deep;
        result.push(item);
        angular.forEach(toPlain(item.childs, deep+1), function(item: any) {
            result.push(item);
        });
    });
    return result;
}

export class CarsSpecificationsEditorController {
    static $inject = ['$scope', '$http', '$state', 'ItemService', 'AclService'];
  
    private item: autowp.IItem;
    public isAllowedEditEngine: boolean = false;
    public isSpecsAdmin: boolean = false;
    public isModer: boolean = false;
    public resultHtml: string = '';
    public engine: autowp.IItem;
    public tab: string = 'info';
    public tabs = {
          info: {
              icon: 'fa fa-info',
              title: 'specifications-editor/tabs/info',
              count: 0,
          },
          engine: {
              icon: 'glyphicon glyphicon-align-left',
              title: 'specifications-editor/tabs/engine',
              count: 0,
          },
          spec: {
              icon: 'fa fa-car',
              title: 'specifications-editor/tabs/specs',
              count: 0,
          },
          result: {
              icon: 'fa fa-table',
              title: 'specifications-editor/tabs/result',
              count: 0,
          },
          admin: {
              icon: 'fa fa-cog',
              title: 'specifications-editor/tabs/admin',
              count: 0,
          }
    };
    public attributes: any[] = [];
    public values: Map<number, any>;
    public userValues: Map<number, any>;
    public currentUserValues: Map<number, any>;
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService, 
        private $state: any,
        private ItemService: ItemService,
        private acl: AclService
    ) {
        let self = this;
        
        this.values = new Map<number, autowp.IUser>();
        this.userValues = new Map<number, autowp.IUser>();
        this.currentUserValues = new Map<number, autowp.IUser>();
        
        this.acl.isAllowed('specifications', 'edit-engine').then(function(allow: boolean) {
            self.isAllowedEditEngine = !!allow;
        }, function() {
            self.isAllowedEditEngine = false;
        });
        
        this.acl.isAllowed('specifications', 'admin').then(function(allow: boolean) {
            self.isSpecsAdmin = !!allow;
        }, function() {
            self.isSpecsAdmin = false;
        });
        
        this.acl.inheritsRole('moder').then(function(inherits: boolean) {
            self.isModer = !!inherits;
        }, function() {
            self.isModer = false;
        });
        
        this.tab = this.$state.params.tab ||'info';
        
        this.ItemService.getItem(this.$state.params.item_id, {
            fields: 'name_html,name_text,engine_id,attr_zone_id'
        }).then(function(item: autowp.IItem) {
            self.item = item;
        
            self.$scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/102/name',
                pageId: 102,
                args: {
                    CAR_NAME: self.item.name_text
                }
            });
            
            self.tabs.engine.count = self.item.engine_id ? 1 : 0;
            
            if (self.tab == 'engine') {
                if (self.item.engine_id) {
                    self.ItemService.getItem(self.item.engine_id, {
                        fields: 'name_html,name_text,engine_id'
                    }).then(function(engine: autowp.IItem) {
                        self.engine = engine;
                        
                    }, function(response: ng.IHttpResponse<any>) {
                        notify.response(response);
                    });
                }
            }
            
            if (self.tab == 'result') {
                self.$http({
                    method: 'GET',
                    url: '/api/item/' + self.item.id + '/specifications'
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.resultHtml = response.data;
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
            }
            
            if (self.tab == 'spec') {
                $('.spec-editor-form .subform-header').hover(function() {
                    $(this).addClass('hover');
                    $('.' + $(this).attr('id')).addClass('hover');
                }, function() {
                    $(this).removeClass('hover');
                    $('.' + $(this).attr('id')).removeClass('hover');
                });
                
                self.$http({
                    method: 'GET',
                    url: '/api/attr/attribute',
                    params: {
                        fields: 'unit,childs.unit',
                        zone_id: item.attr_zone_id
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.attributes = toPlain(response.data.items, 0);
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
                
                self.$http({
                    method: 'GET',
                    url: '/api/attr/value',
                    params: {
                        item_id: item.id,
                        zone_id: item.attr_zone_id,
                        limit: 500,
                        fields: 'value'
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.values.clear();
                    for (let value of response.data.items) {
                        self.values.set(value.attribute_id, value);
                    }
                    
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
                
                self.$http({
                    method: 'GET',
                    url: '/api/attr/user-value',
                    params: {
                        item_id: item.id,
                        user_id: self.$scope.user.id,
                        zone_id: item.attr_zone_id,
                        limit: 500,
                        fields: 'value'
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.currentUserValues.clear();
                    for (let value of response.data.items) {
                        self.currentUserValues.set(value.attribute_id, value);
                    }
                    
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
                
                self.$http({
                    method: 'GET',
                    url: '/api/attr/user-value',
                    params: {
                        item_id: item.id,
                        exclude_user_id: self.$scope.user.id,
                        zone_id: item.attr_zone_id,
                        limit: 500,
                        fields: 'value,user'
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.userValues.clear();
                    for (let value of response.data.items) {
                        self.userValues.set(value.attribute_id, value);
                    }
                    
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
            }
            
            
            
        }, function(response: ng.IHttpResponse<any>) {
            self.$state.go('error-404');
        });

    }
    
    public inheritEngine()
    {
        var self = this;
        
        this.$http({
            method: 'PUT',
            url: '/api/item/' + this.item.id,
            data: {
                engine_id: 'inherited'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.$state.go('cars-specifications-editor', {
                item_id: self.item.id,
                tab: 'engine'
            });
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    public cancelInheritance()
    {
        var self = this;
        
        this.$http({
            method: 'PUT',
            url: '/api/item/' + this.item.id,
            data: {
                engine_id: ''
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.$state.go('cars-specifications-editor', {
                item_id: self.item.id,
                tab: 'engine'
            });
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    public refreshInheritance()
    {
        var self = this;
        
        this.$http({
            method: 'POST',
            url: '/api/item/' + this.item.id + '/refresh-inheritance'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.$state.go('cars-specifications-editor', {
                item_id: self.item.id,
                tab: 'admin'
            });
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, CarsSpecificationsEditorController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/cars/specifications-editor?item_id&tab',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);

