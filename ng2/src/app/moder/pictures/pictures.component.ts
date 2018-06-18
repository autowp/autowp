import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import { PerspectiveService, APIPerspective } from '../../services/perspective';
import { PictureModerVoteService } from '../../services/picture-moder-vote';
import {
  PictureModerVoteTemplateService,
  APIPictureModerVoteTemplate
} from '../../services/picture-moder-vote-template';
import {
  VehicleTypeService,
  APIVehicleType
} from '../../services/vehicle-type';
import {
  ItemService,
  GetItemsServiceOptions,
  APIItem
} from '../../services/item';
import { chunkBy } from '../../chunk';
import { UserService, APIUser } from '../../services/user';
import {
  Subscription,
  Observable,
  of,
  empty,
  forkJoin,
  BehaviorSubject
} from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';
import {
  APIPicture,
  PictureService,
  APIGetPicturesOptions
} from '../../services/picture';
import { NgbTypeaheadSelectItemEvent } from '@ng-bootstrap/ng-bootstrap';
import {
  debounceTime,
  map,
  switchMap,
  catchError,
  tap,
  distinctUntilChanged,
  switchMapTo
} from 'rxjs/operators';
import { PageEnvService } from '../../services/page-env.service';

interface VehicleTypeInPictures {
  name: string;
  value: number;
  deep: number;
}

interface PerspectiveInList {
  name: string;
  value: number | null | 'null';
}

function toPlainVehicleTypes(
  options: APIVehicleType[],
  deep: number
): VehicleTypeInPictures[] {
  const result: VehicleTypeInPictures[] = [];
  for (const item of options) {
    result.push({
      name: item.name,
      value: item.id,
      deep: deep
    });
    for (const subitem of toPlainVehicleTypes(item.childs, deep + 1)) {
      result.push(subitem);
    }
  }
  return result;
}

// Acl.inheritsRole('moder', 'unauthorized');

