import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';
import './edit';

const CONTROLLER_NAME = 'ModerPagesController';
const STATE_NAME = 'moder-pages';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/pages',
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
        '$scope', '$http', ACL_SERVICE_NAME,
        function($scope, $http, Acl) {
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                pageId: 68
            });
            
            var ctrl = this;
            ctrl.items = [];
            
            ctrl.canManage = false;
            Acl.isAllowed('hotlinks', 'manage').then(function() {
                ctrl.canManage = true;
            }, function() {
                ctrl.canManage = false;
            });
            
            var load = function() {
                $http({
                    method: 'GET',
                    url: '/api/page'
                }).then(function(response) {
                    ctrl.items = toPlainArray(response.data.items, 0);
                });
            };
            
            load();
            
            function toPlainArray(pages, level) {
                var result = [];
                angular.forEach(pages, function(page, i) {
                    page.level = level;
                    page.moveUp = i > 0;
                    page.moveDown = i < pages.length-1;
                    result.push(page);
                    angular.forEach(toPlainArray(page.childs, level+1), function(child) {
                        result.push(child);
                    });
                });
                return result;
            }
            
            ctrl.move = function(page, direction) {
                $http({
                    method: 'PUT',
                    url: '/api/page/' + page.id,
                    data: {
                        position: direction
                    }
                }).then(function(response) {
                	load();
                });
            };
        }
    ]);

export default CONTROLLER_NAME;
