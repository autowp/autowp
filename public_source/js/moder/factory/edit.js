define(
    ['jquery', 'googlemaps'],
    function($, googlemaps) {
        return {
            init: function(options) {
                var startPosition = new googlemaps.LatLng(55.7423627, 37.6786422);
                var lat = parseFloat($('#lat').val());
                var lng = parseFloat($('#lng').val());
                if (lng && lat) {
                    startPosition = new googlemaps.LatLng(lat, lng);
                }

                var node = $('<div style="width:100%; height: 300px" />').insertAfter($('#lng'))[0];
                var marker = null;

                var map = new googlemaps.Map(node, {
                    zoom: 2,
                    center: startPosition,
                    mapTypeId: googlemaps.MapTypeId.ROADMAP
                });

                if (lng && lat) {
                    setMarkerPoint(startPosition);
                }

                function setMarkerPoint(latLng) {
                    if (marker) {
                        marker.setPosition(latLng);
                    } else {
                        marker = new googlemaps.Marker({
                            position: latLng,
                            map: map
                        });
                    }
                }

                function clearMarker() {
                    if (marker) {
                        marker.setMap(null);
                        marker = null;
                    }
                }

                $('#lng, #lat').change(function() {
                    var lat = parseFloat($('#lat').val());
                    var lng = parseFloat($('#lng').val());
                    if (lng && lat) {
                        var point = new googlemaps.LatLng(lat, lng);
                        setMarkerPoint(point);
                    } else {
                        clearMarker();
                    }
                });

                googlemaps.event.addListener(map, 'click', function(event) {
                    setMarkerPoint(event.latLng);
                    $('#lng').val(event.latLng.lng());
                    $('#lat').val(event.latLng.lat());
                });
            }
        };
    }
);