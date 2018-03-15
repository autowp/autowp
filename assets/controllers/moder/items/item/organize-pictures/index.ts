import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import { VehicleTypeService } from 'services/vehicle-type';
import { SpecService } from 'services/spec';
import { ContentLanguageService } from 'services/content-language';
import { ItemService } from 'services/item';
import './styles.scss';
import notify from 'notify';

const STATE_NAME = 'moder-items-item-organize-pictures';
const CONTROLLER_NAME = 'ModerItemsItemOrganizePicturesController';

export class ModerItemsItemOrganizePicturesController {
    static $inject = ['$scope', '$rootScope', '$http', '$state', '$translate', '$q', '$element', 'SpecService', 'VehicleTypeService', 'AclService', 'ContentLanguageService', 'ItemService'];

    public item: autowp.IItem;
    public newItem: any = null;
    public hasSelectedPicture: boolean = false;
    public loading: number = 0;
    public pictures: any[];
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

        this.ItemService.getItem($state.params.id, {
            fields: ['name_text', 'name', 'is_concept',
                     'name_default', 'body', 'subscription', 'begin_year',
                     'begin_month', 'end_year', 'end_month', 'today',
                     'begin_model_year', 'end_model_year', 'produced',
                     'is_group', 'spec_id', 'full_name',
                     'catname', 'lat', 'lng'].join(',')
        }).then(function(item: autowp.IItem) {
            self.item = item;
            self.newItem = angular.copy(self.item);
            self.newItem.is_group = false;
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

        }, function() {
            $state.go('error-404');
        });

        this.$http({
            method: 'GET',
            url: '/api/picture-item',
            params: {
                item_id: $state.params.id,
                limit: 500,
                fields: 'picture.thumbnail,picture.name_text',
                order: 'status'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.pictures = response.data.items;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }

    public pictureSelected(picture: any) {
        picture.selected = !picture.selected;
        var result = false;
        angular.forEach(this.pictures, function(picture: any) {
            if (picture.selected) {
                result = true;
            }
        });

        this.hasSelectedPicture = result;
    };

    public submit() {

        this.loading++;

        var data = {
            item_type_id: this.newItem.item_type_id,
            name: this.newItem.name,
            full_name: this.newItem.full_name,
            catname: this.newItem.catname,
            body: this.newItem.body,
            spec_id: this.newItem.spec_id,
            begin_model_year: this.newItem.begin_model_year,
            end_model_year: this.newItem.end_model_year,
            begin_year: this.newItem.begin_year,
            begin_month: this.newItem.begin_month,
            end_year: this.newItem.end_year,
            end_month: this.newItem.end_month,
            today: this.newItem.today,
            produced: this.newItem.produced,
            produced_exactly: this.newItem.produced_exactly,
            is_concept: this.newItem.is_concept,
            is_group: this.newItem.is_group,
            lat: this.newItem.lat,
            lng: this.newItem.lng
        };

        var promises: any = {
            createItem: this.$http({
                method: 'POST',
                url: '/api/item',
                data: data
            })
        };

        if (! this.item.is_group) {
            promises.setIsGroup = this.$http({
                method: 'PUT',
                url: '/api/item/' + this.item.id,
                data: {
                    is_group: true
                }
            });
        }

        var self = this;

        this.$q.all(promises).then(function(response: any) {

            var location = response.createItem.headers('Location');

            self.loading++;
            self.$http({
                method: 'GET',
                url: location
            }).then(function(response: ng.IHttpResponse<any>) {

                var promises: any[] = [];

                var vehicleTypeIds: number[] = [];
                angular.forEach(self.newItem.vehicle_type, function(vehicle_type) {
                    vehicleTypeIds.push(vehicle_type.id);
                });
                promises.push(self.ItemService.setItemVehicleTypes(response.data.id, vehicleTypeIds));

                promises.push(self.$http.post('/api/item-parent', {
                    parent_id: self.item.id,
                    item_id: response.data.id
                }));

                angular.forEach(self.pictures, function(picture) {
                    if (picture.selected) {
                        promises.push(
                            self.$http({
                                method: 'PUT',
                                url: '/api/picture-item/' + picture.picture_id + '/' + picture.item_id + '/' + picture.type,
                                data: {
                                    item_id: response.data.id
                                }
                            }).then(function() {}, function(response: ng.IHttpResponse<any>) {
                                notify.response(response);
                            })
                        );
                    }
                });


                self.loading++;
                self.$q.all(promises).then(function(results) {
                    self.$state.go('moder-items-item', {
                        id: response.data.id,
                        tab: 'pictures'
                    });
                    self.loading--;
                });

                self.loading--;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });

            self.loading--;
        }, function(response: ng.IHttpResponse<any>) {
            self.invalidParams = response.data.invalid_params;
            self.loading--;
        });
    }

    public srcset(picture) {

        let sources = [];
        if (picture.thumb) {
            sources.push(picture.thumb);
        }
        if (picture.medium) {
            sources.push(picture.medium);
        }

        let parts = [];

        for (const source of sources) {
            parts.push(source.src + ' ' + source.width + 'w');
        }

        console.log(picture);

        if (sources.length) {
            return parts.join(', ');
        }

        return null;
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerItemsItemOrganizePicturesController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/items/item/{id}/organize-pictures?item_type_id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.isAllowed('car', 'move', 'unauthorized');
                    }]
                }
            });
        }
    ]);
