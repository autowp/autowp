import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import PERSPECTIVE_SERVICE from 'services/perspective';
import PICTURE_ITEM_SERVICE from 'services/picture-item';
import ACL_SERVICE_NAME from 'services/acl';
import './crop';
import './move';
import './area';
var sprintf = require("sprintf-js").sprintf;

const CONTROLLER_NAME = 'ModerPicturesItemController';
const STATE_NAME = 'moder-pictures-item';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/pictures/{id}',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                resolve: {
                    access: [ACL_SERVICE_NAME, function (Acl) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', '$q', '$translate', '$element', PERSPECTIVE_SERVICE, PICTURE_ITEM_SERVICE,
        function($scope, $http, $state, $q, $translate, $element, PerspectiveService, PictureItemService) {
            
            var that = this;
            
            $scope.picture = null;
            $scope.last_item = null;
            $scope.specialNameLoading = false;
            $scope.copyrightsLoading = false;
            $scope.statusLoading = false;
            $scope.repairLoading = false;
            $scope.similarLoading = false;
            $scope.pictureItemLoading = false;
            $scope.replaceLoading = false;
            
            $scope.banPeriods = {
                1: 'ban/period/hour',
                2: 'ban/period/2-hours',
                4: 'ban/period/4-hours',
                8: 'ban/period/8-hours',
                16: 'ban/period/16-hours',
                24: 'ban/period/day',
                48: 'ban/period/2-days'
            };
            that.banPeriod = 1;
            that.banReason = null;
            
            function loadPicture(callback) {
                $http({
                    method: 'GET',
                    url: '/api/picture/' + $state.params.id,
                    params: {
                        fields: ['owner', 'thumbnail', 'add_date', 'iptc', 'exif', 'image', 
                            'items.item.name_html', 'items.item.brands.name_html',
                            'special_name', 'copyrights', 'change_status_user',
                            'rights', 'moder_votes', 'moder_voted', 'is_last', 'views',
                            'accepted_count', 'similar.picture.thumbnail',
                            'replaceable', 'siblings.name_text', 'ip.rights', 'ip.blacklist'].join(',')
                    }
                }).then(function(response) {
                    $scope.picture = response.data;
                    
                    if (callback) {
                        callback();
                    }
                }, function() {
                    $state.go('error-404');
                });
                
                $http({
                    method: 'GET',
                    url: '/api/item',
                    params: {
                        last_item: 1,
                        fields: 'name_html',
                        limit: 1
                    }
                }).then(function(response) {
                    $scope.last_item = response.data.items.length ? response.data.items[0] : null;
                });
            }
            
            loadPicture(function() {
                $translate('moder/picture/picture-n-%s').then(function(translation) {
                    $scope.pageEnv({
                        layout: {
                            isAdminPage: true,
                            blankPage: false,
                            needRight: false
                        },
                        pageId: 72,
                        args: {
                            PICTURE_ID:   $scope.picture.id,
                            PICTURE_NAME: sprintf(translation, $scope.picture.id)
                        }
                    });
                });
            });
            
            $scope.perspectives = [];
            PerspectiveService.getPerspectives().then(function(perspectives) {
                $scope.perspectives = perspectives;
            });
            
            $scope.hasItem = function(itemId) {
                var found = false;
                angular.forEach($scope.picture.items, function(item) {
                    if (item.item_id == itemId) {
                        found = true;
                    }
                });
                
                return found;
            };
            
            $scope.addItem = function(itemId) {
                $scope.pictureItemLoading = true;
                PictureItemService.create($state.params.id, itemId, 1, {}).then(function() {
                    loadPicture(function() {
                        $scope.pictureItemLoading = false;
                    });
                }, function() {
                    $scope.pictureItemLoading = false;
                });
            };
            
            $scope.moveItem = function(type, srcItemId, dstItemId) {
                $scope.pictureItemLoading = true;
                PictureItemService.changeItem($state.params.id, type, srcItemId, dstItemId).then(function() {
                    loadPicture(function() {
                        $scope.pictureItemLoading = false;
                    });
                }, function() {
                    $scope.pictureItemLoading = false;
                });
            };
            
            $scope.saveSpecialName = function() {
                $scope.specialNameLoading = true;
                $http({
                    method: 'PUT',
                    url: '/api/picture/' + $state.params.id,
                    data: {
                        special_name: $scope.picture.special_name
                    }
                }).then(function(response) {
                    $scope.specialNameLoading = false;
                }, function() {
                    $scope.specialNameLoading = false;
                });
            };
            
            $scope.saveCopyrights = function() {
                $scope.copyrightsLoading = true;
                $http({
                    method: 'PUT',
                    url: '/api/picture/' + $state.params.id,
                    data: {
                        copyrights: $scope.picture.copyrights
                    }
                }).then(function(response) {
                    $scope.copyrightsLoading = false;
                }, function() {
                    $scope.copyrightsLoading = false;
                });
            };
            
            $scope.unacceptPicture = function() {
                $scope.statusLoading = true;
                $http({
                    method: 'PUT',
                    url: '/api/picture/' + $state.params.id,
                    data: {
                        status: 'inbox'
                    }
                }).then(function(response) {
                    loadPicture(function() {
                        $scope.statusLoading = false;
                    });
                }, function() {
                    $scope.statusLoading = false;
                });
            };
            
            $scope.acceptPicture = function() {
                $scope.statusLoading = true;
                $http({
                    method: 'PUT',
                    url: '/api/picture/' + $state.params.id,
                    data: {
                        status: 'accepted'
                    }
                }).then(function(response) {
                    loadPicture(function() {
                        $scope.statusLoading = false;
                    });
                }, function() {
                    $scope.statusLoading = false;
                });
            };
            
            $scope.deletePicture = function() {
                $scope.statusLoading = true;
                $http({
                    method: 'PUT',
                    url: '/api/picture/' + $state.params.id,
                    data: {
                        status: 'removing'
                    }
                }).then(function(response) {
                    loadPicture(function() {
                        $scope.statusLoading = false;
                    });
                }, function() {
                    $scope.statusLoading = false;
                });
            };
            
            $scope.restorePicture = function() {
                $scope.statusLoading = true;
                $http({
                    method: 'PUT',
                    url: '/api/picture/' + $state.params.id,
                    data: {
                        status: 'inbox'
                    }
                }).then(function(response) {
                    loadPicture(function() {
                        $scope.statusLoading = false;
                    });
                }, function() {
                    $scope.statusLoading = false;
                });
            };
            
            $scope.pictureVoted = function() {
                loadPicture();
            };
            
            $scope.normalizePicture = function() {
                $scope.repairLoading = true;
                $http({
                    method: 'PUT',
                    url: '/api/picture/' + $state.params.id + '/normalize'
                }).then(function(response) {
                    loadPicture(function() {
                        $scope.repairLoading = false;
                    });
                }, function() {
                    $scope.repairLoading = false;
                });
            };
            
            $scope.flopPicture = function() {
                $scope.repairLoading = true;
                $http({
                    method: 'PUT',
                    url: '/api/picture/' + $state.params.id + '/flop'
                }).then(function(response) {
                    loadPicture(function() {
                        $scope.repairLoading = false;
                    });
                }, function() {
                    $scope.repairLoading = false;
                });
            };
            
            $scope.repairPicture = function() {
                $scope.repairLoading = true;
                $http({
                    method: 'PUT',
                    url: '/api/picture/' + $state.params.id + '/repair'
                }).then(function(response) {
                    loadPicture(function() {
                        $scope.repairLoading = false;
                    });
                }, function() {
                    $scope.repairLoading = false;
                });
            };
            
            $scope.correctFileNames = function() {
                $scope.repairLoading = true;
                $http({
                    method: 'PUT',
                    url: '/api/picture/' + $state.params.id + '/correct-file-names'
                }).then(function(response) {
                    loadPicture(function() {
                        $scope.repairLoading = false;
                    });
                }, function() {
                    $scope.repairLoading = false;
                });
            };

            $scope.cancelSimilar = function() {
                $scope.similarLoading = true;
                $http({
                    method: 'DELETE',
                    url: '/api/picture/' + $state.params.id + '/similar/' + $scope.picture.similar.picture_id
                }).then(function(response) {
                    loadPicture(function() {
                        $scope.similarLoading = false;
                    });
                }, function() {
                    $scope.similarLoading = false;
                });
            };
            
            $scope.savePerspective = function(item) {
                PictureItemService.setPerspective(
                    item.picture_id,
                    item.item_id,
                    item.type,
                    item.perspective_id
                );
            };
            
            $scope.deletePictureItem = function(item) {
                $scope.pictureItemLoading = true;
                PictureItemService.remove(item.picture_id, item.item_id, item.type).then(function() {
                    loadPicture(function() {
                        $scope.pictureItemLoading = false;
                    });
                }, function() {
                    $scope.pictureItemLoading = false;
                });
            };
            
            $scope.cancelReplace = function() {
                $scope.replaceLoading = true;
                $http({
                    method: 'PUT',
                    url: '/api/picture/' + $state.params.id,
                    data: {
                        replace_picture_id: ''
                    }
                }).then(function(response) {
                    loadPicture(function() {
                        $scope.replaceLoading = false;
                    });
                }, function() {
                    $scope.replaceLoading = false;
                });
            };
            
            $scope.acceptReplace = function() {
                $scope.replaceLoading = true;
                $http({
                    method: 'PUT',
                    url: '/api/picture/' + $state.params.id + '/accept-replace'
                }).then(function(response) {
                    loadPicture(function() {
                        $scope.replaceLoading = false;
                    });
                }, function() {
                    $scope.replaceLoading = false;
                });
            };
            
            $scope.removeFromBlacklist = function(ip) {
                $http({
                    method: 'DELETE',
                    url: '/api/traffic/blacklist/' + ip
                }).then(function(response) {
                    loadPicture();
                });
            };
            
            $scope.addToBlacklist = function(ip) {
                $http({
                    method: 'POST',
                    url: '/api/traffic/blacklist',
                    data: {
                        ip: ip,
                        period: that.banPeriod,
                        reason: that.banReason
                    }
                }).then(function(response) {
                    loadPicture();
                });
            };
        }
    ]);

export default CONTROLLER_NAME;
