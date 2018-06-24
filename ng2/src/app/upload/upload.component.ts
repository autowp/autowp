import {
  Component,
  Injectable,
  OnInit,
  OnDestroy,
  ViewChild
} from '@angular/core';
// import { CropDialog } from 'crop-dialog';
import { HttpClient, HttpEventType } from '@angular/common/http';
import { APIItem, ItemService } from '../services/item';
import Notify from '../notify';
import {
  Subscription,
  empty,
  of,
  Observable,
  concat,
  combineLatest
} from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';
import { PictureService, APIPicture } from '../services/picture';
import { AuthService } from '../services/auth.service';
import { PageEnvService } from '../services/page-env.service';
import {
  switchMap,
  catchError,
  tap,
  distinctUntilChanged,
  debounceTime
} from 'rxjs/operators';
import { APIUser } from '../services/user';
import { UploadCropComponent } from './crop/crop.component';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';

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
  public files: any[];
  public note: string;
  public progress: UploadProgress[] = [];
  public pictures: APIPicture[] = [];
  public item: APIItem;
  public formHidden = false;
  private perspectiveID: number;
  public user: APIUser;

  @ViewChild('input') input;

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private route: ActivatedRoute,
    private router: Router,
    private pictureService: PictureService,
    public auth: AuthService,
    private pageEnv: PageEnvService,
    private modalService: NgbModal
  ) {}

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/29/name',
          pageId: 29
        }),
      0
    );

    this.auth.getUser().subscribe(user => (this.user = user));

    this.querySub = this.route.queryParams
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => {
          this.perspectiveID = params.perspective_id;
          const replace = parseInt(params.replace, 10);
          const itemId = parseInt(params.item_id, 10);

          return combineLatest(
            replace
              ? this.pictureService.getPicture(replace, {
                  fields: 'name_html'
                })
              : of(null),
            itemId
              ? this.itemService.getItem(itemId, {
                  fields: 'name_html'
                })
              : of(null)
          );
        })
      )
      .subscribe(responses => {
        if (responses[0]) {
          this.selected = true;
          this.replace = responses[0];
          this.selectionName = this.replace.name_html;
        }

        if (responses[1]) {
          this.selected = true;
          this.item = responses[1];
          this.selectionName = this.item.name_html;
        }
      });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  public onChange(event: any, input: any) {
    const files = [].slice.call(event.target.files);

    this.files = files;
  }

  public submit() {
    this.progress = [];

    this.formHidden = true;

    const xhrs: Observable<APIPicture>[] = [];

    for (const file of this.files) {
      xhrs.push(this.uploadFile(file));
    }

    concat(...xhrs).subscribe(
      a => {
        console.log('a', a);
      },
      undefined,
      () => {
        this.input.nativeElement.value = '';
        this.formHidden = false;
        this.files = undefined;
      }
    );

    return false;
  }

  private uploadFile(file: any): Observable<APIPicture> {
    const progress = {
      filename: file.fileName || file.name,
      percentage: 0,
      success: false,
      failed: false,
      invalidParams: {}
    };

    this.progress.push(progress);

    const formData: FormData = new FormData();
    formData.append('file', file);
    if (this.note) {
      formData.append('comment', this.note);
    }
    if (this.item) {
      formData.append('item_id', this.item.id + '');
    }
    if (this.replace) {
      formData.append('replace_picture_id', this.replace.id + '');
    }
    if (this.perspectiveID) {
      formData.append('perspective_id', this.perspectiveID + '');
    }

    return this.http
      .post('/api/picture', formData, {
        observe: 'events',
        reportProgress: true
      })
      .pipe(
        catchError((response, caught) => {
          progress.percentage = 100;
          progress.failed = true;

          progress.invalidParams = response.error.invalid_params;

          return empty();
        }),
        switchMap(event => {
          if (event.type === HttpEventType.DownloadProgress) {
            progress.percentage = Math.round(
              50 + 25 * (event.loaded / event.total)
            );
            return empty();
          }

          if (event.type === HttpEventType.UploadProgress) {
            progress.percentage = Math.round(50 * (event.loaded / event.total));
            return empty();
          }

          if (event.type === HttpEventType.Response) {
            progress.percentage = 75;
            progress.success = true;

            const location = event.headers.get('Location');

            return this.pictureService
              .getPictureByLocation(location, {
                fields:
                  'crop,image_gallery_full,thumb_medium,votes,views,comments_count,perspective_item,name_html,name_text'
              })
              .pipe(
                tap(picture => {
                  progress.percentage = 100;
                  this.pictures.push(picture);
                }),
                catchError((response, caught) => {
                  Notify.response(response);

                  return empty();
                })
              );
          }

          return empty();
        })
      );
  }

  public crop(picture: APIPicture) {
    const modalRef = this.modalService.open(UploadCropComponent, {
      size: 'lg',
      centered: true
    });

    modalRef.componentInstance.picture = picture;
    modalRef.componentInstance.changed.subscribe(() => {
      this.http
        .put<void>('/api/picture/' + picture.id, {
          crop: picture.crop
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
          },
          response => {
            Notify.response(response);
          }
        );
    });

    return false;
  }
}
