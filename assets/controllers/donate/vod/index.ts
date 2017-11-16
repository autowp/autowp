import * as angular from "angular";
import Module from 'app.module';
import { IAutowpControllerScope } from 'declarations.d.ts';
import { UserService } from 'services/user';
require('services/user');
import notify from 'notify';

import './select';
import './success';

const CONTROLLER_NAME = 'DonateVodController';
const STATE_NAME = 'donate-vod';

interface ICarOfDayDate {
    name: string;
    value: string;
    free: boolean;
}

interface IVodResponse {
    dates: ICarOfDayDate[];
    sum: number;
}

export class DonateVodController {
    static $inject = ['$scope', '$translate', '$http', '$state'];
    private formParams: any;
    private selectedDate: string;
    private selectedItem: any;
    private anonymous: boolean;
    private userId: number;
    private sum: number;
    private dates: ICarOfDayDate[];
    private itemId: number;
  
    constructor(
        private $scope: IAutowpControllerScope,
        private $translate: any,
        private $http: ng.IHttpService,
        private $state: any
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/196/name',
            pageId: 196
        });
      
        this.itemId = $state.params.item_id || 0;
        this.selectedDate = $state.params.date;
        this.userId = $scope.user ? $scope.user.id : 0;
        this.anonymous = this.userId ? !!$state.params.anonymous : true;
      
        var self = this;
      
        this.$http({
            method: 'GET',
            url: '/api/donate/vod'
        }).then(function(response: ng.IHttpResponse<IVodResponse>) {
            self.sum = response.data.sum;
            self.dates = response.data.dates;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
      
        if (this.itemId) {
            this.$http({
                method: 'GET',
                url: '/api/item/' + this.itemId,
                params: {
                    fields: 'name_html,item_of_day_pictures'
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.selectedItem = response.data;
                self.updateForm();

            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
        }
        
        this.updateForm();
    }
  
    private updateForm()
    {
        if (!this.selectedItem || !this.selectedDate) {
            this.formParams = null;
            return;
        }
      
        var self = this;
      
        this.$translate(['donate/vod/order-message', 'donate/vod/order-target']).then(function (translations: any) {
          
            var label = 'vod/' + self.selectedDate  + '/' + self.selectedItem.id + '/' + (self.anonymous ? 0 : self.userId);

            self.formParams = {
                'receiver'     : '41001161017513',
                'sum'          : self.sum,
                'need-email'   : 'false',
                'need-fio'     : 'false',
                'need-phone'   : 'false',
                'need-address' : 'false',
                'formcomment'  : translations['donate/vod/order-message'],
                'short-dest'   : translations['donate/vod/order-message'],
                'label'        : label,
                'quickpay-form': 'donate',
                'targets'      : sprintf(translations['donate/vod/order-target'], label),
                'successURL'   : 'https://' + window.location.host + '/ng/donate/vod/success',
            };
        });
    }
  
    public selectDate(date: string)
    {
        this.selectedDate = date;
        this.updateForm();
    }
  
    public setAnonymous(value: boolean)
    {
        this.anonymous = value;
        this.updateForm();
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, DonateVodController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/donate/vod?date&item_id&anonymous',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ])
