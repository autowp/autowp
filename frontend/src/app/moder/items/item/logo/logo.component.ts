import {AsyncPipe} from '@angular/common';
import {HttpClient, HttpErrorResponse, HttpEventType} from '@angular/common/http';
import {ChangeDetectionStrategy, ChangeDetectorRef, Component, inject, input, output} from '@angular/core';
import {APIItem} from '@grpc/spec.pb';
import {NgbProgressbar} from '@ng-bootstrap/ng-bootstrap';
import {AuthService, Role} from '@services/auth.service';
import {InvalidParams, InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {RemarkModule} from 'ngx-remark';
import {EMPTY} from 'rxjs';
import {catchError, switchMap} from 'rxjs/operators';

@Component({
  selector: 'app-moder-items-item-logo',
  imports: [NgbProgressbar, AsyncPipe, InvalidParamsPipe, RemarkModule],
  templateUrl: './logo.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemLogoComponent {
  readonly #auth = inject(AuthService);
  readonly #http = inject(HttpClient);
  readonly #cdr = inject(ChangeDetectorRef);

  readonly item = input.required<APIItem>();

  readonly itemUpdated = output<void>();

  protected readonly canLogo$ = this.#auth.hasRole$(Role.BRANDS_MODER);
  protected progress: null | {
    failed: boolean;
    filename: string;
    invalidParams: InvalidParams;
    percentage: number;
    success: boolean;
  } = null;

  protected onChange(event: Event) {
    const files = (event.target as HTMLInputElement).files;
    if (!files || files.length <= 0) {
      return;
    }
    const file = files[0];

    this.progress = {
      failed: false,
      filename: file.name,
      invalidParams: {},
      percentage: 0,
      success: false,
    };

    const formData: FormData = new FormData();
    formData.append('file', file);

    this.#cdr.markForCheck();

    if (this.item()) {
      this.#http
        .request('POST', '/api/item/' + this.item().id + '/logo', {
          body: formData,
          observe: 'events',
          reportProgress: true,
        })
        .pipe(
          catchError((response: unknown) => {
            if (response instanceof HttpErrorResponse) {
              if (this.progress) {
                this.progress.percentage = 100;
                this.progress.failed = true;

                this.progress.invalidParams = response.error.invalid_params;
                this.#cdr.markForCheck();
              }
            }

            return EMPTY;
          }),
          switchMap((httpEvent) => {
            if (httpEvent.type === HttpEventType.DownloadProgress) {
              if (this.progress && httpEvent.total) {
                this.progress.percentage = Math.round(50 + 25 * (httpEvent.loaded / httpEvent.total));
                this.#cdr.markForCheck();
              }

              return EMPTY;
            }

            if (httpEvent.type === HttpEventType.UploadProgress) {
              if (this.progress && httpEvent.total) {
                this.progress.percentage = Math.round(50 * (httpEvent.loaded / httpEvent.total));
                this.#cdr.markForCheck();
              }

              return EMPTY;
            }

            if (httpEvent.type === HttpEventType.Response) {
              if (this.progress) {
                this.progress.percentage = 100;
                this.progress.success = true;
                this.#cdr.markForCheck();
              }

              if (!this.item()) {
                return EMPTY;
              }

              this.itemUpdated.emit(void 0);
            }

            return EMPTY;
          }),
        )
        .subscribe();
    }
  }
}
