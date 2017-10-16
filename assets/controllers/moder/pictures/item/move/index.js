import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import './item'; // directive
import PICTURE_ITEM_SERVICE from 'services/picture-item';
import ACL_SERVICE_NAME from 'services/acl';
import notify from 'notify';

const CONTROLLER_NAME = 'ModerPicturesItemMoveController';
const STATE_NAME = 'moder-pictures-item-move';

function chunk(arr, count) {
    var newArr = [];
    var size = Math.ceil(arr.length / count);
    for (var i=0; i<arr.length; i+=size) {
        newArr.push(arr.slice(i, i+size));
    }
    return newArr;
}

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/pictures/{id}/move?show_museums&show_factories&show_authors&show_persons&brand_id&src_item_id&src_type&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                resolve: {
                    access: [ACL_SERVICE_NAME, function (Acl) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', '$q', PICTURE_ITEM_SERVICE,
        function($scope, $http, $state, $q, PictureItemService) {
            
            var ctrl = this;
            
            ctrl.search = '';
            
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                name: 'page/149/name',
                pageId: 149
            });
            
            $scope.page = $state.params.page;
            $scope.src_item_id = $state.params.src_item_id;
            $scope.src_type = $state.params.src_type;
            
            $scope.picture = null;
            $scope.show_museums = $state.params.show_museums;
            $scope.show_factories = $state.params.show_factories;
            $scope.show_persons = $state.params.show_persons;
            $scope.show_authors = $state.params.show_authors;
            $scope.brand_id = $state.params.brand_id;
            
            if ($scope.src_type == 2) {
                $scope.show_authors = true;
            }
            
            $scope.museums = [];
            $scope.museums_paginator = null;
            
            $scope.factories = [];
            $scope.factories_paginator = null;
            
            $scope.brands = [];
            $scope.brands_paginator = null;
            
            $scope.vehicles = [];
            $scope.engines = [];
            
            $scope.concepts_expanded = false;
            
            if ($scope.show_museums) {
                $http({
                    method: 'GET',
                    url: '/api/item',
                    params: {
                        type_id: 7,
                        fields: 'name_html',
                        limit: 50,
                        page: $scope.page
                    }
                }).then(function(response) {
                    $scope.museums = response.data.items;
                    $scope.museums_paginator = response.data.paginator;
                }, function(response) {
                    notify.response(response);
                });
            }
            
            if ($scope.show_factories) {
                $http({
                    method: 'GET',
                    url: '/api/item',
                    params: {
                        type_id: 6,
                        fields: 'name_html',
                        limit: 50,
                        page: $scope.page
                    }
                }).then(function(response) {
                    $scope.factories = response.data.items;
                    $scope.factories_paginator = response.data.paginator;
                }, function(response) {
                    notify.response(response);
                });
            }
            
            var personsCanceler;
            function loadPersons() {
                
                if (personsCanceler) {
                    personsCanceler.resolve();
                    personsCanceler = null;
                }
                
                personsCanceler = $q.defer();
                
                $http({
                    method: 'GET',
                    url: '/api/item',
                    params: {
                        type_id: 8,
                        fields: 'name_html',
                        limit: 50,
                        name: ctrl.search ? '%' + ctrl.search + '%' : null,
                        page: $scope.page
                    },
                    timeout: personsCanceler.promise
                }).then(function(response) {
                    $scope.persons = response.data.items;
                    $scope.persons_paginator = response.data.paginator;
                }, function(response) {
                    notify.response(response);
                });
            }
            
            if ($scope.show_persons) {
                ctrl.doSearch = function() {
                    loadPersons();
                };
                
                loadPersons();
            }
            
            var authorsCanceler;
            function loadAuthors() {
                
                if (authorsCanceler) {
                    authorsCanceler.resolve();
                    authorsCanceler = null;
                }
                
                authorsCanceler = $q.defer();
                
                $http({
                    method: 'GET',
                    url: '/api/item',
                    params: {
                        type_id: 8,
                        fields: 'name_html',
                        limit: 50,
                        name: ctrl.search ? '%' + ctrl.search + '%' : null,
                        page: $scope.page
                    },
                    timeout: authorsCanceler.promise
                }).then(function(response) {
                    $scope.authors = response.data.items;
                    $scope.authors_paginator = response.data.paginator;
                }, function(response) {
                    notify.response(response);
                });
            }
            
            if ($scope.show_authors) {
                
                ctrl.doSearch = function() {
                    loadAuthors();
                };
                
                loadAuthors();
            }
            
            function loadBrands() {
                $http({
                    method: 'GET',
                    url: '/api/item',
                    params: {
                        type_id: 5,
                        fields: 'name_html',
                        limit: 200,
                        name: ctrl.search ? '%' + ctrl.search + '%' : null,
                        page: $scope.page
                    }
                }).then(function(response) {
                    $scope.brands = chunk(response.data.items, 6);
                    $scope.brands_paginator = response.data.paginator;
                }, function(response) {
                    notify.response(response);
                });
            }
            
            if (! $scope.show_museums && ! $scope.show_factories && ! $scope.show_persons && ! $scope.show_authors) {
                if ($scope.brand_id) {
                    $http({
                        method: 'GET',
                        url: '/api/item-parent',
                        params: {
                            item_type_id: 1,
                            parent_id: $scope.brand_id,
                            fields: 'item.name_html,item.childs_count',
                            limit: 500,
                            page: 1
                        }
                    }).then(function(response) {
                        $scope.vehicles = response.data.items;
                        $scope.vehicles_paginator = response.data.paginator;
                    }, function(response) {
                        notify.response(response);
                    });
                    $http({
                        method: 'GET',
                        url: '/api/item-parent',
                        params: {
                            item_type_id: 2,
                            parent_id: $scope.brand_id,
                            fields: 'item.name_html,item.childs_count',
                            limit: 500,
                            page: 1
                        }
                    }).then(function(response) {
                        $scope.engines = response.data.items;
                        $scope.engines_paginator = response.data.paginator;
                    }, function(response) {
                        notify.response(response);
                    });
                    
                    $http({
                        method: 'GET',
                        url: '/api/item-parent',
                        params: {
                            item_type_id: 1,
                            concept: true,
                            ancestor_id: $scope.brand_id,
                            fields: 'item.name_html,item.childs_count',
                            limit: 500,
                            page: 1
                        }
                    }).then(function(response) {
                        $scope.concepts = response.data.items;
                    }, function(response) {
                        notify.response(response);
                    });
                    
                } else {
                    
                    ctrl.doSearch = function() {
                        loadBrands();
                    };
                    
                    loadBrands();
                }
            }
            
            $scope.toggleConcepts = function() {
                $scope.concepts_expanded = !$scope.concepts_expanded;
            };
            
            $scope.selectItem = function(itemId, perspectiveId, type) {
                if ($scope.src_item_id && $scope.src_type) {
                    PictureItemService.changeItem($state.params.id, $scope.src_type, $scope.src_item_id, itemId).then(function() {
                        if (Number.isInteger(perspectiveId)) {
                            PictureItemService.setPerspective($state.params.id, $scope.src_type, itemId, perspectiveId).then(function() {
                                $state.go('moder-pictures-item', {
                                    id: $state.params.id
                                });
                            });
                        } else {
                            $state.go('moder-pictures-item', {
                                id: $state.params.id
                            });
                        }
                    });
                } else {
                    var data = {
                        perspective_id: perspectiveId ? perspectiveId : null
                    };
                    
                    PictureItemService.create($state.params.id, itemId, type, data).then(function() {
                        $state.go('moder-pictures-item', {
                            id: $state.params.id
                        });
                    });
                }
            };
        }
    ]);

export default CONTROLLER_NAME;
