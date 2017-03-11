define(
    ['jquery', 'chart'],
    function($, ChartJS) {
        return {
            init: function(options) {
                var $chart = $('.chart');
                
                this.chart = new ChartJS($chart[0], {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: []
                    }
                });
                
                var self = this;
                
                $('.nav-pills a').on('click', function(e) {
                    e.preventDefault();
                    self.loadData($(this).data('id'));
                    $('.nav-pills li').removeClass('active');
                    $(this).parent().addClass('active');
                });
                
                $('.nav-pills a').first().click();
            },
            loadData: function(id) {
                var self = this;
                
                var colors = [
                    "rgba(41,84,109,1)",
                    "rgba(242,80,122,1)",
                ];
                
                $.get('/chart/years-data', {id: id}, function(json) {
                    
                    var datasets = [];
                    $.map(json.datasets, function(dataset, i) {
                        datasets.push({
                            label: dataset.name,
                            fill: false,

                            // String - the color to fill the area under the line with if fill is true
                            backgroundColor: "rgba(220,220,220,1)",

                            // String or array - Line color
                            borderColor: colors[i % colors.length],

                            // String - cap style of the line. See https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D/lineCap
                            borderCapStyle: 'butt',

                            // Array - Length and spacing of dashes. See https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D/setLineDash
                            borderDash: [],

                            // Number - Offset for line dashes. See https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D/lineDashOffset
                            borderDashOffset: 0.0,

                            // String - line join style. See https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D/lineJoin
                            borderJoinStyle: 'miter',

                            // String or array - Point stroke color
                            pointBorderColor: "rgba(220,220,220,1)",

                            // String or array - Point fill color
                            pointBackgroundColor: "#fff",

                            // Number or array - Stroke width of point border
                            pointBorderWidth: 1,

                            // Number or array - Radius of point when hovered
                            pointHoverRadius: 5,

                            // String or array - point background color when hovered
                            pointHoverBackgroundColor: "rgba(220,220,220,1)",

                            // Point border color when hovered
                            pointHoverBorderColor: "rgba(220,220,220,1)",

                            // Number or array - border width of point when hovered
                            pointHoverBorderWidth: 2,

                            // Tension - bezier curve tension of the line. Set to 0 to draw straight Wlines connecting points
                            tension: 0.5,

                            // The actual data
                            data: dataset.values
                        });
                    });
                    
                    var data = {
                        labels: json.years,
                        datasets: datasets
                    };
                    
                    self.chart.chart.config.data = data;
                    
                    self.chart.update();
                    
                });
            }
        };
    }
);