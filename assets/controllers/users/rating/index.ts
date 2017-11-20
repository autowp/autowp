import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

const CONTROLLER_NAME = 'UsersRatingController';
const STATE_NAME = 'users-rating';

export class UsersRatingController {
    static $inject = ['$scope', '$state', '$http'];
    public rating: string;
    public loading: number = 0;
    public valueTitle: string;
    public users: any[];
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $state: any,
        private $http: ng.IHttpService
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/173/name',
            pageId: 173
        });
        
        this.rating = this.$state.params.rating || 'specs-volume';
        
        switch (this.rating) {
            case 'specs':
                this.valueTitle = 'users/rating/specs-volume';
                break;
            case 'pictures':
                this.valueTitle = 'users/rating/pictures';
                break;
            case 'likes':
                this.valueTitle = 'users/rating/likes';
                break;
            case 'picture-likes':
                this.valueTitle = 'users/rating/picture-likes';
                break;
        }
      
        var self = this;
        
        this.loading++;
        this.$http({
            method: 'GET',
            url: '/api/rating/' + this.rating
        }).then(function(response: ng.IHttpResponse<any>) {
            self.loading--;
            self.users = response.data.users;
        }, function(response: ng.IHttpResponse<any>) {
            self.loading--;
            notify.response(response);
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, UsersRatingController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/users/rating/:rating',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                params: {
                    rating: {
                        replace: true,
                        value: 'specs',
                        reload: true,
                        squash: true
                    }
                }
            });
        }
    ]);
