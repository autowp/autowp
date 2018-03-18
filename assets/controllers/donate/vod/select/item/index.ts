import * as angular from 'angular';
import Module from 'app.module';
import './styles.scss';
import notify from 'notify';

interface IAutowpDonateVodSelectItemDirectiveScope extends ng.IScope {
    item: any;
}

class AutowpDonateVodSelectItemController {

    public childs: any[] = [];
    public loading: boolean = false;

    static $inject = ['$scope', '$http'];
    constructor(
        protected $scope: IAutowpDonateVodSelectItemDirectiveScope,
        private $http: ng.IHttpService
    ) {
    }

    public toggleItem() {
        var self = this;

        this.$scope.item.expanded = !this.$scope.item.expanded;

        if (this.$scope.item.expanded) {
            this.loading = true;
            this.$http({
                method: 'GET',
                url: '/api/item-parent',
                params: {
                    type_id: 1,
                    parent_id: this.$scope.item.item_id,
                    fields: 'item.name_html,item.childs_count,item.is_compiles_item_of_day',
                    limit: 500
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.loading = false;
                self.childs = response.data.items;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
        }
    };
}

class AutowpDonateVodSelectItemDirective implements ng.IDirective {
    public controllerAs = 'ctrl';
    public restrict = 'E';
    public scope = {
        item: '<',
        selectItem: '<'
    };
    public template = require('./template.html');
    public controller = AutowpDonateVodSelectItemController;
    public bindToController: true;

    static factory(): ng.IDirectiveFactory {
        return () => new AutowpDonateVodSelectItemDirective();
    }
}

angular.module(Module).directive('autowpDonateVodSelectItem', AutowpDonateVodSelectItemDirective.factory());
