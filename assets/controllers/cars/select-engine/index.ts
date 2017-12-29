import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { ItemService } from 'services/item';
import { chunk } from 'chunk';
import './tree-item';

const CONTROLLER_NAME = 'CarsSelectEngineController';
const STATE_NAME = 'cars-select-engine';

export class CarsSelectEngineController {
    static $inject = ['$scope', '$http', '$state', '$q', 'ItemService'];
  
    public item: autowp.IItem;
    private loadBrandsCanceler: ng.IDeferred<{}> | undefined;
    public loading: number = 0;
    public paginator: autowp.IPaginator;
    public search: string;
    public brands: autowp.IItem[];
    public items: any[];
    public brandId: number;
    public loadChildCatalogues: Function;
    public selectEngine: (engineId: number) => void;
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService, 
        private $state: any,
        private $q: ng.IQService,
        private ItemService: ItemService
    ) {
        let self = this;
        
        this.ItemService.getItem(this.$state.params.item_id, {
            fields: 'name_html,name_text'
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
            
            if (self.$state.params.brand_id) {
                self.brandId = self.$state.params.brand_id;
                $http({
                    method: 'GET',
                    url: '/api/item-parent',
                    params: {
                        limit: 500, 
                        fields: 'item.name_html,item.childs_count',
                        parent_id: self.$state.params.brand_id,
                        item_type_id: 2,
                        page: self.$state.params.page
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.items = response.data.items;
                    self.paginator = response.data.paginator;
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
            } else {
                self.loadBrands();
            }
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
        
        this.loadChildCatalogues = (parent: any) => {
            parent.loading = true;
            this.$http({
                method: 'GET',
                url: '/api/item-parent',
                params: {
                    limit: 500,
                    fields: 'item.name_html,item.childs_count',
                    parent_id: parent.item_id,
                    item_type_id: 2,
                    order: 'type_auto'
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                parent.item.childs = response.data.items;
                parent.loading = false;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
                parent.loading = false;
            });
        };
        
        this.selectEngine = (engineId: number) => {
            self.$http({
                method: 'PUT',
                url: '/api/item/' + self.item.id,
                data: {
                    engine_id: engineId
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.$state.go('cars-specifications-editor', {
                    item_id: self.item.id
                });
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
        }
    }
    
    private loadBrands()
    {
        this.loading++;
        
        let self = this;
        
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
            have_childs_of_type: 2,
            name: this.search ? '%' + this.search + '%' : null
        }, this.loadBrandsCanceler.promise).then(function(result: autowp.GetItemsResult) {
            self.brands = chunk(result.items, 6);
            self.paginator = result.paginator;
            self.loading--;
        }, function(response: ng.IHttpResponse<any>) {
            if (response.status != -1) {
                notify.response(response);
            }
            self.loading--;
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, CarsSelectEngineController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/cars/select-engine?item_id&brand_id&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);

