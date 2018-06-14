import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import * as $ from 'jquery';
require('jcrop-0.9.12/css/jquery.Jcrop.css');
require('jcrop-0.9.12/js/jquery.Jcrop');
import { sprintf } from 'sprintf-js';
import { HttpClient } from '@angular/common/http';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import { PictureService } from '../../../../services/picture';
import { PageEnvService } from '../../../../services/page-env.service';

// Acl.inheritsRole( 'moder', 'unauthorized' );

interface Crop {
  w: number;
  h: number;
  x: number;
  y: number;
}

@Component({
  selector: 'app-moder-pictures-item-crop',
  templateUrl: './crop.component.html'
})
@Injectable()
export class ModerPicturesItemCropComponent implements OnInit, OnDestroy {
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
  private minSize = [400, 300];
  public picture: any;

  constructor(
    private http: HttpClient,
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
      this.pictureService
        .getPicture(params.id, {
          fields: 'crop,image'
        })

        .subscribe(response => {
          this.picture = response;

          /* const $body = $($element[0]).find('.crop-area');
          const $img = $body.find('img');

          this.jcrop = null;
          if (this.picture.crop) {
            this.currentCrop = {
              w: this.picture.crop.width,
              h: this.picture.crop.height,
              x: this.picture.crop.left,
              y: this.picture.crop.top
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
        });
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }

  public selectAll() {
    this.jcrop.setSelect([0, 0, this.picture.width, this.picture.height]);
  }

  public saveCrop() {
    this.http
      .put<void>('/api/picture/' + this.picture.id, {
        crop: {
          left: Math.round(this.currentCrop.x),
          top: Math.round(this.currentCrop.y),
          width: Math.round(this.currentCrop.w),
          height: Math.round(this.currentCrop.h)
        }
      })
      .subscribe(() => {
        this.router.navigate(['/moder/pictures', this.picture.id]);
      });
  }

  private updateSelectionText() {
    const text =
      Math.round(this.currentCrop.w) + '×' + Math.round(this.currentCrop.h);
    const pw = 4;
    const ph = pw * this.currentCrop.h / this.currentCrop.w;
    const phRound = Math.round(ph * 10) / 10;

    this.aspect = pw + ':' + phRound;
    this.resolution = text;
  }
}