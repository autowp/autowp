import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

var BrandPopover = require("brand-popover");
require("brandicon");

const CONTROLLER_NAME = 'BrandsController';
const STATE_NAME = 'brands';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/brands',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http',
        function($scope, $http) {
            
            var ctrl = this;
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/61/name',
                pageId: 61
            });
            
            $http({
                method: 'GET',
                url: '/api/brands'
            }).then(function(response) {
                ctrl.items = response.data.items;
                angular.forEach(ctrl.items, function(line) {
                    angular.forEach(line, function(info) {
                        angular.forEach(info.brands, function(item) {
                            item.cssClass = item.catname.replace(/\./g, '_');
                        });
                    });
                });
                BrandPopover.apply('.popover-handler');
            }, function(response) {
                notify.response(response);
            });
        }
    ]);

export default CONTROLLER_NAME;
