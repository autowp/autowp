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
    static $inject = ['$scope', '$http', '$state', 'MostsService'];
    public items: any[];
    public years: any[];
    public ratings: any[];
    public vehilceTypes: any[];
    private ratingCatname: string;
    private typeCatname: string;
    private yearsCatname: string;
  
    constructor(
        private $scope: IAutowpControllerScope,
        private $http: ng.IHttpService,
        private $state: any,
        private MostsService: MostsService
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/156/name',
            pageId: 156
        });
      
        this.ratingCatname = this.$state.params.rating_catname;
        this.typeCatname = this.$state.params.type_catname;
        this.yearsCatname = this.$state.params.years_catname;
      
        var self = this;
      
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
          
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
      
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
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
      
        $('small.unit').tooltip({
            placement: 'bottom'
        });
      
        /*
        if ($this->carType) {
        if ($this->cYear) {
            $this->pageEnv([
                'layout' => $layout,
                'pageId' => 156,
                'args'   => [
                    'MOST_CATNAME'     => $this->cMost['catName'],
                    'MOST_NAME'        => $this->translate('most/'.$this->cMost['catName']),
                    'CAR_TYPE_NAME'    => $this->translate($this->carType['name_rp']),
                    'CAR_TYPE_CARNAME' => $this->carType['catname'],
                    'YEAR_NAME'        => $this->translate($this->cYear['name']),
                    'YEAR_CATNAME'     => $this->cYear['folder'],
                ]
            ]);
        } else {
            $this->pageEnv([
                'layout' => $layout,
                'pageId' => 155,
                'args'   => [
                    'MOST_CATNAME'     => $this->cMost['catName'],
                    'MOST_NAME'        => $this->translate('most/'.$this->cMost['catName']),
                    'CAR_TYPE_NAME'    => $this->translate($this->carType['name_rp']),
                    'CAR_TYPE_CARNAME' => $this->carType['catname']
                ]
            ]);
        }
    } else {
        $this->pageEnv([
            'layout' => $layout,
            'pageId' => 154,
            'args'   => [
                'MOST_CATNAME' => $this->cMost['catName'],
                'MOST_NAME'    => $this->translate('most/'.$this->cMost['catName']),
            ]
        ]);
    }*/ 
        
      
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
