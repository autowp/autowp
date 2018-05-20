import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIItem, ItemService } from '../../services/item';
import Notify from '../../notify';
import { TranslateService } from '@ngx-translate/core';
import { ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import { AuthService } from '../../services/auth.service';
import { DonateService, APIDonateCarOfDayDate } from '../../services/donate';
import { PageEnvService } from '../../services/page-env.service';

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
  private userId: number;
  public sum: number;
  public dates: APIDonateCarOfDayDate[];
  private itemId: number;

  constructor(
    private translate: TranslateService,
    private http: HttpClient,
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
    this.querySub = this.route.queryParams.subscribe(params => {
      this.itemId = params.item_id || 0;
      this.selectedDate = params.date;
      this.userId = this.auth.user ? this.auth.user.id : 0;
      this.anonymous = this.userId ? !!params.anonymous : true;

      this.donateService.getVOD().subscribe(
        response => {
          this.sum = response.sum;
          this.dates = response.dates;
        },
        response => {
          Notify.response(response);
        }
      );

      if (this.itemId) {
        this.itemService
          .getItem(this.itemId, {
            fields: 'name_html,item_of_day_pictures'
          })
          .subscribe(
            (item: APIItem) => {
              this.selectedItem = item;
              this.updateForm();
            },
            response => {
              Notify.response(response);
            }
          );
      }

      this.updateForm();
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  private updateForm() {
    if (!this.selectedItem || !this.selectedDate) {
      this.formParams = null;
      return;
    }

    this.translate
      .get(['donate/vod/order-message', 'donate/vod/order-target'])
      .subscribe((translations: string[]) => {
        const label =
          'vod/' +
          this.selectedDate +
          '/' +
          this.selectedItem.id +
          '/' +
          (this.anonymous ? 0 : this.userId);

        this.formParams = [
          { name: 'receiver', value: '41001161017513' },
          { name: 'sum', value: this.sum.toString() },
          { name: 'need-email', value: 'false' },
          { name: 'need-fio', value: 'false' },
          { name: 'need-phone', value: 'false' },
          { name: 'need-address', value: 'false' },
          { name: 'formcomment', value: translations[0] },
          { name: 'short-dest', value: translations[0] },
          { name: 'label', value: label },
          { name: 'quickpay-form', value: 'donate' },
          { name: 'targets', value: sprintf(translations[1], label) },
          {
            name: 'successURL',
            value: 'https://' + window.location.host + '/ng/donate/vod/success'
          }
        ];
      });
  }

  public selectDate(date: string) {
    this.selectedDate = date;
    this.updateForm();
  }

  public setAnonymous(value: boolean) {
    this.anonymous = value;
    this.updateForm();
  }
}
