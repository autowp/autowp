import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
var $ = require("jquery");
require('jcrop-0.9.12/css/jquery.Jcrop.css');
require('jcrop-0.9.12/js/jquery.Jcrop');
var sprintf = require("sprintf-js").sprintf;
import { PictureItemService } from 'services/picture-item';
import { AclService } from 'services/acl';

const CONTROLLER_NAME = 'ModerPicturesItemAreaController';
const STATE_NAME = 'moder-pictures-item-area';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/pictures/{id}/area?item_id&type',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                resolve: {
                    access: ['AclService', function (Acl) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME,
        ['$scope', '$element', '$http', '$state', '$q', 'PictureItemService',
            function($scope, $element, $http, $state, $q, PictureItemService) {
            
                $scope.pageEnv({
                    layout: {
                        isAdminPage: true,
                        blankPage: false,
                        needRight: false
                    },
                    name: 'page/148/name',
                    pageId: 148
                });
                
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
                var minSize = [50, 50];
                
                $scope.selectAll = function() {
                    jcrop.setSelect([0, 0, $scope.picture.width, $scope.picture.height]);
                };
                
                $scope.saveCrop = function() {
                    
                    var area = {
                        left:   Math.round(currentCrop.x),
                        top:    Math.round(currentCrop.y),
                        width:  Math.round(currentCrop.w),
                        height: Math.round(currentCrop.h)
                    };
                    
                    PictureItemService.setArea($state.params.id, $state.params.item_id, $state.params.type, area).then(function() {
                        $state.go('moder-pictures-item', {
                            id: $scope.picture.id
                        });
                    }, function() {
                        
                    });
                };

                var getPicturePromise = $http({
                    method: 'GET',
                    url: '/api/picture/' + $state.params.id,
                    params: {
                        fields: 'crop,image'
                    }
                });
                
                var getPictureItemPromise = PictureItemService.get($state.params.id, $state.params.item_id, $state.params.type, {
                    fields: 'area'
                });
                
                $q.all([getPicturePromise, getPictureItemPromise]).then(function(data) {
                    var area = data[1].data.area;
                    
                    var response = data[0];
                    
                    $scope.picture = response.data;
                    
                    $body = $($element[0]).find('.crop-area');
                    var $img = $body.find('img');
                    
                    jcrop = null;
                    if (area) {
                        currentCrop = {
                            w: area.width,
                            h: area.height,
                            x: area.left,
                            y: area.top
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
                }, function() {
                    $state.go('error-404');
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
