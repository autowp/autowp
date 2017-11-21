import * as angular from 'angular';
import Module from 'app.module';

import './access';
import './accounts';
import './contacts';
import './delete';
import './email';
import './emailcheck';
import './inbox-pictures';
import './messages';
import './sidebar';
import './specs-conflicts';
import './profile';

const CONTROLLER_NAME = 'AccountController';
const STATE_NAME = 'account';

export class AccountController {
    static $inject = ['$state'];
  
    constructor(
        private $state: any
    ) {
        this.$state.go('account-profile');
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, AccountController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);

