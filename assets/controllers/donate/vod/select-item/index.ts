import * as angular from "angular";
import Module from 'app.module';
import { IAutowpControllerScope } from 'declarations.d.ts';
import { UserService } from 'services/user';
require('services/user');
import notify from 'notify';

const CONTROLLER_NAME = 'DonateVodSelectItemController';
const STATE_NAME = 'donate-vod-select-item';

export class DonateVodSelectItemController {
    static $inject = ['$scope', '$translate', '$http', '$state'];
    public page: number;
    public brands: any[];
    public paginator: any;
    public brand: any;
  
    constructor(
        private $scope: IAutowpControllerScope,
        private $translate: any,
        private $http: ng.IHttpService,
        private $state: any
    ) {
        this.page = this.$state.params.page || 1;
      
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/196/name',
            pageId: 196
        });
      
        var self = this;
      
        this.$http({
            method: 'GET',
            url: '/api/item',
            params: {
                type_id: 5,
                limit: 500,
                fields: 'name_html',
                page: this.page
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.brands = self.chunk(response.data.items, 6);
            self.paginator = response.data.paginator;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
        
        
    }

    private chunk(arr: any[], count: number): any[] {
        var newArr = [];
        var size = Math.ceil(arr.length / count);
        for (var i=0; i<arr.length; i+=size) {
            newArr.push(arr.slice(i, i+size));
        }
        return newArr;
    };
};

angular.module(Module)
    .controller(CONTROLLER_NAME, DonateVodSelectItemController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/donate/vod/select-item?brand_id&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ])
