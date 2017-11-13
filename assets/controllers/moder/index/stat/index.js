import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import { AclService } from 'services/acl';
import notify from 'notify';

const CONTROLLER_NAME = 'ModerIndexStatController';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: 'moder-index-stat',
                url: '/moder/index/stat',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                resolve: {
                    access: ['AclService', function (Acl) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http',
        function($scope, $http) {
            
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                name: 'page/119/name',
                pageId: 119
            });
            
            $http.get('/api/stat/global-summary').then(function(response) {
                $scope.items = response.data.items;
            }, function(response) {
                notify.response(response);
            });
        }
    ]);

export default CONTROLLER_NAME;
