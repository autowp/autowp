export interface IAutowpControllerScope extends ng.IScope
{
    user: any;
    pageEnv: (data: any) => void;
}

export interface IAutowpRootControllerScope extends IAutowpControllerScope
{
    getUser: () => any;
}