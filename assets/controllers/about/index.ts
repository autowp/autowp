import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import * as showdown from 'showdown';
import * as escapeRegExp from 'lodash.escaperegexp';
import * as filesize from 'filesize';
import { UserService } from 'services/user';
import * as $ from 'jquery';

const CONTROLLER_NAME = 'AboutController';
const STATE_NAME = 'about';

function replaceAll(str: string, find: string, replace: string): string {
    return str.replace(new RegExp(escapeRegExp(find), 'g'), replace);
}

function replacePairs(str: string, pairs: any): string {
    angular.forEach(pairs, function(value, key) {
        str = replaceAll(str, key, value);
    });
    return str;
}

export class AboutController {
    static $inject = ['$scope', '$http', '$translate', '$filter', 'UserService', '$q', '$state'];
    public html: string = '';
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $translate: ng.translate.ITranslateService,
        private $filter: any,
        private userService: UserService,
        private $q: ng.IQService,
        private $state: any
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/136/name',
            pageId: 136
        });
        
        let self = this;
        
        this.$http({
            url: '/api/about',
            method: 'GET'
        }).then(function(response: ng.IHttpResponse<any>) {
            
            let promises: ng.IPromise<any>[] = [
                self.$translate('about/text')
            ];
        
            let ids: number[] = response.data.contributors;
            ids.push(response.data.developer);
            ids.push(response.data.fr_translator);
            ids.push(response.data.zh_translator);
            ids.push(response.data.be_translator);
            ids.push(response.data.pt_br_translator);
        
            promises.push(self.userService.getUserMap(ids));
            
            
            self.$q.all(promises).then(function(responses: any[]) {
                
                let users: Map<number, autowp.IUser> = responses[1];
            
                let contributorsHtml: string[] = [];
                for (let id of response.data.contributors) {
                    contributorsHtml.push(self.userHtml(users.get(id)));
                }
                
                let markdownConverter = new showdown.Converter({});
                self.html = replacePairs(
                    markdownConverter.makeHtml(responses[0]), {
                        '%users%'            : contributorsHtml.join(' '),
                        '%total-pictures%'   : self.$filter('number')(response.data.total_pictures),
                        '%total-vehicles%'   : response.data.total_cars,
                        '%total-size%'       : filesize(response.data.pictures_size),
                        '%total-users%'      : response.data.total_users,
                        '%total-comments%'   : response.data.total_comments,
                        '%github%'           : '<i class="fa fa-github"></i> <a href="https://github.com/autowp/autowp">https://github.com/autowp/autowp</a>',
                        '%developer%'        : self.userHtml(users.get(response.data.developer)),
                        '%fr-translator%'    : self.userHtml(users.get(response.data.fr_translator)),
                        '%zh-translator%'    : self.userHtml(users.get(response.data.zh_translator)),
                        '%be-translator%'    : self.userHtml(users.get(response.data.be_translator)),
                        '%pt-br-translator%' : self.userHtml(users.get(response.data.pt_br_translator))
                    }
                );
            }, function() {
                console.log('reject');
            });
            
            self.$translate('about/text').then(function(translation: string) {
                
            });
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    private userHtml(user: any): string {
        let span = $('<span class="user" />');
        span.toggleClass('muted', user.deleted);
        span.toggleClass('long-away', user.long_away);
        span.toggleClass('green-man', user.green);
        let a = $('<a />', {
            href: this.$state.href('users-user', {identity: user.identity ? user.identity : 'user' + user.id}, {inherit: false}),
            text: user.name
        });
        
        
        return '<i class="fa fa-user"></i> ' + (span.append(a))[0].outerHTML;
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, AboutController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/about',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);

