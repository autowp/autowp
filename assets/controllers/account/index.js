import angular from 'angular';
import Module from 'app.module';
import template from './template.html';

import './contacts';
import './messages';
import './sidebar';
import './profile';

const CONTROLLER_NAME = 'AccountController';
const STATE_NAME = 'account';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$state', 
        function($state) {
            console.log('go');
            $state.go('account-profile');
        }
    ]);

export default CONTROLLER_NAME;
