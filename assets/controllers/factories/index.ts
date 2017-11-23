import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import * as $ from "jquery";
import { chunkBy } from 'chunk';
var leaflet = require("leaflet-bundle");

import './items';

const CONTROLLER_NAME = 'FactoryController';
const STATE_NAME = 'factory';

export class FactoryController {
    static $inject = ['$scope', '$http', '$state'];
    public factory: any;
    public pictures: any[];
    public relatedPictures: any[];
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $state: any
    ) {
      
        var self = this;
      
        $http({
            method: 'GET',
            url: '/api/item/' + this.$state.params.id,
            params: {
                fields: ['name_text', 'name_html', 'lat', 'lng', 'description', 'related_group_pictures'].join(',')
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            
            self.factory = response.data;
            
            self.relatedPictures = chunkBy(self.factory.related_group_pictures, 4);
            
            if (self.factory.item_type_id != 6) {
                self.$state.go('error-404');
                return;
            }
  
            self.$scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: true
                },
                name: 'page/181/name',
                pageId: 181,
                args: {
                    FACTORY_ID: self.factory.id,
                    FACTORY_NAME: self.factory.name_text
                }
            });
          
            if (self.factory.lat && self.factory.lng) {
                
                $('#google-map').each(function() {
                    
                    var map = leaflet.map(this).setView([self.factory.lat, self.factory.lng], 17);
                    leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
                    }).addTo(map);
                  
                    leaflet.marker([self.factory.lat, self.factory.lng]).addTo(map);
                });
            }
          
            self.$http({
                method: 'GET',
                url: '/api/picture',
                params: {
                    status: 'accepted',
                    item_id: self.factory.id,
                    limit: 32,
                    fields: 'owner,thumbnail,votes,views,comments_count,name_html,name_text'
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.pictures = chunkBy(response.data.pictures, 4);
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
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

