import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import './styles.less';
import { VehicleTypeService } from 'services/vehicle-type';
import { SpecService } from 'services/spec';
import { LanguageService } from 'services/language';
var sprintf = require("sprintf-js").sprintf;

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
    .directive('autowpItemMetaForm', function() {
        return {
            restirct: 'E',
            scope: {
                item: '=',
                submit: '<',
                parent: '<',
                invalidParams: '<',
                hideSubmit: '<',
                disableIsGroup: '<'
            },
            template: template,
            transclude: true,
            controllerAs: 'ctrl',
            controller: ['$scope', '$q', 'SpecService', 'VehicleTypeService', 'LanguageService', 'leafletData',
                function($scope, $q, SpecService, VehicleTypeService, LanguageService, leafletData) {
                    var ctrl = this;
                    
                    ctrl.center = {
                        lat: 55.7423627,
                        lng: 37.6786422,
                        zoom: 8
                    };
                    
                    ctrl.markers = {};
                    
                    if ($scope.item && $scope.item.lat && $scope.item.lng) {
                        ctrl.markers.point = {
                            lat: $scope.item ? $scope.item.lat : null,
                            lng: $scope.item ? $scope.item.lng : null,
                            focus: true
                        };
                    }
                    
                    ctrl.tiles = {
                        url: "http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
                        options: {
                            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        }
                    };
                    
                    ctrl.coordsChanged = function() {
                        var lat = parseFloat($scope.item.lat);
                        var lng = parseFloat($scope.item.lng);
                        if (ctrl.markers.point) {
                            ctrl.markers.point.lat = isNaN(lat) ? 0 : lat;
                            ctrl.markers.point.lng = isNaN(lng) ? 0 : lng;
                        } else {
                            ctrl.markers.point = {
                                lat: isNaN(lat) ? 0 : lat,
                                lng: isNaN(lng) ? 0 : lng,
                                focus: true
                            };
                        }
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
                        $scope.item.lat = latLng.lat;
                        $scope.item.lng = latLng.lng;
                    });
                    
                    ctrl.invalidParams = {};
                    
                    ctrl.name_maxlength = 100; // DbTable\Item::MAX_NAME
                    ctrl.full_name_maxlength = 255; // BrandModel::MAX_FULLNAME
                    ctrl.body_maxlength = 20;
                    ctrl.model_year_max = new Date().getFullYear() + 10;
                    ctrl.year_max = new Date().getFullYear() + 10;
                    
                    ctrl.specOptions = [];
                    ctrl.monthOptions = [{
                        value: null,
                        name: '--'
                    }];
                    
                    ctrl.todayOptions = [
                        {
                            value: null,
                            name: '--'
                        },
                        {
                            value: false,
                            name: 'moder/vehicle/today/ended'
                        },
                        {
                            value: true,
                            name: 'moder/vehicle/today/continue'
                        }
                    ];
                    
                    ctrl.producedOptions = [
                        {
                            value: false,
                            name: 'moder/item/produced/about'
                        },
                        {
                            value: true,
                            name: 'moder/item/produced/exactly'
                        }
                    ];
                    
                    var date = new Date(Date.UTC(2000, 1, 1, 0, 0, 0, 0));
                    for (var i=0; i<12; i++) {
                        date.setMonth(i);
                        
                        var month = date.toLocaleString(LanguageService.getLanguage(), { month: "long" });
                        ctrl.monthOptions.push({
                            value: i+1,
                            name: sprintf("%02d - %s", i+1, month)
                        });
                    }
                    
                    ctrl.loadVehicleTypes = function(query) {
                        return $q(function(resolve, reject) {
                            VehicleTypeService.getTypes().then(function(data) {
                                var items = toPlain(data, 0);
                                if (query) {
                                    var result = items.filter(function(item) {
                                        return item.nameTranslated.toLowerCase().includes(query.toLowerCase());
                                    });
                                    resolve(result);
                                } else {
                                    resolve(items);
                                }
                            }, function() {
                                reject();
                            });
                        });
                    };
                    
                    ctrl.submit = function() {
                        $scope.submit();
                    };
                    
                    var isConceptOptions = [
                        {
                            value: false,
                            name: 'moder/vehicle/is-concept/no'
                        },
                        {
                            value: true,
                            name: 'moder/vehicle/is-concept/yes'
                        },
                        {
                            value: 'inherited',
                            name: 'moder/vehicle/is-concept/inherited'
                        }
                    ];
                    
                    ctrl.getIsConceptOptions = function(parent) {
                        
                        isConceptOptions[2].name = parent ? (
                            parent.is_concept ? 
                                'moder/vehicle/is-concept/inherited-yes' : 
                                'moder/vehicle/is-concept/inherited-no'
                            ) : 
                            'moder/vehicle/is-concept/inherited';
                        
                        return isConceptOptions;
                    };
                    
                    var defaultSpecOptions = [
                        {
                            id: null,
                            short_name: '--',
                            deep: 0
                        },
                        {
                            id: 'inherited',
                            short_name: 'inherited',
                            deep: 0
                        }
                    ];
                    
                    ctrl.loading++;
                    SpecService.getSpecs().then(function(types) {
                        ctrl.loading--;
                        ctrl.specOptions = toPlain(types, 0);
                    });
                    ctrl.getSpecOptions = function(specOptions) {
                        return defaultSpecOptions.concat(specOptions);
                    };
                }
            ]
        };
    });