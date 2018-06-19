import { Component, Injectable, OnDestroy, OnInit } from '@angular/core';
import { sprintf } from 'sprintf-js';
import { HttpClient } from '@angular/common/http';
import { PerspectiveService } from '../../../services/perspective';
import {
  PictureItemService,
  APIPictureItem
} from '../../../services/picture-item';
import { ItemService, APIItem } from '../../../services/item';
import { TranslateService } from '@ngx-translate/core';
import { ActivatedRoute, Router } from '@angular/router';
import { Subscription, combineLatest, BehaviorSubject } from 'rxjs';
import { PictureService, APIPicture } from '../../../services/picture';
import { APIPerspective } from '../../../services/api.service';
import { PageEnvService } from '../../../services/page-env.service';
import {
  distinctUntilChanged,
  debounceTime,
  switchMap,
  map,
  tap,
  switchMapTo
} from 'rxjs/operators';

// Acl.inheritsRole( 'moder', 'unauthorized' );

@Component({
  selector: 'app-moder-pictures-item',
  templateUrl: './item.component.html'
})
@Injectable()
export class ModerPicturesItemComponent implements OnInit, OnDestroy {
  private id: number;
  private routeSub: Subscription;
  public picture: APIPicture = null;
  public replaceLoading = false;
  public pictureItemLoading = false;
  public similarLoading = false;
  public repairLoading = false;
  public statusLoading = false;
  public copyrightsLoading = false;
  public specialNameLoading = false;
  public last_item: APIItem = null;
  public banPeriods = [
    { value: 1, name: 'ban/period/hour' },
    { value: 2, name: 'ban/period/2-hours' },
    { value: 4, name: 'ban/period/4-hours' },
    { value: 8, name: 'ban/period/8-hours' },
    { value: 16, name: 'ban/period/16-hours' },
    { value: 24, name: 'ban/period/day' },
    { value: 48, name: 'ban/period/2-days' }
  ];
  public banPeriod = 1;
  public banReason: string | null = null;
  public perspectives: APIPerspective[] = [];
  private perspectiveSub: Subscription;
  private change$ = new BehaviorSubject<null>(null);
  private lastItemSub: Subscription;

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private perspectiveService: PerspectiveService,
    private pictureItemService: PictureItemService,
    private itemService: ItemService,
    private route: ActivatedRoute,
    private router: Router,
    private pictureService: PictureService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.perspectiveSub = this.perspectiveService
      .getPerspectives()
      .subscribe(perspectives => (this.perspectives = perspectives));

    this.routeSub = combineLatest(
      this.route.params.pipe(
        distinctUntilChanged(),
        debounceTime(30)
      ),
      this.translate.get('moder/picture/picture-n-%s'),
      (params, translation) => ({ params, translation })
    )
      .pipe(
        tap(data =>
          this.pageEnv.set({
            layout: {
              isAdminPage: true,
              needRight: false
            },
            name: 'page/72/name',
            pageId: 72,
            args: {
              PICTURE_ID: data.params.id + '',
              PICTURE_NAME: sprintf(data.translation, data.params.id)
            }
          })
        ),
        switchMap(data =>
          this.change$.pipe(
            switchMapTo(
              this.pictureService.getPicture(data.params.id, {
                fields: [
                  'owner',
                  'thumb',
                  'add_date',
                  'iptc',
                  'exif',
                  'image',
                  'items.item.name_html',
                  'items.item.brands.name_html',
                  'items.area',
                  'special_name',
                  'copyrights',
                  'change_status_user',
                  'rights',
                  'moder_votes',
                  'moder_voted',
                  'is_last',
                  'views',
                  'accepted_count',
                  'similar.picture.thumb',
                  'replaceable',
                  'siblings.name_text',
                  'ip.rights',
                  'ip.blacklist'
                ].join(',')
              })
            )
          )
        )
      )
      .subscribe(
        data => {
          this.picture = data;
          this.id = this.picture.id;

          /*if (callback) {
            callback();
          }*/
        },
        () => {
          this.router.navigate(['/error-404']);
        }
      );

