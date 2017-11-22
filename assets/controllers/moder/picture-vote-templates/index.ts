import * as angular from 'angular';
import Module from 'app.module';
import { PictureModerVoteTemplateService } from 'services/picture-moder-vote-template';
import { AclService } from 'services/acl';

const CONTROLLER_NAME = 'ModerPictureVoteTemplatesController';
const STATE_NAME = 'moder-picture-vote-templates';

interface IModerPictureVoteTemplatesControllerScope extends autowp.IControllerScope
{
    add: any;
}

export class ModerPictureVoteTemplatesController {
    static $inject = ['$scope', '$http', 'PictureModerVoteTemplateService'];
    
    public templates: any[];
    public vote: number = -1;
    public name: string = '';

    constructor(
        private $scope: IModerPictureVoteTemplatesControllerScope, 
        private $http: ng.IHttpService,
        private VoteTemplateService: PictureModerVoteTemplateService
    ) {
        this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/212/name',
            pageId: 212
        });
        
        var self = this;
        
        this.VoteTemplateService.getTemplates().then(function(templates: any) {
            self.templates = templates;
        });
    }
    
    public deleteTemplate(template: any) {
        var self = this;
        this.VoteTemplateService.deleteTemplate(template.id).then(function() {
            self.VoteTemplateService.getTemplates().then(function(templates: any[]) {
                self.templates = templates;
            });
        });
    }
    
    public createTemplate() {
        var template = {
            vote: this.vote,
            name: this.name
        };
        var self = this;
        this.VoteTemplateService.createTemplate(template).then(function(data: any) {
            self.$scope.add.$setPristine();
            self.$scope.add.$setUntouched();
            self.name = '';
            self.VoteTemplateService.getTemplates().then(function(templates: any[]) {
                self.templates = templates;
            });
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerPictureVoteTemplatesController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/picture-vote-templates',
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

