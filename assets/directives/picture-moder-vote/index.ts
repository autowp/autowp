import * as angular from "angular";
import Module from 'app.module';
import { PictureModerVoteTemplateService } from 'services/picture-moder-vote-template';
import { PictureModerVoteService } from 'services/picture-moder-vote';
import * as $ from 'jquery';

interface IPictureModerVoteDirectiveScope extends ng.IScope {
    picture: any;
    change: any;
    votePicture: (vote: number, reason: string) => void;
    moderVoteTemplateOptions: any;
    vote: any;
    reason: any;
    save: any;
    ModerVoteService: PictureModerVoteService;
    ModerVoteTemplateService: PictureModerVoteTemplateService;
    showCustomDialog: (ev: any, vote: number) => void;
    cancelVotePicture: () => void;
    ok: () => void;
}

class AutowpPictureModerVoteDirective implements ng.IDirective {
    restrict = 'E';
    scope = {
        picture: '=',
        change: '='
    };
    template = require('./template.html');

    constructor(private ModerVoteService: PictureModerVoteService, private ModerVoteTemplateService: PictureModerVoteTemplateService) {
    }

    link = (scope: IPictureModerVoteDirectiveScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {
      
        var self = this;
      
        scope.moderVoteTemplateOptions = [];
        scope.vote = null;
        scope.reason = '';
        scope.save = false;
        
        scope.votePicture = function(vote: number, reason: string): void {
            self.ModerVoteService.vote(scope.picture.id, vote, reason).then(function() { 
                if (scope.change) {
                    scope.change();
                }
            });
        };
        
        scope.cancelVotePicture = function(): void {
            self.ModerVoteService.cancel(scope.picture.id).then(function() { 
                if (scope.change) {
                    scope.change();
                }
            });
        };
        
        this.ModerVoteTemplateService.getTemplates().then(function(templates: any) {
            scope.moderVoteTemplateOptions = templates;
        });
        
        var $modal = $(element[0]).find('.modal');
        
        scope.ok = function(): void {
            if (scope.save) {
                self.ModerVoteTemplateService.createTemplate({
                    vote: scope.vote,
                    name: scope.reason
                });
            }
            
            $modal.modal('hide');
            scope.votePicture(scope.vote, scope.reason);
        };
        
        scope.showCustomDialog = function(ev: any, vote: number): void {
            scope.vote = vote;
            
            $modal.modal({
                show: true
            });
        };
    }

    static factory(): ng.IDirectiveFactory {
        const directive = (ModerVoteService: any, ModerVoteTemplateService: any) => new AutowpPictureModerVoteDirective(ModerVoteService, ModerVoteTemplateService);
        directive.$inject = ['PictureModerVoteService', 'PictureModerVoteTemplateService'];
        return directive;
    }
}

angular.module(Module).directive('autowpPictureModerVote', AutowpPictureModerVoteDirective.factory());
