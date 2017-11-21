import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import * as $ from 'jquery';

var ChartJS = require('chart');

const CONTROLLER_NAME = 'ChartController';
const STATE_NAME = 'chart';

export class ChartController {
    static $inject = ['$scope', '$http', '$state'];
  
    public parameters: any[] = [];
    private chart: any;
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService, 
        private $state: any
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/1/name',
            title: 'page/1/title',
            pageId: 1
        });
        
        var $chart = $('.chart');
        
        this.chart = new ChartJS($chart[0], {
            type: 'line',
            data: {
                labels: [],
                datasets: []
            }
        });
        
        var self = this;
        $http({
            method: 'GET',
            url: '/api/chart/parameters'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.parameters = response.data.parameters;
            self.selectParam(self.parameters[0]);
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
  
    private loadData(id: number) {
        var colors = [
            "rgba(41,84,109,1)",
            "rgba(242,80,122,1)",
        ];
      
        var self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/chart/data',
            params: {id: id}
        }).then(function(response: ng.IHttpResponse<any>) {
            var datasets: any[] = [];
            $.map(response.data.datasets, function(dataset: any, i: number) {
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
            
            self.chart.chart.config.data = data;
            
            self.chart.update();
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
  
    public selectParam(param: any) {
        angular.forEach(this.parameters, function(param: any) {
            param.active = false;
        });
        param.active = true;
        this.loadData(param.id);
    };
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ChartController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/chart',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);

