import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';
import USER_SERVICE_NAME from 'services/user';
import $ from 'jquery';

var JsDiff = require('diff');

const CONTROLLER_NAME = 'InfoTextController';
const STATE_NAME = 'info-text';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/info/text/:id?revision',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', USER_SERVICE_NAME,
        function($scope, $http, $state, UserService) {
            
            var ctrl = this;
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/197/name',
                pageId: 197
            });
            
            $http({
                method: 'GET',
                url: '/api/text/' + $state.params.id,
                params: {
                    revision: $state.params.revision
                }
            }).then(function(response) {
                ctrl.current = response.data.current;
                ctrl.prev = response.data.prev;
                ctrl.next = response.data.next;
                
                if (ctrl.current.user_id) {
                    UserService.getUser(ctrl.current.user_id).then(function(user) {
                        ctrl.current.user = user;
                    }, function(response) {
                        notify.response(response);
                    });
                }
                
                if (ctrl.prev.user_id) {
                    UserService.getUser(ctrl.prev.user_id).then(function(user) {
                        ctrl.prev.user = user;
                    }, function(response) {
                        notify.response(response);
                    });
                }
                
                doDiff();
                
            }, function(response) {
                notify.response(response);
            });
            
            function doDiff() {
                
                var $pre = $('pre');
                
                console.log($pre);
                
                var diff = JsDiff.diffChars($pre.eq(0).text(), $pre.eq(1).text());
                
                var fragment = document.createDocumentFragment();
                for (var i=0; i < diff.length; i++) {

                    if (diff[i].added && diff[i + 1] && diff[i + 1].removed) {
                        var swap = diff[i];
                        diff[i] = diff[i + 1];
                        diff[i + 1] = swap;
                    }

                    var node;
                    if (diff[i].removed) {
                        node = document.createElement('del');
                        node.appendChild(document.createTextNode(diff[i].value));
                    } else if (diff[i].added) {
                        node = document.createElement('ins');
                        node.appendChild(document.createTextNode(diff[i].value));
                    } else {
                        node = document.createTextNode(diff[i].value);
                    }
                    fragment.appendChild(node);
                }

                $pre.eq(1).empty().append(fragment);
            };
        }
    ]);

export default CONTROLLER_NAME;
