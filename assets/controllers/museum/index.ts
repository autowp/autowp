import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { AclService } from 'services/acl';
import { ItemService } from 'services/item';
import * as $ from "jquery";

var leaflet = require("leaflet-bundle");

const CONTROLLER_NAME = 'MuseumController';
const STATE_NAME = 'museum';

export class MuseumController {
    static $inject = ['$scope', '$http', '$state', 'AclService', 'ItemService'];

    public museumModer: boolean = false;
    public links: any[] = [];
    public pictures: any[] = [];
    public item: autowp.IItem;

    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $state: any,
        private Acl: AclService,
        private ItemService: ItemService
    ) {
        var self = this;

        this.ItemService.getItem(this.$state.params.id, {
            fields: ['name_text', 'lat', 'lng', 'description'].join(',')
        }).then(function(item: autowp.IItem) {

            self.item = item;

            if (self.item.item_type_id != 7) {
                self.$state.go('error-404');
                return;
            }

            self.$scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: true
                },
                name: 'page/159/name',
                pageId: 159,
                args: {
                    MUSEUM_ID: self.item.id,
                    MUSEUM_NAME: self.item.name_text
                }
            });

            self.$http({
                method: 'GET',
                url: '/api/item-link',
                params: {
                    item_id: self.item.id
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.links = response.data.items;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });

            if (self.item.lat && self.item.lng) {

                $('#google-map').each(function() {
                    var map = leaflet.map(this).setView([self.item.lat, self.item.lng], 17);
                    leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
                    }).addTo(map);

                    leaflet.marker([self.item.lat, self.item.lng]).addTo(map);
                });
            }

            Acl.inheritsRole('moder').then(function(isModer: boolean) {
                self.museumModer = isModer;
            }, function() {
                self.museumModer = false;
            });

            self.$http({
                method: 'GET',
                url: '/api/picture',
                params: {
                    status: 'accepted',
                    exact_item_id: self.item.id,
                    fields: 'owner,thumb_medium,votes,views,comments_count,name_html,name_text',
                    limit: 20,
                    order: 12
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.pictures = response.data.pictures;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });

        }, function() {
            $state.go('error-404');
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, MuseumController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/museums/:id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);

