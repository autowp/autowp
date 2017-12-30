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
    static $inject = ['$scope', '$http', '$state', 'ItemService', 'AclService', '$translate'];
  
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
    public currentUserValues: any = {};
    public loading: number = 0;
    public userValuesLoading: number = 0;
    public specsWeight: number;
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService, 
        private $state: any,
        private ItemService: ItemService,
        private acl: AclService,
        private $translate: ng.translate.ITranslateService
    ) {
        let self = this;
        
        this.values = new Map<number, autowp.IUser>();
        this.userValues = new Map<number, autowp.IUser>();
        
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
        
        self.loading++;
        this.$http({
            url: '/api/user/me',
            method: 'GET',
            params: {
                fields: 'specs_weight'
            }
        }).then(function(response: ng.IHttpResponse<autowp.IUser>) {
            self.specsWeight = response.data.specs_weight
            self.loading--;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
            self.loading--;
        });
        
        this.loading++;
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
                    self.loading++;
                    self.ItemService.getItem(self.item.engine_id, {
                        fields: 'name_html,name_text,engine_id'
                    }).then(function(engine: autowp.IItem) {
                        self.engine = engine;
                        self.loading--;
                    }, function(response: ng.IHttpResponse<any>) {
                        notify.response(response);
                        self.loading--;
                    });
                }
            }
            
            if (self.tab == 'result') {
                self.loading++;
                self.$http({
                    method: 'GET',
                    url: '/api/item/' + self.item.id + '/specifications'
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.resultHtml = response.data;
                    self.loading--;
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                    self.loading--;
                });
            }
            
            if (self.tab == 'spec') {
                self.loading++;
                self.$http({
                    method: 'GET',
                    url: '/api/attr/attribute',
                    params: {
                        fields: 'unit,options,childs.unit,childs.options',
                        zone_id: item.attr_zone_id
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.$translate(['specifications/no-value-text', 'specifications/boolean/false', 'specifications/boolean/true']).then(function(translations) {
                        self.attributes = toPlain(response.data.items, 0);
                        for (let attribute of self.attributes) {
                            if (attribute.options) {
                                attribute.options.splice(0, 0, {
                                    name: '—',
                                    id: null
                                })
                            }
                            
                            if (attribute.type_id == 5) {
                                attribute.options = [{
                                    name: '—',
                                    id: null
                                }, {
                                    name: translations['specifications/boolean/false'],
                                    id: 0
                                }, {
                                    name: translations['specifications/boolean/true'],
                                    id: 1
                                }];
                            }
                        }
                    });
                    
                    self.loading++;
                    self.$http({
                        method: 'GET',
                        url: '/api/attr/value',
                        params: {
                            item_id: item.id,
                            zone_id: item.attr_zone_id,
                            limit: 500,
                            fields: 'value,value_text'
                        }
                    }).then(function(response: ng.IHttpResponse<any>) {
                        self.values.clear();
                        for (let value of response.data.items) {
                            self.values.set(value.attribute_id, value);
                        }
                        self.loading--;
                    }, function(response: ng.IHttpResponse<any>) {
                        notify.response(response);
                        self.loading--;
                    });
                    
                    self.loadValues();
                    
                    self.loadAllValues();
                    
                    self.loading--;
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                    self.loading--;
                });
            }
            
            
            self.loading--;
        }, function(response: ng.IHttpResponse<any>) {
            self.$state.go('error-404');
            self.loading--;
        });

    }
    
    private getAttribute(id: number): any
    {
        let self = this;
        for (let attribute of this.attributes) {
            if (attribute.id == id) {
                return attribute;
            }
        }
        
        return undefined;
    }
    
    private isMultipleAttribute(id: number): boolean
    {
        let self = this;
        for (let attribute of this.attributes) {
            if (attribute.id == id) {
                return attribute.is_multiple;
            }
        }
        
        return false;
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
    
    public saveSpecs()
    {
        let items = [];
        for (let attribute_id in this.currentUserValues) {
            if (this.currentUserValues.hasOwnProperty(attribute_id)) {
                items.push({
                    item_id: this.item.id,
                    attribute_id: attribute_id,
                    user_id: this.$scope.user.id,
                    value: this.currentUserValues[attribute_id].value,
                    empty: this.currentUserValues[attribute_id].empty
                });
            }
        }
       
        let self = this;
        this.loading++;
        this.$http({
            method: 'PATCH',
            url: '/api/attr/user-value',
            data: {
                items: items
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.loadValues();
            self.loadAllValues();
            self.loading--;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
            self.loading--;
        });
    }
    
    private loadValues()
    {
        let self = this;
        
        this.loading++;
        this.userValuesLoading++;
        this.$http({
            method: 'GET',
            url: '/api/attr/user-value',
            params: {
                item_id: self.item.id,
                user_id: self.$scope.user.id,
                zone_id: self.item.attr_zone_id,
                limit: 500,
                fields: 'value'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            let currentUserValues: any = {};
            for (let value of response.data.items) {
                let attribute = self.getAttribute(value.attribute_id);
                if (attribute.type_id == 2 || attribute.type_id == 3) {
                    if (value.value !== null) {
                        value.value = +value.value;
                    }
                }
                if (attribute.is_multiple) {
                    if (! (value.value instanceof Array)) {
                        value.value = [value.value];
                    }
                }
                currentUserValues[value.attribute_id] = value;
            }
            self.currentUserValues = currentUserValues;
            self.loading--;
            self.userValuesLoading--;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
            self.loading--;
            self.userValuesLoading--;
        });
    }
    
    public getStep(attribute: any): number
    {
        return Math.pow(10, -attribute.precision)
    }
    
    private httpUserValues(page: number)
    {
        return this.$http({
            method: 'GET',
            url: '/api/attr/user-value',
            params: {
                item_id: this.item.id,
                //exclude_user_id: self.$scope.user.id,
                zone_id: this.item.attr_zone_id,
                limit: 500,
                fields: 'value_text,user'
            }
        });
    }
    
    private applyUserValues(items: any[])
    {
        for (let value of items) {
            let values = this.userValues.get(value.attribute_id);
            if (values === undefined) {
                this.userValues.set(value.attribute_id, [value]);
            } else {
                values.push(value);
                this.userValues.set(value.attribute_id, values);
            }
        }
    }
    
    private loadAllValues()
    {
        this.loading++;
        
        let self = this;
        this.httpUserValues(1).then(function(response: ng.IHttpResponse<any>) {
            self.userValues.clear();
            self.applyUserValues(response.data.items);
            
            for (let i=2; i<=response.data.paginator.pageCount; i++) {
                self.loading++;
                self.httpUserValues(i).then(function(response: ng.IHttpResponse<any>) {
                    self.applyUserValues(response.data.items);
                    self.loading--;
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                    self.loading--;
                });
            }
            
            self.loading--;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
            self.loading--;
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

