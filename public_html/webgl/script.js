$(function() {
    var raf = (
        window.requestAnimationFrame ||
        window.mozRequestAnimationFrame ||
        window.webkitRequestAnimationFrame ||
        window.oRequestAnimationFrame
    );
    var canvas = $('canvas');
    try{
        var heatmap = createWebGLHeatmap({canvas: canvas[0]});
    }
    catch(error){
        $('#heatmap-example').empty();
        $('<div class="error"></div>').text(error).appendTo('#heatmap-example');
        return;
    }
           
    var paintAtCoord = function(x, y){
        var i = 0;
        while(i < count){
            var xoff = Math.random()*2-1;
            var yoff = Math.random()*2-1;
            var l = xoff*xoff + yoff*yoff;
            if(l > 1){
                continue;
            }
            var ls = Math.sqrt(l);
            xoff/=ls; yoff/=ls;
            xoff*=1-l; yoff*=1-l;
            i += 1;
            heatmap.addPoint(x+xoff*spread, y+yoff*spread, size, intensity/1000);
        }
    };

    canvas
        .mousemove(function(event){
            console.log('mousemove');
            var offset = canvas.offset();
            var x = event.pageX - offset.left;
            var y = event.pageY - offset.top;
            paintAtCoord(x, y);
        })
        .click(function(){
            heatmap.clear();
        })
        
    var onTouchMove = function(evt) {
        evt.preventDefault();
        var touches = evt.changedTouches;
        var offset = canvas.offset();
        for(var i=0; i<touches.length; i++){
            var touch = touches[i];
            var x = touch.pageX - offset.left;
            var y = touch.pageY - offset.top;
            paintAtCoord(x, y);
        }
    };
    canvas[0].addEventListener("touchmove", onTouchMove, false);

    var count = 180;
    var size = 25;
    var intensity = 5;
    var spread = 100;
    var decay = 1;

    var over = false;
    $(canvas).hover(function() {
        over=true;
    }, function(){
        over=false
    });

    var update = function(){
        if(over){
            heatmap.adjustSize(); // can be commented out for statically sized heatmaps, resize clears the map
            heatmap.update(); // adds the buffered points
            heatmap.multiply(1-decay/(100*100));
            heatmap.display(); // draws the heatmap to the canvas

            //heatmap.blur();
            //heatmap.clamp(0.0, 1.0); // depending on usecase you might want to clamp it
        }
        raf(update);
    }
    raf(update);
});