import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { ItemService } from 'services/item';
import { chunk } from 'chunk';

import './tree-item';

const CONTROLLER_NAME = 'UploadSelectController';
const STATE_NAME = 'upload-select';

export class UploadSelectController {
    static $inject = ['$scope', '$http', '$state', '$q', 'ItemService'];
    
    public brand: autowp.IItem;
    public brands: autowp.IItem[];
    public paginator: autowp.IPaginator;
    public vehicles: any[];
    public engines: any[];
    public loadChildCatalogues: Function;
    public search: string;
    private loadBrandsCanceler: ng.IDeferred<{}> | undefined;
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService, 
        private $state: any,
        private $q: ng.IQService,
        private ItemService: ItemService
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/30/name',
            pageId: 30
        });
        
        var self = this;
        
        let brandId = parseInt($state.params.brand_id);
        if (brandId) {
            this.ItemService.getItem(brandId).then(function(item: autowp.IItem) {
                self.brand = item;
                
                self.$http({
                    method: 'GET',
                    url: '/api/item-parent',
                    params: {
                        limit: 500, 
                        fields: 'item.name_html,item.childs_count',
                        parent_id: self.brand.id,
                        order: 'name',
                        item_type_id: 1
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.vehicles = response.data.items;
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
                
                self.$http({
                    method: 'GET',
                    url: '/api/item-parent',
                    params: {
                        limit: 500, 
                        fields: 'item.name_html,item.childs_count',
                        parent_id: self.brand.id,
                        order: 'name',
                        item_type_id: 2
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.engines = response.data.items;
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
                
            }, function(response: ng.IHttpResponse<any>) {
                self.$state.go('error-404');
            });
        } else {
            
            this.loadBrands();
        }
        
        this.loadChildCatalogues = (parent: any) => {
            parent.loading = true;
            this.$http({
                method: 'GET',
                url: '/api/item-parent',
                params: {
                    limit: 500,
                    fields: 'item.name_html,item.childs_count',
                    parent_id: parent.item_id,
                    order: 'type_auto'
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                parent.item.childs = response.data.items;
                parent.loading = false;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
                parent.loading = false;
            });
        }
    }
    
    private loadBrands()
    {
        var self = this;
        
        if (this.loadBrandsCanceler) {
            this.loadBrandsCanceler.resolve();
            this.loadBrandsCanceler = undefined;
        }
        
        this.loadBrandsCanceler = this.$q.defer();
        
        this.ItemService.getItems({
            type_id: 5,
            order: 'name',
            limit: 500,
            fields: 'name_only',
            name: this.search ? '%' + this.search + '%' : null
        }, this.loadBrandsCanceler.promise).then(function(result: autowp.GetItemsResult) {
            self.brands = chunk(result.items, 6);
            self.paginator = result.paginator;
        }, function(response: ng.IHttpResponse<any>) {
            if (response.status != -1) {
                notify.response(response);
            }
        });
    }
    
    public toggle(item: any)
    {
        if (! item.expanded) {
            item.expanded = true;
        } else {
            item.expanded = false;
        }
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, UploadSelectController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/upload/select?brand_id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);

