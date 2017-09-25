import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

const CONTROLLER_NAME = 'MapController';
const STATE_NAME = 'map';

require("./styles.less");

require('leaflet-webgl-heatmap/src/webgl-heatmap/webgl-heatmap');
require('leaflet-webgl-heatmap/dist/leaflet-webgl-heatmap.min');

var $ = require("jquery");
var leaflet = require("leaflet-bundle");
var popupMarkup = require('./popup.html'); 

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/map',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', '$q',
        function($scope, $http, $state, $q) {
            
            var ctrl = this;
            
            var map = null;
            var heatmap = null;
            
            var canceler = null;
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                pageId: 117
            });
            
            var currentPopup = null;
            
            var xhrTimeout = null;
            
            var markers = [];
            
            var zoomStarted = false;
                
            $('#google-map').each(function() {
                
                var defaultZoom = 4;
                
                map = leaflet.map(this).setView([50, 20], defaultZoom);
                map.on('zoom', function() {
                    //console.log('viewreset');
                }); 
                
                map.on('zoomstart', function() {
                    zoomStarted = true;
                    if (canceler) {
                        canceler.resolve();
                        canceler = null;
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
            
            function zoomToSize(zoom) {
                return 2000000 / zoom;
            }
            
            function queueLoadData(zoom) {
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
            
            function isHeatmap(zoom) {
                return zoom < 6;
            }
            
            function loadData(zoom) {
                if (canceler) {
                    canceler.resolve()
                    canceler = null;
                }
                
                var params = { 
                    bounds: map.getBounds().pad(0.1).toBBoxString(),
                    'points-only': isHeatmap(zoom) ? 1 : 0
                };
                
                var canceler = $q.defer();
                
                $http({
                    method: 'GET',
                    url: '/api/map/data',
                    params: params,
                    timeout: canceler.promise
                }).then(function(response) {
                    if (map.getZoom() == zoom) {
                        renderData(response.data, zoom);
                    }
                }, function(response) {
                    notify.response(response);
                });
                
                closePopup();
            }
            
            function renderData(data, zoom) {
                $.map(markers, function(marker) {
                    marker.remove(null);
                });
                markers = [];
                
                var points = [];
                
                var zoomIsHeatmap = isHeatmap(zoom);
                
                $.each(data, function(key, factory) {
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
            
            function popupHtml(factory) {
                
                var $element = $(popupMarkup);

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
    ]);

export default CONTROLLER_NAME;
