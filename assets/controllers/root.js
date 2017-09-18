import angular from 'angular';
import Module from 'app.module';
import PageServiceName from 'services/page';
import 'directives/breadcrumbs';
import notify from 'notify';

const CONTROLLER_NAME = 'RootController';

angular.module(Module).controller(CONTROLLER_NAME, [
    '$scope', '$http', '$location', '$translate', '$rootScope', '$state', PageServiceName,
    function($scope, $http, $location,  $translate, $rootScope, $state, PageService) {
        var that = this;
        
        $scope.languages = opt.languages;
        $scope.path = $location.path();
        
        setSidebars(false);
        
        $scope.user = opt.user;
        $scope.isModer = opt.isModer;
        $scope.newPersonalMessages = opt.sidebar.newPersonalMessages;
        $scope.mainMenu = opt.mainMenu;
        $scope.moderMenu = opt.moderMenu;
        $scope.searchHostname = opt.searchHostname;
        $scope.pageName = null;
        $scope.title = 'WheelsAge';
        $scope.pageId = null;
        $scope.pageEnv = function(data) {
            setSidebars(data.layout.needRight);
            $scope.isAdminPage = data.layout.isAdminPage;
            
            var args = data.args ? data.args : {};
            var preparedUrlArgs = {};
            var preparedNameArgs = {};
            angular.forEach(args, function(value, key) {
                preparedUrlArgs['%' + key + '%'] = encodeURIComponent(value);
                preparedNameArgs['%' + key + '%'] = value;
            });
            
            PageService.setCurrent(data.pageId, preparedNameArgs);
            
            if (data.pageId) {
                var nameKey = 'page/' + data.pageId + '/name';
                var titleKey = 'page/' + data.pageId + '/title';
                $translate([nameKey, titleKey]).then(function (translations) {
                    var name = replaceArgs(translations[nameKey], preparedNameArgs);
                    var title = replaceArgs(translations[titleKey], preparedNameArgs);
                    $scope.pageName = name;
                    $scope.title = title ? title : name;
                }, function () {
                    $scope.pageName = nameKey;
                    $scope.title = titleKey;
                });
            } else {
                $scope.pageName = null;
                $scope.title = data.title ? data.title : null;
            }
        };
        
        $scope.isSecondaryMenuItems = function(page) {
            return [25, 117, 42].indexOf(+page.id) !== -1;
        };
        
        function setSidebars(right) {
            $scope.needRight = right;
            
            $scope.spanRight = right ? 4 : 0;
            $scope.spanCenter = 12 - $scope.spanRight;
        }
        
        function replaceArgs(str, args) {
            angular.forEach(args, function(value, key) {
                str = str.replace(key, value);
            });
            return str;
        }
        
        $rootScope.$on('$stateChangeSuccess', function() {
            document.body.scrollTop = document.documentElement.scrollTop = 0;
        });
        
        $rootScope.$on("$stateChangeError", function (event, toState, toParams, fromState, fromParams, error) {
            switch (error) {
                case 'unauthorized':
                    $state.go('error-403');
                    break;
            }
        });

        $rootScope.loginForm = {
            login: '',
            password: '',
            remember: false
        };
        
        $rootScope.doLogin = function() {
            $http({
                method: 'POST',
                url: '/api/login',
                data: $rootScope.loginForm
            }).then(function() {
                $http({
                    method: 'GET',
                    url: '/api/user/me'
                }).then(function(response) {
                    $scope.user = response.data;
                    $state.go('login-ok');
                }, function(response) {
                    notify.response(response);
                });
                
            }, function(response) {
                if (response.status == 400) {
                    $scope.loginInvalidParams = response.data.invalid_params;
                } else {
                    notify.response(response);
                }
            });
        };
    }
]);

export default CONTROLLER_NAME;
