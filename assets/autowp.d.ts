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
}
