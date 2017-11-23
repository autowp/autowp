import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import { VehicleTypeService } from 'services/vehicle-type';
import { SpecService } from 'services/spec';
import { ContentLanguageService } from 'services/content-language';
import { ItemService } from 'services/item';
import { chunkBy } from 'chunk';
import * as $ from 'jquery';
require('corejs-typeahead');
import './tree';
import './select-parent';
import './organize';
import './organize-pictures';

const STATE_NAME = 'moder-items-item';
const CONTROLLER_NAME = 'ModerItemsItemController';

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

export class ModerItemsItemController {
    static $inject = ['$scope', '$rootScope', '$http', '$state', '$translate', '$q', '$element', 'SpecService', 'VehicleTypeService', 'AclService', 'ContentLanguageService', 'ItemService'];

    public loading: number = 0;
    public metaLoading: number = 0;
    public catalogueLoading: number = 0;
    public languagesLoading: number = 0;
    public linksLoading: number = 0;
    public logoLoading: number = 0;
    
    public item: any = null;
    public specsAllowed: boolean = false;
    public canMove: boolean = false;
    public canEditSpecifications: boolean = false;
    public picturesChunks: any[] = [];
    public canEditMeta: boolean = false;
    public canLogo: boolean = false;
    
    public canHaveParents: boolean = false;
    public canHaveParentBrand: boolean = false;
    
    public currentLanguage: any = null;
    
    public itemLanguages: any = {};
    public tree: any[] = [];
    
    public parents: any[] = [];
    public childs: any[] = [];
    public suggestions: any[] = [];
    
    public newLink = {
        name: '',
        url: '',
        type_id: 'default'
    };
    
    public tabs: any;
    
    public organizeTypeId: number;
    public canUseTurboGroupCreator: boolean = false;
    
    public pictures: any[];
    public engineVehicles: any[];
    public links: any[];
    public invalidParams: any;

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
        
