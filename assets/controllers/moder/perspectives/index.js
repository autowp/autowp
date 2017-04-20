import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';

const CONTROLLER_NAME = 'ModerPerspectivesController';
const STATE_NAME = 'moder-perspectives';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/perspectives',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                resolve: {
                    access: [ACL_SERVICE_NAME, function (Acl) {
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
                pageId: 202
            });
                
            $http({
                method: 'GET',
                url: '/api/perspective-page',
                params: {
                    fields: 'groups.perspectives'
                }
            }).then(function(response) {
                $scope.pages = response.data.items;
            });
        }
    ]);

export default CONTROLLER_NAME;
