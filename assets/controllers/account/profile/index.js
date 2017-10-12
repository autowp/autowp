import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';
import ngFileUpload from 'ng-file-upload';

const CONTROLLER_NAME = 'AccountProfileController';
const STATE_NAME = 'account-profile';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/profile',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$state', '$scope', '$http', '$translate', '$timeout', 'Upload',
        function($state, $scope, $http, $translate, $timeout, Upload) {
            
            var ctrl = this;
            
            if (! $scope.user) {
                $state.go('login');
                return;
            }
            
            ctrl.profile = {
                name: null
            };
            ctrl.profileInvalidParams = {};
            
            ctrl.settings = {
                timezone: null,
                language: null
            };
            ctrl.settingsInvalidParams = {};
            
            ctrl.photoInvalidParams = {};
            
            ctrl.votesPerDay = null;
            ctrl.votesLeft = null;
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/129/name',
                pageId: 129
            });
            
            $http({
                method: 'GET',
                url: '/api/user/me',
                params: {
                    fields: 'name,timezone,language,votes_per_day,votes_left,img'
                }
            }).then(function(response) {
                ctrl.profile.name = response.data.name;
                ctrl.settings.timezone = response.data.timezone;
                ctrl.settings.language = response.data.language;
                ctrl.votesPerDay = response.data.votes_per_day;
                ctrl.votesLeft = response.data.votes_left;
                ctrl.photo = response.data.img;
            }, function(response) {
                notify.response(response);
            });
            
            $http({
                method: 'GET',
                url: '/api/timezone'
            }).then(function(response) {
                ctrl.timezones = response.data.items;
            }, function(response) {
                notify.response(response);
            });
            
            $http({
                method: 'GET',
                url: '/api/language'
            }).then(function(response) {
                ctrl.languages = response.data.items;
            }, function(response) {
                notify.response(response);
            });
            
            ctrl.sendProfile = function() {
                
                ctrl.profileInvalidParams = {};
                
                $http({
                    method: 'PUT',
                    url: '/api/user/me',
                    data: ctrl.profile
                }).then(function() {
                    
                    $scope.user.name = ctrl.profile.name;
                    
                    $translate('account/profile/saved').then(function(translation) {
                        notify({
                            icon: 'fa fa-check',
                            message: translation
                        }, {
                            type: 'success'
                        });
                    });
                    
                }, function(response) {
                    if (response.status == 400) {
                        ctrl.profileInvalidParams = response.data.invalid_params;
                    } else {
                        notify.response(response);
                    }
                });
            };
            
            ctrl.sendSettings = function() {
                
                ctrl.settingsInvalidParams = {};
                
                $http({
                    method: 'PUT',
                    url: '/api/user/me',
                    data: ctrl.settings
                }).then(function() {
                    
                    $translate('account/profile/saved').then(function(translation) {
                        notify({
                            icon: 'fa fa-check',
                            message: translation
                        }, {
                            type: 'success'
                        });
                    });
                    
                }, function(response) {
                    if (response.status == 400) {
                        ctrl.settingsInvalidParams = response.data.invalid_params;
                    } else {
                        notify.response(response);
                    }
                });
            };
            
            ctrl.uploadFiles = function(file, errFiles) {
                ctrl.file = file;
                if (file) {
                    
                    ctrl.photoInvalidParams = {};
                    ctrl.file.progress = 0;
                    
                    file.upload = Upload.upload({
                        url: '/api/user/me/photo',
                        data: {file: file}
                    });

                    file.upload.then(function(response) {
                        ctrl.file.progress = 0;
                        $timeout(function() {
                            file.result = response.data;
                            
                            $http({
                                method: 'GET',
                                url: '/api/user/me',
                                params: {
                                    fields: 'img'
                                }
                            }).then(function(response) {
                                ctrl.photo = response.data.img;
                            }, function(response) {
                                notify.response(response);
                            });
                            
                        });
                    }, function (response) {
                        if (response.status > 0) {
                            if (response.status == 400) {
                                ctrl.photoInvalidParams = response.data.invalid_params;
                            } else {
                                notify.response(response);
                            }
                        }
                        ctrl.file.progress = 0;
                    }, function (evt) {
                        file.progress = Math.min(100, parseInt(100.0 * evt.loaded / evt.total));
                    });
                }
                
                //account/profile/photo/saved
            };
            
            ctrl.resetPhoto = function() {
                $http({
                    method: 'DELETE',
                    url: '/api/user/me/photo',
                }).then(function() {
                    
                    $scope.user.avatar = null;
                    ctrl.photo = null;
                    
                }, function(response) {
                    notify.response(response);
                });
            };
        }
    ]);

export default CONTROLLER_NAME;
