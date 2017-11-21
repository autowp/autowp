import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

var BrandPopover = require("brand-popover");
require("brandicon");

const CONTROLLER_NAME = 'BrandsController';
const STATE_NAME = 'brands';

export class BrandsController {
    static $inject = ['$scope', '$http'];
  
    public items: any[];
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService
    ) {
        var self = this;
            
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/61/name',
            pageId: 61
        });
        
        this.$http({
            method: 'GET',
            url: '/api/brands'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.items = response.data.items;
            angular.forEach(self.items, function(line: any) {
                angular.forEach(line, function(info: any) {
                    angular.forEach(info.brands, function(item: any) {
                        item.cssClass = item.catname.replace(/\./g, '_');
                    });
                });
            });
            BrandPopover.apply('.popover-handler');
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, BrandsController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/brands',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);

