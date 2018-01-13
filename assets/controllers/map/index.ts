import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import * as $ from "jquery";

const CONTROLLER_NAME = 'MapController';
const STATE_NAME = 'map';

require("./styles.less");

require('leaflet-webgl-heatmap/src/webgl-heatmap/webgl-heatmap');
require('leaflet-webgl-heatmap/dist/leaflet-webgl-heatmap.min');

var leaflet = require("leaflet-bundle");
var popupMarkup = require('./popup.html'); 

export class MapController {
    static $inject = ['$scope', '$http', '$state', '$q'];

    private canceler: ng.IDeferred<{}> | undefined;
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService, 
        private $state: any, 
        private $q: ng.IQService
    ) {
        var self = this;
            
        var map: any = null;
        var heatmap: any = null;
        
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            disablePageName: true,
            name: 'page/117/name',
            pageId: 117
        });
        
        var currentPopup: any = null;
        
        var xhrTimeout: any = null;
        
        var markers: any[] = [];
        
        var zoomStarted: boolean = false;
            
        $('#google-map').each(function() {
            
            var defaultZoom = 4;
            
            map = leaflet.map(this).setView([50, 20], defaultZoom);
            map.on('zoom', function() {
                //console.log('viewreset');
            }); 
            
            map.on('zoomstart', function() {
                zoomStarted = true;
                if (self.canceler) {
                    self.canceler.resolve();
                    self.canceler = undefined;
                }
            });
            map.on('zoomend', function() {
                zoomStarted = false;
                queueLoadData(map.getZoom());
                //heatmap.setSize(zoomToSize(map.getZoom()));
            });
            
            map.on('moveend', function() {
                queueLoadData(map.getZoom());
            });
            
            leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>',
                //maxZoom: 18
            }).addTo(map);
            
            heatmap = new leaflet.webGLHeatmap({
                size: zoomToSize(defaultZoom),
                opacity: 0.5,
                alphaRange: 0.5
            });
            heatmap.addTo(map);
            
            queueLoadData(defaultZoom);
        });
        
        function zoomToSize(zoom: number) {
            return 2000000 / zoom;
        }
        
        function queueLoadData(zoom: number) {
            if (xhrTimeout) {
                clearTimeout(xhrTimeout);
                xhrTimeout = null;
            }

            xhrTimeout = setTimeout(function() {
                if (! zoomStarted) {
                    loadData(zoom);
                }
            }, 100);
        }
        
        function isHeatmap(zoom: number) {
            return zoom < 6;
        }
        
        function loadData(zoom: number) {
            if (self.canceler) {
                self.canceler.resolve();
                self.canceler = undefined;
            }
            
            var params = { 
                bounds: map.getBounds().pad(0.1).toBBoxString(),
                'points-only': isHeatmap(zoom) ? 1 : 0
            };
            
            self.canceler = $q.defer();
            
            $http({
                method: 'GET',
                url: '/api/map/data',
                params: params,
                timeout: self.canceler.promise
            }).then(function(response: ng.IHttpResponse<any>) {
                if (map.getZoom() == zoom) {
                    renderData(response.data, zoom);
                }
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
            
            closePopup();
        }
        
        function renderData(data: any, zoom: number) {
            $.map(markers, function(marker) {
                marker.remove(null);
            });
            markers = [];
            
            var points: any[] = [];
            
            var zoomIsHeatmap = isHeatmap(zoom);
            
            $.each(data, function(key: any, factory: any) {
                if (factory.location) {
                    
                    if (zoomIsHeatmap) {
                        points.push([
                            factory.location.lat,
                            factory.location.lng,
                            1
                        ]);
                    } else {
                        var marker = leaflet.marker([factory.location.lat, factory.location.lng]).addTo(map);
                        
                        var element = popupHtml(factory);
                        
                        marker.bindPopup(element);
                        
                        markers.push(marker);
                    }
                }
            });
            
            if (zoomIsHeatmap) {
                heatmap.setData(points);
                heatmap.addTo(map);
            } else {
                heatmap.remove();
            }
        }
        
        function popupHtml(factory: any) {
            
            var $element: JQuery = $(popupMarkup);

            $element.find('h5').text(factory.name);
            $element.find('.details a').attr('href', factory.url);
            $element.find('.desc').html(factory.desc);
            
            if (factory.image) {
                $element.find('h5').after($('<p />').append($('<img />').attr('src', factory.image)));
            }
            
            return $element[0];
        }
        
        function closePopup() {
            if (currentPopup) {
                map.removeOverlay(currentPopup);
                currentPopup = null;
            }
        }
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, MapController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/map',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);

