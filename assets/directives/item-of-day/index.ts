import * as angular from "angular";
import Module from '../../app.module';

interface IAutowpItemOfDayDirectiveScope extends ng.IScope {
    item: any;
    user: any;
    pictures: any[];
}

class AutowpItemOfDayController {

    static $inject = ['$scope'];
    constructor(protected $scope: IAutowpItemOfDayDirectiveScope) {

    }

}

class AutowpItemOfDayDirective implements ng.IDirective {
    public controllerAs = 'ctrl';
    public restrict = 'E';
    public scope = {
        item: '<',
        user: '<',
        pictures: '<'
    };
    public template = require('./template.html');
    public controller = AutowpItemOfDayController;
    public bindToController: true;

    static factory(): ng.IDirectiveFactory {
        return () => new AutowpItemOfDayDirective();
    }
}

angular.module(Module).directive('autowpItemOfDay', AutowpItemOfDayDirective.factory());
