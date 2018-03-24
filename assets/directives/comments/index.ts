import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

interface IAutowpCommentsDirectiveScope extends ng.IScope {
    limit: number;
    page: number;
    typeId: number;
    itemId: number;
}

class AutowpCommentsDirectiveController {

    public messages: any[] = [];
    public onSent: Function;
    public paginator: autowp.IPaginator;

    static $inject = ['$scope', '$state', '$http'];

    constructor(
        protected $scope: IAutowpCommentsDirectiveScope,
        private $state: any,
        private $http: ng.IHttpService
    ) {
        this.load();

        var self = this;

        this.onSent = function(location: string) {
            if ($scope.limit) {
                self.$http({
                    method: 'GET',
                    url: location,
                    params: {
                        fields: 'page',
                        limit: $scope.limit
                    }
                }).then(function(response: ng.IHttpResponse<any>) {

                    if ($scope.page != response.data.page) {
                        $state.go('.', {page: response.data.page}); // , { notify: false }
                    } else {
                        self.load();
                    }

                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
            } else {
                self.load();
            }
        };
    }

    public load() {
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/comment',
            params: {
                type_id: this.$scope.typeId,
                item_id: this.$scope.itemId,
                no_parents: 1,
                fields: 'user.avatar,user.gravatar,replies,text_html,datetime,vote,user_vote',
                order: 'date_asc',
                limit: this.$scope.limit ? this.$scope.limit : null,
                page: this.$scope.page
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.messages = response.data.items;
            self.paginator = response.data.paginator;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
}

class AutowpCommentsDirective implements ng.IDirective {
    public controllerAs = 'ctrl';
    public restrict = 'E';
    public scope = {
        itemId: '=',
        typeId: '=',
        user: '=',
        limit: '<',
        page: '<'
    };
    public template = require('./template.html');
    public controller = AutowpCommentsDirectiveController;
    public bindToController: true;

    static factory(): ng.IDirectiveFactory {
        return () => new AutowpCommentsDirective();
    }
}

angular.module(Module).directive('autowpComments', AutowpCommentsDirective.factory());
