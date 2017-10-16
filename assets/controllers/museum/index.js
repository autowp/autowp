import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';
import ACL_SERVICE_NAME from 'services/acl';

var $ = require("jquery");
var leaflet = require("leaflet-bundle");

const CONTROLLER_NAME = 'MuseumController';
const STATE_NAME = 'museum';

function chunkBy(arr, count) {
    var newArr = [];
    var size = Math.ceil(count);
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
                url: '/museums/:id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', ACL_SERVICE_NAME,
        function($scope, $http, $state, Acl) {
            
            var ctrl = this;
            
            ctrl.museumModer = false;
            ctrl.links = [];
            ctrl.chunks = [];

            $http({
                method: 'GET',
                url: '/api/item/' + $state.params.id,
                params: {
                    fields: ['name_text', 'lat', 'lng', 'description'].join(',')
                }
            }).then(function(response) {
                
                ctrl.item = response.data;
                
                if (ctrl.item.item_type_id != 7) {
                    $state.go('error-404');
                }
            
                $scope.pageEnv({
                    layout: {
                        blankPage: false,
                        needRight: true
                    },
                    name: 'page/159/name',
                    pageId: 159,
                    args: {
                        MUSEUM_ID: ctrl.item.id,
                        MUSEUM_NAME: ctrl.item.name_text
                    }
                });
                
                $http({
                    method: 'GET',
                    url: '/api/item-link',
                    params: {
                        item_id: ctrl.item.id
                    }
                }).then(function(response) {
                    ctrl.links = response.data.items;
                }, function(response) {
                    notify.response(response);
                });
                
                if (ctrl.item.lat && ctrl.item.lng) {
                    
                    $('#google-map')
                        .css({
                            width: '100%',
                            height: '300px',
                            margin: '0 0 40px'
                        })
                        .each(function() {
                            
                            var map = leaflet.map(this).setView([ctrl.item.lat, ctrl.item.lng], 17);
                            leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
                            }).addTo(map);
                          
                            leaflet.marker([ctrl.item.lat, ctrl.item.lng]).addTo(map);
                        });
                }
                
                Acl.inheritsRole('moder').then(function(isModer) {
                    ctrl.museumModer = isModer;
                }, function() {
                    ctrl.museumModer = false;
                });
                
                $http({
                    method: 'GET',
                    url: '/api/picture',
                    params: {
                        status: 'accepted',
                        item_id: ctrl.item.id,
                        fields: 'owner,thumbnail,votes,views,comments_count,name_html,name_text',
                        limit: 20,
                        order: 12
                    }
                }).then(function(response) {
                    ctrl.pictures = response.data.pictures;
                    ctrl.chunks = chunkBy(ctrl.pictures, 4);
                }, function(response) {
                    notify.response(response);
                });
                
            }, function() {
                $state.go('error-404');
            });
            
            
        }
    ]);

export default CONTROLLER_NAME;
