import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';
import VEHICLE_TYPE_SERVICE from 'services/vehicle-type';
import SPEC_SERVICE from 'services/spec';
import CONTENT_LANGUAGE_SERVICE from 'services/content-language';
import './tree';
import './tree-item';
import 'directives/auto-focus';

const STATE_NAME = 'moder-items-item-select-parent';
const CONTROLLER_NAME = 'ModerItemsItemSelectParentController';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/items/item/{id}/select-parent?tab&brand_id&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                resolve: {
                    access: [ACL_SERVICE_NAME, function (Acl) {
                        return Acl.isAllowed('car', 'edit_meta', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$rootScope', '$http', '$state', '$translate', '$q', '$element', SPEC_SERVICE, VEHICLE_TYPE_SERVICE, ACL_SERVICE_NAME, CONTENT_LANGUAGE_SERVICE,
        function($scope, $rootScope, $http, $state, $translate, $q, $element, SpecService, VehicleTypeService, Acl, ContentLanguage) {
            
            var ctrl = this;
            
            ctrl.tab = $state.params.tab || 'catalogue';
            ctrl.showCatalogueTab = false;
            ctrl.showBrandsTab = false;
            ctrl.showTwinsTab = false;
            ctrl.showFactoriesTab = false;
            
            ctrl.brand_id = null;
            ctrl.paginator = null;
            ctrl.page = $state.params.page;
            ctrl.search = '';
            
            $http({
                method: 'GET',
                url: '/api/item/' + $state.params.id
            }).then(function(response) {
                ctrl.item = response.data;
                
                $translate('item/type/'+ctrl.item.item_type_id+'/name').then(function(translation) {
                    $scope.pageEnv({
                        layout: {
                            isAdminPage: true,
                            blankPage: false,
                            needRight: false
                        },
                        pageId: 144,
                        args: {
                            CAR_ID: ctrl.item.id,
                            CAR_NAME: translation + ': ' + ctrl.item.name_text
                        }
                    });
                });
                
                ctrl.showCatalogueTab = [1, 2, 5].includes(ctrl.item.item_type_id);
                ctrl.showBrandsTab = [1, 2, 5].includes(ctrl.item.item_type_id);
                ctrl.showTwinsTab = ctrl.item.item_type_id == 1;
                ctrl.showFactoriesTab = [1, 2].includes(ctrl.item.item_type_id);
                
                function loadCatalogueBrands() {
                    $http({
                        method: 'GET',
                        url: '/api/item',
                        params: {
                            type_id: 5,
                            limit: 500,
                            fields: 'name_html',
                            have_childs_of_type: ctrl.item.item_type_id,
                            name: ctrl.search ? '%' + ctrl.search + '%' : null,
                            page: ctrl.page
                        }
                    }).then(function(response) {
                        ctrl.brands = ctrl.chunk(response.data.items, 6);
                        ctrl.paginator = response.data.paginator;
                    });
                }
                
                if (ctrl.tab == 'catalogue') {
                    ctrl.brand_id = $state.params.brand_id;
                    if (ctrl.brand_id) {
                        $http({
                            method: 'GET',
                            url: '/api/item-parent',
                            params: {
                                limit: 100, 
                                fields: 'item.name_html,item.childs_count',
                                parent_id: ctrl.brand_id,
                                is_group: true,
                                type_id: ctrl.item.item_type_id,
                                page: ctrl.page
                            }
                        }).then(function(response) {
                            ctrl.items = response.data.items;
                            ctrl.paginator = response.data.paginator;
                        });
                    } else {
                        
                        ctrl.doSearch = function() {
                            loadCatalogueBrands();
                        };
                        
                        loadCatalogueBrands();
                    }
                }
                
                function loadBrands() {
                    $http({
                        method: 'GET',
                        url: '/api/item',
                        params: {
                            type_id: 5,
                            limit: 500,
                            fields: 'name_html',
                            name: ctrl.search ? '%' + ctrl.search + '%' : null,
                            page: ctrl.page
                        }
                    }).then(function(response) {
                        ctrl.brands = ctrl.chunk(response.data.items, 6);
                        ctrl.paginator = response.data.paginator;
                    });
                }
                
                if (ctrl.tab == 'brands') {
                    
                    ctrl.doSearch = function() {
                        loadBrands();
                    };
                    
                    loadBrands();
                }
                
                if (ctrl.tab == 'categories') {
                    
                    $http({
                        method: 'GET',
                        url: '/api/item',
                        params: {
                            type_id: 3,
                            limit: 100,
                            fields: 'name_html,childs_count',
                            page: ctrl.page,
                            no_parent: true
                        }
                    }).then(function(response) {
                        ctrl.categories = response.data.items;
                        ctrl.paginator = response.data.paginator;
                    });
                }
                
                if (ctrl.tab == 'twins') {
                    ctrl.brand_id = $state.params.brand_id;
                    if (ctrl.brand_id) {
                        $http({
                            method: 'GET',
                            url: '/api/item',
                            params: {
                                type_id: 4,
                                limit: 100,
                                fields: 'name_html',
                                have_common_childs_with: ctrl.brand_id,
                                page: ctrl.page
                            }
                        }).then(function(response) {
                            ctrl.items = response.data.items;
                            ctrl.paginator = response.data.paginator;
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
                                page: ctrl.page
                            }
                        }).then(function(response) {
                            ctrl.brands = ctrl.chunk(response.data.items, 6);
                            ctrl.paginator = response.data.paginator;
                        });
                    }
                }
                
                if (ctrl.tab == 'factories') {
                    $http({
                        method: 'GET',
                        url: '/api/item',
                        params: {
                            type_id: 6,
                            limit: 100,
                            fields: 'name_html',
                            page: ctrl.page
                        }
                    }).then(function(response) {
                        ctrl.factories = response.data.items;
                        ctrl.paginator = response.data.paginator;
                    });
                }
                
            }, function() {
                $state.go('error-404');
            });
            
            ctrl.select = function(parent) {
                $http({
                    method: 'POST',
                    url: '/api/item-parent',
                    data: {
                        item_id: ctrl.item.id,
                        parent_id: parent.id
                    }
                }).then(function(response) {
                    $state.go('moder-items-item', {
                        id: ctrl.item.id,
                        tab: 'catalogue'
                    });
                });
            };
            
            ctrl.chunkBy = function(arr, count) {
                if (! arr) {
                    return [];
                }
                var newArr = [];
                var size = Math.ceil(count);
                for (var i=0; i<arr.length; i+=size) {
                    newArr.push(arr.slice(i, i+size));
                }

                return newArr;
            };
            
            ctrl.chunk = function(arr, count) {
                var newArr = [];
                var size = Math.ceil(arr.length / count);
                for (var i=0; i<arr.length; i+=size) {
                    newArr.push(arr.slice(i, i+size));
                }
                return newArr;
            };
            
            ctrl.loadChildCategories = function(parent) {
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
                }).then(function(response) {
                    parent.childs = response.data.items;
                });
            };
            
            ctrl.loadChildCatalogues = function(parent) {
                $http({
                    method: 'GET',
                    url: '/api/item-parent',
                    params: {
                        limit: 100,
                        fields: 'item.name_html,item.childs_count',
                        parent_id: parent.id,
                        is_group: true,
                        order: 'moder_auto'
                    }
                }).then(function(response) {
                    parent.childs = response.data.items;
                });
            };
        }
    ]);

export default STATE_NAME;
