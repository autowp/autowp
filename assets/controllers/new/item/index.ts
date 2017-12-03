import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import * as moment from 'moment';
import { chunkBy } from 'chunk';
import { ItemService } from 'services/item';

const CONTROLLER_NAME = 'NewItemController';
const STATE_NAME = 'new-item';

export class NewItemController {
    static $inject = ['$scope', '$http', '$state', 'ItemService'];

    public paginator: autowp.IPaginator;
    public chunks: any[];
    public item: autowp.IItem;
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService, 
        private $state: any,
        private ItemService: ItemService
    ) {
        var self = this;
        
        this.ItemService.getItem(this.$state.params.item_id, {
            fields: 'name_html,name_text'
        }).then(function(item: autowp.IItem) {
            self.item = item;
            
            self.$scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/210/name',
                pageId: 210,
                args: {
                    DATE: moment($state.params.date).format('LL'),
                    DATE_STR: self.$state.params.date,
                    ITEM_NAME: self.item.name_text
                }
            });
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
        
        this.$http({
            method: 'GET',
            url: '/api/picture',
            params: {
                fields: 'owner,thumbnail,moder_vote,votes,views,comments_count,name_html,name_text',
                limit: 24,
                status: 'accepted',
                accept_date: this.$state.params.date,
                item_id: this.$state.params.item_id,
                page: this.$state.params.page
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.chunks = chunkBy(response.data.pictures, 6);
            self.paginator = response.data.paginator;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, NewItemController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/new/:date/item/:item_id/:page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                params: {
                    date: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    },
                    page: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    }
                }
            });
        }
    ]);

