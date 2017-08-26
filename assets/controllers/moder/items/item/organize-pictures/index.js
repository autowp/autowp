import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';
import VEHICLE_TYPE_SERVICE from 'services/vehicle-type';
import SPEC_SERVICE from 'services/spec';
import CONTENT_LANGUAGE_SERVICE from 'services/content-language';
import ITEM_SERVICE from 'services/item';
import './styles.less';
import notify from 'notify';

const STATE_NAME = 'moder-items-item-organize-pictures';
const CONTROLLER_NAME = 'ModerItemsItemOrganizePicturesController';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/items/item/{id}/organize-pictures?item_type_id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                resolve: {
                    access: [ACL_SERVICE_NAME, function (Acl) {
                        return Acl.isAllowed('car', 'move', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$rootScope', '$http', '$state', '$translate', '$q', '$element', SPEC_SERVICE, VEHICLE_TYPE_SERVICE, ACL_SERVICE_NAME, CONTENT_LANGUAGE_SERVICE, ITEM_SERVICE,
        function($scope, $rootScope, $http, $state, $translate, $q, $element, SpecService, VehicleTypeService, Acl, ContentLanguage, ItemService) {
            
            var ctrl = this;
            
            ctrl.item = null;
            ctrl.newItem = null;
            ctrl.hasSelectedPicture = false;
            ctrl.loading = 0;
            
            $http({
                method: 'GET',
                url: '/api/item/' + $state.params.id,
                params: {
                    fields: ['name_text', 'name', 'is_concept', 
                    'name_default', 'body', 'subscription', 'begin_year', 
                    'begin_month', 'end_year', 'end_month', 'today', 
                    'begin_model_year', 'end_model_year', 'produced', 
                    'is_group', 'spec_id', 'full_name', 
                    'catname', 'lat', 'lng'].join(',')
                }
            }).then(function(response) {
                ctrl.item = response.data;
                ctrl.newItem = angular.copy(ctrl.item);
                ctrl.newItem.is_group = false;
                $translate('item/type/'+ctrl.item.item_type_id+'/name').then(function(translation) {
                    $scope.pageEnv({
                        layout: {
                            isAdminPage: true,
                            blankPage: false,
                            needRight: false
                        },
                        pageId: 78,
                        args: {
                            CAR_ID: ctrl.item.id,
                            CAR_NAME: translation + ': ' + ctrl.item.name_text
                        }
                    });
                });
                
            }, function() {
                $state.go('error-404');
            });
            
            $http({
                method: 'GET',
                url: '/api/picture-item',
                params: {
                    item_id: $state.params.id,
                    limit: 500,
                    fields: 'picture.thumbnail,picture.name_text',
                    order: 'status'
                }
            }).then(function(response) {
                ctrl.pictures = response.data.items;
            }, function(response) {
                notify.response(response);
            });
            
            ctrl.pictureSelected = function(picture) {
                picture.selected = !picture.selected;
                var result = false;
                angular.forEach(ctrl.pictures, function(picture) {
                    if (picture.selected) {
                        result = true;
                    }
                });
                
                ctrl.hasSelectedPicture = result;
            };
            
            ctrl.submit = function() {
                
                ctrl.loading++;
                
                var data = {
                    item_type_id: ctrl.newItem.item_type_id,
                    name: ctrl.newItem.name,
                    full_name: ctrl.newItem.full_name,
                    catname: ctrl.newItem.catname,
                    body: ctrl.newItem.body,
                    spec_id: ctrl.newItem.spec_id,
                    begin_model_year: ctrl.newItem.begin_model_year,
                    end_model_year: ctrl.newItem.end_model_year,
                    begin_year: ctrl.newItem.begin_year,
                    begin_month: ctrl.newItem.begin_month,
                    end_year: ctrl.newItem.end_year,
                    end_month: ctrl.newItem.end_month,
                    today: ctrl.newItem.today,
                    produced: ctrl.newItem.produced,
                    produced_exactly: ctrl.newItem.produced_exactly,
                    is_concept: ctrl.newItem.is_concept,
                    is_group: ctrl.newItem.is_group,
                    lat: ctrl.newItem.lat,
                    lng: ctrl.newItem.lng
                };
                
                var promises = {
                    createItem: $http({
                        method: 'POST',
                        url: '/api/item',
                        data: data
                    })
                };
                
                if (! ctrl.item.is_group) {
                    promises.setIsGroup = $http({
                        method: 'PUT',
                        url: '/api/item/' + ctrl.item.id,
                        data: {
                            is_group: true
                        }
                    });
                }
                
                $q.all(promises).then(function(response) {
                    
                    var location = response.createItem.headers('Location');
                    
                    ctrl.loading++;
                    $http({
                        method: 'GET',
                        url: location
                    }).then(function(response) {
                        
                        var promises = [];
                        
                        var vehicleTypeIds = [];
                        angular.forEach(ctrl.newItem.vehicle_type, function(vehicle_type) {
                            vehicleTypeIds.push(vehicle_type.id);
                        });
                        promises.push(ItemService.setItemVehicleTypes(response.data.id, vehicleTypeIds));
                        
                        promises.push($http.post('/api/item-parent', {
                            parent_id: ctrl.item.id,
                            item_id: response.data.id
                        }));
                        
                        angular.forEach(ctrl.pictures, function(picture) {
                            if (picture.selected) {
                                promises.push(
                                    $http({
                                        method: 'PUT',
                                        url: '/api/picture-item/' + picture.picture_id + '/' + picture.item_id + '/' + picture.type,
                                        data: {
                                            item_id: response.data.id
                                        }
                                    }).then(function() {}, function(response) {
                                        notify.response(response);
                                    })
                                );
                            }
                        });
                        
                        
                        ctrl.loading++;
                        $q.all(promises).then(function(results) {
                            $state.go('moder-items-item', {
                                id: response.data.id,
                                tab: 'pictures'
                            });
                            ctrl.loading--;
                        });
                        
                        ctrl.loading--;
                    }, function(response) {
                        notify.response(response);
                    });
                    
                    ctrl.loading--;
                }, function(response) {
                    ctrl.invalidParams = response.data.invalid_params;
                    ctrl.loading--;
                });
            };
        }
    ]);

export default STATE_NAME;
