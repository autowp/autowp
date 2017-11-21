import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import { AclService } from 'services/acl';
import { VehicleTypeService } from 'services/vehicle-type';
import { SpecService } from 'services/spec';
import { ContentLanguageService } from 'services/content-language';
import { ItemService } from 'services/item';
import { chunkBy } from 'chunk';
var $ = require('jquery');
require('corejs-typeahead');
import './tree';
import './select-parent';
import './organize';
import './organize-pictures';

const STATE_NAME = 'moder-items-item';
const CONTROLLER_NAME = 'ModerItemsItemController';

function toPlain(options, deep) {
    var result = [];
    angular.forEach(options, function(item) {
        item.deep = deep;
        result.push(item);
        angular.forEach(toPlain(item.childs, deep+1), function(item) {
            result.push(item);
        });
    });
    return result;
}

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/items/item/{id}?tab',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                params: { 
                    tab: { dynamic: true }
                },
                resolve: {
                    access: ['AclService', function (Acl) {
                        return Acl.isAllowed('car', 'edit_meta', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$rootScope', '$http', '$state', '$translate', '$q', '$element', 'SpecService', 'VehicleTypeService', 'AclService', 'ContentLanguageService', 'ItemService',
        function($scope, $rootScope, $http, $state, $translate, $q, $element, SpecService, VehicleTypeService, Acl, ContentLanguage, ItemService) {
            
            var ctrl = this;
            
            ctrl.loading = 0;
            ctrl.metaLoading = 0;
            ctrl.catalogueLoading = 0;
            ctrl.languagesLoading = 0;
            ctrl.linksLoading = 0;
            
            ctrl.item = null;
            ctrl.specsAllowed = false;
            ctrl.canMove = false;
            ctrl.canEditSpecifications = false;
            ctrl.picturesChunks = [];
            ctrl.canEditMeta = false;
            ctrl.canLogo = false;
            
            ctrl.canHaveParents = false;
            ctrl.canHaveParentBrand = false;
            
            ctrl.currentLanguage = null;
            
            ctrl.itemLanguages = {};
            ctrl.tree = [];
            
            ctrl.parents = [];
            ctrl.childs = [];
            ctrl.suggestions = [];
            
            ctrl.newLink = {
                name: '',
                url: '',
                type_id: 'default'
            };
            
            ctrl.loading++;
            $http({
                method: 'GET',
                url: '/api/item/' + $state.params.id,
                params: {
                    fields: ['name_text', 'name_html', 'name', 'is_concept', 
                        'name_default', 'body', 'subscription', 'begin_year', 
                        'begin_month', 'end_year', 'end_month', 'today', 
                        'begin_model_year', 'end_model_year', 'produced', 
                        'is_group', 'spec_id', 'childs_count', 'full_name', 
                        'catname', 'lat', 'lng', 'pictures_count', 
                        'specifications_count', 'links_count', 'parents_count', 
                        'item_language_count', 'engine_vehicles_count', 'logo'].join(',')
                }
            }).then(function(response) {
                ctrl.item = response.data;
                
                ctrl.specsAllowed = [1, 2].indexOf(ctrl.item.item_type_id) != -1;
                
                $translate('item/type/'+ctrl.item.item_type_id+'/name').then(function(translation) {
                    $scope.pageEnv({
                        layout: {
                            isAdminPage: true,
                            blankPage: false,
                            needRight: false
                        },
                        name: 'page/78/name',
                        pageId: 78,
                        args: {
                            CAR_ID: ctrl.item.id,
                            CAR_NAME: translation + ': ' + ctrl.item.name_text
                        }
                    });
                });
                
                ctrl.tabs = {
                    meta: {
                        icon: 'glyphicon glyphicon-pencil',
                        title: 'moder/vehicle/tabs/meta',
                        count: 0,
                        init: initMetaTab
                    },
                    name: {
                        icon: 'glyphicon glyphicon-align-left',
                        title: 'moder/vehicle/tabs/name',
                        count: ctrl.item.item_language_count,
                        init: initItemLanguageTab
                    },
                    logo: {
                        icon: 'glyphicon glyphicon-align-left',
                        title: 'brand/logo',
                        count: ctrl.item.logo ? 1 : 0,
                        init: initLogoTab
                    },
                    catalogue: {
                        icon: false,
                        title: 'moder/vehicle/tabs/catalogue',
                        count: ctrl.item.parents_count + ctrl.item.childs_count,
                        init: initCatalogueTab
                    },
                    vehicles: {
                        icon: false,
                        title: 'moder/vehicle/tabs/vehicles',
                        count: ctrl.item.engine_vehicles_count,
                        init: initVehiclesTab
                    },
                    tree: {
                        icon: 'fa fa-tree',
                        title: 'moder/vehicle/tabs/tree',
                        count: 0,
                        init: initTreeTab
                    },
                    pictures: {
                        icon: 'glyphicon glyphicon-th',
                        title: 'moder/vehicle/tabs/pictures',
                        count: ctrl.item.pictures_count,
                        init: initPicturesTab
                    },
                    links: {
                        icon: 'glyphicon glyphicon-globe',
                        title: 'moder/brands/links',
                        count: ctrl.item.links_count,
                        init: initLinksTab
                    }
                };

                if (ctrl.item.item_type_id == 7) {
                    delete ctrl.tabs.catalogue;
                    delete ctrl.tabs.tree;
                }

                if ([5, 7, 8].indexOf(ctrl.item.item_type_id) === -1) {
                    delete ctrl.tabs.links;
                }

                if (ctrl.item.item_type_id != 5) { //  || ! $this->user()->isAllowed('brand', 'logo')
                    delete ctrl.tabs.logo;
                }

                if (ctrl.item.item_type_id != 1) {
                    delete ctrl.tabs.twins;
                }

                if (ctrl.item.item_type_id != 2) {
                    delete ctrl.tabs.vehicles;
                }

                if ([2, 1, 5, 6, 7, 8].indexOf(ctrl.item.item_type_id) === -1) {
                    delete ctrl.tabs.pictures;
                }

                if ([2, 1].indexOf(ctrl.item.item_type_id) !== -1) {
                    delete ctrl.tabs.factories;
                }
                
                ctrl.setActiveTab($state.params.tab ? $state.params.tab : 'meta');

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

                ctrl.metaLoading++;
                Acl.isAllowed('car', 'edit_meta').then(function(allow) {
                    ctrl.canEditMeta = !!allow;
                    ctrl.metaLoading--;
                }, function() {
                    ctrl.canEditMeta = false;
                    ctrl.metaLoading--;
                });
                ctrl.canHaveParents = ![4, 6].includes(ctrl.item.item_type_id);
                ctrl.canHaveParentBrand = [1, 2].includes(ctrl.item.item_type_id);
                
                ctrl.organizeTypeId = ctrl.item.item_type_id;
                switch (ctrl.organizeTypeId) {
                    case 5:
                        ctrl.organizeTypeId = 1;
                        break;
                }
                
                ctrl.canUseTurboGroupCreator = [1, 2].indexOf(ctrl.item.item_type_id) !== -1;
                
                if (ctrl.item.item_type_id == 1 || ctrl.item.item_type_id == 4) {
                    ctrl.metaLoading++;
                    $http({
                        method: 'GET',
                        url: '/api/item-vehicle-type',
                        params: {
                            item_id: ctrl.item.id
                        }
                    }).then(function(response) {
                        var ids = [];
                        angular.forEach(response.data.items, function(row) {
                            ids.push(row.vehicle_type_id);
                        });
    
                        VehicleTypeService.getTypesById(ids).then(function(types) {
                            ctrl.item.vehicle_type = types;
                        });
                        ctrl.metaLoading--;
                    }, function() {
                        ctrl.metaLoading--;
                    });
                }
                
                var $input = $($element[0]).find('.item-autocomplete');
                $input
                    .typeahead({ }, {
                        display: function(car) {
                            return car.name;
                        },
                        templates: {
                            suggestion: function(item) {
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
                        source: function(query, syncResults, asyncResults) {
                            $http({
                                method: 'GET',
                                url: '/api/item',
                                params: {
                                    autocomplete: query,
                                    exclude_self_and_childs: ctrl.item.id,
                                    is_group: true,
                                    parent_types_of: ctrl.item.item_type_id,
                                    fields: 'name_html,brandicon',
                                    limit: 15
                                }
                            }).then(function(response) {
                                asyncResults(response.data.items);
                            });
                        }
                    })
                    .on('typeahead:select', function(ev, car) {
                        ctrl.addParent(car.id);
                        $input.typeahead('val', '');
                    });
                
                ctrl.loading--;
            }, function() {
                $state.go('error-404');
                ctrl.loading--;
            });
            
            function initMetaTab() {
                setTimeout(function() {
                    $rootScope.$broadcast('invalidateSize', {});
                }, 100);
            }
            
            function initTreeTab() {
                $http({
                    method: 'GET',
                    url: '/api/item/' + ctrl.item.id + '/tree'
                }).then(function(response) {
                    ctrl.tree = response.data.item;
                }, function() {
                });
            }
            
            function initPicturesTab() {
                ctrl.loading++;
                $http({
                    method: 'GET',
                    url: '/api/picture',
                    params: {
                        exact_item_id: ctrl.item.id,
                        limit: 500,
                        fields: 'owner,thumbnail,moder_vote,votes,similar,comments_count,perspective_item,name_html,name_text,views',
                        order: 14
                    }
                }).then(function(response) {
                    ctrl.pictures = response.data.pictures;
                    ctrl.picturesChunks = chunkBy(ctrl.pictures, 6);
                    ctrl.loading--;
                }, function() {
                    ctrl.loading--;
                });
            }
            
            function initItemLanguageTab() {
                // TODO: move to service
                ctrl.languagesLoading++;
                ContentLanguage.getList().then(function(contentLanguages) {
                    ctrl.currentLanguage = contentLanguages[0];
                    
                    angular.forEach(contentLanguages, function(language) {
                        ctrl.itemLanguages[language] = {
                            language: language
                        };
                    });
                    
                    $http({
                        method: 'GET',
                        url: '/api/item/' + ctrl.item.id + '/language'
                    }).then(function(response) {
                        angular.forEach(response.data.items, function(itemLanguage) {
                            ctrl.itemLanguages[itemLanguage.language] = itemLanguage;
                        });
                    });
                    ctrl.languagesLoading--;
                }, function() {
                    ctrl.languagesLoading--;
                });
            }
            
            function initLogoTab() {
                Acl.isAllowed('brand', 'logo').then(function(allow) {
                    ctrl.canLogo = !!allow;
                }, function() {
                    ctrl.canLogo = false;
                });
            }
            
            function initCatalogueTab() {
                
                ctrl.catalogueLoading++;
                $http({
                    method: 'GET',
                    url: '/api/item-parent',
                    params: {
                        parent_id: ctrl.item.id,
                        limit: 500,
                        fields: 'name,duplicate_child.name_html,item.name_html,item.name,item.public_urls',
                        order: 'moder_auto'
                    }
                }).then(function(response) {
                    ctrl.childs = response.data.items;
                    ctrl.catalogueLoading--;
                }, function() {
                    ctrl.catalogueLoading--;
                });
                
                ctrl.catalogueLoading++;
                $http({
                    method: 'GET',
                    url: '/api/item-parent',
                    params: {
                        item_id: ctrl.item.id,
                        limit: 500,
                        fields: 'name,duplicate_parent.name_html,parent.name_html,parent.name,parent.public_urls'
                    }
                }).then(function(response) {
                    ctrl.parents = response.data.items;
                    ctrl.catalogueLoading--;
                }, function() {
                    ctrl.catalogueLoading--;
                });
                
                ctrl.catalogueLoading++;
                $http({
                    method: 'GET',
                    url: '/api/item',
                    params: {
                        suggestions_to: ctrl.item.id,
                        limit: 3,
                        fields: 'name_text'
                    }
                }).then(function(response) {
                    ctrl.suggestions = response.data.items;
                    ctrl.catalogueLoading--;
                }, function() {
                    ctrl.catalogueLoading--;
                });
            }
            
            function initVehiclesTab() {
                $http({
                    method: 'GET',
                    url: '/api/item',
                    params: {
                        engine_id: ctrl.item.id,
                        limit: 100,
                        fields: 'name_html'
                    }
                }).then(function(response) {
                    ctrl.engineVehicles = response.data.items;
                });
            }
            
            function initLinksTab() {
                ctrl.linksLoading++;
                $http({
                    method: 'GET',
                    url: '/api/item-link',
                    params: {
                        item_id: ctrl.item.id
                    }
                }).then(function(response) {
                    ctrl.links = response.data.items;
                    ctrl.linksLoading--;
                }, function() {
                    ctrl.linksLoading--;
                });
            }
            
            Acl.isAllowed('specifications', 'edit').then(function(allow) {
                ctrl.canEditSpecifications = !!allow;
            }, function() {
                ctrl.canEditSpecifications = false;
            });
            
            Acl.isAllowed('car', 'move').then(function(allow) {
                ctrl.canMove = !!allow;
            }, function() {
                ctrl.canMove = false;
            });

            ctrl.setActiveTab = function(tab) {
                if (! ctrl.tabs[tab]) {
                    throw "Unexpected tab: " + tab;
                }
                angular.forEach(ctrl.tabs, function(tab) {
                    tab.active = false;
                });
                ctrl.tabs[tab].active = true;
                
                $state.go(STATE_NAME, {tab: tab}, {
                    notify: false,
                    reload: false,
                    location: 'replace'
                });
                
                if (ctrl.tabs[tab].init) {
                    ctrl.tabs[tab].init();
                }
            };
            
            ctrl.toggleSubscription = function() {
                var newValue = !ctrl.item.subscription;
                $http({
                    method: 'PUT',
                    url: '/api/item/' + ctrl.item.id,
                    data: {
                        subscription: newValue
                    }
                }).then(function() {
                    ctrl.item.subscription = newValue;
                });
            };
            
            ctrl.saveMeta = function() {
                ctrl.metaLoading++;
                
                var data = {
                    item_type_id: $state.params.item_type_id,
                    name: ctrl.item.name,
                    full_name: ctrl.item.full_name,
                    catname: ctrl.item.catname,
                    body: ctrl.item.body,
                    spec_id: ctrl.item.spec_id,
                    begin_model_year: ctrl.item.begin_model_year,
                    end_model_year: ctrl.item.end_model_year,
                    begin_year: ctrl.item.begin_year,
                    begin_month: ctrl.item.begin_month,
                    end_year: ctrl.item.end_year,
                    end_month: ctrl.item.end_month,
                    today: ctrl.item.today,
                    produced: ctrl.item.produced,
                    produced_exactly: ctrl.item.produced_exactly,
                    is_concept: ctrl.item.is_concept,
                    is_group: ctrl.item.is_group,
                    lat: ctrl.item.lat,
                    lng: ctrl.item.lng
                };
                
                $http({
                    method: 'PUT',
                    url: '/api/item/' + ctrl.item.id,
                    data: data
                }).then(function(response) {
                    
                    ctrl.invalidParams = {};
                    
                    var promises = [];
                    
                    var ids = [];
                    angular.forEach(ctrl.item.vehicle_type, function(vehicle_type) {
                        ids.push(vehicle_type.id);
                    });
                    promises.push(ItemService.setItemVehicleTypes(ctrl.item.id, ids));
                    
                    ctrl.loading++;
                    $q.all(promises).then(function(results) {
                        ctrl.loading--;
                    });
                    
                    ctrl.metaLoading--;
                }, function(response) {
                    ctrl.invalidParams = response.data.invalid_params;
                    ctrl.metaLoading--;
                });
            };
            
            ctrl.saveLanguages = function() {
                angular.forEach(ctrl.itemLanguages, function(language) {
                    ctrl.languagesLoading++;
                    $http({
                        method: 'PUT',
                        url: '/api/item/' + ctrl.item.id + '/language/' + language.language,
                        data: {
                            name: language.name,
                            text: language.text,
                            full_text: language.full_text
                        }
                    }).then(function(response) {
                        ctrl.languagesLoading--;
                    }, function(response) {
                        ctrl.languagesLoading--;
                    });
                });
            };
            
            ctrl.deleteParent = function(parentId) {
                ctrl.catalogueLoading++;
                $http({
                    method: 'DELETE',
                    url: '/api/item-parent/' + ctrl.item.id + '/' + parentId
                }).then(function() {
                    initCatalogueTab();
                    ctrl.catalogueLoading--;
                }, function() {
                    ctrl.catalogueLoading--;
                });
            };
            
            ctrl.deleteChild = function(itemId) {
                ctrl.catalogueLoading++;
                $http({
                    method: 'DELETE',
                    url: '/api/item-parent/' + itemId + '/' + ctrl.item.id
                }).then(function() {
                    initCatalogueTab();
                    ctrl.catalogueLoading--;
                }, function() {
                    ctrl.catalogueLoading--;
                });
            };
            
            ctrl.addParent = function(parentId) {
                ctrl.catalogueLoading++;
                $http({
                    method: 'POST',
                    url: '/api/item-parent',
                    data: {
                        item_id: ctrl.item.id,
                        parent_id: parentId
                    }
                }).then(function() {
                    initCatalogueTab();
                    ctrl.catalogueLoading--;
                }, function() {
                    ctrl.catalogueLoading--;
                });
            };
            
            ctrl.saveLinks = function() {
                
                var promises = [];
                
                if (ctrl.newLink.url) {
                    promises.push(
                        $http({
                            method: 'POST',
                            url: '/api/item-link',
                            data: {
                                item_id: ctrl.item.id,
                                name: ctrl.newLink.name,
                                url: ctrl.newLink.url,
                                type_id: ctrl.newLink.type_id,
                            }
                        }).then(function() {
                            ctrl.newLink.name = '';
                            ctrl.newLink.url = '';
                            ctrl.newLink.type_id = 'default';
                        })
                    );
                }
                
                angular.forEach(ctrl.links, function(link) {
                    if (link.url) {
                        promises.push(
                            $http({
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
                            $http({
                                method: 'DELETE',
                                url: '/api/item-link/' + link.id
                            })
                        );
                    }
                });
                
                ctrl.linksLoading++;
                $q.all(promises).then(function(results) {
                    initLinksTab();
                    ctrl.linksLoading--;
                }, function() {
                    ctrl.linksLoading--;
                });
            };
            
            ctrl.uploadLogo = function() {
                ctrl.logoLoading++;
                var element = $('#logo-upload');
                $http({
                    url: '/api/item/' + ctrl.item.id + '/logo',
                    method: 'PUT',
                    data: element[0].files[0],
                    headers: {'Content-Type': undefined}
                }).then(function (response) {
                    
                    ctrl.logoLoading++;
                    $http({
                        url: '/api/item/' + ctrl.item.id + '/logo',
                        method: 'GET'
                    }).then(function (response) {
                        ctrl.item.logo = response.data;
                        ctrl.logoLoading--;
                    }, function() {
                        ctrl.logoLoading--;
                    });
                    
                    ctrl.logoLoading--;
                }, function(response) {
                    ctrl.logoLoading--;
                });
            };
        }
    ]);

export default STATE_NAME;
