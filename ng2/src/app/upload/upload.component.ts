import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
// import { CropDialog } from 'crop-dialog';
import { HttpClient } from '@angular/common/http';
import { APIItem, ItemService } from '../services/item';
import Notify from '../notify';
import { Subscription } from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';
import { PictureService, APIPicture } from '../services/picture';
import { AuthService } from '../services/auth.service';

interface UploadProgress {
  filename: string;
  percentage: number;
  success: boolean;
  failed: boolean;
  invalidParams: any;
}

@Component({
  selector: 'app-upload',
  templateUrl: './upload.component.html'
})
@Injectable()
export class UploadComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public selected: boolean;
  public selectionName: string;
  public replace: APIPicture;
  public file: any;
  public note: string;
  public progress: UploadProgress[] = [];
  public pictures: APIPicture[] = [];
  public item: APIItem;
  public formHidden = false;
  private perspective_id: number;
  private Upload: any; // TODO: private Upload: ng.angularFileUpload.IUploadService,

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private route: ActivatedRoute,
    private router: Router,
    private pictureService: PictureService,
    public auth: AuthService
  ) {}

  ngOnInit(): void {
    /*this.$scope.pageEnv({
      layout: {
        blankPage: false,
        needRight: false
      },
      name: 'page/29/name',
      pageId: 29
    });*/
    this.querySub = this.route.queryParams.subscribe(params => {
      this.perspective_id = params.perspective_id;
      const replace = parseInt(params.replace, 10);
      if (replace) {
        this.pictureService
          .getPicture(replace, {
            fields: 'name_html'
          })
          .subscribe(
            response => {
              this.replace = response;

              this.selected = true;
              this.selectionName = this.replace.name_html;
            },
            response => {
              this.router.navigate(['/error-404']);
            }
          );
      }

      const itemId = parseInt(params.item_id, 10);
      if (itemId) {
        this.itemService
          .getItem(itemId, {
            fields: 'name_html'
          })
          .subscribe(
            (item: APIItem) => {
              this.selected = true;
              this.item = item;
              this.selectionName = item.name_html;
            },
            response => {
              this.router.navigate(['/error-404']);
            }
          );
      }
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  public submit() {
    this.progress = [];

    this.formHidden = true;

    const xhrs: any[] = [];

    if (this.replace) {
      const promise = this.uploadFile(this.file);

      xhrs.push(promise);
    } else {
      for (const file of this.file) {
        const promise = this.uploadFile(file);

        xhrs.push(promise);
      }
    }

    Promise.all(xhrs).then(
      () => {
        this.formHidden = false;
        this.file = undefined;
      },
      () => {
        this.formHidden = false;
        this.file = undefined;
      }
    );
  }

  private uploadFile(file: any) {
    const progress = {
      filename: file.fileName || file.name,
      percentage: 0,
      success: false,
      failed: false,
      invalidParams: {}
    };

    this.progress.push(progress);

    const itemId = this.item ? this.item.id : undefined;
    let perspectiveId = this.perspective_id;
    if (!perspectiveId) {
      perspectiveId = undefined;
    }

    const promise = this.Upload.upload({
      method: 'POST',
      url: '/api/picture',
      data: {
        file: file,
        comment: this.note,
        item_id: itemId,
        replace_picture_id: this.replace ? this.replace.id : undefined,
        perspective_id: perspectiveId
      }
    }).then(
      response => {
        progress.percentage = 100;
        progress.success = true;

        const location = response.headers('Location');

        this.pictureService
          .getPictureByLocation(location, {
            fields:
              'crop,image_gallery_full,thumb_medium,votes,views,comments_count,perspective_item,name_html,name_text'
          })
          .subscribe(
            picture => {
              this.pictures.push(picture);
            },
            subresponse => {
              Notify.response(subresponse);
            }
          );
      },
      response => {
        progress.percentage = 100;
        progress.failed = true;

        progress.invalidParams = response.error.invalid_params;
      },
      evt => {
        progress.percentage = Math.round(100.0 * evt.loaded / evt.total);
      }
    );

    return promise;
  }

  public crop(picture: APIPicture) {
    /* const cropDialog = new CropDialog({
      sourceUrl: picture.image_gallery_full.src,
      crop: {
        x: picture.crop ? picture.crop.left : 0,
        y: picture.crop ? picture.crop.top : 0,
        w: picture.crop ? picture.crop.width : picture.width,
        h: picture.crop ? picture.crop.height : picture.height
      },
      width: picture.width,
      height: picture.height,
      onSave: (crop: any, callback: Function) => {
        this.http
          .put<void>('/api/picture/' + picture.id, {
              crop: {
                left: crop.x,
                top: crop.y,
                width: crop.w,
                height: crop.h
              }
          })
          .subscribe(
            () => {
              this.pictureService
                .getPicture(picture.id, {
                  fields: 'crop,thumb_medium'
                })
                .subscribe(
                  response => {
                    picture.crop = response.crop;
                    picture.thumb_medium = response.thumb_medium;
                  },
                  response => {
                    Notify.response(response);
                  }
                );

              cropDialog.hide();

              callback();
            },
            response => {
              Notify.response(response);
            }
          );
      }
    });

    cropDialog.show();*/
  }
}
