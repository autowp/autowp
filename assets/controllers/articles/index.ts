import * as angular from "angular";
import Module from 'app.module';
import { IAutowpControllerScope } from 'declarations.d.ts';
import notify from 'notify';

import './article';
import './styles.less';

const CONTROLLER_NAME = 'ArticlesController';
const STATE_NAME = 'articles';

export class ArticlesController {
    static $inject = ['$scope', '$http', '$state'];
    public articles: any[];
    public paginator: any;
  
    constructor(
        private $scope: IAutowpControllerScope,
        private $http: ng.IHttpService,
        private $state: any
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/31/name',
            pageId: 31
        });
        
        var self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/article',
            params: {
                page: this.$state.params.page,
                limit: 10,
                fields: 'description,author'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.articles = response.data.items;
            self.paginator = response.data.paginator;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, ArticlesController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/articles',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ])
