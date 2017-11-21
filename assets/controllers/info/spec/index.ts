import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { SpecService } from 'services/spec';

import './row';

const CONTROLLER_NAME = 'InfoSpecController';
const STATE_NAME = 'info-spec';

export class InfoSpecController {
    static $inject = ['$scope', 'SpecService'];

    public specs: any;
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private SpecService: SpecService
    ) {
        var self = this;
        
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/174/name',
            pageId: 174
        });
        
        this.SpecService.getSpecs().then(function(specs: any) {
            self.specs = specs;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, InfoSpecController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/info/spec',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


