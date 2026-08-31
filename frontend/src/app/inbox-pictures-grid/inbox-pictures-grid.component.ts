import type {DoCheck} from '@angular/core';
import type {Image, PictureAuthorSuggestion} from '@grpc/spec.pb';
import type {PersonSearchSelection} from '@utils/person-search/person-search.component';
import type {Observable} from 'rxjs';

import {ChangeDetectionStrategy, ChangeDetectorRef, Component, inject, input, signal} from '@angular/core';
import {
  CreatePictureItemRequest,
  DeletePictureItemRequest,
  Picture,
  PictureCrop,
  PictureFields,
  PictureItem,
  PictureItemType,
  PictureListOptions,
  PicturesRequest,
  UpdatePictureItemRequest,
  UpdatePictureRequest,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {NgbModal} from '@ng-bootstrap/ng-bootstrap';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {LanguageService} from '@services/language';
import {getModalComponentRef} from '@utils/modal-component-ref';
import {PersonSearchComponent} from '@utils/person-search/person-search.component';
import {ThumbnailComponent} from 'app/thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from 'app/toasts/toasts.service';
import {catchError, EMPTY, merge, switchMap, tap, toArray} from 'rxjs';

import {ModerPicturesPerspectivePickerComponent} from '../moder/pictures/perspective-picker/perspective-picker.component';
import {UploadCropComponent} from '../upload/crop/crop.component';

export interface InboxPicture {
  author: null | {id: string; nameHtml: string};
  authorSuggestions: PictureAuthorSuggestion[];
  // item_id of the CONTENT picture-item the perspective is set on; null when the picture has none.
  contentItemId: null | string;
  cropTitle: string;
  perspectiveId: number;
  picture: Picture;
  suggestionKey: string;
}

interface CascadeState {
  authorName: string;
  itemId: string;
  key: string;
  pictureIds: string[];
}

// A stable key for a picture's set of EXIF-derived author candidates, so a batch of photos that
// resolved to the same candidates can share one decision.
export const inboxSuggestionKey = (suggestions: PictureAuthorSuggestion[]): string =>
  suggestions
    .map((suggestion) => suggestion.itemId)
    .sort((a, b) => a.localeCompare(b))
    .join('|');

export const inboxCropTitle = (image: Image | undefined): string => {
  if (!(image?.cropWidth && image.cropHeight)) {
    return '';
  }
  const cropSize = `${image.cropWidth}×${image.cropHeight}+${image.cropLeft}+${image.cropTop}`;
  return $localize`cropped to ${cropSize}`;
};

// Above this many photos, the "applied to N others" banner stays until dismissed rather than
// being a quick confirmation the uploader might miss.
const cascadeStickyThreshold = 20;

@Component({
  selector: 'app-inbox-pictures-grid',
  imports: [ThumbnailComponent, PersonSearchComponent, ModerPicturesPerspectivePickerComponent],
  templateUrl: './inbox-pictures-grid.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class InboxPicturesGridComponent implements DoCheck {
  readonly #modalService = inject(NgbModal);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);
  readonly #cdr = inject(ChangeDetectorRef);

  readonly pictures = input.required<InboxPicture[]>();

  protected readonly PictureItemType = PictureItemType;

  protected readonly batchAuthor = signal<null | PersonSearchSelection>(null);
  protected readonly cascade = signal<CascadeState | null>(null);
  protected readonly cascadeSticky = signal(false);
  protected readonly editingAuthor = signal<null | string>(null);

  // suggestionKey → chosen person: once a suggestion is accepted on one photo, every later photo
  // with the identical candidate set gets the same author without asking again.
  readonly #batchAuthorChoice = new Map<string, {id: string; nameHtml: string}>();
  readonly #seen = new Set<string>();

  ngDoCheck(): void {
    for (const upload of this.pictures()) {
      if (this.#seen.has(upload.picture.id)) {
        continue;
      }
      this.#seen.add(upload.picture.id);
      this.#applyBatchChoice(upload);
    }
  }

  #linkAuthor$(pictureId: string, itemId: string): Observable<unknown> {
    return this.#picturesClient
      .createPictureItem(new CreatePictureItemRequest({itemId, pictureId, type: PictureItemType.PICTURE_ITEM_AUTHOR}))
      .pipe(
        catchError((response: unknown) => {
          this.#toastService.handleError(response);
          return EMPTY;
        }),
      );
  }

  #unlinkAuthor$(pictureId: string, itemId: string): Observable<unknown> {
    return this.#picturesClient
      .deletePictureItem(new DeletePictureItemRequest({itemId, pictureId, type: PictureItemType.PICTURE_ITEM_AUTHOR}))
      .pipe(
        catchError((response: unknown) => {
          this.#toastService.handleError(response);
          return EMPTY;
        }),
      );
  }

  #applyBatchChoice(upload: InboxPicture): void {
    if (upload.author || upload.suggestionKey === '') {
      return;
    }

    const choice = this.#batchAuthorChoice.get(upload.suggestionKey);
    if (!choice) {
      return;
    }

    this.#linkAuthor$(upload.picture.id, choice.id).subscribe(() => {
      upload.author = {id: choice.id, nameHtml: choice.nameHtml};
      this.#cdr.markForCheck();
    });
  }

  protected pickSuggestion(upload: InboxPicture, suggestion: PictureAuthorSuggestion): void {
    const nameHtml = suggestion.item?.nameHtml ?? '';
    const itemId = suggestion.itemId;

    this.#linkAuthor$(upload.picture.id, itemId)
      .pipe(
        tap(() => {
          upload.author = {id: itemId, nameHtml};
          this.#cdr.markForCheck();
        }),
        switchMap(() => {
          if (upload.suggestionKey === '') {
            return EMPTY;
          }

          this.#batchAuthorChoice.set(upload.suggestionKey, {id: itemId, nameHtml});

          const affected = this.pictures().filter(
            (other) => other !== upload && !other.author && other.suggestionKey === upload.suggestionKey,
          );
          if (affected.length === 0) {
            return EMPTY;
          }

          return merge(
            ...affected.map((other) =>
              this.#linkAuthor$(other.picture.id, itemId).pipe(
                tap(() => {
                  other.author = {id: itemId, nameHtml};
                  this.#cdr.markForCheck();
                }),
              ),
            ),
          ).pipe(
            toArray(),
            tap(() => {
              this.cascade.set({
                authorName: nameHtml,
                itemId,
                key: upload.suggestionKey,
                pictureIds: affected.map((other) => other.picture.id),
              });
              this.cascadeSticky.set(affected.length > cascadeStickyThreshold);
              this.#cdr.markForCheck();
            }),
          );
        }),
      )
      .subscribe();
  }

  protected chooseAuthor(upload: InboxPicture, selection: PersonSearchSelection): void {
    this.editingAuthor.set(null);

    const link$ = this.#linkAuthor$(upload.picture.id, selection.id);
    const source$ = upload.author
      ? this.#unlinkAuthor$(upload.picture.id, upload.author.id).pipe(switchMap(() => link$))
      : link$;

    source$.subscribe(() => {
      upload.author = {id: selection.id, nameHtml: selection.nameHtml};
      this.#cdr.markForCheck();
    });
  }

  protected clearAuthor(upload: InboxPicture): void {
    if (!upload.author) {
      return;
    }

    const {id} = upload.author;
    this.#unlinkAuthor$(upload.picture.id, id).subscribe(() => {
      upload.author = null;
      this.#cdr.markForCheck();
    });
  }

  protected applyAuthorToAll(): void {
    const choice = this.batchAuthor();
    if (!choice) {
      return;
    }

    for (const upload of this.pictures()) {
      if (upload.author) {
        continue;
      }
      this.#linkAuthor$(upload.picture.id, choice.id).subscribe(() => {
        upload.author = {id: choice.id, nameHtml: choice.nameHtml};
        this.#cdr.markForCheck();
      });
    }
  }

  protected undoCascade(): void {
    const state = this.cascade();
    if (!state) {
      return;
    }

    this.#batchAuthorChoice.delete(state.key);

    for (const upload of this.pictures()) {
      if (upload.author?.id !== state.itemId || !state.pictureIds.includes(upload.picture.id)) {
        continue;
      }
      this.#unlinkAuthor$(upload.picture.id, state.itemId).subscribe(() => {
        upload.author = null;
        this.#cdr.markForCheck();
      });
    }

    this.dismissCascade();
  }

  protected dismissCascade(): void {
    this.cascade.set(null);
    this.cascadeSticky.set(false);
  }

  protected onBatchAuthorSelected(selection: PersonSearchSelection): void {
    this.batchAuthor.set(selection);
  }

  protected clearBatchAuthor(): void {
    this.batchAuthor.set(null);
  }

  protected toggleEditAuthor(pictureId: string): void {
    this.editingAuthor.update((current) => (current === pictureId ? null : pictureId));
  }

  protected setPerspective(upload: InboxPicture, perspectiveId: number): void {
    if (!upload.contentItemId) {
      return;
    }

    this.#picturesClient
      .updatePictureItem(
        new UpdatePictureItemRequest({
          pictureItem: new PictureItem({
            itemId: upload.contentItemId,
            perspectiveId: perspectiveId || undefined,
            pictureId: upload.picture.id,
            type: PictureItemType.PICTURE_ITEM_CONTENT,
          }),
          updateMask: new FieldMask({paths: ['perspective_id']}),
        }),
      )
      .pipe(
        catchError((response: unknown) => {
          this.#toastService.handleError(response);
          return EMPTY;
        }),
      )
      .subscribe(() => {
        upload.perspectiveId = perspectiveId;
        this.#cdr.markForCheck();
      });
  }

  protected crop(upload: InboxPicture): void {
    const modalRef = this.#modalService.open(UploadCropComponent, {centered: true, size: 'lg'});
    const componentRef = getModalComponentRef<UploadCropComponent>(modalRef);
    componentRef.setInput('picture', upload.picture);

    componentRef.instance.changed.subscribe(() => {
      this.#picturesClient
        .updatePicture(
          new UpdatePictureRequest({
            picture: new Picture({
              crop: new PictureCrop({
                height: upload.picture.image?.cropHeight ? Math.round(upload.picture.image.cropHeight) : undefined,
                left: upload.picture.image?.cropLeft ? Math.round(upload.picture.image.cropLeft) : undefined,
                top: upload.picture.image?.cropTop ? Math.round(upload.picture.image.cropTop) : undefined,
                width: upload.picture.image?.cropWidth ? Math.round(upload.picture.image.cropWidth) : undefined,
              }),
              id: upload.picture.id,
            }),
            updateMask: new FieldMask({paths: ['crop']}),
          }),
        )
        .pipe(
          catchError((response: unknown) => {
            this.#toastService.handleError(response);
            return EMPTY;
          }),
          switchMap(() =>
            this.#picturesClient.getPicture(
              new PicturesRequest({
                fields: new PictureFields({image: true, thumbMedium: true}),
                language: this.#languageService.language,
                options: new PictureListOptions({id: upload.picture.id}),
              }),
            ),
          ),
          catchError((response: unknown) => {
            this.#toastService.handleError(response);
            return EMPTY;
          }),
          tap((response: Picture) => {
            upload.picture.image = response.image;
            upload.picture.thumbMedium = response.thumbMedium;
            upload.cropTitle = inboxCropTitle(response.image);
            this.#cdr.markForCheck();
          }),
        )
        .subscribe();
    });
  }
}
