import angular from 'angular';
import Module from 'app.module';

const CONTROLLER_NAME = 'RootController';

angular.module(Module).controller(CONTROLLER_NAME, [
    '$scope', '$http', '$location', '$translate',
    function($scope, $http, $location,  $translate) {
        var that = this;
        
        $scope.languages = opt.languages;
        $scope.path = $location.path();
        
        setSidebars(opt.needLeft, opt.needRight);
        
        $scope.user = true;
        $scope.newPersonalMessages = opt.sidebar.newPersonalMessages;
        $scope.mainMenu = opt.mainMenu;
        $scope.moderMenu = opt.moderMenu;
        $scope.searchHostname = opt.searchHostname;
        $scope.pageName = null;
        $scope.title = 'WheelsAge';
        $scope.pageEnv = function(data) {
            setSidebars(data.layout.needLeft, data.layout.needRight);
            $scope.isAdminPage = data.layout.isAdminPage;
            
            var nameKey = 'page/' + data.pageId + '/name';
            var titleKey = 'page/' + data.pageId + '/title';
            $translate([nameKey, titleKey]).then(function (translations) {
                var name = translations[nameKey];
                var title = translations[titleKey];
                $scope.pageName = name;
                $scope.title = title ? title : name;
            }, function () {
                $scope.pageName = nameKey;
                $scope.title = titleKey;
            });
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
    }
]);

export default CONTROLLER_NAME;
