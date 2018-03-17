import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

const CONTROLLER_NAME = 'InboxController';
const STATE_NAME = 'inbox';

const ALL_BRANDS = 'all';

export class InboxController {
    static $inject = ['$scope', '$http', '$state'];

    public pictures: any[] = [];
    public paginator: autowp.IPaginator;
    public brand_id: number;
    public current: any;
    public prev: any;
    public next: any;
    public brands: any[];

    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $state: any
    ) {
        if (! $scope.user) {
            this.$state.go('login');
            return;
        }

        if (! this.$state.params.brand) {
            this.$state.go(STATE_NAME, {
                brand: ALL_BRANDS,
                page: null
            }, {
                notify: false,
                reload: false,
                location: 'replace'
            });
            return;
        }

        var self = this;

        if (this.$state.params.brand == ALL_BRANDS) {
            this.brand_id = 0;
        } else {
            this.brand_id = this.$state.params.brand ? parseInt(this.$state.params.brand) : 0;
        }

        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/76/name',
            pageId: 76
        });

        this.$http({
            method: 'GET',
            url: '/api/inbox',
            params: {
                brand_id: this.brand_id ? this.brand_id : null,
                date: this.$state.params.date,
                page: this.$state.params.page
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.paginator = response.data.paginator;
            self.prev = response.data.prev;
            self.current = response.data.current;
            self.next = response.data.next;
            self.brands = response.data.brands;

            if (self.$state.params.date != self.current.date) {
                self.$state.go(STATE_NAME, {
                    date: self.current.date,
                    page: null
                }, {
                    notify: false,
                    reload: false,
                    location: 'replace'
                });
                return;
            }

            self.$http({
                method: 'GET',
                url: '/api/picture',
                params: {
                    status: 'inbox',
                    fields: 'owner,thumb_medium,votes,views,comments_count,name_html,name_text',
                    limit: 30,
                    page: self.$state.params.page,
                    item_id: self.brand_id,
                    add_date: self.current.date,
                    order: 1
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.pictures = response.data.pictures;
                self.paginator = response.data.paginator;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }

    public changeBrand() {
        this.$state.go('.', {
            brand: this.brand_id,
            page: null
        });
    };
}

angular.module(Module)
    .controller(CONTROLLER_NAME, InboxController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/inbox/:brand/:date/:page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                params: {
                    brand: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    },
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

