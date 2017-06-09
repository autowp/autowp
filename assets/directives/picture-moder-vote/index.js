import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import MODER_VOTE_TEMPLATE_SERVICE from 'services/picture-moder-vote-template';
import MODER_VOTE_SERVICE from 'services/picture-moder-vote';
import $ from 'jquery';

angular.module(Module)
    .directive('autowpPictureModerVote', [MODER_VOTE_SERVICE, MODER_VOTE_TEMPLATE_SERVICE,
        function(ModerVoteService, ModerVoteTemplateService) {
            return {
                restirct: 'E',
                scope: {
                    picture: '=',
                    change: '='
                },
                template: template,
                controller: ['$scope', '$element', function($scope, $element) {
                    
                    $scope.moderVoteTemplateOptions = [];
                    $scope.vote = null;
                    $scope.reason = '';
                    $scope.save = false;
                    
                    $scope.votePicture = function(vote, reason) {
                        ModerVoteService.vote($scope.picture.id, vote, reason).then(function() { 
                            if ($scope.change) {
                                $scope.change();
                            }
                        });
                    };
                    
                    $scope.cancelVotePicture = function() {
                        ModerVoteService.cancel($scope.picture.id).then(function() { 
                            if ($scope.change) {
                                $scope.change();
                            }
                        });
                    };
                    
                    ModerVoteTemplateService.getTemplates().then(function(templates) {
                        $scope.moderVoteTemplateOptions = templates;
                    });
                    
                    var $modal = $($element[0]).find('.modal');
                    
                    $scope.ok = function(answer) {
                        if ($scope.save) {
                            ModerVoteTemplateService.createTemplate({
                                vote: $scope.vote,
                                name: $scope.reason
                            });
                        }
                        
                        $modal.modal('hide');
                        $scope.votePicture($scope.vote, $scope.reason);
                    };
                    
                    $scope.showCustomDialog = function(ev, vote) {
                        $scope.vote = vote;
                        
                        $modal.modal({
                            show: true
                        });
                    };
                }]
            };
        }
    ]);