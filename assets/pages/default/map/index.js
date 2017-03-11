var $ = require("jquery");
var leaflet = require("leaflet");
require("leaflet/dist/leaflet.css");
var popupMarkup = require('./popup.html'); 
require("./index.less");

require('webgl-heatmap-leaflet/dist/webgl-heatmap');
require('webgl-heatmap-leaflet/dist/webgl-heatmap-leaflet');

delete leaflet.Icon.Default.prototype._getIconUrl;
leaflet.Icon.Default.mergeOptions({
    iconRetinaUrl: require('leaflet/dist/images/marker-icon-2x.png'),
    iconUrl: require('leaflet/dist/images/marker-icon.png'),
    shadowUrl: require('leaflet/dist/images/marker-shadow.png'),
});

module.exports = {
    init: function(options) {
        this.currentPopup = null;
        this.googlemaps = null;
        
        this.xhrTimeout = null;
        
        var self = this;
        
        this.markers = [];
        
        this.zoomStarted = false;
            
        $('#google-map').each(function() {
            
            var defaultZoom = 4;
            
            self.map = leaflet.map(this).setView([50, 20], defaultZoom);
            self.map.on('zoom', function() {
                //console.log('viewreset');
            }); 
            
            self.map.on('zoomstart', function() {
                self.zoomStarted = true;
                if (self.xhr) {
                    self.xhr.abort();
                    self.xhr = null;
                }
            });
            self.map.on('zoomend', function() {
                self.zoomStarted = false;
                self.queueLoadData(self.map.getZoom());
                self.heatmap.setSize(self.zoomToSize(self.map.getZoom()));
            });
            
            self.map.on('moveend', function() {
                self.queueLoadData(self.map.getZoom());
            });
            
            leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>',
                //maxZoom: 18
            }).addTo(self.map);
            
            self.heatmap = new leaflet.webGLHeatmap({
                size: self.zoomToSize(defaultZoom),
                opacity: 0.5,
                alphaRange: 0.5
            });
            self.heatmap.addTo(self.map);
            
            self.queueLoadData(defaultZoom);
        });
    },
    zoomToSize: function(zoom) {
        return 2000000 / zoom;
    },
    queueLoadData: function(zoom) {
        if (this.xhrTimeout) {
            clearTimeout(this.xhrTimeout);
            this.xhrTimeout = null;
        }
        var self = this;
        this.xhrTimeout = setTimeout(function() {
            if (! self.zoomStarted) {
                self.loadData(zoom);
            }
        }, 100);
    },
    isHeatmap: function(zoom) {
        return zoom < 6;
    },
    loadData: function(zoom) {
        if (this.xhr) {
            this.xhr.abort();
            this.xhr = null;
        }
        
        var params = { 
            bounds: this.map.getBounds().pad(0.1).toBBoxString(),
            'points-only': this.isHeatmap(zoom) ? 1 : 0
        };
        var self = this;
        this.xhr = $.getJSON('/map/data', params, function(data) {
            if (self.map.getZoom() == zoom) {
                self.renderData(data, zoom);
            }
        });
        
        self.closePopup();
    },
    renderData: function(data, zoom) {
        var self = this;
        
        $.map(this.markers, function(marker) {
            marker.remove(null);
        });
        self.markers = [];
        
        var points = [];
        
        var isHeatmap = this.isHeatmap(zoom);
        
        $.each(data, function(key, factory) {
            if (factory.location) {
                
                if (isHeatmap) {
                    points.push([
                        factory.location.lat,
                        factory.location.lng,
                        1
                    ]);
                } else {
                    var marker = leaflet.marker([factory.location.lat, factory.location.lng]).addTo(self.map);
                    
                    var element = self.popupHtml(factory);
                    
                    marker.bindPopup(element);
                    
                    self.markers.push(marker);
                }
            }
        });
        
        if (isHeatmap) {
            this.heatmap.setData(points);
            this.heatmap.addTo(this.map);
        } else {
            this.heatmap.remove();
        }
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