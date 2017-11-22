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
}
