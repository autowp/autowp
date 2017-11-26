import * as angular from 'angular';
import Module from 'app.module';
import './styles.less';
import { VehicleTypeService } from 'services/vehicle-type';
import { SpecService } from 'services/spec';
import { LanguageService } from 'services/language';
import { sprintf } from "sprintf-js";

function toPlain(options: any[], deep: number): any[] {
    var result: any[] = [];
    angular.forEach(options, function(item: any) {
        item.deep = deep;
        result.push(item);
        angular.forEach(toPlain(item.childs, deep+1), function(item: any) {
            result.push(item);
        });
    });
    return result;
}

interface IAAutowpItemMetaFormDirectiveScope extends ng.IScope {
    submitNotify: Function,
    submit: Function;
    item: any;
}

class AutowpItemMetaFormDirectiveController {

    public loading: number = 0;
    public todayOptions = [
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
     
    public producedOptions = [
        {
            value: false,
            name: 'moder/item/produced/about'
        },
        {
            value: true,
            name: 'moder/item/produced/exactly'
        }
    ];
    public center = {
        lat: 55.7423627,
        lng: 37.6786422,
        zoom: 8
    };
    
    public markers: any = {};
    public tiles = {
        url: "http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
        options: {
            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }
    };
    public name_maxlength = 100; // DbTable\Item::MAX_NAME
    public full_name_maxlength = 255; // BrandModel::MAX_FULLNAME
    public body_maxlength = 20;
    public model_year_max: number;
    public year_max: number;
    public invalidParams: any = {};
    public specOptions: any[] = [];
    public monthOptions: any[];
    private isConceptOptions = [
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
    public defaultSpecOptions = [
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

    static $inject = ['$scope', '$q', 'SpecService', 'VehicleTypeService', 'LanguageService', 'leafletData'];
    constructor(
        protected $scope: IAAutowpItemMetaFormDirectiveScope, 
        private $q: ng.IQService,
        private SpecService: SpecService,
        private VehicleTypeService: VehicleTypeService,
        private LanguageService: LanguageService,
        private leafletData: any
    ) {
        var ctrl = this;
        
        if ($scope.item && $scope.item.lat && $scope.item.lng) {
            ctrl.markers.point = {
                lat: $scope.item ? $scope.item.lat : null,
                lng: $scope.item ? $scope.item.lng : null,
                focus: true
            };
        }
        
        $scope.$on('leafletDirectiveMap.click', function(event: any, e: any) {
            var latLng = e.leafletEvent.latlng;
            ctrl.markers.point = {
                lat: latLng.lat,
                lng: latLng.lng,
                focus: true
            };
            $scope.item.lat = latLng.lat;
            $scope.item.lng = latLng.lng;
        });
        
        
        
        
        this.model_year_max = new Date().getFullYear() + 10;
        this.year_max = new Date().getFullYear() + 10;
        
        
        ctrl.monthOptions = [{
            value: null,
            name: '--'
        }];
        
        var date = new Date(Date.UTC(2000, 1, 1, 0, 0, 0, 0));
        for (var i=0; i<12; i++) {
            date.setMonth(i);
            let language = LanguageService.getLanguage();
            if (language) {
                var month = date.toLocaleString(language, { month: "long" });
                ctrl.monthOptions.push({
                    value: i+1,
                    name: sprintf("%02d - %s", i+1, month)
                });
            }
        }
        
        this.loading++;
        var self = this;
        this.SpecService.getSpecs().then(function(types) {
            self.loading--;
            self.specOptions = toPlain(types, 0);
        });
        
    }
        
    public coordsChanged() {
        var lat = parseFloat(this.$scope.item.lat);
        var lng = parseFloat(this.$scope.item.lng);
        if (this.markers.point) {
            this.markers.point.lat = isNaN(lat) ? 0 : lat;
            this.markers.point.lng = isNaN(lng) ? 0 : lng;
        } else {
            this.markers.point = {
                lat: isNaN(lat) ? 0 : lat,
                lng: isNaN(lng) ? 0 : lng,
                focus: true
            };
        }
        this.center.lat = isNaN(lat) ? 0 : lat;
        this.center.lng = isNaN(lng) ? 0 : lng;
    }
        
    public loadVehicleTypes(query: string) {
        var self = this;
        return this.$q(function(resolve, reject) {
            self.VehicleTypeService.getTypes().then(function(data: any) {
                var items = toPlain(data, 0);
                if (query) {
                    var result = items.filter(function(item: any) {
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
    }
        
    public submit() {
        this.$scope.submitNotify();
    }
        
    public getIsConceptOptions(parent: any) {
        this.isConceptOptions[2].name = parent ? (
            parent.is_concept ? 
                'moder/vehicle/is-concept/inherited-yes' : 
                'moder/vehicle/is-concept/inherited-no'
            ) : 
            'moder/vehicle/is-concept/inherited';
        
        return this.isConceptOptions;
    }

    public getSpecOptions(specOptions: any[]): any[] {
        return this.defaultSpecOptions.concat(specOptions);
    }
}

class AutowpItemMetaFormDirective implements ng.IDirective {
    public controllerAs = 'ctrl';
    public restrict = 'E';
    public transclude: true;
    public scope = {
        item: '=',
        submitNotify: '&submit',
        parent: '<',
        invalidParams: '<',
        hideSubmit: '<',
        disableIsGroup: '<'
    };
    public template = require('./template.html');
    public controller = AutowpItemMetaFormDirectiveController;
    public bindToController: true;

    static factory(): ng.IDirectiveFactory {
        return () => new AutowpItemMetaFormDirective();
    }
}

angular.module(Module).directive('autowpItemMetaForm', AutowpItemMetaFormDirective.factory());
