import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import * as $ from 'jquery';
var Raphael = require('raphael');

const CONTROLLER_NAME = 'PulseController';
const STATE_NAME = 'pulse';

Raphael.fn.drawGrid = function (x: number, y: number, w: number, h: number, wv: number, hv: number, color: string) {
    color = color || "#000";
    var path = ["M", Math.round(x) + 0.5, Math.round(y) + 0.5, "L", Math.round(x + w) + 0.5, Math.round(y) + 0.5, Math.round(x + w) + 0.5, Math.round(y + h) + 0.5, Math.round(x) + 0.5, Math.round(y + h) + 0.5, Math.round(x) + 0.5, Math.round(y) + 0.5],
        rowHeight = h / hv,
        columnWidth = w / wv;
    for (var i = 1; i < hv; i++) {
        path = path.concat(["M", Math.round(x) + 0.5, Math.round(y + i * rowHeight) + 0.5, "H", Math.round(x + w) + 0.5]);
    }
    for (i = 1; i < wv; i++) {
        path = path.concat(["M", Math.round(x + i * columnWidth) + 0.5, Math.round(y) + 0.5, "V", Math.round(y + h) + 0.5]);
    }
    return this.path(path.join(",")).attr({stroke: color});
};

export class PulseController {
    static $inject = ['$scope', '$http', '$state'];
    public map: any = {};
    public legend: any;
    public grid: any;
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService, 
        private $state: any
    ) {
        var self = this;
            
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/161/name',
            pageId: 161
        });
        
        this.$http({
            method: 'GET',
            url: '/api/pulse'
        }).then(function(response: ng.IHttpResponse<any>) {
            
            self.legend = response.data.legend;
            self.grid = response.data.grid;
          
            self.render();
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
  
    private render()
    {
        var self = this;
      
        $('#pulse-graph').each(function() {
            
            var $element = $(this);
            
            var values = self.grid;
            
            // Grab the data
            var //labels = [],
                maxes: any[] = [],
                lines: any[] = [];
            
            $.each(values, function(userId, info) {
                //labels = [];
                var line: any[] = [];
                $.each(info.line, function(date, value) {
                    //labels.push(date);
                    line.push(value);
                });
                
                lines.push({
                    userId: userId,
                    line: line,
                    color: info.color
                });
                
                maxes.push(Math.max.apply(Math, line));
            });
            
            var max = Math.max.apply(Math, maxes);
            // Draw
            var width = $element.width() as number,
                height = $element.height() as number,
                leftgutter = 30,
                bottomgutter = 50,
                topgutter = 20,
                r = Raphael(this, width, height),
                labelsCount = lines[0].line.length,
                X = (width - leftgutter) / labelsCount,
                Y = (height - bottomgutter - topgutter) / max;
            
            r.drawGrid(
                leftgutter + X * 0.5 + 0.5, 
                topgutter + 0.5, 
                width - leftgutter - X, 
                height - topgutter - bottomgutter, 
                labelsCount-1, 
                10, 
                "#000"
            );
            
            var columnWidth = (width - leftgutter - X) / (labelsCount-1);

            $.map(lines, function(line: any) {
                
                var rects = [];
                
                var data = line.line;
                
                var color = line.color;
                
                var cWidth = columnWidth;
                for (var i = 0, ii = labelsCount; i < ii; i++) {
                    var value = data[i],
                        cHeight = Y * value,
                        y = Math.round(height - bottomgutter - cHeight),
                        x = Math.round(leftgutter + X * (i + 0.5));
                    
                    if (value) {
                        rects.push(r.rect(x - cWidth/2, y, cWidth, Math.round(cHeight)).attr({
                            fill: color,
                            opacity: 0.9,
                            stroke: color
                        }));
                    }
                }
                
                self.map[line.userId] = rects;
            });
        });
    }
  
    public selectUser(id: number) {
        $.map(this.map, function(rects) {
            $.map(rects, function(rect) {
                rect.attr({
                    opacity: 0.1
                });
            });
        });
        
        $.map(this.map[id], function(rect) {
            rect.attr({
                opacity: 1
            });
        });
    };
    
    public deselectUser() {
        $.map(this.map, function(rects) {
            $.map(rects, function(rect) {
                rect.attr({
                    opacity: 0.9
                });
            });
        });
    };
};

angular.module(Module)
    .controller(CONTROLLER_NAME, PulseController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/pulse',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


