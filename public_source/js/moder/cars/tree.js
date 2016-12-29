define(
    ['jquery', 'bootstrap'],
    function($) {
        return {
            init: function(options) {
                
                this.addParentUrl = options.addParentUrl;
                this.removeParentUrl = options.removeParentUrl;

                var self = this,
                    map = {};
                $('#add-parent-search')
                    .typeahead({
                        minLength: 1,
                        items: 20,
                        showHintOnFocus: true,
                        source: function(query, process) {
                            $.getJSON('/moder/cars/car-autocomplete', {q: query}, function(cars) {
                                var lines = [];
                                map = {};
                                $.map(cars, function(name, id) {
                                    lines.push(name);
                                    map[name] = id;
                                });
                                
                                process(lines);
                            });
                        },
                        matcher: function(item) {
                            return true;
                        },
                        updater: function(item) {
                            if (map[item]) {
                                var id = map[item];
                                self.postAddParent(id);
                            }
                            
                            return item;
                        },
                        sorter: function(items) {
                            return items;
                        }
                    })
                    .on('keyup', function(e) {
                        if (e.which == 13) {
                            var value = $(this).val();
                            if (map[value]) {
                                var id = map[value];
                                
                                self.postAddParent(id);
                            }
                        }
                    });
                
                $('.remove-parent').on('click', function(e) {
                    e.preventDefault();
                    
                    if (window.confirm("Are you sure?")) {
                        $.post(self.removeParentUrl, {parent_id: $(this).data('id')}, function() {
                            window.location = window.location;
                        });
                    }
                });
                
                this.renderGraph(options.graphItems, options.graphLinks);
            },
            postAddParent: function(parentId) {
                $.post(this.addParentUrl, {parent_id: parentId}, function() {
                    window.location = window.location;
                });
            },
            renderGraph: function(items, links) {
                var redraw;
                var $element = $('#graph');
                var height = $element.height();
                var width = $element.width();
                var nodes = [];
                var edges = [];

                var g = new Graph();
                g.edgeFactory.template.style.directed = true;
                
                $.map(items, function(item, id) {
                    var node = g.addNode(id, { label : item });
                    nodes.push(node);
                });
                
                $.map(links, function(link) {
                    g.addEdge(link.parent_id, link.car_id, { directed : true } );
                });


                /* layout the graph using the Spring layout implementation */
                var layouter = new Graph.Layout.Spring(g);
                layouter.layout();
                
                /* draw the graph using the RaphaelJS draw implementation */
                var renderer = new Graph.Renderer.Raphael('graph', g, width, height);
                renderer.draw();
                
                redraw = function() {
                    layouter.layout();
                    renderer.draw();
                };

            }
        };
    }
);