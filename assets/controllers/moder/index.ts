import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';

const CONTROLLER_NAME = 'ModerController';
const STATE_NAME = 'moder';

export class ModerController {
    static $inject = ['$scope'];

    constructor(
        private $scope: autowp.IControllerScope
    ) {
        this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/67/name',
            pageId: 67
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ]);
