import { Input, Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { APIItem } from '../../../../services/item';
import { ACLService } from '../../../../services/acl.service';
import { HttpClient, HttpEventType } from '@angular/common/http';
import { APIImage } from '../../../../services/api.service';
import { Subscription, empty } from 'rxjs';
import { catchError, switchMap, tap } from 'rxjs/operators';
import Notify from '../../../../notify';

@Component({
  selector: 'app-moder-items-item-logo',
  templateUrl: './logo.component.html'
})
@Injectable()
export class ModerItemsItemLogoComponent implements OnInit, OnDestroy {
  @Input() item: APIItem;

  public canLogo = false;
  private aclSub: Subscription;
  public progress: {
    filename: any;
    percentage: number;
    success: boolean;
    failed: boolean;
    invalidParams: any;
  } = null;

  constructor(private acl: ACLService, private http: HttpClient) {}

  ngOnInit(): void {
    this.aclSub = this.acl
      .isAllowed('brand', 'logo')
      .subscribe(allow => (this.canLogo = allow));
  }

  ngOnDestroy(): void {
    this.aclSub.unsubscribe();
  }

  public onChange(event: any, input: any) {
    if (event.target.files.length <= 0) {
      return;
    }
    const file = event.target.files[0];

    this.progress = {
      filename: file.fileName || file.name,
      percentage: 0,
      success: false,
      failed: false,
      invalidParams: {}
    };

    const formData: FormData = new FormData();
    formData.append('file', file);

    return this.http
      .post('/api/item/' + this.item.id + '/logo', formData, {
        observe: 'events',
        reportProgress: true
      })
      .pipe(
        catchError(response => {
          console.log(response);
          this.progress.percentage = 100;
          this.progress.failed = true;

          this.progress.invalidParams = response.error.invalid_params;

          return empty();
        }),
        switchMap(httpEvent => {
          if (httpEvent.type === HttpEventType.DownloadProgress) {
            this.progress.percentage = Math.round(
              50 + 25 * (httpEvent.loaded / httpEvent.total)
            );
            return empty();
          }

          if (httpEvent.type === HttpEventType.UploadProgress) {
            this.progress.percentage = Math.round(
              50 * (httpEvent.loaded / httpEvent.total)
            );
            return empty();
          }

          if (httpEvent.type === HttpEventType.Response) {
            this.progress.percentage = 75;
            this.progress.success = true;

            return this.http
              .get<APIImage>('/api/item/' + this.item.id + '/logo')
              .pipe(
                tap(subresponse => {
                  this.progress.percentage = 100;
                  this.item.logo = subresponse;
                }),
                catchError((response, caught) => {
                  Notify.response(response);

                  return empty();
                })
              );
          }

          return empty();
        })
      )
      .subscribe();
  }
}
