import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import * as $ from 'jquery';
import "corejs-typeahead";
import notify from 'notify';

const CONTROLLER_NAME = 'ModerCommentsController';
const STATE_NAME = 'moder-comments';

export class ModerCommentsController {
    static $inject = ['$scope', '$http', '$state', '$q', '$element'];
    
    public loading: number = 0;
    public comments = [];
    public paginator: autowp.IPaginator;
    public user: any;
    public moderator_attention: any;
    public pictures_of_item_id: number|null;
    public page: number;

    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private $state: any,
        private $q: ng.IQService,
        private $element: any
    ) {

        this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/110/name',
            title: 'page/119/title',
            pageId: 110
        });
        
        this.user = this.$state.params.user;
        this.moderator_attention = this.$state.params.moderator_attention;
        this.pictures_of_item_id = this.$state.params.pictures_of_item_id;
        this.page = this.$state.params.page;
        
        var self = this;
        
        var $userIdElement = $(this.$element[0]).find(':input[name=user_id]');
        $userIdElement.val(this.user ? '#' + this.user : '');
        var userIdLastValue = $userIdElement.val();
        $userIdElement
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
                        id: '',
                        search: ''
                    };
                    if (query.substring(0, 1) == '#') {
                        params.id = query.substring(1);
                    } else {
                        params.search = query;
                    }
                    
                    $http({
                        method: 'GET',
                        url: '/api/user',
                        params: params
                    }).then(function(response: ng.IHttpResponse<any>) {
                        asyncResults(response.data.items);
                    });
                    
                }
            })
            .on('typeahead:select', function(ev: any, item: any) {
                userIdLastValue = item.name;
                self.user = item.id;
                self.load();
            })
            .bind('change blur', function(ev: any, item: any) {
                var curValue = $(this).val();
                if (userIdLastValue && !curValue) {
                    self.user = null;
                    self.load();
                }
                userIdLastValue = curValue;
            });
        
        var $itemIdElement = $($element[0]).find(':input[name=pictures_of_item_id]');
        $itemIdElement.val(this.pictures_of_item_id ? '#' + this.pictures_of_item_id : '');
        var itemIdLastValue = $itemIdElement.val();
        $itemIdElement
            .on('typeahead:select', function(ev: any, item: any) {
                itemIdLastValue = item.name_text;
                self.pictures_of_item_id = item.id;
                self.load();
            })
            .bind('change blur', function(ev: any, item: any) {
                var curValue = $(this).val();
                if (itemIdLastValue && !curValue) {
                    self.pictures_of_item_id = null;
                    self.load();
                }
                itemIdLastValue = curValue;
            })
            .typeahead({ }, {
                display: function(item: any) {
                    return item.name_text;
                },
                templates: {
                    suggestion: function(item: any) {
                        return $('<div class="tt-suggestion tt-selectable"></div>')
                            .html(item.name_html);
                    }
                },
                source: function(query: string, syncResults: Function, asyncResults: Function) {
                    var params = {
                        limit: 10,
                        fields: 'name_text,name_html',
                        id: '',
                        name: ''
                    };
                    if (query.substring(0, 1) == '#') {
                        params.id = query.substring(1);
                    } else {
                        params.name = '%' + query + '%';
                    }
                    
                    $http({
                        method: 'GET',
                        url: '/api/item',
                        params: params
                    }).then(function(response: ng.IHttpResponse<any>) {
                        asyncResults(response.data.items);
                    });
                    
                }
            });
        
        this.load();
    }
    
    public load() {
        this.loading++;
        
        var params = {
            user: this.user,
            moderator_attention: this.moderator_attention,
            pictures_of_item_id: this.pictures_of_item_id ? this.pictures_of_item_id : null,
            page: this.page,
            order: 'date_desc',
            fields: 'preview,user,is_new,status,url'
        };
        
        this.$state.go(STATE_NAME, params, {
            notify: false,
            reload: false,
            location: 'replace'
        });
        
        var self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/comment',
            params: params
        }).then(function(response: ng.IHttpResponse<any>) {
            self.comments = response.data.items;
            self.paginator = response.data.paginator;
            self.loading--;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
            self.loading--;
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerCommentsController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/comments?user&moderator_attention&pictures_of_item_id&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                params: { 
                    user: { dynamic: true },
                    moderator_attention: { dynamic: true },
                    item_id: { dynamic: true },
                    page: { dynamic: true }
                },
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ]);
