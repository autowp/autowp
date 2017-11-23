import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import { VehicleTypeService } from 'services/vehicle-type';
import { SpecService } from 'services/spec';
import { ItemService } from 'services/item';
import { sprintf } from "sprintf-js";
import notify from 'notify';

const STATE_NAME = 'moder-items-new';
const CONTROLLER_NAME = 'ModerItemsNewController';

function toPlain(options: any[], deep: number): any[] {
    var result: any[] = [];
    angular.forEach(options, function(item: any) {
        item.deep = deep;
        result.push(item);
        angular.forEach(toPlain(item.childs, deep+1), function(item) {
            result.push(item);
        });
    });
    return result;
}

interface NewItem {
    produced_exactly: string,
    is_concept: any,
    spec_id: any,
    item_type_id: number,
    name: any,
    full_name: any,
    catname: any,
    body: any,
    begin_model_year: any,
    end_model_year: any,
    begin_year: any,
    begin_month: any,
    end_year: any,
    end_month: any,
    today: any,
    produced: any,
    is_group: boolean,
    lat: any,
    lng: any,
    vehicle_type: any
}

export class ModerItemsNewController {
    static $inject = ['$scope', '$http', '$state', '$translate', '$q', 'SpecService', 'VehicleTypeService', 'ItemService'];
    
    public loading: number = 0;
    public item: NewItem;
    public parent: autowp.IItem;
    public parentSpec: any = null;
    public invalidParams: any;

    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private $state: any,
        private $translate: ng.translate.ITranslateService, 
        private $q: ng.IQService, 
        private SpecService: SpecService, 
        private VehicleTypeService: VehicleTypeService, 
        private ItemService: ItemService
    ) {
        var self = this;
        
        this.item = {
            produced_exactly: '0',
            is_concept: 'inherited',
            spec_id: 'inherited',
            item_type_id: parseInt($state.params.item_type_id),
            name: undefined,
            full_name: undefined,
            catname: undefined,
            body: undefined,
            begin_model_year: undefined,
            end_model_year: undefined,
            begin_year: undefined,
            begin_month: undefined,
            end_year: undefined,
            end_month: undefined,
            today: undefined,
            produced: undefined,
            is_group: false,
            lat: undefined,
            lng: undefined,
            vehicle_type: undefined
        };
        
        if ([1, 2, 3, 4, 5, 6, 7, 8].indexOf(this.item.item_type_id) == -1) {
            $state.go('error-404');
            return;
        }
        
        if ($state.params.parent_id) {
            this.loading++;
            this.ItemService.getItem($state.params.parent_id, {
                fields: 'is_concept,name_html,spec_id'
            }).then(function(item: autowp.IItem) {
                self.parent = item;
                
                if (self.parent.spec_id) {
                    SpecService.getSpec(self.parent.spec_id).then(function(spec) {
                        self.parentSpec = spec;
                    });
                }
                self.loading--;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
                self.loading--;
            });
        }

        this.$translate('item/type/'+$state.params.item_type_id+'/new-item').then(function(translation: string) {
            self.$scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                name: 'page/163/name',
                pageId: 163,
                args: {
                    NEW_ITEM_OF_TYPE: translation
                }
            });
        });
    }
    
    public submit() {
        this.loading++;
        
        var data = {
            item_type_id: this.$state.params.item_type_id,
            name: this.item.name,
            full_name: this.item.full_name,
            catname: this.item.catname,
            body: this.item.body,
            spec_id: this.item.spec_id,
            begin_model_year: this.item.begin_model_year,
            end_model_year: this.item.end_model_year,
            begin_year: this.item.begin_year,
            begin_month: this.item.begin_month,
            end_year: this.item.end_year,
            end_month: this.item.end_month,
            today: this.item.today,
            produced: this.item.produced,
            produced_exactly: this.item.produced_exactly,
            is_concept: this.item.is_concept,
            is_group: this.item.is_group,
            lat: this.item.lat,
            lng: this.item.lng
        };
        
        var self = this;
        this.$http({
            method: 'POST',
            url: '/api/item',
            data: data
        }).then(function(response: ng.IHttpResponse<any>) {
            
            var location = response.headers('Location');
            
            self.loading++;
            self.$http({
                method: 'GET',
                url: location
            }).then(function(response: ng.IHttpResponse<any>) {
                
                var promises = [];
                
                var ids: number[] = [];
                angular.forEach(self.item.vehicle_type, function(vehicle_type) {
                    ids.push(vehicle_type.id);
                });
                promises.push(self.ItemService.setItemVehicleTypes(response.data.id, ids));
                
                if (self.parent) {
                    promises.push(self.$http.post('/api/item-parent', {
                        parent_id: self.parent.id,
                        item_id: response.data.id
                    }));
                }
                
                self.loading++;
                self.$q.all(promises).then(function(results) {
                    self.$state.go('moder-items-item', {
                        id: response.data.id
                    });
                    self.loading--;
                });
                
                self.loading--;
            });
            
            self.loading--;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
            self.invalidParams = response.data.invalid_params;
            self.loading--;
        });
    };
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerItemsNewController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/items/new?item_type_id&parent_id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.isAllowed('car', 'add', 'unauthorized');
                    }]
                }
            });
        }
    ]);

