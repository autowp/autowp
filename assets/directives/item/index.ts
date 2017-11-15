import * as angular from "angular";
import Module from 'app.module';
import './styles.less';
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
        angular.forEach(item.preview_pictures, function(picture: any) {
            if (picture.thumbnail) {
                found = true;
                return false;
            }
        });
        return found;
    }
  
    public canHavePhoto(item: any) {
        return [1, 2, 5, 6, 7].indexOf(item.item_type_id) != -1;
    };
  
    public thumbnailClasses(picture: any, $index: number) {
        
        var thumbColumns = 4;
        var singleThumbPart = Math.round(12 / thumbColumns);
        
        var classes: any = {};
        var col = picture.large && $index === 0  ? 2*singleThumbPart : singleThumbPart;
        var colSm = picture.large && $index === 0  ? 12 : 6;
        
        classes['col-md-'+col] = true;
        classes['col-sm-'+colSm] = true;
        
        return classes;
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
        const directive = () => new AutowpItemDirective();
        directive.$inject = ['AclService'];
        return directive;
    }
}

angular.module(Module).directive('autowpItem', AutowpItemDirective.factory());
