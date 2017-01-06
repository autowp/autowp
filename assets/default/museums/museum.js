var $ = require("jquery");
var ol = require("openlayers");

module.exports = {
    init: function(options) {
        if (options.lat && options.lng) {
            
            $('#google-map').each(function() {
                
                $(this).css({
                    width: '100%',
                    height: '300px',
                    margin: '0 0 40px'
                });
                
                var center = ol.proj.fromLonLat([options.lng, options.lat]);
                
                var map = new ol.Map({
                    target: this,
                    layers: [
                        new ol.layer.Tile({
                            source: new ol.source.OSM()
                        })
                    ],
                    view: new ol.View({
                        center: center,
                        zoom: 17
                    })
                });
                
                var vectorLayer = new ol.layer.Vector();
                map.addLayer(vectorLayer);
                
                var iconFeature = new ol.Feature({
                    geometry: new ol.geom.Point(center)
                });
                
                iconFeature.setStyle(require('map/icon-style'));
                
                var vectorSource = new ol.source.Vector({
                    features: [
                        iconFeature
                    ]
                });

                vectorLayer.setSource(vectorSource);
            });
        }
    }
};
