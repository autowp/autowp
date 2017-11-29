import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { ItemService } from 'services/item';
import { chunk } from 'chunk';

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
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService, 
        private $state: any,
        private $q: ng.IQService,
        private ItemService: ItemService
    ) {
        let self = this;
        
        this.ItemService.getItem(this.$state.params.item_id).then(function(item: autowp.IItem) {
            self.item = item;
            
            self.$scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/102/name',
                pageId: 102,
                args: {
                     CAR_NAME: self.item.name_html
                }
            });
            
            if (self.$state.params.brand_id) {
                $http({
                    method: 'GET',
                    url: '/api/item-parent',
                    params: {
                        limit: 100, 
                        fields: 'item.name_html,item.childs_count',
                        parent_id: self.$state.params.brand_id,
                        is_group: true,
                        type_id: self.item.item_type_id,
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

