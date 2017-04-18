import angular from 'angular';
import Module from 'app.module';

const CONTROLLER_NAME = 'RootController';

angular.module(Module).controller(CONTROLLER_NAME, [
    '$scope', '$http', '$location', '$translate', '$rootScope',
    function($scope, $http, $location,  $translate, $rootScope) {
        var that = this;
        
        $scope.languages = opt.languages;
        $scope.path = $location.path();
        
        setSidebars(opt.needLeft, opt.needRight);
        
        $scope.user = true;
        $scope.isModer = opt.isModer;
        $scope.newPersonalMessages = opt.sidebar.newPersonalMessages;
        $scope.mainMenu = opt.mainMenu;
        $scope.moderMenu = opt.moderMenu;
        $scope.searchHostname = opt.searchHostname;
        $scope.pageName = null;
        $scope.title = 'WheelsAge';
        $scope.pageEnv = function(data) {
            setSidebars(data.layout.needLeft, data.layout.needRight);
            $scope.isAdminPage = data.layout.isAdminPage;
            
            var args = data.args ? data.args : {};
            var preparedUrlArgs = {};
            var preparedNameArgs = {};
            angular.forEach(args, function(value, key) {
                preparedUrlArgs['%' + key + '%'] = encodeURIComponent(value);
                preparedNameArgs['%' + key + '%'] = value;
            });
            
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
        
        function setSidebars(left, right) {
            $scope.needLeft = left;
            $scope.needRight = right;
            
            $scope.spanLeft = left ? 4 : 0;
            $scope.spanRight = right ? 4 : 0;
            $scope.spanCenter = 12 - $scope.spanLeft - $scope.spanRight;
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
    }
]);

export default CONTROLLER_NAME;
