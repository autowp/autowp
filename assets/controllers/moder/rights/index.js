import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';
import './tree';

const CONTROLLER_NAME = 'ModerRightsController';
const STATE_NAME = 'moder-rights';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/rights',
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
                name: 'page/71/name',
                pageId: 71
            });
            
            var ctrl = this;
            ctrl.rules = [];
            ctrl.resources = [];
            ctrl.roles = [];
            ctrl.rolesTree = [];
            
            ctrl.addRoleParentForm = {
                role: null,
                parentRole: null
            };
            
            ctrl.addRoleForm = {
                name: null
            };
            ctrl.addRuleForm = {
                role: null,
                privilege: null,
                allowed: 0
            };
            
            var loadRules = function() {
                $http({
                    method: 'GET',
                    url: '/api/acl/rules'
                }).then(function(response) {
                    ctrl.rules = response.data.items;
                });
            };
            
            loadRules();
            
            var loadResources = function() {
                $http({
                    method: 'GET',
                    url: '/api/acl/resources'
                }).then(function(response) {
                    ctrl.resources = response.data.items;
                });
            };
            
            loadResources();
            
            var loadRoles = function() {
                $http({
                    method: 'GET',
                    url: '/api/acl/roles'
                }).then(function(response) {
                    ctrl.roles = response.data.items;
                });
            };
            
            loadRoles();
            
            var loadRolesTree = function() {
                $http({
                    method: 'GET',
                    url: '/api/acl/roles',
                    params: {
                        recursive: 1
                    }
                }).then(function(response) {
                    ctrl.rolesTree = response.data.items;
                });
            };
            
            loadRolesTree();
            
            ctrl.addRoleParent = function() {
                $http({
                    method: 'POST',
                    url: '/api/acl/roles/' + encodeURIComponent(ctrl.addRoleParentForm.role) + '/parents',
                    data: {
                        role: ctrl.addRoleParentForm.parentRole
                    }
                }).then(function() {
                    loadRoles();
                    loadRolesTree();
                });
                
            };
            
            ctrl.addRole = function() {
                $http({
                    method: 'POST',
                    url: '/api/acl/roles',
                    data: ctrl.addRoleForm
                }).then(function() {
                    loadRoles();
                    loadRolesTree();
                });
            };
            
            ctrl.addRule = function() {
                var privilege = ctrl.addRuleForm.privilege.split('/');
                $http({
                    method: 'POST',
                    url: '/api/acl/rules',
                    data: {
                        role: ctrl.addRuleForm.role,
                        resource: privilege[0],
                        privilege: privilege[1],
                        allowed: ctrl.addRuleForm.allowed
                    }
                }).then(function() {
                    loadRules();
                });
            };
        }
    ]);

export default CONTROLLER_NAME;
