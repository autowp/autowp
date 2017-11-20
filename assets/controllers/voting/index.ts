import * as angular from "angular";
import Module from 'app.module';
import * as $ from 'jquery';
import notify from 'notify';

const CONTROLLER_NAME = 'VotingController';
const STATE_NAME = 'voting';

export class VotingController {
    static $inject = ['$scope', '$state', '$http', '$translate', '$element'];
    public voting: any;
    public filter: boolean = false;
    private selected: {};
    public votes: any[];
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $state: any,
        private $http: ng.IHttpService,
        private $translate: ng.translate.ITranslateService,
        private $element: ng.IAugmentedJQuery
    ) {
        var self = this;
      
        this.load(function() {
            self.$scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: true
                },
                name: 'page/157/name',
                pageId: 157,
                args: {
                    VOTING_NAME: self.voting.name,
                    VOTING_ID: self.voting.id
                }
            });
        });
      
        $scope.$on('$destroy', function() {
            $(self.$element).find('.modal').modal('hide');
        });
    }
  
    public load(callback?: () => void)
    {
        var self = this;
      
        this.$http({
            method: 'GET',
            url: '/api/voting/' + this.$state.params.id
        }).then(function(response: ng.IHttpResponse<any>) {
          
            self.voting = response.data;
          
            if (callback) {
                callback();
            }
        }, function(response: ng.IHttpResponse<any>) {
            self.$state.go('error-404');
        });
    }
  
    public vote()
    {
        var self = this;
        var ids: number[] = [];
      
        if (! this.voting.multivariant) {
            if (this.selected) {
                ids.push(this.selected as number);
            }
        } else {
            angular.forEach(this.selected, function(value, key) {
                if (value) {
                    ids.push(parseInt(key));
                }
            });
        }
      
        this.$http({
            method: 'PATCH',
            url: '/api/voting/' + this.$state.params.id,
            data: {
                vote: ids
            }
        }).then(function(response: ng.IHttpResponse<any>) {
          
            self.load();
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
  
    public isVariantSelected(): boolean
    {
        if (! this.voting.multivariant) {
            return this.selected > 0;
        }
      
        let count = 0;
        angular.forEach(this.selected, function(value, key) {
            if (value) {
                count++;
            }
        });
        return count > 0;
    }
  
    public showWhoVoted(variant: any)
    {
        this.votes = [];
      
        var self = this;
        
        this.$http({
            url: '/api/voting/' + self.$state.params.id + '/variant/' + variant.id + '/vote',
            method: 'GET',
            params: {
                fields: 'user'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.votes = response.data.items;
            $(self.$element).find('.modal').modal('show');
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, VotingController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/voting/:id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);
