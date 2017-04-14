import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import MODER_VOTE_TEMPLATE_SERVICE from 'services/picture-moder-vote-template';
import MODER_VOTE_SERVICE from 'services/picture-moder-vote';

angular.module(Module)
    .directive('autowpPictureModerVote', ['$mdDialog', MODER_VOTE_SERVICE, MODER_VOTE_TEMPLATE_SERVICE,
        function($mdDialog, ModerVoteService, ModerVoteTemplateService) {
            return {
                restirct: 'E',
                scope: {
                    picture: '=',
                    change: '='
                },
                template: template,
                controller: ['$scope', function($scope) {
                    
                    $scope.moderVoteTemplateOptions = [];
                    
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
                    
                    $scope.showCustomDialog = function(ev, vote) {
                        $mdDialog.show({
                            controller: ['$scope', '$mdDialog', 'vote', DialogController],
                            template: require('./dialog.html'),
                            parent: angular.element(document.body),
                            targetEvent: ev,
                            clickOutsideToClose: true,
                            locals: {
                                vote: vote
                            }
                        })
                        .then(function(reason) {
                            $scope.votePicture(vote, reason);
                        }, function() {
                            
                        });
                    };
                    
                    function DialogController($scope, $mdDialog, vote) {
                        $scope.vote = vote;
                        $scope.reason = '';
                        $scope.save = false;
                        
                        $scope.cancel = function() {
                            $mdDialog.cancel();
                        };

                        $scope.ok = function(answer) {
                            if ($scope.save) {
                                ModerVoteTemplateService.createTemplate({
                                    vote: vote,
                                    name: $scope.reason
                                }).then(function() {
                                    $mdDialog.hide($scope.reason);
                                });
                            } else {
                                $mdDialog.hide($scope.reason);
                            }
                        };
                    }
                }]
            };
        }
    ]);