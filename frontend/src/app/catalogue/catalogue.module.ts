export interface BrandPerspectivePageData {
  catname: string;
  page_id: number;
  perspective_exclude_id?: number[];
  perspective_id?: number;
  picture_page: {
    breadcrumbs: string;
    id: number;
  };
  title: string;
}
