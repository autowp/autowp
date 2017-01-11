var ol = require("openlayers");
var markerSrc = require("img/map-marker-icon.png");

module.exports = new ol.style.Style({
    image: new ol.style.Icon({
        anchor: [0.5, 1],
        anchorXUnits: 'fraction',
        anchorYUnits: 'fraction',
        src: markerSrc,
        scale: [0.05]
    })
});