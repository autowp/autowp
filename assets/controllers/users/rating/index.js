import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

const CONTROLLER_NAME = 'UsersRatingController';
const STATE_NAME = 'users-rating';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/users/rating/:rating',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                params: {
                    rating: {
                        replace: true,
                        value: 'specs',
                        reload: true,
                        squash: true
                    }
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: true
                },
                name: 'page/173/name',
                pageId: 173
            });
            
            var ctrl = this;
            
            ctrl.rating = $state.params.rating || 'specs-volume';
            ctrl.loading = 0;
            
            ctrl.valueTitle = null;
            switch (ctrl.rating) {
                case 'specs':
                    ctrl.valueTitle = 'users/rating/specs-volume';
                    break;
                case 'pictures':
                    ctrl.valueTitle = 'users/rating/pictures';
                    break;
                case 'likes':
                    ctrl.valueTitle = 'users/rating/likes';
                    break;
                case 'picture-likes':
                    ctrl.valueTitle = 'users/rating/picture-likes';
                    break;
            }
            
            ctrl.loading++;
            $http({
                method: 'GET',
                url: '/api/rating/' + ctrl.rating
            }).then(function(response) {
                ctrl.loading--;
                ctrl.users = response.data.users;
            }, function(response) {
                ctrl.loading--;
                notify.response(response);
            });
        }
    ]);

export default CONTROLLER_NAME;
