import type {PageId} from '@services/page-id';

export interface BrandPerspectivePageData {
  catname: string;
  page_id: PageId;
  perspective_exclude_id?: number[];
  perspective_id?: number;
  picture_page: {
    breadcrumbs: string;
    id: PageId;
  };
  title: string;
}
