import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import { VehicleTypeService } from 'services/vehicle-type';
import { SpecService } from 'services/spec';
import { ContentLanguageService } from 'services/content-language';
import { chunk, chunkBy } from 'chunk';
import { ItemService } from 'services/item';
import './tree';
import './tree-item';
import 'directives/auto-focus';

const STATE_NAME = 'moder-items-item-select-parent';
const CONTROLLER_NAME = 'ModerItemsItemSelectParentController';

export class ModerItemsItemSelectParentController {
    static $inject = ['$scope', '$rootScope', '$http', '$state', '$translate', '$q', '$element', 'SpecService', 'VehicleTypeService', 'AclService', 'ContentLanguageService', 'ItemService'];
    
    public showCatalogueTab: boolean = false;
    public showBrandsTab: boolean = false;
    public showTwinsTab: boolean = false;
    public showFactoriesTab: boolean = false;
    public tab: string;
    public brand_id: number;
    public paginator: autowp.IPaginator;
    public page: number;
    public search: string = '';
    public item: autowp.IItem;
    public brands: autowp.IItem[];
    public items: any[];
    public categories: any[];
    public factories: any[];
    public doSearch: Function;
    public loadChildCategories: Function;
    public loadChildCatalogues: Function;

    constructor(
        private $scope: autowp.IControllerScope, 
        private $rootScope: autowp.IRootControllerScope,
        private $http: ng.IHttpService,
        private $state: any,
        private $translate: ng.translate.ITranslateService, 
        private $q: ng.IQService, 
        private $element: any, 
        private SpecService: SpecService, 
        private VehicleTypeService: VehicleTypeService, 
        private Acl: AclService, 
        private ContentLanguage: ContentLanguageService,
        private ItemService: ItemService
    ) {
        var self = this;
        
        this.tab = $state.params.tab || 'catalogue';
        
        this.page = $state.params.page;
        
        this.ItemService.getItem($state.params.id, {
            fields: 'name_text'
        }).then(function(item: autowp.IItem) {
            self.item = item;
            
            $translate('item/type/'+self.item.item_type_id+'/name').then(function(translation: string) {
                $scope.pageEnv({
                    layout: {
                        isAdminPage: true,
                        blankPage: false,
                        needRight: false
                    },
                    name: 'page/144/name',
                    pageId: 144,
                    args: {
                        CAR_ID: self.item.id,
                        CAR_NAME: translation + ': ' + self.item.name_text
                    }
                });
            });
            
            self.showCatalogueTab = [1, 2, 5].includes(self.item.item_type_id);
            self.showBrandsTab = [1, 2, 5].includes(self.item.item_type_id);
            self.showTwinsTab = self.item.item_type_id == 1;
            self.showFactoriesTab = [1, 2].includes(self.item.item_type_id);
            
            if (self.tab == 'catalogue') {
                self.brand_id = $state.params.brand_id;
                if (self.brand_id) {
                    $http({
                        method: 'GET',
                        url: '/api/item-parent',
                        params: {
                            limit: 100, 
                            fields: 'item.name_html,item.childs_count',
                            parent_id: self.brand_id,
                            is_group: true,
                            type_id: self.item.item_type_id,
                            page: self.page
                        }
                    }).then(function(response: ng.IHttpResponse<any>) {
                        self.items = response.data.items;
                        self.paginator = response.data.paginator;
                    });
                } else {
                    
                    self.doSearch = function() {
                        self.loadCatalogueBrands();
                    };
                    
                    self.loadCatalogueBrands();
                }
            }
            
            
            
            if (self.tab == 'brands') {
                
                self.doSearch = function() {
                    self.loadBrands();
                };
                
                self.loadBrands();
            }
            
            if (self.tab == 'categories') {
                
                $http({
                    method: 'GET',
                    url: '/api/item',
                    params: {
                        type_id: 3,
                        limit: 100,
                        fields: 'name_html,childs_count',
                        page: self.page,
                        no_parent: true
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.categories = response.data.items;
                    self.paginator = response.data.paginator;
                });
            }
            
            if (self.tab == 'twins') {
                self.brand_id = $state.params.brand_id;
                if (self.brand_id) {
                    $http({
                        method: 'GET',
                        url: '/api/item',
                        params: {
                            type_id: 4,
                            limit: 100,
                            fields: 'name_html',
                            have_common_childs_with: self.brand_id,
                            page: self.page
                        }
                    }).then(function(response: ng.IHttpResponse<any>) {
                        self.items = response.data.items;
                        self.paginator = response.data.paginator;
                    });
                } else {
                    $http({
                        method: 'GET',
                        url: '/api/item',
                        params: {
                            type_id: 5,
                            limit: 500,
                            fields: 'name_html',
                            have_childs_with_parent_of_type: 4,
                            page: self.page
                        }
                    }).then(function(response: ng.IHttpResponse<any>) {
                        self.brands = chunk(response.data.items, 6);
                        self.paginator = response.data.paginator;
                    });
                }
            }
            
            if (self.tab == 'factories') {
                $http({
                    method: 'GET',
                    url: '/api/item',
                    params: {
                        type_id: 6,
                        limit: 100,
                        fields: 'name_html',
                        page: self.page
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.factories = response.data.items;
                    self.paginator = response.data.paginator;
                });
            }
            
        }, function() {
            $state.go('error-404');
        });
        
        this.loadChildCategories = (parent: any) => {
            $http({
                method: 'GET',
                url: '/api/item-parent',
                params: {
                    limit: 100,
                    fields: 'item.name_html,item.childs_count',
                    parent_id: parent.id,
                    is_group: true,
                    order: 'categories_first'
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                parent.childs = response.data.items;
            });
        }
                
        this.loadChildCatalogues = (parent: any) => {
            this.$http({
                method: 'GET',
                url: '/api/item-parent',
                params: {
                    limit: 100,
                    fields: 'item.name_html,item.childs_count',
                    parent_id: parent.id,
                    is_group: true,
                    order: 'type_auto'
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                parent.childs = response.data.items;
            });
        }
    }
    
    private loadCatalogueBrands() {
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/item',
            params: {
                type_id: 5,
                limit: 500,
                fields: 'name_html',
                have_childs_of_type: self.item.item_type_id,
                name: self.search ? '%' + self.search + '%' : null,
                page: self.page
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.brands = chunk(response.data.items, 6);
            self.paginator = response.data.paginator;
        });
    }
    
    private loadBrands() {
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/item',
            params: {
                type_id: 5,
                limit: 500,
                fields: 'name_html',
                name: this.search ? '%' + this.search + '%' : null,
                page: this.page
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.brands = chunk(response.data.items, 6);
            self.paginator = response.data.paginator;
        });
    }
    
    public select(parent: any) {
        var self = this;
        this.$http({
            method: 'POST',
            url: '/api/item-parent',
            data: {
                item_id: this.item.id,
                parent_id: parent.id
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.$state.go('moder-items-item', {
                id: self.item.id,
                tab: 'catalogue'
            });
        });
    }
    
    
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerItemsItemSelectParentController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/items/item/{id}/select-parent?tab&brand_id&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.isAllowed('car', 'edit_meta', 'unauthorized');
                    }]
                }
            });
        }
    ]);

