import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';

var $ = require("jquery");
require('jcrop-0.9.12/css/jquery.Jcrop.css');
require('jcrop-0.9.12/js/jquery.Jcrop');
var sprintf = require("sprintf-js").sprintf;

const CONTROLLER_NAME = 'ModerPicturesItemCropController';
const STATE_NAME = 'moder-pictures-item-crop';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/pictures/{id}/crop',
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
    .controller(CONTROLLER_NAME,
        ['$scope', '$element', '$http', '$state',
            function($scope, $element, $http, $state) {
            
                $scope.pageEnv({
                    layout: {
                        isAdminPage: true,
                        blankPage: false,
                        needRight: false
                    },
                    name: 'page/148/name',
                    pageId: 148
                });
                
                $scope.picture = null;
                $scope.aspect = '';
                $scope.resolution = '';
                
                var $body;
                var jcrop;
                var currentCrop = {
                    w: 0,
                    h: 0,
                    x: 0,
                    y: 0
                };
                var minSize = [400, 300];
                
                $scope.selectAll = function() {
                    jcrop.setSelect([0, 0, $scope.picture.width, $scope.picture.height]);
                };
                
                $scope.saveCrop = function() {
                    
                    $http({
                        method: 'PUT',
                        url: '/api/picture/' + $scope.picture.id,
                        data: {
                            crop: {
                                left:   Math.round(currentCrop.x),
                                top:    Math.round(currentCrop.y),
                                width:  Math.round(currentCrop.w),
                                height: Math.round(currentCrop.h)
                            }
                        }
                    }).then(function() {
                        $state.go('moder-pictures-item', {
                            id: $scope.picture.id
                        });
                    });

                };

                $http({
                    method: 'GET',
                    url: '/api/picture/' + $state.params.id,
                    params: {
                        fields: 'crop,image'
                    }
                }).then(function(response) {
                    $scope.picture = response.data;
                    
                    $body = $($element[0]).find('.crop-area');
                    var $img = $body.find('img');
                    
                    jcrop = null;
                    if ($scope.picture.crop) {
                        currentCrop = {
                            w: $scope.picture.crop.width,
                            h: $scope.picture.crop.height,
                            x: $scope.picture.crop.left,
                            y: $scope.picture.crop.top
                        };
                    } else {
                        currentCrop = {
                            w: $scope.picture.width,
                            h: $scope.picture.height,
                            x: 0,
                            y: 0
                        };
                    }
                    
                    var scale = $scope.picture.width / $body.width(),
                        width = $scope.picture.width / scale,
                        height = $scope.picture.height / scale;
                    
                    $img.css({
                        width: width,
                        height: height
                    }).on('load', function() {
                        
                        // sometimes Jcrop fails without delay
                        setTimeout(function() {
            
                            jcrop = $.Jcrop($img[0], {
                                onSelect: function(c) {
                                    currentCrop = c;
                                    updateSelectionText();
                                },
                                setSelect: [
                                    currentCrop.x,
                                    currentCrop.y,
                                    currentCrop.x + currentCrop.w,
                                    currentCrop.y + currentCrop.h
                                ],
                                minSize: minSize,
                                boxWidth: width,
                                boxHeight: height,
                                trueSize: [$scope.picture.width, $scope.picture.height],
                                keySupport: false
                            });
                            
                        }, 100);
                    });
                });

                function updateSelectionText() {
                    var text = Math.round(currentCrop.w) + '×' + Math.round(currentCrop.h);
                    var pw = 4;
                    var ph = pw * currentCrop.h / currentCrop.w;
                    var phRound = Math.round(ph * 10) / 10;
                    
                    $scope.aspect = pw+':'+phRound;
                    $scope.resolution = text;
                }
            }
        ]
    );

export default CONTROLLER_NAME;
