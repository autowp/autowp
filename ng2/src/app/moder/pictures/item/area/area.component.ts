import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import * as $ from 'jquery';
require('jcrop-0.9.12/css/jquery.Jcrop.css');
require('jcrop-0.9.12/js/jquery.Jcrop');
import { sprintf } from 'sprintf-js';
import { HttpClient } from '@angular/common/http';
import { PictureItemService } from '../../../../services/picture-item';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription, forkJoin } from 'rxjs';
import { PictureService, APIPicture } from '../../../../services/picture';
import { PageEnvService } from '../../../../services/page-env.service';

// Acl.inheritsRole('moder', 'unauthorized');

interface Crop {
  w: number;
  h: number;
  x: number;
  y: number;
}

@Component({
  selector: 'app-moder-pictures-item-area',
  templateUrl: './area.component.html'
})
@Injectable()
export class ModerPicturesItemAreaComponent implements OnInit, OnDestroy {
  private id: number;
  private item_id: number;
  private type: number;
  private querySub: Subscription;
  private routeSub: Subscription;
  public aspect = '';
  public resolution = '';
  private jcrop: any;
  private currentCrop: Crop = {
    w: 0,
    h: 0,
    x: 0,
    y: 0
  };
  private minSize = [50, 50];
  public picture: APIPicture;

  constructor(
    private http: HttpClient,
    private pictureItemService: PictureItemService,
    private router: Router,
    private route: ActivatedRoute,
    private pictureService: PictureService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/148/name',
          pageId: 148
        }),
      0
    );

    this.routeSub = this.route.params.subscribe(params => {
      this.id = params.id;
      this.item_id = params.item_id;
      this.type = params.type;

      this.load();
    });

    this.querySub = this.route.queryParams.subscribe(params => {
      this.load();
    });
  }

  private load() {
    forkJoin(
      this.pictureService.getPicture(this.id, {
        fields: 'crop,image'
      }),
      this.pictureItemService.get(this.id, this.item_id, this.type, {
        fields: 'area'
      })
    ).subscribe(
      data => {
        const area = data[1].data.area;

        const response = data[0];

        this.picture = response;

        /* const $body = $($element[0]).find('.crop-area');
        const $img = $body.find('img');

        this.jcrop = null;
        if (area) {
          this.currentCrop = {
            w: area.width,
            h: area.height,
            x: area.left,
            y: area.top
          };
        } else {
          this.currentCrop = {
            w: this.picture.width,
            h: this.picture.height,
            x: 0,
            y: 0
          };
        }

        const bWidth = $body.width() || 1;

        const scale = this.picture.width / bWidth,
          width = this.picture.width / scale,
          height = this.picture.height / scale;

        $img
          .css({
            width: width,
            height: height
          })
          .on('load', () => {
            // sometimes Jcrop fails without delay
            setTimeout(() => {
              this.jcrop = $.Jcrop($img[0], {
                onSelect: (c: Crop) => {
                  this.currentCrop = c;
                  this.updateSelectionText();
                },
                setSelect: [
                  this.currentCrop.x,
                  this.currentCrop.y,
                  this.currentCrop.x + this.currentCrop.w,
                  this.currentCrop.y + this.currentCrop.h
                ],
                minSize: this.minSize,
                boxWidth: width,
                boxHeight: height,
                trueSize: [this.picture.width, this.picture.height],
                keySupport: false
              });
            }, 100);
          });*/
      },
      () => {
        this.router.navigate(['/error-404']);
      }
    );
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
    this.querySub.unsubscribe();
  }

  public selectAll() {
    this.jcrop.setSelect([0, 0, this.picture.width, this.picture.height]);
  }

  private updateSelectionText() {
    const text =
      Math.round(this.currentCrop.w) + '×' + Math.round(this.currentCrop.h);
    const pw = 4;
    const ph = (pw * this.currentCrop.h) / this.currentCrop.w;
    const phRound = Math.round(ph * 10) / 10;

    this.aspect = pw + ':' + phRound;
    this.resolution = text;
  }

  public saveCrop() {
    const area = {
      left: Math.round(this.currentCrop.x),
      top: Math.round(this.currentCrop.y),
      width: Math.round(this.currentCrop.w),
      height: Math.round(this.currentCrop.h)
    };

    this.pictureItemService
      .setArea(this.id, this.item_id, this.type, area)
      .subscribe(
        () => {
          this.router.navigate(['/moder/pictures', this.picture.id]);
        },
        () => {}
      );
  }
}