        this.loading++;
        this.ItemService.getItem($state.params.id, {
            fields: ['name_text', 'name_html', 'name', 'is_concept', 
                     'name_default', 'body', 'subscription', 'begin_year', 
                     'begin_month', 'end_year', 'end_month', 'today', 
                     'begin_model_year', 'end_model_year', 'produced', 
                     'is_group', 'spec_id', 'childs_count', 'full_name', 
                     'catname', 'lat', 'lng', 'pictures_count', 
                     'specifications_count', 'links_count', 'parents_count', 
                     'item_language_count', 'engine_vehicles_count', 'logo'].join(',')
        }).then(function(item: autowp.IItem) {
            self.item = item;
            
            self.specsAllowed = [1, 2].indexOf(self.item.item_type_id) != -1;
            
            $translate('item/type/'+self.item.item_type_id+'/name').then(function(translation) {
                $scope.pageEnv({
                    layout: {
                        isAdminPage: true,
                        blankPage: false,
                        needRight: false
                    },
                    name: 'page/78/name',
                    pageId: 78,
                    args: {
                        CAR_ID: self.item.id,
                        CAR_NAME: translation + ': ' + self.item.name_text
                    }
                });
            });
            
            self.tabs = {
                meta: {
                    icon: 'glyphicon glyphicon-pencil',
                    title: 'moder/vehicle/tabs/meta',
                    count: 0,
                    init: self.initMetaTab
                },
                name: {
                    icon: 'glyphicon glyphicon-align-left',
                    title: 'moder/vehicle/tabs/name',
                    count: self.item.item_language_count,
                    init: self.initItemLanguageTab
                },
                logo: {
                    icon: 'glyphicon glyphicon-align-left',
                    title: 'brand/logo',
                    count: self.item.logo ? 1 : 0,
                    init: self.initLogoTab
                },
                catalogue: {
                    icon: false,
                    title: 'moder/vehicle/tabs/catalogue',
                    count: self.item.parents_count + self.item.childs_count,
                    init: self.initCatalogueTab
                },
                vehicles: {
                    icon: false,
                    title: 'moder/vehicle/tabs/vehicles',
                    count: self.item.engine_vehicles_count,
                    init: self.initVehiclesTab
                },
                tree: {
                    icon: 'fa fa-tree',
                    title: 'moder/vehicle/tabs/tree',
                    count: 0,
                    init: self.initTreeTab
                },
                pictures: {
                    icon: 'glyphicon glyphicon-th',
                    title: 'moder/vehicle/tabs/pictures',
                    count: self.item.pictures_count,
                    init: self.initPicturesTab
                },
                links: {
                    icon: 'glyphicon glyphicon-globe',
                    title: 'moder/brands/links',
                    count: self.item.links_count,
                    init: self.initLinksTab
                }
            };

            if (self.item.item_type_id == 7) {
                delete self.tabs.catalogue;
                delete self.tabs.tree;
            }

            if ([5, 7, 8].indexOf(self.item.item_type_id) === -1) {
                delete self.tabs.links;
            }

            if (self.item.item_type_id != 5) { //  || ! $this->user()->isAllowed('brand', 'logo')
                delete self.tabs.logo;
            }

            if (self.item.item_type_id != 1) {
                delete self.tabs.twins;
            }

            if (self.item.item_type_id != 2) {
                delete self.tabs.vehicles;
            }

            if ([2, 1, 5, 6, 7, 8].indexOf(self.item.item_type_id) === -1) {
                delete self.tabs.pictures;
            }

            if ([2, 1].indexOf(self.item.item_type_id) !== -1) {
                delete self.tabs.factories;
            }
            
            self.setActiveTab($state.params.tab ? $state.params.tab : 'meta');

            /*if ($this->user()->get()->id == 1) {
                $tabs['modifications'] = [
                    icon: 'glyphicon glyphicon-th',
                    title: 'moder/vehicle/tabs/modifications',
                    'data-load' => $this->url()->fromRoute('moder/cars/params', [
                        'action' => 'car-modifications'
                    ], [], true),
                    count: 0
                ];
            }*/

            self.metaLoading++;
            Acl.isAllowed('car', 'edit_meta').then(function(allow) {
                self.canEditMeta = !!allow;
                self.metaLoading--;
            }, function() {
                self.canEditMeta = false;
                self.metaLoading--;
            });
            self.canHaveParents = ![4, 6].includes(self.item.item_type_id);
            self.canHaveParentBrand = [1, 2].includes(self.item.item_type_id);
            
            self.organizeTypeId = self.item.item_type_id;
            switch (self.organizeTypeId) {
                case 5:
                    self.organizeTypeId = 1;
                    break;
            }
            
            self.canUseTurboGroupCreator = [1, 2].indexOf(self.item.item_type_id) !== -1;
            
            if (self.item.item_type_id == 1 || self.item.item_type_id == 4) {
                self.metaLoading++;
                $http({
                    method: 'GET',
                    url: '/api/item-vehicle-type',
                    params: {
                        item_id: self.item.id
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    var ids: number[] = [];
                    angular.forEach(response.data.items, function(row) {
                        ids.push(row.vehicle_type_id);
                    });

                    VehicleTypeService.getTypesById(ids).then(function(types) {
                        self.item.vehicle_type = types;
                    });
                    self.metaLoading--;
                }, function() {
                    self.metaLoading--;
                });
            }
            
            var $input = $($element[0]).find('.item-autocomplete');
            $input
                .on('typeahead:select', function(ev: any, car: any) {
                    self.addParent(car.id);
                    $input.typeahead('val', '');
                })
                .typeahead({ }, {
                    display: function(car: any) {
                        return car.name;
                    },
                    templates: {
                        suggestion: function(item: any) {
                            var $div = $('<div class="tt-suggestion tt-selectable car"></div>')
                                .html(item.name_html);
                            
                            if (item.brandicon) {
                                $div.prepend($('<img />', {
                                    src: item.brandicon.src
                                }));
                            }
                            
                            return $div[0];
                        }
                    },
                    source: function(query: string, syncResults: Function, asyncResults: Function) {
                        $http({
                            method: 'GET',
                            url: '/api/item',
                            params: {
                                autocomplete: query,
                                exclude_self_and_childs: self.item.id,
                                is_group: true,
                                parent_types_of: self.item.item_type_id,
                                fields: 'name_html,brandicon',
                                limit: 15
                            }
                        }).then(function(response: ng.IHttpResponse<any>) {
                            asyncResults(response.data.items);
                        });
                    }
                });
            
            self.loading--;
        }, function() {
            $state.go('error-404');
            self.loading--;
        });
                
        Acl.isAllowed('specifications', 'edit').then(function(allow) {
            self.canEditSpecifications = !!allow;
        }, function() {
            self.canEditSpecifications = false;
        });
        
        Acl.isAllowed('car', 'move').then(function(allow) {
            self.canMove = !!allow;
        }, function() {
            self.canMove = false;
        });

    }
    
