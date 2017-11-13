import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import { PictureModerVoteTemplateService } from 'services/picture-moder-vote-template';
import { AclService } from 'services/acl';

const CONTROLLER_NAME = 'ModerPictureVoteTemplatesController';
const STATE_NAME = 'moder-picture-vote-templates';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/picture-vote-templates',
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
        '$scope', '$http', 'PictureModerVoteTemplateService',
        function($scope, $http, VoteTemplateService) {
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                name: 'page/212/name',
                pageId: 212
            });
            
            var ctrl = this;
            
            ctrl.templates = [];
            ctrl.vote = -1;
            ctrl.name = '';
                
            VoteTemplateService.getTemplates().then(function(templates) {
            	ctrl.templates = templates;
            });
            
            ctrl.deleteTemplate = function(template) {
                VoteTemplateService.deleteTemplate(template.id).then(function() {
                    VoteTemplateService.getTemplates().then(function(templates) {
                    	ctrl.templates = templates;
                    });
                });
            };
            
            ctrl.createTemplate = function() {
                var template = {
                    vote: ctrl.vote,
                    name: ctrl.name
                };
                VoteTemplateService.createTemplate(template).then(function(data) {
                    $scope.add.$setPristine();
                    $scope.add.$setUntouched();
                    ctrl.name = '';
                    VoteTemplateService.getTemplates().then(function(templates) {
                    	ctrl.templates = templates;
                    });
                });
            };
        }
    ]);

export default CONTROLLER_NAME;
