import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import * as ngFileUpload from 'ng-file-upload';

const CONTROLLER_NAME = 'AccountProfileController';
const STATE_NAME = 'account-profile';

export class AccountProfileController {
    static $inject = ['$scope', '$translate', '$http', '$state', '$timeout', 'Upload'];
  
    public profile = {
        name: null
    };
    public profileInvalidParams: any = {};
    public settings = {
        timezone: null,
        language: null
    };
    public settingsInvalidParams: any = {};
    public photoInvalidParams: any = {};
    public votesPerDay: number|null = null;
    public votesLeft: number|null = null;
    public photo: any;
    public timezones: any[];
    public languages: any[];
    public file: any;
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $translate: ng.translate.ITranslateService,
        private $http: ng.IHttpService,
        private $state: any,
        private $timeout: ng.ITimeoutService, 
        private Upload: any
    ) {
        if (! this.$scope.user) {
            this.$state.go('login');
            return;
        }
        
        
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/129/name',
            pageId: 129
        });
        
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/user/me',
            params: {
                fields: 'name,timezone,language,votes_per_day,votes_left,img'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.profile.name = response.data.name;
            self.settings.timezone = response.data.timezone;
            self.settings.language = response.data.language;
            self.votesPerDay = response.data.votes_per_day;
            self.votesLeft = response.data.votes_left;
            self.photo = response.data.img;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
        
        $http({
            method: 'GET',
            url: '/api/timezone'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.timezones = response.data.items;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
        
        $http({
            method: 'GET',
            url: '/api/language'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.languages = response.data.items;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
        
        
    }
    
    public sendProfile() {
        
        this.profileInvalidParams = {};
        
        var self = this;
        
        this.$http({
            method: 'PUT',
            url: '/api/user/me',
            data: this.profile
        }).then(function() {
            
            self.$scope.user.name = self.profile.name;
            
            self.$translate('account/profile/saved').then(function(translation: string) {
                notify({
                    icon: 'fa fa-check',
                    message: translation
                }, {
                    type: 'success'
                });
            });
            
        }, function(response: ng.IHttpResponse<any>) {
            if (response.status == 400) {
                self.profileInvalidParams = response.data.invalid_params;
            } else {
                notify.response(response);
            }
        });
    }
    
    public sendSettings() {
        
        this.settingsInvalidParams = {};
        
        var self = this;
        
        this.$http({
            method: 'PUT',
            url: '/api/user/me',
            data: this.settings
        }).then(function() {
            
            self.$translate('account/profile/saved').then(function(translation: string) {
                notify({
                    icon: 'fa fa-check',
                    message: translation
                }, {
                    type: 'success'
                });
            });
            
        }, function(response: ng.IHttpResponse<any>) {
            if (response.status == 400) {
                self.settingsInvalidParams = response.data.invalid_params;
            } else {
                notify.response(response);
            }
        });
    }
    
    public uploadFiles(file: any, errFiles: any) {
        this.file = file;
        if (file) {
            
            this.photoInvalidParams = {};
            this.file.progress = 0;
            
            file.upload = this.Upload.upload({
                url: '/api/user/me/photo',
                data: {file: file}
            });
            
            var self = this;

            file.upload.then(function(response: any) {
                self.file.progress = 0;
                self.$timeout(function() {
                    file.result = response.data;
                    
                    self.$http({
                        method: 'GET',
                        url: '/api/user/me',
                        params: {
                            fields: 'img'
                        }
                    }).then(function(response: ng.IHttpResponse<any>) {
                        self.photo = response.data.img;
                    }, function(response: ng.IHttpResponse<any>) {
                        notify.response(response);
                    });
                    
                });
            }, function (response: any) {
                if (response.status > 0) {
                    if (response.status == 400) {
                        self.photoInvalidParams = response.data.invalid_params;
                    } else {
                        notify.response(response);
                    }
                }
                self.file.progress = 0;
            }, function (evt: any) {
                file.progress = Math.min(100, Math.round(100.0 * evt.loaded / evt.total));
            });
        }
        
        //account/profile/photo/saved
    }
    
    public resetPhoto() {
        var self = this;
        this.$http({
            method: 'DELETE',
            url: '/api/user/me/photo',
        }).then(function() {
            
            self.$scope.user.avatar = null;
            self.photo = null;
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, AccountProfileController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/profile',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);

