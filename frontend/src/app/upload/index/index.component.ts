import type {ElementRef, OnInit} from '@angular/core';
import type {Item, Picture} from '@grpc/spec.pb';
import type {InvalidParams} from '@utils/invalid-params.pipe';
import type {PersonSearchSelection} from '@utils/person-search/person-search.component';
import type {InboxPicture} from 'app/inbox-pictures-grid/inbox-pictures-grid.component';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {HttpClient, HttpErrorResponse, HttpEventType} from '@angular/common/http';
import {ChangeDetectionStrategy, ChangeDetectorRef, Component, inject, signal, viewChild} from '@angular/core';
import {FormControl, FormsModule, ReactiveFormsModule} from '@angular/forms';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemRequest,
  ItemsRequest,
  PictureFields,
  PictureItemFields,
  PictureItemListOptions,
  PictureItemsRequest,
  PictureItemType,
  PictureListOptions,
  PicturesRequest,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {NgbProgressbar} from '@ng-bootstrap/ng-bootstrap';
import {AuthService} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {browserWindow} from '@utils/browser-window';
import {InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {PersonSearchComponent} from '@utils/person-search/person-search.component';
import {
  inboxCropTitle,
  InboxPicturesGridComponent,
  inboxSuggestionKey,
} from 'app/inbox-pictures-grid/inbox-pictures-grid.component';
import {ToastsService} from 'app/toasts/toasts.service';
import Keycloak from 'keycloak-js';
import {RemarkModule} from 'ngx-remark';
import {
  catchError,
  combineLatest,
  concat,
  distinctUntilChanged,
  EMPTY,
  map,
  of,
  switchMap,
  take,
  tap,
  throwError,
} from 'rxjs';

interface UploadProgress {
  failed: boolean;
  filename: string;
  invalidParams: InvalidParams;
  percentage: number;
  success: boolean;
}

@Component({
  selector: 'app-upload-index',
  imports: [
    FormsModule,
    RouterLink,
    NgbProgressbar,
    AsyncPipe,
    InvalidParamsPipe,
    ReactiveFormsModule,
    RemarkModule,
    PersonSearchComponent,
    InboxPicturesGridComponent,
  ],
  templateUrl: './index.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UploadIndexComponent implements OnInit {
  readonly #http = inject(HttpClient);
  readonly #route = inject(ActivatedRoute);
  protected readonly auth = inject(AuthService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #keycloak = inject(Keycloak);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #cdr = inject(ChangeDetectorRef);
  readonly #window = browserWindow();

  protected files: File[] | undefined;
  protected readonly note = new FormControl<string>('', {nonNullable: true});
  protected progress: UploadProgress[] = [];
  protected readonly pictures: InboxPicture[] = [];
  protected readonly formHidden = signal(false);
  protected readonly authenticated$ = this.auth.authenticated$;

  protected readonly PictureItemType = PictureItemType;

  // Batch author picked in the form and sent as author_id with every uploaded file.
  protected readonly batchAuthor = signal<null | PersonSearchSelection>(null);

  public readonly input = viewChild<ElementRef<HTMLInputElement>>('input');

  readonly #perspectiveID$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('perspective_id') ?? '', 10)),
    distinctUntilChanged(),
  );

  protected readonly replace$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('replace') ?? '', 10)),
    distinctUntilChanged(),
  );

  readonly #replacePicture$: Observable<null | Picture> = this.replace$.pipe(
    switchMap((replace) => {
      return replace
        ? this.#picturesClient
            .getPicture(
              new PicturesRequest({
                fields: new PictureFields({nameHtml: true}),
                language: this.#languageService.language,
                options: new PictureListOptions({id: '' + replace}),
              }),
            )
            .pipe(
              catchError((response: unknown) => {
                this.#toastService.handleError(response);
                return EMPTY;
              }),
            )
        : of(null);
    }),
  );

  protected readonly itemID$: Observable<string> = this.#route.queryParamMap.pipe(
    map((params) => params.get('item_id') ?? ''),
    distinctUntilChanged(),
  );

  readonly #item$: Observable<Item | null> = this.itemID$.pipe(
    switchMap((id) => {
      if (!id) {
        return of(null);
      }
      return this.#itemsClient
        .item(
          new ItemRequest({
            fields: new ItemFields({nameHtml: true}),
            id,
            language: this.#languageService.language,
          }),
        )
        .pipe(
          catchError((response: unknown) => {
            this.#toastService.handleError(response);
            return of(null);
          }),
        );
    }),
  );

  protected readonly selection$ = combineLatest([this.#replacePicture$, this.#item$]).pipe(
    map(([replace, item]) => ({
      name: replace?.nameHtml ?? item?.nameHtml ?? '',
      selected: !!(replace ?? item),
    })),
  );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.UPLOAD});
  }

  protected doLogin() {
    void this.#keycloak.login({
      locale: this.#languageService.language,
      redirectUri: this.#window?.location.href,
    });
  }

  protected onChange(event: Event) {
    this.files = [].slice.call((event.target as HTMLInputElement).files);
    this.#cdr.markForCheck();
  }

  protected submit() {
    this.progress = [];
    this.#cdr.markForCheck();

    this.formHidden.set(true);

    const xhrs: Observable<Picture>[] = [];

    for (const file of this.files ?? []) {
      xhrs.push(this.uploadFile$(file));
    }

    concat(...xhrs).subscribe({
      complete: () => {
        const elementRef = this.input();
        if (elementRef) {
          elementRef.nativeElement.value = '';
        }
        this.formHidden.set(false);
        this.files = undefined;
        this.#cdr.markForCheck();
      },
    });

    return false;
  }

  private uploadFile$(file: File): Observable<Picture> {
    const progress = {
      failed: false,
      filename: file.name,
      invalidParams: {},
      percentage: 0,
      success: false,
    };

    this.progress.push(progress);
    this.#cdr.markForCheck();

    return combineLatest([
      this.itemID$.pipe(take(1)),
      this.replace$.pipe(take(1)),
      this.#perspectiveID$.pipe(take(1)),
    ]).pipe(
      map(([itemID, replace, perspectiveID]) => {
        const formData: FormData = new FormData();
        formData.append('file', file);
        if (this.note.value) {
          formData.append('comment', this.note.value);
        }

        if (itemID) {
          formData.append('item_id', itemID);
        }
        if (replace) {
          formData.append('replace_picture_id', replace + '');
        }
        if (perspectiveID) {
          formData.append('perspective_id', perspectiveID + '');
        }
        const batchAuthor = this.batchAuthor();
        if (batchAuthor) {
          formData.append('author_id', batchAuthor.id);
        }

        return formData;
      }),
      switchMap((formData) =>
        this.#http.request<{id: string}>('POST', '/api/picture', {
          body: formData,
          observe: 'events',
          reportProgress: true,
        }),
      ),
      catchError((response: unknown) => {
        if (response instanceof HttpErrorResponse) {
          progress.percentage = 100;
          progress.failed = true;

          // HttpErrorResponse.error is `any` - the backend error body's shape is only known by
          // convention (an `invalid_params` field), not typed by Angular.
          const body = response.error as {invalid_params: InvalidParams};
          progress.invalidParams = body.invalid_params;
          this.#cdr.markForCheck();
        }

        return EMPTY;
      }),
      switchMap((event) => {
        if (event.type === HttpEventType.DownloadProgress) {
          if (event.total) {
            progress.percentage = Math.round(50 + 25 * (event.loaded / event.total));
          }

          this.#cdr.markForCheck();
          return EMPTY;
        }

        if (event.type === HttpEventType.UploadProgress) {
          if (event.total) {
            progress.percentage = Math.round(50 * (event.loaded / event.total));
          }

          this.#cdr.markForCheck();
          return EMPTY;
        }

        if (event.type === HttpEventType.Response) {
          progress.percentage = 75;
          progress.success = true;
          this.#cdr.markForCheck();

          if (!event.body) {
            return throwError(() => new Error('no response body'));
          }

          const pictureID = event.body.id;

          return this.#picturesClient
            .getPicture(
              new PicturesRequest({
                fields: new PictureFields({
                  authorSuggestions: true,
                  commentsCount: true,
                  image: true,
                  imageGalleryFull: true,
                  moderVote: true,
                  nameHtml: true,
                  nameText: true,
                  pictureItem: new PictureItemsRequest({
                    fields: new PictureItemFields({item: new ItemsRequest({fields: new ItemFields({nameHtml: true})})}),
                    options: new PictureItemListOptions(),
                  }),
                  thumbMedium: true,
                  views: true,
                  votes: true,
                }),
                language: this.#languageService.language,
                options: new PictureListOptions({id: pictureID}),
              }),
            )
            .pipe(
              tap((picture) => {
                progress.percentage = 100;

                const pictureItems = picture.pictureItems?.items ?? [];
                const authorItem = pictureItems.find(
                  (pictureItem) => pictureItem.type === PictureItemType.PICTURE_ITEM_AUTHOR,
                );
                const contentItem = pictureItems.find(
                  (pictureItem) => pictureItem.type === PictureItemType.PICTURE_ITEM_CONTENT,
                );
                const suggestions = picture.authorSuggestions ?? [];

                this.pictures.push({
                  author: authorItem?.item ? {id: authorItem.itemId, nameHtml: authorItem.item.nameHtml} : null,
                  authorSuggestions: suggestions,
                  contentItemId: contentItem ? contentItem.itemId : null,
                  cropTitle: inboxCropTitle(picture.image),
                  perspectiveId: contentItem?.perspectiveId ?? 0,
                  picture,
                  suggestionKey: inboxSuggestionKey(suggestions),
                });
                this.#cdr.markForCheck();
              }),
              catchError((response: unknown) => {
                if (response instanceof HttpErrorResponse) {
                  this.#toastService.response(response);
                }
                return EMPTY;
              }),
            );
        }

        return EMPTY;
      }),
    );
  }

  protected onBatchAuthorSelected(selection: PersonSearchSelection): void {
    this.batchAuthor.set(selection);
  }

  protected clearBatchAuthor(): void {
    this.batchAuthor.set(null);
  }
}
