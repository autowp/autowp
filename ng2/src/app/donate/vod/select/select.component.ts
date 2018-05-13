import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { APIPaginator } from '../../../services/api.service';
import { APIItem, ItemService } from '../../../services/item';
import Notify from '../../../notify';
import { chunk } from '../../../chunk';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import {
  ItemParentService,
  APIItemParent
} from '../../../services/item-parent';
import { PageEnvService } from '../../../services/page-env.service';

@Component({
  selector: 'app-donate-vod-select',
  templateUrl: './select.component.html'
})
@Injectable()
export class DonateVodSelectComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public page: number;
  public brands: APIItem[][];
  public paginator: APIPaginator;
  public brand: APIItem;
  public vehicles: APIItemParent[];
  public vehicles_paginator: APIPaginator;
  public concepts: APIItemParent[];
  public selectItem: (itemId: number) => void;
  private date: string;
  private anonymous: boolean;
  public loading = 0;

  constructor(
    private itemService: ItemService,
    private router: Router,
    private route: ActivatedRoute,
    private itemParentService: ItemParentService,
    private pageEnv: PageEnvService
  ) {
    this.pageEnv.set({
      layout: {
        needRight: false
      },
      name: 'page/196/name',
      pageId: 196
    });

    this.selectItem = (itemId: number) => {
      this.router.navigate(['/donate/vod'], {
        queryParams: {
          item_id: itemId,
          date: this.date,
          anonymous: this.anonymous ? 1 : null
        }
      });
    };
  }

  ngOnInit(): void {
    this.querySub = this.route.queryParams.subscribe(params => {
      this.page = params.page || 1;
      this.date = params.date;
      this.anonymous = !!params.anonymous;
      const brandId = params.brand_id;

      if (brandId) {
        this.loading++;

        this.itemService.getItem(brandId).subscribe(
          (brand: APIItem) => {
            this.brand = brand;

            this.loading++;
            this.itemParentService
              .getItems({
                item_type_id: 1,
                parent_id: this.brand.id,
                fields:
                  'item.name_html,item.childs_count,item.is_compiles_item_of_day',
                limit: 500,
                page: 1
              })
              .subscribe(
                response => {
                  this.vehicles = response.items;
                  this.vehicles_paginator = response.paginator;
                  this.loading--;
                },
                response => {
                  Notify.response(response);
                  this.loading--;
                }
              );

            this.loading++;
            this.itemParentService
              .getItems({
                item_type_id: 1,
                concept: true,
                ancestor_id: this.brand.id,
                fields:
                  'item.name_html,item.childs_count,item.is_compiles_item_of_day',
                limit: 500,
                page: 1
              })
              .subscribe(
                response => {
                  this.concepts = response.items;
                  this.loading--;
                },
                response => {
                  Notify.response(response);
                  this.loading--;
                }
              );

            this.loading--;
          },
          response => {
            Notify.response(response);
            this.loading--;
          }
        );
      } else {
        this.loading++;
        this.itemService
          .getItems({
            type_id: 5,
            limit: 500,
            fields: 'name_only',
            page: this.page
          })
          .subscribe(
            response => {
              this.brands = chunk(response.items, 6);
              this.paginator = response.paginator;
              this.loading--;
            },
            response => {
              Notify.response(response);
              this.loading--;
            }
          );
      }
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }
}
