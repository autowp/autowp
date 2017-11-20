import * as angular from "angular";
import Module from 'app.module';
import notify from 'notify';

import './item';

const CONTROLLER_NAME = 'DonateVodSelectController';
const STATE_NAME = 'donate-vod-select';

export class DonateVodSelectController {
    static $inject = ['$scope', '$translate', '$http', '$state'];
    public page: number;
    public brands: any[];
    public paginator: autowp.IPaginator;
    public brand: any;
    public vehicles: any[];
    public vehicles_paginator: autowp.IPaginator;
    public concepts: any[];
    public selectItem: (itemId: number) => void;
    private date: string;
    private anonymous: boolean;
    public loading: number = 0;
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $translate: ng.translate.ITranslateService,
        private $http: ng.IHttpService,
        private $state: any
    ) {
        this.page = this.$state.params.page || 1;
        this.date = this.$state.params.date;
        this.anonymous = !!this.$state.params.anonymous;
        const brandId = this.$state.params.brand_id;
      
        let self = this;
      
        if (brandId) {
            this.loading++;
            this.$http({
                method: 'GET',
                url: '/api/item/' + brandId
            }).then(function(response: ng.IHttpResponse<any>) {
                self.brand = response.data;
              
                self.loading++;
                self.$http({
                    method: 'GET',
                    url: '/api/item-parent',
                    params: {
                        item_type_id: 1,
                        parent_id: self.brand.id,
                        fields: 'item.name_html,item.childs_count,item.is_compiles_item_of_day',
                        limit: 500,
                        page: 1
                    }
                }).then(function(response: ng.IHttpResponse<autowp.IPaginatedCollection<any>>) {
                    self.vehicles = response.data.items;
                    self.vehicles_paginator = response.data.paginator;
                    self.loading--;
                }, function(response) {
                    notify.response(response);
                    self.loading--;
                });

                self.loading++;
                self.$http({
                    method: 'GET',
                    url: '/api/item-parent',
                    params: {
                        item_type_id: 1,
                        concept: true,
                        ancestor_id: self.brand.id,
                        fields: 'item.name_html,item.childs_count,item.is_compiles_item_of_day',
                        limit: 500,
                        page: 1
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.concepts = response.data.items;
                    self.loading--;
                }, function(response) {
                    notify.response(response);
                    self.loading--;
                });
              
                self.loading--;
              
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
                self.loading--;
            });
        } else {
            this.loading++;
            this.$http({
                method: 'GET',
                url: '/api/item',
                params: {
                    type_id: 5,
                    limit: 500,
                    fields: 'name_only',
                    page: this.page
                }
            }).then(function(response: ng.IHttpResponse<autowp.IPaginatedCollection<any>>) {
                self.brands = self.chunk(response.data.items, 6);
                self.paginator = response.data.paginator;
                self.loading--;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
                self.loading--;
            });
        }
      
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/196/name',
            pageId: 196
        });
      
        this.selectItem = function(itemId: number) {
            self.$state.go('donate-vod', {
                item_id: itemId,
                date: self.date,
                anonymous: self.anonymous ? 1 : null
            });
        }
    }

    private chunk(arr: any[], count: number): any[] {
        var newArr = [];
        var size = Math.ceil(arr.length / count);
        for (var i=0; i<arr.length; i+=size) {
            newArr.push(arr.slice(i, i+size));
        }
        return newArr;
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, DonateVodSelectController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/donate/vod/select-item?anonymous&date&brand_id&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ])