    private initItemLanguageTab() {
        // TODO: move to service
        this.languagesLoading++;
        var self = this;
        this.ContentLanguage.getList().then(function(contentLanguages) {
            self.currentLanguage = contentLanguages[0];
            
            angular.forEach(contentLanguages, function(language) {
                self.itemLanguages[language] = {
                    language: language
                };
            });
            
            self.$http({
                method: 'GET',
                url: '/api/item/' + self.item.id + '/language'
            }).then(function(response: ng.IHttpResponse<any>) {
                angular.forEach(response.data.items, function(itemLanguage) {
                    self.itemLanguages[itemLanguage.language] = itemLanguage;
                });
            });
            self.languagesLoading--;
        }, function() {
            self.languagesLoading--;
        });
    }
    
    private initMetaTab() {
        var self = this;
        setTimeout(function() {
            self.$rootScope.$broadcast('invalidateSize', {});
        }, 100);
    }
    
    private initLogoTab() {
        var self = this;
        this.Acl.isAllowed('brand', 'logo').then(function(allow: boolean) {
            self.canLogo = !!allow;
        }, function() {
            self.canLogo = false;
        });
    }
    
    private initCatalogueTab() {
        
        this.catalogueLoading++;
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/item-parent',
            params: {
                parent_id: self.item.id,
                limit: 500,
                fields: 'name,duplicate_child.name_html,item.name_html,item.name,item.public_urls',
                order: 'moder_auto'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.childs = response.data.items;
            self.catalogueLoading--;
        }, function() {
            self.catalogueLoading--;
        });
        
