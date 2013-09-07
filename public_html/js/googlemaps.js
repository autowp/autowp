define(
    'googlemaps',
    ['async!http://maps.googleapis.com/maps/api/js?sensor=false'],
    function() {
        // return the gmaps namespace for brevity
        return window.google.maps;
    }
);
