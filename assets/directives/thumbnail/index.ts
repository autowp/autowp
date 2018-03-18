import * as angular from "angular";
import Module from 'app.module';
import { PerspectiveService } from 'services/perspective';
import { PictureItemService } from 'services/picture-item';
import './styles.scss';

interface IThumbnailDirectiveScope extends ng.IScope {
    picture: any;
    perspectiveOptions: any[];
    savePerspective: () => void;
    onPictureSelect: ($event: any, picture: any) => void;
    onselect: any;
    isModer: boolean;
}

class AutowpThumbnailDirective implements ng.IDirective {
    restrict = 'E';
    template = require('./template.html');
    scope = {
        picture: '=',
        onselect: '=',
        isModer: '='
    };

    constructor(private $timeout: ng.ITimeoutService, private perspectiveService: PerspectiveService, private pictureItemService: PictureItemService) {
    }

    link = (scope: IThumbnailDirectiveScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {

        var self = this;

        if (scope.picture.perspective_item) {
            scope.perspectiveOptions = [];

            this.perspectiveService.getPerspectives().then(function(perspectives: any[]) {
                scope.perspectiveOptions = perspectives;
            });

            scope.savePerspective = function() {
                if (scope.picture.perspective_item) {
                    self.pictureItemService.setPerspective(
                        scope.picture.id,
                        scope.picture.perspective_item.item_id,
                        scope.picture.perspective_item.type,
                        scope.picture.perspective_item.perspective_id
                    );
                }
            };
        }

        if (scope.onselect) {
            scope.onPictureSelect = function($event: any, picture: any) {
                var element = $event.currentTarget;
                self.$timeout(function() {
                    var active = angular.element(element).hasClass('active');
                    scope.onselect(picture, active);
                });
            };
        }
    }

    static factory(): ng.IDirectiveFactory {
        const directive = ($timeout: ng.ITimeoutService, PerspectiveService: PerspectiveService, PictureItemService: any) => new AutowpThumbnailDirective($timeout, PerspectiveService, PictureItemService);
        directive.$inject = ['$timeout', 'PerspectiveService', 'PictureItemService'];
        return directive;
    }
}

angular.module(Module).directive('autowpThumbnail', AutowpThumbnailDirective.factory());
