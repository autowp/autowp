import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { APIItem, ItemService } from '../../services/item';
import { TranslateService } from '@ngx-translate/core';
import { ActivatedRoute } from '@angular/router';
import { Subscription, combineLatest, of } from 'rxjs';
import { AuthService } from '../../services/auth.service';
import { DonateService, APIDonateCarOfDayDate } from '../../services/donate';
import { PageEnvService } from '../../services/page-env.service';
import {
  debounceTime,
  distinctUntilChanged,
  switchMap,
  map
} from 'rxjs/operators';
import { sprintf } from 'sprintf-js';
import { APIUser } from '../../services/user';

@Component({
  selector: 'app-donate-vod',
  templateUrl: './vod.component.html'
})
@Injectable()
export class DonateVodComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public formParams: {
    name: string;
    value: string;
  }[];
  public selectedDate: string;
  public selectedItem: APIItem;
  public anonymous: boolean;
  public user: APIUser;
  public userID: number;
  public sum: number;
  public dates: APIDonateCarOfDayDate[];
  public paymentType = 'AC';

  constructor(
    private translate: TranslateService,
    private itemService: ItemService,
    private route: ActivatedRoute,
    public auth: AuthService,
    private donateService: DonateService,
    private pageEnv: PageEnvService
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: true
          },
          name: 'page/196/name',
          pageId: 196
        }),
      0
    );
  }

  ngOnInit(): void {
    this.querySub = combineLatest(
      this.route.queryParams.pipe(
        map(params => ({ anonymous: params.anonymous, date: params.date })),
        distinctUntilChanged(),
        debounceTime(30)
      ),
      this.donateService.getVOD(),
      this.route.queryParams.pipe(
        map(params => ({ item_id: params.item_id })),
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => {
          if (!params.item_id) {
            return of(null);
          }

          return this.itemService.getItem(params.item_id, {
            fields: 'name_html,item_of_day_pictures'
          });
        })
      ),
      this.auth.getUser(),
      this.translate.get([
        'donate/vod/order-message',
        'donate/vod/order-target'
      ]),
      (params, vod, item, user, translations) => ({
        params,
        vod,
        item,
        user,
        translations
      })
    ).subscribe(data => {
      this.sum = data.vod.sum;
      this.dates = data.vod.dates;
      this.selectedDate = data.params.date;
      this.selectedItem = data.item;
      this.user = data.user;
      this.userID = data.user ? data.user.id : 0;
      this.anonymous = this.userID ? !!data.params.anonymous : true;

      if (!this.selectedItem || !this.selectedDate) {
        this.formParams = null;
        return;
      }

      const label =
        'vod/' +
        this.selectedDate +
        '/' +
        this.selectedItem.id +
        '/' +
        (this.anonymous ? 0 : this.userID);

      this.formParams = [
        { name: 'receiver', value: '41001161017513' },
        { name: 'sum', value: this.sum.toString() },
        { name: 'need-email', value: 'false' },
        { name: 'need-fio', value: 'false' },
        { name: 'need-phone', value: 'false' },
        { name: 'need-address', value: 'false' },
        { name: 'formcomment', value: data.translations[0] },
        { name: 'short-dest', value: data.translations[0] },
        { name: 'label', value: label },
        { name: 'quickpay-form', value: 'donate' },
        { name: 'targets', value: sprintf(data.translations[1], label) },
        {
          name: 'successURL',
          value: 'https://' + window.location.host + '/ng/donate/vod/success'
        }
      ];
    });
  }

  submit(e) {
    if (e.defaultPrevented) {
      e.target.submit();
    }
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }
}
