import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import './tree';

const CONTROLLER_NAME = 'ModerRightsController';
const STATE_NAME = 'moder-rights';

interface IAddRoleParentForm {
    role: null|string;
    parentRole: null|string;
}

interface IAddRoleForm {
    name: null|string;
}

interface IAddRuleForm {
    role: null|string;
    privilege: null|string;
    allowed: number;
}

export class ModerRightsController {
    static $inject = ['$scope', '$http'];

    public rules: any[] = [];
    public resources: any[] = [];
    public roles: any[] = [];
    public rolesTree: any[] = [];
    
    public addRoleParentForm: IAddRoleParentForm = {
        role: null,
        parentRole: null
    };
    
    public addRoleForm: IAddRoleForm = {
        name: null
    };
    public addRuleForm: IAddRuleForm = {
        role: null,
        privilege: null,
        allowed: 0
    };
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService
    ) {
        this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/71/name',
            pageId: 71
        });
        
        this.loadRules();
        this.loadResources();
        this.loadRoles();
        this.loadRolesTree();
    }
    
    private loadResources() {
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/acl/resources'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.resources = response.data.items;
        });
    };
    
    private loadRolesTree() {
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/acl/roles',
            params: {
                recursive: 1
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.rolesTree = response.data.items;
        });
    };
    
    private loadRoles() {
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/acl/roles'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.roles = response.data.items;
        });
    };
    
    private loadRules() {
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/acl/rules'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.rules = response.data.items;
        });
    }
    
    public addRoleParent() {
        var self = this;
        if (this.addRoleParentForm.role) {
            this.$http({
                method: 'POST',
                url: '/api/acl/roles/' + encodeURIComponent(this.addRoleParentForm.role) + '/parents',
                data: {
                    role: this.addRoleParentForm.parentRole
                }
            }).then(function() {
                self.loadRoles();
                self.loadRolesTree();
            });
        }
    }
    
    public addRole() {
        var self = this;
        this.$http({
            method: 'POST',
            url: '/api/acl/roles',
            data: this.addRoleForm
        }).then(function() {
            self.loadRoles();
            self.loadRolesTree();
        });
    }
    
    public addRule() {
        
        if (! this.addRuleForm.privilege) {
            return;
        }
        
        var self = this;
        var privilege = this.addRuleForm.privilege.split('/');
        this.$http({
            method: 'POST',
            url: '/api/acl/rules',
            data: {
                role: this.addRuleForm.role,
                resource: privilege[0],
                privilege: privilege[1],
                allowed: this.addRuleForm.allowed
            }
        }).then(function() {
            self.loadRules();
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerRightsController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/rights',
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
