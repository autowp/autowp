export interface APIAccountStartPostResponse {
  url: string;
}

export interface APIAccountItemsGetResponse {
  items: APIAccount[];
}

export interface APIAccount {
  id: number;
  can_remove: boolean;
  icon: string;
  link: string;
  name: string;
}
