define(
    'moder/museum/edit',
    ['jquery', 'googlemaps', 'tinymce'],
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

                $('#address').change(function() {
                    $.getJSON(options.addressToLatLngUrl, {address: $(this).val()}, function(point) {
                        if (point) {
                            var latLng = new googlemaps.LatLng(point.lat, point.lng);
                            setMarkerPoint(latLng);
                            $('#lat').val(point.lat);
                            $('#lng').val(point.lng);
                        }
                    });
                });
                
                $('textarea#description').tinymce({
                    // Location of TinyMCE script
                    script_url : '/tiny_mce/tinymce.min.js',

                    plugins: [
                        "link"
                    ],

                    // General options
                    theme: "modern",

                    menubar: false,
                    toolbar: "undo redo link unlink",
                    resize: true,

                    // Example content CSS (should be your site CSS)
                    //content_css : "/css/styles.css",

                    object_resizing : false,
                    convert_fonts_to_spans : true,
                    force_p_newlines : true,
                    //remove_trailing_nbsp : true,
                    //trim_span_elements : true,
                    valid_elements : "a[href|title],p",
                    language: "en"
                });
            }
        }
    }
);