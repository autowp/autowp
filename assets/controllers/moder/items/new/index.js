import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';
import VEHICLE_TYPE_SERVICE from 'services/vehicle-type';
import SPEC_SERVICE from 'services/spec';
var sprintf = require("sprintf-js").sprintf;

const STATE_NAME = 'moder-items-new';
const CONTROLLER_NAME = 'ModerItemsNewController';

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
                url: '/moder/items/new?item_type_id&parent_id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                resolve: {
                    access: [ACL_SERVICE_NAME, function (Acl) {
                        return Acl.isAllowed('car', 'add', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', '$translate', '$q', SPEC_SERVICE, VEHICLE_TYPE_SERVICE,
        function($scope, $http, $state, $translate, $q, SpecService, VehicleTypeService) {
            
            var ctrl = this;
            
            ctrl.loading = 0;
            ctrl.item_type_id = parseInt($state.params.item_type_id);
            ctrl.parent = null;
            ctrl.parentSpec = null;
            
            if ([1, 2, 3, 4, 5, 6, 7].indexOf(ctrl.item_type_id) == -1) {
                $state.go('error-404');
                return;
            }
            
            if ($state.params.parent_id) {
                ctrl.loading++;
                $http({
                    method: 'GET',
                    url: '/api/item/' + $state.params.parent_id,
                    params: {
                        fields: 'is_concept,name_html,spec_id'
                    }
                }).then(function(response) {
                    ctrl.parent = response.data;
                    setIsConceptOptions();
                    
                    if (ctrl.parent.spec_id) {
                        SpecService.getSpec(ctrl.parent.spec_id).then(function(spec) {
                            ctrl.parentSpec = spec;
                        });
                    }
                    ctrl.loading--;
                }, function() {
                    ctrl.loading--;
                });
            }
            
            ctrl.center = {
                lat: 55.7423627,
                lng: 37.6786422,
                zoom: 8
            };
            ctrl.markers = {
                point: {
                    lat: 0,
                    lng: 0,
                    focus: true
                }
            };
            
            ctrl.tiles = {
                url: "http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
                options: {
                    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }
            };
            
            ctrl.coordsChanged = function() {
                var lat = parseFloat(ctrl.item.lat);
                var lng = parseFloat(ctrl.item.lng);
                ctrl.markers.point.lat = isNaN(lat) ? 0 : lat;
                ctrl.markers.point.lng = isNaN(lng) ? 0 : lng;
                ctrl.center.lat = isNaN(lat) ? 0 : lat;
                ctrl.center.lng = isNaN(lng) ? 0 : lng;
            };


            $scope.$on('leafletDirectiveMap.click', function(event, e) {
                var latLng = e.leafletEvent.latlng;
                ctrl.markers.point = {
                    lat: latLng.lat,
                    lng: latLng.lng,
                    focus: true
                };
                ctrl.item.lat = latLng.lat;
                ctrl.item.lng = latLng.lng;
            });
            
            ctrl.invalidParams = {};
            
            ctrl.item = {
                produced_exactly: '0',
                is_concept: 'inherited',
                spec_id: 'inherited'
            };
            
            ctrl.name_maxlength = 100; // DbTable\Item::MAX_NAME
            ctrl.full_name_maxlength = 255; // BrandModel::MAX_FULLNAME
            ctrl.body_maxlength = 20;
            ctrl.model_year_max = parseInt(new Date().getFullYear()) + 10;
            ctrl.year_max = parseInt(new Date().getFullYear()) + 10;
            
            ctrl.specOptions = [];
            ctrl.monthOptions = [];
            
            var date = new Date("01/01/2000");
            for (var i=0; i<12; i++) {
                date.setMonth(i);
                
                var month = date.toLocaleString({}, { month: "long" });
                ctrl.monthOptions.push({
                    value: i+1,
                    name: sprintf("%02d - %s", i+1, month)
                });
            }
            
            function setIsConceptOptions() {
                ctrl.isConceptOptions = [
                    {
                        value: 0,
                        name: 'moder/vehicle/is-concept/no',
                    },
                    {
                        value: 1,
                        name: 'moder/vehicle/is-concept/yes',
                    }
                ];
                if (ctrl.parent) {
                    ctrl.isConceptOptions = [{
                        value: 'inherited',
                        name: ctrl.parent.is_concept ? 
                            'moder/vehicle/is-concept/inherited-yes': 
                            'moder/vehicle/is-concept/inherited-no'
                    }].concat(ctrl.isConceptOptions);
                } else {
                    ctrl.isConceptOptions = [{
                        value: 'inherited',
                        name: 'moder/vehicle/is-concept/inherited'
                    }].concat(ctrl.isConceptOptions);
                }
            }
            
            setIsConceptOptions();
            
            ctrl.loadVehicleTypes = function(query) {
                return VehicleTypeService.getTypes();
            };

            $translate('item/type/'+$state.params.item_type_id+'/new-item').then(function(translation) {
                $scope.pageEnv({
                    layout: {
                        isAdminPage: true,
                        blankPage: false,
                        needRight: false
                    },
                    pageId: 163,
                    args: {
                        NEW_ITEM_OF_TYPE: translation
                    }
                });
            });
            
            ctrl.loading++;
            SpecService.getSpecs().then(function(types) {
                ctrl.loading--;
                ctrl.specOptions = toPlain(types, 0);
            });
            
            ctrl.submit = function() {
                ctrl.loading++;
                
                var data = {
                    item_type_id: $state.params.item_type_id,
                    name: ctrl.item.name,
                    full_name: ctrl.item.full_name,
                    catname: ctrl.item.catname,
                    body: ctrl.item.body,
                    spec_id: ctrl.item.spec_id == 'inherited' ? null : ctrl.item.spec_id,
                    spec_inherited: ctrl.item.spec_id == 'inherited' ? 1 : 0,
                    begin_model_year: ctrl.item.begin_model_year,
                    end_model_year: ctrl.item.end_model_year,
                    begin_year: ctrl.item.begin_year,
                    begin_month: ctrl.item.begin_month,
                    end_year: ctrl.item.end_year,
                    end_month: ctrl.item.end_month,
                    today: ctrl.item.today,
                    produced_count: ctrl.item.produced_count,
                    produced_exactly: ctrl.item.produced_exactly,
                    is_concept: ctrl.item.is_concept == 'inherited' ? null : ctrl.item.is_concept,
                    is_concept_inherited: ctrl.item.is_concept == 'inherited' ? 1 : 0,
                    is_group: ctrl.item.is_group,
                    lat: ctrl.item.lat,
                    lng: ctrl.item.lng
                };
                
                $http({
                    method: 'POST',
                    url: '/api/item',
                    data: data
                }).then(function(response) {
                    
                    var location = response.headers('Location');
                    
                    ctrl.loading++;
                    $http({
                        method: 'GET',
                        url: location
                    }).then(function(response) {
                        
                        var promises = [];
                        
                        angular.forEach(ctrl.item_vehicle_type, function(vehicle_type) {
                            promises.push($http.post('/api/item-vehicle-type/' + response.data.id + '/' + vehicle_type.id));
                        });
                        
                        if (ctrl.parent) {
                            promises.push($http.post('/api/item-parent/' + response.data.id + '/' + ctrl.parent.id));
                        }
                        
                        ctrl.loading++;
                        $q.all(promises).then(function(results) {
                            window.location = '/moder/cars/car/item_id/' + response.data.id;
                            ctrl.loading--;
                        });
                        
                        ctrl.loading--;
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