@Component({
  selector: 'app-moder-pictures',
  templateUrl: './pictures.component.html'
})
@Injectable()
export class ModerPicturesComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public loading = 0;
  public pictures: APIPicture[] = [];
  public hasSelectedItem = false;
  private selected: number[] = [];
  public chunks: APIPicture[][] = [];
  public paginator: APIPaginator;
  public moderVoteTemplateOptions: APIPictureModerVoteTemplate[] = [];

  public status: string | null;
  public statusOptions = [
    {
      name: 'moder/picture/filter/status/any',
      value: null
    },
    {
      name: 'moder/picture/filter/status/inbox',
      value: 'inbox'
    },
    {
      name: 'moder/picture/filter/status/accepted',
      value: 'accepted'
    },
    {
      name: 'moder/picture/filter/status/removing',
      value: 'removing'
    },
    {
      name: 'moder/picture/filter/status/all-except-removing',
      value: 'custom1'
    }
  ];

  private defaultVehicleTypeOptions: VehicleTypeInPictures[] = [
    {
      name: 'Any',
      value: null,
      deep: 0
    }
  ];
  public vehicleTypeOptions: VehicleTypeInPictures[] = [];
  public vehicleTypeID: number | null;

  private defaultPerspectiveOptions: PerspectiveInList[] = [
    {
      name: 'moder/pictures/filter/perspective/any',
      value: null
    },
    {
      name: 'moder/pictures/filter/perspective/empty',
      value: 'null'
    }
  ];
  public perspectiveOptions: PerspectiveInList[] = [];
  public perspectiveID: number | null | 'null';

  public commentsOptions = [
    {
      name: 'moder/pictures/filter/comments/not-matters',
      value: null
    },
    {
      name: 'moder/pictures/filter/comments/has-comments',
      value: true
    },
    {
      name: 'moder/pictures/filter/comments/has-no-comments',
      value: false
    }
  ];
  public comments: boolean | null;

  public replaceOptions = [
    {
      name: 'moder/pictures/filter/replace/not-matters',
      value: null
    },
    {
      name: 'moder/pictures/filter/replace/replaces',
      value: true
    },
    {
      name: 'moder/pictures/filter/replace/without-replaces',
      value: false
    }
  ];
  public replace: boolean | null;

  public requestsOptions = [
    {
      name: 'moder/pictures/filter/votes/not-matters',
      value: null
    },
    {
      name: 'moder/pictures/filter/votes/none',
      value: 0
    },
    {
      name: 'moder/pictures/filter/votes/accept',
      value: 1
    },
    {
      name: 'moder/pictures/filter/votes/delete',
      value: 2
    },
    {
      name: 'moder/pictures/filter/votes/any',
      value: 3
    }
  ];
  public requests: number | null;

  public orderOptions = [
    {
      name: 'moder/pictures/filter/order/add-date-desc',
      value: 1
    },
    {
      name: 'moder/pictures/filter/order/add-date-asc',
      value: 2
    },
    {
      name: 'moder/pictures/filter/order/resolution-desc',
      value: 3
    },
    {
      name: 'moder/pictures/filter/order/resolution-asc',
      value: 4
    },
    {
      name: 'moder/pictures/filter/order/filesize-desc',
      value: 5
    },
    {
      name: 'moder/pictures/filter/order/filesize-asc',
      value: 6
    },
    {
      name: 'moder/pictures/filter/order/commented',
      value: 7
    },
    {
      name: 'moder/pictures/filter/order/views',
      value: 8
    },
    {
      name: 'moder/pictures/filter/order/moder-votes',
      value: 9
    },
    {
      name: 'moder/pictures/filter/order/removing-date',
      value: 11
    },
    {
      name: 'moder/pictures/filter/order/likes',
      value: 12
    },
    {
      name: 'moder/pictures/filter/order/dislikes',
      value: 13
    }
  ];
  public order: number;

  public similar = false;
  public gps = false;
  public lost = false;
  public specialName = false;

  public ownerID: number;
  public ownerQuery = '';
  public ownersDataSource: (text$: Observable<string>) => Observable<any[]>;

  public itemID: number;
  public itemQuery = '';
  public itemsDataSource: (text$: Observable<string>) => Observable<any[]>;
  private vehicleTypeSub: Subscription;
  private mvtSub: Subscription;

  private perspectiveSub: Subscription;

  private change$ = new BehaviorSubject<null>(null);

  constructor(
    private http: HttpClient,
    private perspectiveService: PerspectiveService,
    private moderVoteService: PictureModerVoteService,
    private moderVoteTemplateService: PictureModerVoteTemplateService,
    private vehicleTypeService: VehicleTypeService,
    private itemService: ItemService,
    private userService: UserService,
    private route: ActivatedRoute,
    private pictureService: PictureService,
    private router: Router,
    private pageEnv: PageEnvService
  ) {
    this.ownersDataSource = (text$: Observable<string>) =>
      text$.pipe(
        debounceTime(200),
        switchMap(query => {
          if (query === '') {
            return of([]);
          }

          const params = {
            limit: 10,
            id: [],
            search: ''
          };
          if (query.substring(0, 1) === '#') {
            params.id.push(parseInt(query.substring(1), 10));
          } else {
            params.search = query;
          }

          return this.userService.get(params).pipe(
            catchError((err, caught) => {
              console.log(err, caught);
              return empty();
            }),
            map(response => response.items)
          );
        })
      );

    this.itemsDataSource = (text$: Observable<string>) =>
      text$.pipe(
        debounceTime(200),
        switchMap(query => {
          if (query === '') {
            return of([]);
          }

          const params: GetItemsServiceOptions = {
            limit: 10,
            fields: 'name_text,name_html',
            id: 0,
            name: ''
          };
          if (query.substring(0, 1) === '#') {
            params.id = parseInt(query.substring(1), 10);
          } else {
            params.name = '%' + query + '%';
          }

          return this.itemService.getItems(params).pipe(
            catchError((err, caught) => {
              console.log(err, caught);
              return empty();
            }),
            map(response => response.items)
          );
        })
      );
  }

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/73/name',
          pageId: 73
        }),
      0
    );

    this.vehicleTypeSub = this.vehicleTypeService
      .getTypes()
      .subscribe(types => {
        this.vehicleTypeOptions = this.defaultVehicleTypeOptions.concat(
          toPlainVehicleTypes(types, 0)
        );
      });

    this.perspectiveSub = this.perspectiveService
      .getPerspectives()
      .subscribe(perspectives => {
        this.perspectiveOptions = this.defaultPerspectiveOptions.slice(0);
        for (const perspective of perspectives) {
          this.perspectiveOptions.push({
            value: perspective.id,
            name: perspective.name
          });
        }
      });

    this.mvtSub = this.moderVoteTemplateService
      .getTemplates()
      .subscribe(templates => (this.moderVoteTemplateOptions = templates));

    this.querySub = this.route.queryParams
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => {
          this.status = params.status ? params.status : null;
          this.vehicleTypeID = params.vehicle_type_id
            ? parseInt(params.vehicle_type_id, 10)
            : null;
          this.perspectiveID = params.perspective_id
            ? params.perspective_id === 'null'
              ? 'null'
              : parseInt(params.perspective_id, 10)
            : null;
          this.itemID = params.item_id ? parseInt(params.item_id, 10) : 0;
          if (this.itemID && !this.itemQuery) {
            this.itemQuery = '#' + this.itemID;
          }
          switch (params.comments) {
            case 'true':
              this.comments = true;
              break;
            case 'false':
              this.comments = false;
              break;
            default:
              this.comments = null;
              break;
          }
          this.ownerID = parseInt(params.owner_id, 10);
          if (this.ownerID && !this.ownerQuery) {
            this.ownerQuery = '#' + this.ownerID;
          }
          switch (params.replace) {
            case 'true':
              this.replace = true;
              break;
            case 'false':
              this.replace = false;
              break;
            default:
              this.replace = null;
              break;
          }
          this.requests = params.requests
            ? parseInt(params.requests, 10)
            : null;
          this.specialName = !!params.special_name;
          this.lost = !!params.lost;
          this.gps = !!params.gps;
          this.similar = !!params.similar;
          this.order = params.order ? parseInt(params.order, 10) : 1;

          this.loading++;
          this.pictures = [];

          this.selected = [];
          this.hasSelectedItem = false;
          const qParams: APIGetPicturesOptions = {
            status: this.status,
            car_type_id: this.vehicleTypeID,
            perspective_id: this.perspectiveID,
            item_id: this.itemID,
            owner_id: this.ownerID,
            requests: this.requests,
            special_name: this.specialName,
            lost: this.lost,
            gps: this.gps,
            similar: this.similar,
            order: this.order,
            page: params.page,
            comments: this.comments,
            replace: this.replace,
            fields:
              'owner,thumb_medium,moder_vote,votes,similar,views,comments_count,perspective_item,name_html,name_text',
            limit: 18
          };

          return this.change$.pipe(
            switchMapTo(this.pictureService.getPictures(qParams))
          );
        })
      )
      .subscribe(
        response => {
          this.pictures = response.pictures;
          this.chunks = chunkBy<APIPicture>(this.pictures, 3);
          this.paginator = response.paginator;
          this.loading--;
        },
        () => {
          this.loading--;
        }
      );
  }

  ngOnDestroy(): void {
    this.vehicleTypeSub.unsubscribe();
    this.querySub.unsubscribe();
    this.mvtSub.unsubscribe();
    this.perspectiveSub.unsubscribe();
  }

  public onPictureSelect(active: boolean, picture: APIPicture) {
    if (active) {
      this.selected.push(picture.id);
    } else {
      const index = this.selected.indexOf(picture.id);
      if (index > -1) {
        this.selected.splice(index, 1);
      }
    }
    this.hasSelectedItem = this.selected.length > 0;
  }

  public itemFormatter(x: APIItem) {
    return x.name_text;
  }

  public ownerFormatter(x: APIUser) {
    return x.name;
  }

  public itemOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    this.router.navigate([], {
      queryParamsHandling: 'merge',
      queryParams: {
        item_id: e.item.id
      }
    });
  }

  public clearItem(): void {
    this.itemQuery = '';
    this.router.navigate([], {
      queryParamsHandling: 'merge',
      queryParams: {
        item_id: null
      }
    });
  }

  public ownerOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    this.router.navigate([], {
      queryParamsHandling: 'merge',
      queryParams: {
        owner_id: e.item.id
      }
    });
  }

  public clearOwner(): void {
    this.ownerQuery = '';
    this.router.navigate([], {
      queryParamsHandling: 'merge',
      queryParams: {
        owner_id: null
      }
    });
  }

  public votePictures(vote: number, reason: string) {
    for (const id of this.selected) {
      const promises: Observable<void>[] = [];
      for (const picture of this.pictures) {
        if (picture.id === id) {
          const q = this.moderVoteService.vote(picture.id, vote, reason);
          promises.push(q);
        }
      }

      forkJoin(promises).subscribe(() => {
        this.change$.next(null);
      });
    }
    this.selected = [];
    this.hasSelectedItem = false;
  }

  public acceptPictures() {
    for (const id of this.selected) {
      const promises: Observable<void>[] = [];
      for (const picture of this.pictures) {
        if (picture.id === id) {
          promises.push(
            this.http
              .put<void>('/api/picture/' + picture.id, {
                status: 'accepted'
              })
              .pipe(tap(() => (picture.status = 'accepted')))
          );
        }
      }

      forkJoin(promises).subscribe(() => {
        this.change$.next(null);
      });
    }
    this.selected = [];
    this.hasSelectedItem = false;
  }
}
