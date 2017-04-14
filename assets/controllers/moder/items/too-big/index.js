import angular from 'angular';
import Module from 'app.module';
import template from './template.html';

const STATE_NAME = 'moder-items-too-big';
const CONTROLLER_NAME = 'ModerItemsTooBigController';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/item/too-big',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {

            $scope.loading = true;
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                }
            });
            
            $http({
                method: 'GET',
                url: '/api/item',
                params: {
                    order: 'childs_count',
                    limit: 100,
                    fields: 'childs_count,moder_url'
                }
            }).then(function(response) {
                $scope.items = response.data.items;
                $scope.loading = false;
            });
        }
    ]);

export default STATE_NAME;