    this.lastItemSub = this.itemService
      .getItems({
        last_item: true,
        fields: 'name_html',
        limit: 1
      })
      .subscribe(response => {
        this.last_item = response.items.length ? response.items[0] : null;
      });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
    this.perspectiveSub.unsubscribe();
    this.lastItemSub.unsubscribe();
  }

  public pictureVoted() {
    this.change$.next(null);
  }

  public hasItem(itemId: number): boolean {
    let found = false;
    for (const item of this.picture.items) {
      if (item.item_id === itemId) {
        found = true;
      }
    }

    return found;
  }

  public addItem(item: APIItem, type: number) {
    this.pictureItemLoading = true;
    this.pictureItemService.create(this.id, item.id, type, {}).subscribe(
      () => {
        this.change$.next(null);
        this.pictureItemLoading = false;
      },
      () => {
        this.pictureItemLoading = false;
      }
    );
  }

  public moveItem(type: number, srcItemId: number, dstItemId: number) {
    this.pictureItemLoading = true;
    this.pictureItemService
      .changeItem(this.id, type, srcItemId, dstItemId)
      .subscribe(
        () => {
          this.change$.next(null);
          this.pictureItemLoading = false;
        },
        () => {
          this.pictureItemLoading = false;
        }
      );
  }

  public saveSpecialName() {
    this.specialNameLoading = true;
    this.http
      .put<void>('/api/picture/' + this.id, {
        special_name: this.picture.special_name
      })
      .subscribe(
        response => {
          this.specialNameLoading = false;
        },
        () => {
          this.specialNameLoading = false;
        }
      );
  }

  public saveCopyrights() {
    this.copyrightsLoading = true;

    this.http
      .put<void>('/api/picture/' + this.id, {
        copyrights: this.picture.copyrights
      })
      .subscribe(
        response => {
          this.copyrightsLoading = false;
        },
        () => {
          this.copyrightsLoading = false;
        }
      );
  }

  public unacceptPicture() {
    this.statusLoading = true;
    this.http
      .put<void>('/api/picture/' + this.id, {
        status: 'inbox'
      })
      .subscribe(
        response => {
          this.change$.next(null);
          this.statusLoading = false;
        },
        () => {
          this.statusLoading = false;
        }
      );
  }

  public acceptPicture() {
    this.statusLoading = true;
    this.http
      .put<void>('/api/picture/' + this.id, {
        status: 'accepted'
      })
      .subscribe(
        response => {
          this.change$.next(null);
          this.statusLoading = false;
        },
        () => {
          this.statusLoading = false;
        }
      );
  }

  public deletePicture() {
    this.statusLoading = true;
    this.http
      .put<void>('/api/picture/' + this.id, {
        status: 'removing'
      })
      .subscribe(
        response => {
          this.change$.next(null);
          this.statusLoading = false;
        },
        () => {
          this.statusLoading = false;
        }
      );
  }

  public restorePicture() {
    this.statusLoading = true;
    this.http
      .put<void>('/api/picture/' + this.id, {
        status: 'inbox'
      })
      .subscribe(
        response => {
          this.change$.next(null);
          this.statusLoading = false;
        },
        () => {
          this.statusLoading = false;
        }
      );
  }

  public normalizePicture() {
    this.repairLoading = true;
    this.http.put<void>('/api/picture/' + this.id + '/normalize', {}).subscribe(
      response => {
        this.change$.next(null);
        this.repairLoading = false;
      },
      () => {
        this.repairLoading = false;
      }
    );
  }

  public flopPicture() {
    this.repairLoading = true;
    this.http.put<void>('/api/picture/' + this.id + '/flop', {}).subscribe(
      response => {
        this.change$.next(null);
        this.repairLoading = false;
      },
      () => {
        this.repairLoading = false;
      }
    );
  }

  public repairPicture() {
    this.repairLoading = true;
    this.http.put<void>('/api/picture/' + this.id + '/repair', {}).subscribe(
      response => {
        this.change$.next(null);
        this.repairLoading = false;
      },
      () => {
        this.repairLoading = false;
      }
    );
  }

  public correctFileNames() {
    this.repairLoading = true;
    this.http
      .put<void>('/api/picture/' + this.id + '/correct-file-names', {})
      .subscribe(
        response => {
          this.change$.next(null);
          this.repairLoading = false;
        },
        () => {
          this.repairLoading = false;
        }
      );
  }

  public cancelSimilar() {
    this.similarLoading = true;
    this.http
      .delete<void>(
        '/api/picture/' +
          this.id +
          '/similar/' +
          this.picture.similar.picture_id
      )
      .subscribe(
        () => {
          this.change$.next(null);
          this.similarLoading = false;
        },
        () => {
          this.similarLoading = false;
        }
      );
  }

  public savePerspective(item: APIPictureItem) {
    this.pictureItemService
      .setPerspective(
        item.picture_id,
        item.item_id,
        item.type,
        item.perspective_id
      )
      .subscribe();
  }

  public deletePictureItem(item: APIPictureItem) {
    this.pictureItemLoading = true;
    this.pictureItemService
      .remove(item.picture_id, item.item_id, item.type)
      .subscribe(
        () => {
          this.change$.next(null);
          this.pictureItemLoading = false;
        },
        () => {
          this.pictureItemLoading = false;
        }
      );
  }

  public cancelReplace() {
    this.replaceLoading = true;

    this.http
      .put<void>('/api/picture/' + this.id, {
        replace_picture_id: ''
      })
      .subscribe(
        response => {
          this.change$.next(null);
          this.replaceLoading = false;
        },
        () => {
          this.replaceLoading = false;
        }
      );
  }

  public acceptReplace() {
    this.replaceLoading = true;
    this.http
      .put<void>('/api/picture/' + this.id + '/accept-replace', {})
      .subscribe(
        response => {
          this.change$.next(null);
          this.replaceLoading = false;
        },
        () => {
          this.replaceLoading = false;
        }
      );
  }

  public removeFromBlacklist(ip: string) {
    this.http
      .delete<void>('/api/traffic/blacklist/' + ip)
      .subscribe(response => {
        this.change$.next(null);
      });
  }

  public addToBlacklist(ip: string) {
    this.http
      .post<void>('/api/traffic/blacklist', {
        ip: ip,
        period: this.banPeriod,
        reason: this.banReason
      })
      .subscribe(response => {
        this.change$.next(null);
      });
  }
}
