import * as angular from "angular";
import Module from 'app.module';
import './styles.scss';
import { AclService } from 'services/acl';


interface IAutowpItemDirectiveScope extends ng.IScope {
    item: any;
}

class AutowpItemController {

    public is_moder: boolean = false;

    static $inject = ['$scope', 'AclService'];
    constructor(protected $scope: IAutowpItemDirectiveScope, private AclService: AclService) {
        var self = this;

        this.AclService.inheritsRole('moder').then(function(inherits) {
            self.is_moder = !!inherits;
        }, function() {
            self.is_moder = false;
        });
    }

    public havePhoto(item: any) {
        var found = false;
        angular.forEach(item.preview_pictures, function(picture: autowp.IPreviewPicture) {
            if (picture.picture) {
                found = true;
                return false;
            }
        });
        return found;
    }

    public canHavePhoto(item: any) {
        return [1, 2, 5, 6, 7].indexOf(item.item_type_id) != -1;
    };
}

class AutowpItemDirective implements ng.IDirective {
    public controllerAs = 'ctrl';
    public restrict = 'E';
    public scope = {
        item: '=',
        disableTitle: '=',
        disableDescription: '=',
        disableDetailsLink: '='
    };
    public template = require('./template.html');
    public controller = AutowpItemController;
    public bindToController: true;

    static factory(): ng.IDirectiveFactory {
        return () => new AutowpItemDirective();
    }
}

angular.module(Module).directive('autowpItem', AutowpItemDirective.factory());