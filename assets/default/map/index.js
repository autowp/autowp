var $ = require("jquery");
var ol = require("openlayers");
var markerSrc = require("img/map-marker-icon.png");
var popupMarkup = require('./popup.html');
require("./index.less");

module.exports = {
    init: function(options) {
        this.currentPopup = null;
        this.googlemaps = null;
        
        this.xhrTimeout = null;
        
        var self = this;
            
        $('#google-map').each(function() {
            
            self.map = new ol.Map({
                target: this,
                layers: [
                    new ol.layer.Tile({
                        source: new ol.source.OSM()
                        /*visible: true,
                        preload: Infinity,
                        source: new ol.source.BingMaps({
                            key: 'Aid2FAZU17lX3ZNTeW7Q-SuqQ3K8h2W6BLpgomM2mQB-lLAXWBakYm9bbjTlv4gX',
                            imagerySet: 'Road'
                            // use maxZoom 19 to see stretched tiles instead of the BingMaps
                            // "no photos at this zoom level" tiles
                            // maxZoom: 19
                        })*/
                    })
                ],
                view: new ol.View({
                    center: ol.proj.fromLonLat([13.45, 52.48]),
                    zoom: 4,
                    //minZoom: 4
                })
            });
            
            self.vectorLayer = new ol.layer.Vector();
            self.map.addLayer(self.vectorLayer);
            
            self.iconStyle = require('map/icon-style');
            
            self.map.on('moveend', function() {
                self.queueLoadData();
            });
            
            self.map.on('zoomend', function() {
                self.queueLoadData();
            });
            
            self.map.on("pointermove", function (evt) {
                var hit = this.forEachFeatureAtPixel(evt.pixel, function (feature, layer) {
                    return true;
                }); 
                if (hit) {
                    this.getTarget().style.cursor = 'pointer';
                } else {
                    this.getTarget().style.cursor = '';
                }
            });
            
            self.map.on("click", function(e) {
                self.map.forEachFeatureAtPixel(e.pixel, function (feature, layer) {
                    
                    self.closePopup();
                    
                    var factory = feature.get('place');
                    
                    var element = self.popupHtml(factory);
                    var point = ol.proj.fromLonLat([factory.location.lng, factory.location.lat]);
                    
                    var popup = new ol.Overlay({
                        element: element
                    });
                    popup.setPosition(point);
                    self.map.addOverlay(popup);
                    
                    self.currentPopup = popup;
                });
            });
            
            /*
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    var latLng = new googlemaps.LatLng(position.coords.latitude, position.coords.longitude);
                    var geocoder = new googlemaps.Geocoder();
                    geocoder.geocode({'latLng': latLng}, function(results, status) {
                        if (status == googlemaps.GeocoderStatus.OK) {
                            var country = null;
                            $.each(results, function(i, address) {
                                $.each(address.address_components, function(j, component) {
                                    $.each(component.types, function(k, type) {
                                        if (type == 'country') {
                                            country = component.long_name;
                                        }
                                        if (country) {
                                            return false;
                                        }
                                    });
                                    if (country) {
                                        return false;
                                    }
                                });
                                if (country) {
                                    return false;
                                }
                            });
                            
                            if (country) {
                                geocoder.geocode({'address': country}, function(results, status) {
                                    $.each(results, function(i, address) {
                                        self.map.fitBounds(address.geometry.viewport);
                                        self.loadData();
                                        return false;
                                    });
                                });
                            }
                        }
                    });
                    
                });
            }*/
        });
    },
    queueLoadData: function() {
        if (this.xhrTimeout) {
            clearTimeout(this.xhrTimeout);
            this.xhrTimeout = null;
        }
        var self = this;
        this.xhrTimeout = setTimeout(function() {
            self.loadData();
        }, 300);
    },
    loadData: function() {
        var extent = this.map.getView().calculateExtent(this.map.getSize());
        extent = ol.proj.transformExtent(extent, 'EPSG:3857', 'EPSG:4326');
        
        var params = {
            bounds: extent.join(',')
        };
        var self = this;
        $.getJSON('/map/data', params, function(data) {
            self.renderData(data);
        });
        
        self.closePopup();
    },
    renderData: function(data) {
        var self = this;
        
        var features = [];
        
        $.each(data, function(key, factory) {
            if (factory.location) {
                var point = ol.proj.fromLonLat([factory.location.lng, factory.location.lat]);

                var iconFeature = new ol.Feature({
                    geometry: new ol.geom.Point(point),
                    place: factory
                });
                
                iconFeature.setStyle(self.iconStyle);
                features.push(iconFeature);
            }
        });
        
        var vectorSource = new ol.source.Vector({
            features: features
        });

        this.vectorLayer.setSource(vectorSource);
    },
    popupHtml: function(factory) {
        
        var $element = $(popupMarkup);

        $element.find('h5').text(factory.name);
        $element.find('.details a').attr('href', factory.url);
        $element.find('.desc').html(factory.desc);
        
        if (factory.image) {
            $element.find('h5').after($('<p />').append($('<img />').attr('src', factory.image)));
        }
        
        return $element[0];
    },
    closePopup: function() {
        if (this.currentPopup) {
            this.map.removeOverlay(this.currentPopup);
            this.currentPopup = null;
        }
    }
};