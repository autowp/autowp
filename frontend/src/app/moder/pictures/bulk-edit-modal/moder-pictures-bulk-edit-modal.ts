import type {OnInit} from '@angular/core';
import type {PersonSearchSelection} from '@utils/person-search/person-search';
import type {Observable} from 'rxjs';

import {ChangeDetectionStrategy, Component, computed, inject, input, output, signal} from '@angular/core';
import {disabled, form, FormField, maxLength} from '@angular/forms/signals';
import {
  CreatePictureItemRequest,
  DeletePictureItemRequest,
  ItemFields,
  ItemsRequest,
  Picture,
  PictureFields,
  PictureItemFields,
  PictureItemListOptions,
  PictureItemsRequest,
  PictureItemType,
  PictureLicense,
  PictureListOptions,
  PicturesRequest,
  UpdatePictureRequest,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {NgbActiveModal, NgbModal, NgbProgressbar} from '@ng-bootstrap/ng-bootstrap';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {LanguageService} from '@services/language';
import {pictureLicenseOptions} from '@utils/license-badge/license-badge';
import {LicenseHelpModal} from '@utils/license-help-modal/license-help-modal';
import {numericEnumValue} from '@utils/numeric-enum-value';
import {PersonSearch} from '@utils/person-search/person-search';
import {getPictureLicenseTranslation, pictureLicenseRequiresSourceUrl} from '@utils/translations';
import {ToastsService} from 'app/toasts/toasts.service';
import {catchError, EMPTY, forkJoin, map, of, switchMap, tap} from 'rxjs';

interface PictureSummary {
  author: null | {id: string; nameHtml: string};
  id: string;
  license: PictureLicense;
  sourceUrl: string;
}

@Component({
  selector: 'app-moder-pictures-bulk-edit-modal',
  imports: [FormField, PersonSearch, NgbProgressbar],
  templateUrl: './moder-pictures-bulk-edit-modal.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPicturesBulkEditModal implements OnInit {
  protected readonly activeModal = inject(NgbActiveModal);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);
  readonly #modalService = inject(NgbModal);

  readonly pictureIds = input.required<string[]>();

  readonly applied = output();

  protected readonly PictureItemType = PictureItemType;

  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly pictures = signal<PictureSummary[]>([]);

  protected readonly licenseOptions: {label: string; value: PictureLicense}[] = pictureLicenseOptions.map((value) => ({
    label: getPictureLicenseTranslation(value.toString()),
    value,
  }));

  // Empty licence/URL and a null author mean "leave as is".
  readonly #editModel = signal({license: '', sourceUrl: ''});
  protected readonly editForm = form(this.#editModel, (path) => {
    maxLength(path.sourceUrl, 500);
    disabled(path, {when: () => this.saving()});
  });
  protected readonly licenseChoice = computed(() =>
    this.#editModel().license === ''
      ? null
      : numericEnumValue(PictureLicense, Number(this.#editModel().license), PictureLicense.PICTURE_LICENSE_UNKNOWN),
  );
  protected readonly sourceUrlChoice = computed(() => this.#editModel().sourceUrl);
  protected readonly authorChoice = signal<null | PersonSearchSelection>(null);

  // Counts of already-selected photos whose current value differs from what is about to be
  // written, so the template can show "you are about to overwrite N photos" next to each field.
  protected readonly licenseWarningCount = computed(() => {
    const choice = this.licenseChoice();
    if (choice === null) {
      return 0;
    }
    return this.pictures().filter(
      (picture) => picture.license !== PictureLicense.PICTURE_LICENSE_UNKNOWN && picture.license !== choice,
    ).length;
  });

  protected readonly sourceUrlWarningCount = computed(() => {
    const choice = this.sourceUrlChoice().trim();
    if (choice === '') {
      return 0;
    }
    return this.pictures().filter((picture) => picture.sourceUrl !== '' && picture.sourceUrl !== choice).length;
  });

  protected readonly authorWarningCount = computed(() => {
    const choice = this.authorChoice();
    if (!choice) {
      return 0;
    }
    return this.pictures().filter((picture) => picture.author !== null && picture.author.id !== choice.id).length;
  });

  // A CC/PD licence claim requires a source URL (see pictureLicenseRequiresSourceUrl). Counts
  // photos that would end up with neither the typed URL nor one of their own.
  protected readonly licenseNeedsSourceCount = computed(() => {
    const choice = this.licenseChoice();
    if (choice === null || !pictureLicenseRequiresSourceUrl(choice.toString())) {
      return 0;
    }
    const typed = this.sourceUrlChoice().trim();
    return typed === '' ? this.pictures().filter((picture) => picture.sourceUrl === '').length : 0;
  });

  ngOnInit(): void {
    this.#picturesClient
      .getPictures(
        new PicturesRequest({
          fields: new PictureFields({
            pictureItem: new PictureItemsRequest({
              fields: new PictureItemFields({
                item: new ItemsRequest({fields: new ItemFields({nameHtml: true})}),
              }),
              options: new PictureItemListOptions({typeId: PictureItemType.PICTURE_ITEM_AUTHOR}),
            }),
          }),
          language: this.#languageService.language,
          limit: this.pictureIds().length,
          options: new PictureListOptions({ids: this.pictureIds()}),
        }),
      )
      .pipe(
        catchError((error: unknown) => {
          this.#toastService.handleError(error);
          this.activeModal.dismiss();
          return EMPTY;
        }),
      )
      .subscribe((response) => {
        const summaries: PictureSummary[] = (response.items ?? []).map((picture) => {
          const authorItem = (picture.pictureItems?.items ?? []).at(0);
          return {
            author: authorItem ? {id: authorItem.itemId, nameHtml: authorItem.item?.nameHtml ?? ''} : null,
            id: picture.id,
            license: picture.license,
            sourceUrl: picture.sourceUrl,
          };
        });

        this.pictures.set(summaries);
        this.loading.set(false);
        this.#preselectShared(summaries);
      });
  }

  // Preselects a field only when every selected photo already agrees on it. A mixed or empty
  // field stays on "leave as is" - the whole point is that opening this dialog to set one field
  // can never overwrite another with a value nobody chose.
  #preselectShared(summaries: PictureSummary[]): void {
    const licenses = new Set(summaries.map((picture) => picture.license));
    if (licenses.size === 1) {
      const only = [...licenses].at(0);
      if (only !== undefined && only !== PictureLicense.PICTURE_LICENSE_UNKNOWN) {
        this.editForm.license().value.set(String(only));
      }
    }

    const urls = new Set(summaries.map((picture) => picture.sourceUrl));
    if (urls.size === 1) {
      const only = [...urls].at(0);
      if (only) {
        this.editForm.sourceUrl().value.set(only);
      }
    }

    const authorIds = new Set(summaries.map((picture) => picture.author?.id ?? ''));
    if (authorIds.size === 1) {
      const only = summaries[0]?.author;
      if (only) {
        this.authorChoice.set({id: only.id, nameHtml: only.nameHtml, nameText: only.nameHtml});
      }
    }
  }

  protected onAuthorSelected(selection: PersonSearchSelection): void {
    this.authorChoice.set(selection);
  }

  protected clearAuthorChoice(): void {
    this.authorChoice.set(null);
  }

  protected showLicenseHelp(): void {
    this.#modalService.open(LicenseHelpModal, {centered: true, size: 'lg'});
  }

  protected apply(): void {
    if (this.licenseNeedsSourceCount() > 0 || this.saving() || this.loading() || this.editForm().invalid()) {
      return;
    }

    this.saving.set(true);

    const license = this.licenseChoice();
    const sourceUrl = this.sourceUrlChoice().trim();
    const author = this.authorChoice();

    const requests = this.pictures().map((picture) =>
      this.#applyToPicture$(picture, license, sourceUrl, author).pipe(
        map(() => true),
        catchError((error: unknown) => {
          this.#toastService.handleError(error);
          return of(false);
        }),
      ),
    );

    forkJoin(requests.length > 0 ? requests : [of(true)]).subscribe({
      next: (results) => {
        this.pictures.update((pictures) => [...pictures]);
        this.saving.set(false);
        if (results.some((success) => !success)) return;
        this.applied.emit();
        this.activeModal.close();
      },
      error: () => {
        this.saving.set(false);
      },
    });
  }

  #applyToPicture$(
    picture: PictureSummary,
    license: null | PictureLicense,
    sourceUrl: string,
    author: null | PersonSearchSelection,
  ): Observable<unknown> {
    const paths: string[] = [];
    const patch = new Picture({id: picture.id});

    if (license !== null) {
      paths.push('license');
      patch.license = license;
    }
    if (sourceUrl !== '') {
      paths.push('source_url');
      patch.sourceUrl = sourceUrl;
    }

    const update$: Observable<unknown> =
      paths.length > 0
        ? this.#picturesClient
            .updatePicture(new UpdatePictureRequest({picture: patch, updateMask: new FieldMask({paths})}))
            .pipe(
              tap(() => {
                if (license !== null) picture.license = license;
                if (sourceUrl !== '') picture.sourceUrl = sourceUrl;
              }),
            )
        : of(null);

    if (!author || picture.author?.id === author.id) {
      return update$;
    }

    const link$ = this.#picturesClient
      .createPictureItem(
        new CreatePictureItemRequest({
          itemId: author.id,
          pictureId: picture.id,
          type: PictureItemType.PICTURE_ITEM_AUTHOR,
        }),
      )
      .pipe(
        tap(() => {
          picture.author = {id: author.id, nameHtml: author.nameHtml};
        }),
      );

    const author$: Observable<unknown> = picture.author
      ? this.#picturesClient
          .deletePictureItem(
            new DeletePictureItemRequest({
              itemId: picture.author.id,
              pictureId: picture.id,
              type: PictureItemType.PICTURE_ITEM_AUTHOR,
            }),
          )
          .pipe(
            tap(() => {
              picture.author = null;
            }),
            switchMap(() => link$),
          )
      : link$;

    return update$.pipe(switchMap(() => author$));
  }
}
