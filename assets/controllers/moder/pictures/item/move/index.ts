import * as angular from 'angular';
import Module from 'app.module';
import './item'; // directive
import { PictureItemService } from 'services/picture-item';
import { AclService } from 'services/acl';
import notify from 'notify';
import { chunk } from 'chunk';

const CONTROLLER_NAME = 'ModerPicturesItemMoveController';
const STATE_NAME = 'moder-pictures-item-move';

export class ModerPicturesItemMoveController {
    static $inject = ['$scope', '$http', '$state', '$q', 'PictureItemService'];

    private search: string;
    public selectItem: Function;
    public concepts_expanded: boolean = false;
    public doSearch: Function;
    private src_item_id: number;
    private page: number;
    public src_type: number;
    public show_museums: boolean;
    public show_factories: boolean;
    public show_persons: boolean;
    public show_authors: boolean;
    public show_copyrights: boolean;
    public museums_paginator: autowp.IPaginator;
    public factories_paginator: autowp.IPaginator;
    public brands_paginator: autowp.IPaginator;
    public authors_paginator: autowp.IPaginator;
    public brand_id: number;
    public museums: any[] = [];
    public factories: any[] = [];
    public vehicles: any[] = [];
    public engines: any[] = [];
    public authors: any[] = [];
    private authorsCanceler: any;
    private personsCanceler: any;
    private copyrightsCanceler: any;
    public persons_paginator: autowp.IPaginator;
    public persons: any[] = [];
    public concepts: any[] = [];
    public brands: any[] = [];
    public copyrights: any[] = [];
    public copyrights_paginator: autowp.IPaginator;

    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $state: any,
        private $q: ng.IQService,
        private PictureItemService: PictureItemService
    ) {
        var self = this;

        this.$scope.pageEnv( {
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/149/name',
            pageId: 149
        } );

        this.page = this.$state.params.page;
        this.src_item_id = this.$state.params.src_item_id;
        this.src_type = this.$state.params.src_type;

        this.show_museums = this.$state.params.show_museums;
        this.show_factories = this.$state.params.show_factories;
        this.show_persons = this.$state.params.show_persons;
        this.show_authors = this.$state.params.show_authors;
        this.show_copyrights = this.$state.params.show_copyrights;
        this.brand_id = this.$state.params.brand_id;

        if ( this.src_type == 2 ) {
            this.show_authors = true;
        }

        if ( this.src_type == 3 ) {
            this.show_copyrights = true;
        }

        if ( this.show_museums ) {
            $http( {
                method: 'GET',
                url: '/api/item',
                params: {
                    type_id: 7,
                    fields: 'name_html',
                    limit: 50,
                    page: self.page
                }
            } ).then( function( response: ng.IHttpResponse<any> ) {
                self.museums = response.data.items;
                self.museums_paginator = response.data.paginator;
            }, function( response: ng.IHttpResponse<any> ) {
                notify.response( response );
            } );
        }

        if ( this.show_factories ) {
            $http( {
                method: 'GET',
                url: '/api/item',
                params: {
                    type_id: 6,
                    fields: 'name_html',
                    limit: 50,
                    page: self.page
                }
            } ).then( function( response: ng.IHttpResponse<any> ) {
                self.factories = response.data.items;
                self.factories_paginator = response.data.paginator;
            }, function( response: ng.IHttpResponse<any> ) {
                notify.response( response );
            } );
        }

        if ( this.show_persons ) {
            this.doSearch = function() {
                self.loadPersons();
            };

            this.loadPersons();
        }

        if ( this.show_authors ) {

            this.doSearch = function() {
                self.loadAuthors();
            };

            this.loadAuthors();
        }

        if (this.show_copyrights) {
            this.doSearch = function() {
                self.loadCopyrights();
            };

            this.loadCopyrights();
        }

        if ( !this.show_museums && !this.show_factories && !this.show_persons && !this.show_authors && !this.show_copyrights ) {
            if ( this.brand_id ) {
                $http( {
                    method: 'GET',
                    url: '/api/item-parent',
                    params: {
                        item_type_id: 1,
                        parent_id: this.brand_id,
                        fields: 'item.name_html,item.childs_count',
                        limit: 500,
                        page: 1
                    }
                } ).then( function( response: ng.IHttpResponse<any> ) {
                    self.vehicles = response.data.items;
                }, function( response: ng.IHttpResponse<any> ) {
                    notify.response( response );
                } );
                $http( {
                    method: 'GET',
                    url: '/api/item-parent',
                    params: {
                        item_type_id: 2,
                        parent_id: this.brand_id,
                        fields: 'item.name_html,item.childs_count',
                        limit: 500,
                        page: 1
                    }
                } ).then( function( response: ng.IHttpResponse<any> ) {
                    self.engines = response.data.items;
                }, function( response: ng.IHttpResponse<any> ) {
                    notify.response( response );
                } );

                $http( {
                    method: 'GET',
                    url: '/api/item-parent',
                    params: {
                        item_type_id: 1,
                        concept: true,
                        ancestor_id: this.brand_id,
                        fields: 'item.name_html,item.childs_count',
                        limit: 500,
                        page: 1
                    }
                } ).then( function( response: ng.IHttpResponse<any> ) {
                    self.concepts = response.data.items;
                }, function( response: ng.IHttpResponse<any> ) {
                    notify.response( response );
                } );

            } else {

                self.doSearch = function() {
                    self.loadBrands();
                };

                self.loadBrands();
            }
        }

        this.selectItem = function( itemId: number, perspectiveId: number, type: number ) {
            if ( self.src_item_id && self.src_type ) {
                PictureItemService.changeItem( self.$state.params.id, self.src_type, self.src_item_id, itemId ).then( function() {
                    if ( Number.isInteger( perspectiveId ) ) {
                        PictureItemService.setPerspective( self.$state.params.id, self.src_type, itemId, perspectiveId ).then( function() {
                            self.$state.go( 'moder-pictures-item', {
                                id: self.$state.params.id
                            } );
                        } );
                    } else {
                        self.$state.go( 'moder-pictures-item', {
                            id: self.$state.params.id
                        } );
                    }
                } );
            } else {
                var data = {
                    perspective_id: perspectiveId ? perspectiveId : null
                };

                PictureItemService.create( self.$state.params.id, itemId, type, data ).then( function() {
                    self.$state.go( 'moder-pictures-item', {
                        id: self.$state.params.id
                    } );
                } );
            }
        };
    }

    private loadBrands() {
        var self = this;
        this.$http( {
            method: 'GET',
            url: '/api/item',
            params: {
                type_id: 5,
                fields: 'name_html',
                limit: 200,
                name: this.search ? '%' + this.search + '%' : null,
                page: self.page
            }
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.brands = chunk( response.data.items, 6 );
            self.brands_paginator = response.data.paginator;
        }, function( response: ng.IHttpResponse<any> ) {
            notify.response( response );
        } );
    }

    public toggleConcepts() {
        this.concepts_expanded = !this.concepts_expanded;
    }

    private loadAuthors() {

        if ( this.authorsCanceler ) {
            this.authorsCanceler.resolve();
            this.authorsCanceler = null;
        }

        this.authorsCanceler = this.$q.defer();

        var self = this;
        this.$http( {
            method: 'GET',
            url: '/api/item',
            params: {
                type_id: 8,
                fields: 'name_html',
                limit: 50,
                name: self.search ? '%' + self.search + '%' : null,
                page: self.page
            },
            timeout: this.authorsCanceler.promise
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.authors = response.data.items;
            self.authors_paginator = response.data.paginator;
        }, function( response: ng.IHttpResponse<any> ) {
            if (response.status !== -1) {
                notify.response( response );
            }
        } );
    }

    private loadPersons() {

        if ( this.personsCanceler ) {
            this.personsCanceler.resolve();
            this.personsCanceler = null;
        }

        this.personsCanceler = this.$q.defer();

        var self = this;
        this.$http( {
            method: 'GET',
            url: '/api/item',
            params: {
                type_id: 8,
                fields: 'name_html',
                limit: 50,
                name: self.search ? '%' + self.search + '%' : null,
                page: self.page
            },
            timeout: this.personsCanceler.promise
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.persons = response.data.items;
            self.persons_paginator = response.data.paginator;
        }, function( response: ng.IHttpResponse<any> ) {
            if (response.status !== -1) {
                notify.response( response );
            }
        } );
    }

    private loadCopyrights() {

        if ( this.copyrightsCanceler ) {
            this.copyrightsCanceler.resolve();
            this.copyrightsCanceler = null;
        }

        this.copyrightsCanceler = this.$q.defer();

        var self = this;
        this.$http( {
            method: 'GET',
            url: '/api/item',
            params: {
                type_id: 9,
                fields: 'name_html',
                limit: 50,
                name: self.search ? '%' + self.search + '%' : null,
                page: self.page
            },
            timeout: this.copyrightsCanceler.promise
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.copyrights = response.data.items;
            self.copyrights_paginator = response.data.paginator;
        }, function( response: ng.IHttpResponse<any> ) {
            if (response.status !== -1) {
                notify.response( response );
            }
        } );
    }
}

angular.module( Module )
    .controller( CONTROLLER_NAME, ModerPicturesItemMoveController )
    .config( ['$stateProvider',
        function config( $stateProvider: any ) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/pictures/{id}/move?show_museums&show_factories&show_authors&show_persons&show_copyrights&brand_id&src_item_id&src_type&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require( './template.html' ),
                resolve: {
                    access: ['AclService', function( Acl: AclService ) {
                        return Acl.inheritsRole( 'moder', 'unauthorized' );
                    }]
                }
            } );
        }
    ] );
