import * as angular from 'angular';
import Module from 'app.module';
import { PageService } from 'services/page';
import { MessageService } from 'services/message';
import 'directives/breadcrumbs';
import notify from 'notify';

const CONTROLLER_NAME = 'RootController';

function replaceArgs(str: string, args: any): string {
    angular.forEach(args, function(value: string, key: string) {
        str = str.replace(key, value);
    });
    return str;
}

declare global {
    interface Window { opt: any; }
}

export class RootController {
    static $inject = ['$scope', '$http', '$location', '$translate', '$rootScope', '$state', 'PageService', 'MessageService', '$transitions'];



    constructor(
        private $scope: autowp.IRootControllerScope,
        private $http: ng.IHttpService,
        private $location: ng.ILocationService,
        private $translate: ng.translate.ITranslateService,
        private $rootScope: autowp.IRootControllerScope,
        private $state: any,
        private PageService: PageService,
        private MessageService: MessageService,
        private $transitions: any
    ) {
        let opt = window.opt;

        $scope.languages = opt.languages;
        $scope.path = $location.path();

        this.setSidebars(false);

        $scope.user = opt.user;
        $scope.isModer = opt.isModer;
        $scope.newPersonalMessages = opt.sidebar.newPersonalMessages;
        $scope.mainMenu = opt.mainMenu;
        $scope.moderMenu = opt.moderMenu;
        $scope.searchHostname = opt.searchHostname;
        $scope.pageName = null;
        $scope.title = 'WheelsAge';
        $scope.pageId = null;
        $scope.disablePageName = false;

        var self = this;
        $scope.pageEnv = function(data) {
            self.setSidebars(data.layout.needRight);
            $scope.isAdminPage = data.layout.isAdminPage;
            $scope.disablePageName = !!data.disablePageName;

            var args = data.args ? data.args : {};
            var preparedUrlArgs: any = {};
            var preparedNameArgs: any = {};
            angular.forEach(args, function(value: string, key: string) {
                preparedUrlArgs['%' + key + '%'] = encodeURIComponent(value);
                preparedNameArgs['%' + key + '%'] = value;
            });

            PageService.setCurrent(data.pageId, preparedNameArgs);

            if (data.pageId) {
                var nameKey: string;
                var titleKey: string;
                if (data.name) {
                    nameKey = data.name;
                    titleKey = data.title ? data.title : data.name;
                } else {
                    nameKey = 'page/' + data.pageId + '/name';
                    titleKey = 'page/' + data.pageId + '/title';
                }
                $translate([nameKey, titleKey]).then(function (translations: any) {
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

        $scope.isSecondaryMenuItems = function(page: any): boolean {
            return [25, 117, 42].indexOf(+page.id) !== -1;
        };

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

        $transitions.onError({}, function($transition$: any, a: any, b: any) {
            switch ($transition$.error().detail) {
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

        $rootScope.doLogout = function() {
            $http({
                method: 'DELETE',
                url: '/api/login'
            }).then(function() {

                $rootScope.setUser(null);

                $state.go('login');

            }, function(response) {
                notify.response(response);
            });
        };

        $rootScope.refreshNewMessagesCount = function() {
            if ($scope.user) {
                MessageService.getNewCount().then(function(count) {
                    $scope.newPersonalMessages = count;
                }, function(response) {
                    notify.response(response);
                });
            } else {
                $scope.newPersonalMessages = 0;
            }
        };

        $rootScope.setUser = function(user) {
            var lastUserId = $scope.user ? $scope.user.id : null;
            var newUserId = user ? user.id : null;
            $scope.user = user;

            if (lastUserId != newUserId) {
                $rootScope.refreshNewMessagesCount();
            }
        };

        $rootScope.getUser = function() {
            return $scope.user;
        };
    }

    private setSidebars(right: boolean) {
        this.$scope.needRight = right;

        this.$scope.spanRight = right ? 4 : 0;
        this.$scope.spanCenter = 12 - this.$scope.spanRight;
    }
}

angular.module(Module).controller(CONTROLLER_NAME, RootController);

