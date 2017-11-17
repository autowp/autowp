import * as angular from "angular";
import Module from 'app.module';
import { IAutowpControllerScope } from 'declarations.d.ts';

import './log';
import './success';
import './vod';

const CONTROLLER_NAME = 'DonateController';
const STATE_NAME = 'donate';

export class DonateController {
    static $inject = ['$scope', '$httpParamSerializer', '$translate', '$sce'];
    public frameUrl: string; 
  
    constructor(
        private $scope: IAutowpControllerScope,
        private $httpParamSerializer: ng.IHttpParamSerializer,
        private $translate: ng.translate.ITranslateService,
        private $sce: ng.ISCEService
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/196/name',
            pageId: 196
        });
      
        var self = this;
        
        this.$translate(['donate/target', 'donate/project', 'donate/comment-hint']).then(function (translations: any) {
            self.frameUrl = $sce.trustAsResourceUrl('https://money.yandex.ru/embed/donate.xml?' + $httpParamSerializer({
                'account'                   : '41001161017513',
                'quickpay'                  : 'donate',
                'payment-type-choice'       : 'on',
                'mobile-payment-type-choice': 'on',
                'default-sum'               : '100',
                'targets'                   : translations['donate/target'],
                'target-visibility'         : 'on',
                'project-name'              : translations['donate/project'],
                'project-site'              : 'https://' + window.location.host + '/',
                'button-text'               : '01',
                'comment'                   : 'on',
                'hint'                      : translations['donate/comment-hint'],
                'successURL'                : 'https://' + window.location.host + '/ng/donate/success',
            }));
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, DonateController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/donate',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ])
