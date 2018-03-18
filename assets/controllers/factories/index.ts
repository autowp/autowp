import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import * as $ from "jquery";
import { ItemService } from 'services/item';
var leaflet = require("leaflet-bundle");

import './items';

const CONTROLLER_NAME = 'FactoryController';
const STATE_NAME = 'factory';

export class FactoryController {
    static $inject = ['$scope', '$http', '$state', '$element', 'ItemService'];
    public factory: autowp.IItem;
    public pictures: any[] = [];
    public relatedPictures: any[] = [];
    private map: any;

    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $state: any,
        private $element: any,
        private ItemService: ItemService
    ) {

        var self = this;

        this.ItemService.getItem(this.$state.params.id, {
            fields: ['name_text', 'name_html', 'lat', 'lng', 'description', 'related_group_pictures'].join(',')
        }).then(function(item: autowp.IItem) {

            self.factory = item;

            self.relatedPictures = [];
            if (self.factory.related_group_pictures) {
                self.relatedPictures = self.factory.related_group_pictures;
            }

            if (self.factory.item_type_id != 6) {
                self.$state.go('error-404');
                return;
            }

            self.$scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/181/name',
                pageId: 181,
                args: {
                    FACTORY_ID: self.factory.id,
                    FACTORY_NAME: self.factory.name_text
                }
            });

            self.$http({
                method: 'GET',
                url: '/api/picture',
                params: {
                    status: 'accepted',
                    exact_item_id: self.factory.id,
                    limit: 32,
                    fields: 'owner,thumb_medium,votes,views,comments_count,name_html,name_text'
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.pictures = [];
                if (response.data.pictures) {
                    self.pictures = response.data.pictures;
                }
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });

            if (self.factory.lat && self.factory.lng) {
                $($element[0]).find('.google-map').each(function() {

                    self.map = leaflet.map(this).setView([self.factory.lat, self.factory.lng], 17);
                    leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
                    }).addTo(self.map);

                    leaflet.marker([self.factory.lat, self.factory.lng]).addTo(self.map);
                    setTimeout(function() {
                        self.map.invalidateSize();
                    }, 300)
                });
            }

        }, function(response: ng.IHttpResponse<any>) {
            self.$state.go('error-404');
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, FactoryController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/factories/:id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);

