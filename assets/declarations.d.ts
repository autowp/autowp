export interface IAutowpControllerScope extends ng.IScope
{
    user: any;
    pageEnv: (data: any) => void;
}