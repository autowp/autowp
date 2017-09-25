import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

var $ = require('jquery');
var ChartJS = require('chart');

const CONTROLLER_NAME = 'ChartController';
const STATE_NAME = 'chart';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/chart',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                pageId: 1
            });
            
            var ctrl = this;
            
            ctrl.parameters = [];
            
            var $chart = $('.chart');
            
            var chart = new ChartJS($chart[0], {
                type: 'line',
                data: {
                    labels: [],
                    datasets: []
                }
            });
            
            $http({
                method: 'GET',
                url: '/api/chart/parameters'
            }).then(function(response) {
                ctrl.parameters = response.data.parameters;
                ctrl.selectParam(ctrl.parameters[0]);
            }, function(response) {
                notify.response(response);
            });
            
            function loadData(id) {
                var colors = [
                    "rgba(41,84,109,1)",
                    "rgba(242,80,122,1)",
                ];
                
                $http({
                    method: 'GET',
                    url: '/api/chart/data',
                    params: {id: id}
                }).then(function(response) {
                    var datasets = [];
                    $.map(response.data.datasets, function(dataset, i) {
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
                        labels: response.data.years,
                        datasets: datasets
                    };
                    
                    chart.chart.config.data = data;
                    
                    chart.update();
                }, function(response) {
                    notify.response(response);
                });
            }
            
            ctrl.selectParam = function(param) {
                angular.forEach(ctrl.parameters, function(param) {
                    param.active = false;
                });
                param.active = true;
                loadData(param.id);
            };
        }
    ]);

export default CONTROLLER_NAME;
