declare namespace autowp {

    export interface IControllerScope extends ng.IScope
    {
        user: any;
        pageEnv: (data: any) => void;
    }
    
    export interface IRootControllerScope extends autowp.IControllerScope
    {
        getUser: () => any;
        setUser: (user: any) => void;
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
