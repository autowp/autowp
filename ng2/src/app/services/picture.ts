import { APIPaginator, APIImage } from './api.service';
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { APIUser } from './user';
import { APIPictureItem } from './picture-item';
import { APIIP } from './ip';
import { switchMap, shareReplay } from 'rxjs/operators';
import { AuthService } from './auth.service';

export interface APIPictureGetResponse {
  pictures: APIPicture[];
  paginator: APIPaginator;
}

export interface APIPicture {
  id: number;
  crop: {
    left: number | null;
    top: number | null;
    width: number | null;
    height: number | null;
  };
  thumb_medium: APIImage;
  perspective_item: {
    item_id: number;
    type: number;
    perspective_id: number;
  };
  url: string;
  status: string;
  cropped: boolean;
  name_html: string;
  name_text: string;
  resolution: string;
  views: number;
  comments_count: {
    total: number;
    new: number;
  };
  votes: {
    positive: number;
  };
  owner: APIUser;
  crop_resolution: string;
  similar: {
    distance: number;
    picture_id: number;
    picture: APIPicture;
  };
  image_gallery_full: APIImage;
  width: number;
  height: number;
  thumb: APIImage;
  items: APIPictureItem[];
  special_name: string;
  copyrights: string;
  name: string;
  image: APIImage;
  moder_voted: boolean;
  moder_votes: APIPictureModerVote[];
  is_last: boolean;
  accepted_count: number;
  siblings: {
    prev: APIPicture;
    prev_new: APIPicture;
    next_new: APIPicture;
    next: APIPicture;
  };
  dpi_x: number;
  dpi_y: number;
  filesize: number;
  rights: {
    crop: boolean;
    move: boolean;
    accept: boolean;
    delete: boolean;
    unaccept: boolean;
    restore: boolean;
    normalize: boolean;
    flop: boolean;
  };
  copyrights_text_id: number;
  iptc: string;
  exif: string;
  replaceable: {
    url: string;
  };
  change_status_user: APIUser;
  ip: APIIP;
  add_date: string;
  moder_vote: {
    vote: number;
    count: number;
  };
}

export interface APIPictureModerVote {
  user: APIUser;
  vote: number;
  reason: string;
}

export interface APIGetPictureOptions {
  fields?: string;
}

export interface APIGetPicturesOptions {
  fields?: string;
  status?: string;
  limit?: number;
  page?: number;
  perspective_id?: number | null | 'null';
  order?: number;
  exact_item_id?: number;
  item_id?: number;
  add_date?: string;
  car_type_id?: number;
  comments?: null | boolean;
  owner_id?: number;
  replace?: null | boolean;
  requests?: null | number;
  special_name?: boolean;
  lost?: boolean;
  gps?: boolean;
  similar?: boolean;
  accept_date?: string;
  exact_item_link_type?: number;
}

export interface APIPictureUserSummary {
  inboxCount: number;
  acceptedCount: number;
}

@Injectable()
export class PictureService {
  private summary$: Observable<APIPictureUserSummary>;

  constructor(private http: HttpClient, private auth: AuthService) {
    this.summary$ = this.auth.getUser().pipe(
      switchMap(user => {
        if (!user) {
          return of(null);
        }
        return this.http.get<APIPictureUserSummary>(
          '/api/picture/user-summary'
        );
      }),
      shareReplay(1)
    );
  }

  public getPictureByLocation(
    url: string,
    options?: APIGetPictureOptions
  ): Observable<APIPicture> {
    return this.http.get<APIPicture>(url, {
      params: this.convertPictureOptions(options)
    });
  }

  private convertPictureOptions(
    options: APIGetPictureOptions
  ): { [param: string]: string } {
    const params: { [param: string]: string } = {};

    if (!options) {
      options = {};
    }

    if (options.fields) {
      params.fields = options.fields;
    }

    return params;
  }

  private converPicturesOptions(
    options: APIGetPicturesOptions
  ): { [param: string]: string } {
    const params: { [param: string]: string } = {};

    if (options.fields) {
      params.fields = options.fields;
    }

    if (options.status) {
      params.status = options.status;
    }

    if (options.limit) {
      params.limit = options.limit.toString();
    }

    if (options.page) {
      params.page = options.page.toString();
    }

    if (options.perspective_id) {
      params.perspective_id = options.perspective_id.toString();
    }

    if (options.order) {
      params.order = options.order.toString();
    }

    if (options.exact_item_id) {
      params.exact_item_id = options.exact_item_id.toString();
    }

    if (options.item_id) {
      params.item_id = options.item_id.toString();
    }

    if (options.add_date) {
      params.add_date = options.add_date;
    }

    if (options.car_type_id) {
      params.car_type_id = options.car_type_id.toString();
    }

    if (options.comments !== null && options.comments !== undefined) {
      params.comments = options.comments ? '1' : '0';
    }

    if (options.replace !== null && options.replace !== undefined) {
      params.replace = options.replace ? '1' : '0';
    }

    if (options.owner_id) {
      params.owner_id = options.owner_id.toString();
    }

    if (options.requests !== null && options.requests !== undefined) {
      params.requests = options.requests.toString();
    }

    if (options.special_name !== null && options.special_name !== undefined) {
      params.special_name = options.special_name ? '1' : '0';
    }

    if (options.lost !== null && options.lost !== undefined) {
      params.lost = options.lost ? '1' : '0';
    }

    if (options.gps !== null && options.gps !== undefined) {
      params.gps = options.gps ? '1' : '0';
    }

    if (options.similar) {
      params.similar = '1';
    }

    if (options.accept_date) {
      params.accept_date = options.accept_date;
    }

    if (options.exact_item_link_type) {
      params.exact_item_link_type = options.exact_item_link_type.toString();
    }

    return params;
  }

  public getPicture(
    id: number,
    options?: APIGetPictureOptions
  ): Observable<APIPicture> {
    return this.getPictureByLocation('/api/picture/' + id, options);
  }

  public getPictures(
    options?: APIGetPicturesOptions
  ): Observable<APIPictureGetResponse> {
    return this.http.get<APIPictureGetResponse>('/api/picture', {
      params: this.converPicturesOptions(options)
    });
  }

  public getSummary(): Observable<APIPictureUserSummary> {
    return this.summary$;
  }
}
