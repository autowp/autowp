import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

interface IAutowpCommentsFormDirectiveScope extends ng.IScope {
    onSent: Function;
    typeId: number;
    itemId: number;
    parentId: number|null;
}

class AutowpCommentsFormDirectiveController {

    public invalidParams: any = {};
    public form = {
        message: '',
        moderator_attention: false
    };

    static $inject = ['$scope', '$http'];
    constructor(
        protected $scope: IAutowpCommentsFormDirectiveScope, 
        private $http: ng.IHttpService
    ) {
    }

    public sendMessage() {
        this.invalidParams = {};
        
        let self = this;
        
        this.$http({
            method: 'POST',
            url: '/api/comment',
            data: {
                type_id: this.$scope.typeId,
                item_id: this.$scope.itemId,
                parent_id: this.$scope.parentId,
                moderator_attention: this.form.moderator_attention ? 1 : 0,
                message: this.form.message
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.form.message = '';
            self.form.moderator_attention = false;
            
            var location = response.headers('Location');

            self.$scope.onSent(location);
        }, function(response: ng.IHttpResponse<any>) {
            if (response.status == 400) {
                self.invalidParams = response.data.invalid_params;
            } else {
                notify.response(response);
            }
        });
    }
}

class AutowpCommentsFormDirective implements ng.IDirective {
    public controllerAs = 'ctrl';
    public restrict = 'E';
    public scope = {
        parentId: '=',
        itemId: '=',
        typeId: '=',
        onSent: '='
    };
    public template = require('./template.html');
    public controller = AutowpCommentsFormDirectiveController;
    public bindToController: true;

    static factory(): ng.IDirectiveFactory {
        return () => new AutowpCommentsFormDirective();
    }
}

angular.module(Module).directive('autowpCommentsForm', AutowpCommentsFormDirective.factory());
