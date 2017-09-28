import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import PERSPECTIVE_SERVICE from 'services/perspective';
import PICTURE_ITEM_SERVICE from 'services/picture-item';
import './styles.less';

angular.module(Module)
    .directive('autowpThumbnail', function() {
        return {
            restirct: 'E',
            scope: {
                picture: '=',
                onselect: '=',
                isModer: '='
            },
            template: template,
            transclude: true,
            controller: ['$scope', '$timeout', PERSPECTIVE_SERVICE, PICTURE_ITEM_SERVICE, 
                function($scope, $timeout, PerspectiveService, PictureItemService) {
                    
                    if ($scope.picture.perspective_item) {
                        $scope.perspectiveOptions = [];
                        
                        PerspectiveService.getPerspectives().then(function(perspectives) {
                            $scope.perspectiveOptions = perspectives;
                        });
                        
                        $scope.savePerspective = function() {
                            if ($scope.picture.perspective_item) {
                                PictureItemService.setPerspective(
                                    $scope.picture.id,
                                    $scope.picture.perspective_item.item_id,
                                    $scope.picture.perspective_item.type,
                                    $scope.picture.perspective_item.perspective_id
                                );
                            }
                        };
                    }
                    
                    if ($scope.onselect) {
                        $scope.onPictureSelect = function($event, picture) {
                            var element = $event.currentTarget;
                            $timeout(function() {
                                var active = angular.element(element).hasClass('active');
                                $scope.onselect(picture, active);
                            });
                        };
                    }
                }
            ]
        };
    });