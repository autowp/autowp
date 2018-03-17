declare namespace autowp {

    export interface IControllerScope extends ng.IScope
    {
        user: any;
        pageEnv: (data: any) => void;
        getUser: () => any;
        setUser: (user: any) => void;
        doLogout: () => void;
        doLogin: () => void;
    }

    export interface IRootControllerScope extends autowp.IControllerScope
    {
        refreshNewMessagesCount: () => void;
        spanRight: number;
        spanCenter: number;
        needRight: boolean;
        newPersonalMessages: number;
        loginInvalidParams: any;
        loginForm: {
            login: '',
            password: '',
            remember: false
        };
        isSecondaryMenuItems: (page: any) => boolean;
        disablePageName: boolean;
        title: string|null;
        pageName: string|null;
        isAdminPage: boolean;
        pageId: number|null;
        searchHostname: string;
        mainMenu: any[];
        isModer: boolean;
        path: string;
        languages: any[];
        moderMenu: any[];
    }

    export interface IPaginator
    {
        pageCount: number;
        itemCountPerPage: number;
        first: number;
        current: number;
        last: number;
        next: number;
        pagesInRange: any;
        firstPageInRange: number;
        lastPageInRange: number;
        currentItemCount: number;
        totalItemCount: number;
        firstItemNumber: number;
        lastItemNumber: number;
    }

    export interface IPaginatedCollection<T>
    {
        paginator: autowp.IPaginator;
        items: T[];
    }

    export interface IAttrZone
    {
        id: number;
        name: string;
    }

    export interface IAttrAttribute
    {
        id: number;
        name: string;
    }

    export interface IAttrAttributeType
    {
        id: number;
        name: string;
    }

    export interface IAttrUnit
    {
        id: number;
        name: string;
    }

    export interface IUser
    {
        id: number;
        name: string;
        specs_weight: number;
    }

    export interface GetUsersResult {
        items: autowp.IUser[];
        paginator: autowp.IPaginator;
    }

    export interface IItem
    {
        id: number;
        item_type_id: number;
        is_group: boolean;
        name_text: string;
        name_html: string;
        full_name: string;
        catname: string;
        body: string;
        lat: number;
        lng: number;
        is_concept: boolean;
        today: boolean;
        begin_month: number;
        begin_year: number;
        end_month: number;
        end_year: number;
        begin_model_year: number;
        end_model_year: number;
        produced: number;
        produced_exactly: boolean;
        spec_id: number|string|null;
        logo: any;
        engine_id: number|null,
        attr_zone_id: number,

        engine_vehicles_count: number;
        pictures_count: number;
        childs_count: number;
        parents_count: number;
        links_count: number;
        item_language_count: number;
        subscription: boolean;

        related_group_pictures: any[];
    }

    export interface GetItemsResult {
        items: autowp.IItem[];
        paginator: autowp.IPaginator;
    }

    export interface GetZonesResult {
        items: autowp.IAttrZone[];
    }

    export interface GetAttributeTypesResult {
        items: autowp.IAttrAttributeType[];
    }

    export interface GetUnitResult {
        items: autowp.IAttrUnit[];
    }

    export interface IPreviewPicture {
        picture: any; // IPicture;
        url: string;
    }
}

interface JQuery {
    carousel(index: number): JQuery;
}
