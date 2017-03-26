import angular from 'angular';
import Module from 'app.module';
import template from './paginator.html';
import pad from 'pad';

angular.module(Module)
    .directive('autowpPaginator', function() {
        return {
            restirct: 'E',
            scope: {
                data: '='
            },
            template: template,
            transclude: true,
            controller: ['$scope', function($scope) {
                /*$scope.data =  {
                    pageCount: 0,
                    itemCountPerPage: 1,
                    first: 0,
                    current: 0,
                    last: 0,
                    next: 0,
                    pagesInRange: {},
                    firstPageInRange: 0,
                    lastPageInRange: 0,
                    currentItemCount: 0,
                    totalItemCount: 0,
                    firstItemNumber: 0,
                    lastItemNumber: 0
                };*/

                $scope.padd = function(page) {
                    var size = Math.max(2, $scope.data.pageCount.toString().length);
                    return pad(size, page, '0');
                };
            }]
        };
    });