        this.catalogueLoading++;
        this.$http({
            method: 'GET',
            url: '/api/item-parent',
            params: {
                item_id: self.item.id,
                limit: 500,
                fields: 'name,duplicate_parent.name_html,parent.name_html,parent.name,parent.public_urls'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.parents = response.data.items;
            self.catalogueLoading--;
        }, function() {
            self.catalogueLoading--;
        });
        
        this.catalogueLoading++;
        this.$http({
            method: 'GET',
            url: '/api/item',
            params: {
                suggestions_to: self.item.id,
                limit: 3,
                fields: 'name_text'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.suggestions = response.data.items;
            self.catalogueLoading--;
        }, function() {
            self.catalogueLoading--;
        });
    }
    
    private initTreeTab() {
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/item/' + this.item.id + '/tree'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.tree = response.data.item;
        }, function() {
        });
    }
    
    private initPicturesTab() {
        this.loading++;
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/picture',
            params: {
                exact_item_id: this.item.id,
                limit: 500,
                fields: 'owner,thumbnail,moder_vote,votes,similar,comments_count,perspective_item,name_html,name_text,views',
                order: 14
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.pictures = response.data.pictures;
            self.picturesChunks = chunkBy(self.pictures, 6);
            self.loading--;
        }, function() {
            self.loading--;
        });
    }
    
    private initVehiclesTab() {
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/item',
            params: {
                engine_id: this.item.id,
                limit: 100,
                fields: 'name_html'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.engineVehicles = response.data.items;
        });
    }
    
    private initLinksTab() {
        this.linksLoading++;
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/item-link',
            params: {
                item_id: this.item.id
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.links = response.data.items;
            self.linksLoading--;
        }, function() {
            self.linksLoading--;
        });
    }
    
    public setActiveTab(tab: string) {
        if (! this.tabs[tab]) {
            throw "Unexpected tab: " + tab;
        }
        angular.forEach(this.tabs, function(tab) {
            tab.active = false;
        });
        this.tabs[tab].active = true;
        
        this.$state.go(STATE_NAME, {tab: tab}, {
            notify: false,
            reload: false,
            location: 'replace'
        });
        
        if (this.tabs[tab].init) {
            this.tabs[tab].init();
        }
    }
    
    public toggleSubscription() {
        var newValue = !this.item.subscription;
        var self = this;
        this.$http({
            method: 'PUT',
            url: '/api/item/' + this.item.id,
            data: {
                subscription: newValue
            }
        }).then(function() {
            self.item.subscription = newValue;
        });
    }
    
    public saveMeta() {
        this.metaLoading++;
        
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
            method: 'PUT',
            url: '/api/item/' + this.item.id,
            data: data
        }).then(function(response: ng.IHttpResponse<any>) {
            
            self.invalidParams = {};
            
            var promises = [];
            
            var ids: number[] = [];
            angular.forEach(self.item.vehicle_type, function(vehicle_type) {
                ids.push(vehicle_type.id);
            });
            promises.push(self.ItemService.setItemVehicleTypes(self.item.id, ids));
            
            self.loading++;
            self.$q.all(promises).then(function(results) {
                self.loading--;
            });
            
            self.metaLoading--;
        }, function(response: ng.IHttpResponse<any>) {
            self.invalidParams = response.data.invalid_params;
            self.metaLoading--;
        });
    }
    
    public saveLanguages() {
        var self = this;
        angular.forEach(this.itemLanguages, function(language) {
            self.languagesLoading++;
            self.$http({
                method: 'PUT',
                url: '/api/item/' + self.item.id + '/language/' + language.language,
                data: {
                    name: language.name,
                    text: language.text,
                    full_text: language.full_text
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.languagesLoading--;
            }, function(response) {
                self.languagesLoading--;
            });
        });
    }
    
    public deleteParent(parentId: number) {
        this.catalogueLoading++;
        var self = this;
        this.$http({
            method: 'DELETE',
            url: '/api/item-parent/' + this.item.id + '/' + parentId
        }).then(function() {
            self.initCatalogueTab();
            self.catalogueLoading--;
        }, function() {
            self.catalogueLoading--;
        });
    }
    
    public deleteChild(itemId: number) {
        this.catalogueLoading++;
        var self = this;
        this.$http({
            method: 'DELETE',
            url: '/api/item-parent/' + itemId + '/' + this.item.id
        }).then(function() {
            self.initCatalogueTab();
            self.catalogueLoading--;
        }, function() {
            self.catalogueLoading--;
        });
    }
    
    public addParent(parentId: number) {
        this.catalogueLoading++;
        var self = this;
        this.$http({
            method: 'POST',
            url: '/api/item-parent',
            data: {
                item_id: this.item.id,
                parent_id: parentId
            }
        }).then(function() {
            self.initCatalogueTab();
            self.catalogueLoading--;
        }, function() {
            self.catalogueLoading--;
        });
    }
    
    public saveLinks() {
        
        var promises: any[] = [];
        var self = this;
        
        if (this.newLink.url) {
            promises.push(
                this.$http({
                    method: 'POST',
                    url: '/api/item-link',
                    data: {
                        item_id: this.item.id,
                        name: this.newLink.name,
                        url: this.newLink.url,
                        type_id: this.newLink.type_id,
                    }
                }).then(function() {
                    self.newLink.name = '';
                    self.newLink.url = '';
                    self.newLink.type_id = 'default';
                })
            );
        }
        
        angular.forEach(this.links, function(link) {
            if (link.url) {
                promises.push(
                        self.$http({
                        method: 'PUT',
                        url: '/api/item-link/' + link.id,
                        data: {
                            name: link.name,
                            url: link.url,
                            type_id: link.type_id,
                        }
                    })
                );
            } else {
                promises.push(
                        self.$http({
                        method: 'DELETE',
                        url: '/api/item-link/' + link.id
                    })
                );
            }
        });
        
        this.linksLoading++;
        this.$q.all(promises).then(function(results) {
            self.initLinksTab();
            self.linksLoading--;
        }, function() {
            self.linksLoading--;
        });
    }
    
    public uploadLogo() {
        this.logoLoading++;
        var element = $('#logo-upload') as any;
        var self = this;
        this.$http({
            url: '/api/item/' + self.item.id + '/logo',
            method: 'PUT',
            data: element[0].files[0],
            headers: {'Content-Type': undefined}
        }).then(function (response: ng.IHttpResponse<any>) {
            
            self.logoLoading++;
            self.$http({
                url: '/api/item/' + self.item.id + '/logo',
                method: 'GET'
            }).then(function (response: ng.IHttpResponse<any>) {
                self.item.logo = response.data;
                self.logoLoading--;
            }, function() {
                self.logoLoading--;
            });
            
            self.logoLoading--;
        }, function(response: ng.IHttpResponse<any>) {
            self.logoLoading--;
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerItemsItemController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/items/item/{id}?tab',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                params: { 
                    tab: { dynamic: true }
                },
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.isAllowed('car', 'edit_meta', 'unauthorized');
                    }]
                }
            });
        }
    ]);
