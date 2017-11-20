import * as angular from "angular";
import Module from 'app.module';
import notify from 'notify';

const CONTROLLER_NAME = 'ArticlesArticleController';
const STATE_NAME = 'articles-article';

export class ArticlesArticleController {
    static $inject = ['$scope', '$http', '$state'];
    public article: any;
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $state: any
    ) {
        var self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/article',
            params: {
                catname: this.$state.params.catname,
                limit: 1,
                fields: 'html'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            
            if (response.data.items.length <= 0) {
                $state.go('error-404');
                return;
            }
            
            self.article = response.data.items[0];
            
            self.$scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/32/name',
                pageId: 32,
                args: {
                    ARTICLE_NAME: self.article.name,
                    ARTICLE_CATNAME: self.article.catname
                }
            });
            
        }, function(response: ng.IHttpResponse<any>) {
            if (response.status == 404) {
                $state.go('error-404');
            } else {
                notify.response(response);
            }
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, ArticlesArticleController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/articles/:catname',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ])
