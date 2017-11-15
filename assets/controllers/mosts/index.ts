import * as angular from "angular";
import Module from 'app.module';
import { MostsService } from 'services/mosts';
require('services/mosts');
import { IAutowpControllerScope } from 'declarations.d.ts';
import notify from 'notify';
var $ = require('jquery');

const CONTROLLER_NAME = 'MostsController';
const STATE_NAME = 'mosts';

class MostsController {
    static $inject = ['$scope', '$http', '$state', 'MostsService', '$translate'];
    public items: any[];
    public years: any[];
    public ratings: any[];
    public vehilceTypes: any[];
    public loading: number;
    private ratingCatname: string;
    private typeCatname: string;
    private yearsCatname: string;
  
    constructor(
        private $scope: IAutowpControllerScope,
        private $http: ng.IHttpService,
        private $state: any,
        private MostsService: MostsService,
        private $translate: ng.translate.ITranslateService
    ) {
        this.ratingCatname = this.$state.params.rating_catname;
        this.typeCatname = this.$state.params.type_catname;
        this.yearsCatname = this.$state.params.years_catname;
      
        var self = this;
      
        this.loading++;
        this.MostsService.getMenu().then(function(data: any) {
            self.years = data.years;
            self.ratings = data.ratings;
            self.vehilceTypes = data.vehilce_types;
          
            if (! self.ratingCatname) {
                self.ratingCatname = self.ratings[0].catname;
            }
          
            if (! self.typeCatname) {
                self.typeCatname = self.vehilceTypes[0].catname;
            }
          
            var ratingName = 'most/' + self.ratingCatname;
            if (self.typeCatname) {
              
                var typeName = self.getVehicleTypeName(self.typeCatname);

                if (self.yearsCatname) {
                  
                    var yearName: string = '';
                    for (let year of self.years) {
                        if (year.catname == self.yearsCatname) {
                            yearName = year.name;
                        }
                    }
                
                    self.$translate([ratingName, typeName, yearName]).then(function (translations: any) {
                        self.initPageEnv(156, {
                            MOST_CATNAME: self.ratingCatname,
                            MOST_NAME: translations[ratingName],
                            CAR_TYPE_CARNAME: self.typeCatname,
                            CAR_TYPE_NAME: translations[typeName],
                            YEAR_CATNAME: self.yearsCatname,
                            YEAR_NAME: translations[yearName]
                        });
                    });
                } else {
                    self.$translate([ratingName, typeName]).then(function (translations: any) {
                        self.initPageEnv(155, {
                            MOST_CATNAME: self.ratingCatname,
                            MOST_NAME: translations[ratingName],
                            CAR_TYPE_CARNAME: self.typeCatname,
                            CAR_TYPE_NAME: translations[typeName]
                        });
                    });
                }
            } else {
                self.$translate(ratingName).then(function (translation: string) {
                    self.initPageEnv(154, {
                        MOST_CATNAME: self.ratingCatname,
                        MOST_NAME: translation
                    });
                });
            }
          
            self.loading--;
          
        }, function(response: ng.IHttpResponse<any>) {
            self.loading--;
            notify.response(response);
        });
      
        this.loading++;
        this.$http({
            method: 'GET',
            url: '/api/mosts/items',
            params: {
                rating_catname: self.ratingCatname,
                type_catname: self.typeCatname,
                years_catname: self.yearsCatname
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.items = response.data.items;
            self.loading--;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
            self.loading--;
        });
      
        $('small.unit').tooltip({
            placement: 'bottom'
        });
    }
  
    private getVehicleTypeName(catname: string): string
    {
        var result: string = '';
        for (const vehilceType of this.vehilceTypes) {
            if (vehilceType.catname == catname) {
                result = vehilceType.name;
                break;
            }
          
            for (const subVehilceType of vehilceType.childs) {
                if (subVehilceType.catname == catname) {
                    result = subVehilceType.name;
                    break;
                }
            }
          
            if (result) {
                break;
            }
        }
      
        return result;
    }
  
    private initPageEnv(pageId: number, args: any)
    {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/' + pageId + '/name',
            pageId: pageId,
            args: args
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, MostsController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/mosts/:rating_catname/:type_catname/:years_catname',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                params: {
                    rating_catname: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    },
                    type_catname: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    },
                    years_catname: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    },
                }
            });
        }
    ])
