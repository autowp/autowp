require(['jquery', 'sigma', 'sigma/layout.forceAtlas2'], function() {
    
    var scene = new sigma({
        container: 'network',
        settings: {
            defaultNodeColor: '#ec5148'
            //doubleClickEnabled: false
        }
    });
    
    function loadCar(id) {
        $.get('/moder/graph/data', {id: id}, function(json) {
            $.map(json.nodes, function(node) {
                var n = scene.graph.nodes(node.id);
                if (!n) {
                    scene.graph.addNode(node);
                }
            });
            $.map(json.edges, function(edge) {
                var n = scene.graph.edges(edge.id);
                if (!n) {
                    scene.graph.addEdge(edge);
                }
            });
            //scene.graph.read(json);
            scene.refresh();
        });
    }
    
    loadCar(76815);
    
    scene.bind('clickNode', function(e) {
        loadCar(e.data.node.id.replace('car', ''));
    });
    
    scene.startForceAtlas2();
});