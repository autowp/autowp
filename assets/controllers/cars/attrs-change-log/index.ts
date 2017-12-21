import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import "corejs-typeahead";
import * as $ from 'jquery';

const CONTROLLER_NAME = 'CarsAttrsChangeLogController';
const STATE_NAME = 'cars-attrs-change-log';

export class CarsAttrsChangeLogController {
    static $inject = ['$scope', '$http', '$state', '$element'];
  
    public items: any[] = [];
    public paginator: autowp.IPaginator;
    public user_id: number;
    public page: number; 
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService, 
        private $state: any,
        private $element: any
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/103/name',
            pageId: 103
        });
        
        var self = this;
        
        this.user_id = $state.params.user_id;
        this.page = $state.params.page;
        
        this.load();
        
        var $userIdElement = $($element[0]).find(':input[name=user_id]');
        $userIdElement.val(this.user_id ? '#' + this.user_id : '');
        var userIdLastValue = $userIdElement.val();
        $userIdElement
            .on('typeahead:select', function(ev: any, item: any) {
                userIdLastValue = item.name;
                self.user_id = item.id;
                self.load();
            })
            .bind('change blur', function(ev: any, item: any) {
                var curValue = $(this).val();
                if (userIdLastValue && !curValue) {
                    self.user_id = 0;
                    self.load();
                }
                userIdLastValue = curValue;
            })
            .typeahead({ }, {
                display: function(item: any) {
                    return item.name;
                },
                templates: {
                    suggestion: function(item: any) {
                        return $('<div class="tt-suggestion tt-selectable"></div>')
                            .text(item.name);
                    }
                },
                source: function(query: string, syncResults: Function, asyncResults: Function) {
                    var params = {
                        limit: 10,
                        id: 0,
                        search: ''
                    };
                    if (query.substring(0, 1) == '#') {
                        params.id = parseInt(query.substring(1));
                    } else {
                        params.search = query;
                    }
                    
                    self.$http({
                        method: 'GET',
                        url: '/api/user',
                        params: params
                    }).then(function(response: ng.IHttpResponse<any>) {
                        asyncResults(response.data.items);
                    });
                    
                }
            });
    }
  
    private load() {
      
        let self = this;
            
        var params = {
            user_id: this.user_id ? this.user_id : null,
            item_id: this.$state.params.item_id,
            page: this.page,
            fields: ''
        };
        this.$state.go(STATE_NAME, params, {
            notify: false,
            reload: false,
            location: 'replace'
        });
        
        params.fields = 'user,item.name_html,path,unit,value_text';
        
        this.$http({
            method: 'GET',
            url: '/api/attr/user-value',
            params: params
        }).then(function(response: ng.IHttpResponse<any>) {
            self.items = response.data.items;
            self.paginator = response.data.paginator;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    };
}

angular.module(Module)
    .controller(CONTROLLER_NAME, CarsAttrsChangeLogController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/cars/attrs-change-log?page&user_id&item_id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                params: { 
                    user_id: { dynamic: true }
                },
            });
        }
    ]);

