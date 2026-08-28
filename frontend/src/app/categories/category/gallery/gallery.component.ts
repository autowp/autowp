import type {OnInit} from '@angular/core';
import type {Item, Picture} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute} from '@angular/router';
import {ItemType} from '@grpc/spec.pb';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {requireRouteParent} from '@utils/require-route-parent';
import {GalleryComponent} from 'app/gallery/gallery.component';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map, of, switchMap} from 'rxjs';

import {CategoriesService} from '../../service';

@Component({
  selector: 'app-category-gallery',
  imports: [GalleryComponent],
  templateUrl: './gallery.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CategoryGalleryComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #notFound = inject(NotFoundService);
  readonly #categoriesService = inject(CategoriesService);

  protected readonly identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
  });

  protected readonly dataResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    // Suffixed with the picture identity read once at construction time - a static id would let a
    // second instance of this component, created by navigating away and to a different picture's
    // gallery URL before Angular's whenStable() ever resolves, match TransferState's still-present
    // entry from the first picture and seed itself with the wrong data.
    id: `categories-category-gallery-${this.identity() ?? ''}`,
    params: () => this.identity(),
    stream: ({params: identity}) => {
      if (!identity) {
        return notFoundError();
      }
      return this.#categoriesService
        .categoryPipe$(requireRouteParent(this.#route))
        .pipe(switchMap((data) => (data.current ? of(data) : notFoundError())));
    },
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.dataResource.error())) {
        this.#notFound.report();
      }
    });
  }

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isGalleryPage: true},
      pageId: PageId.PICTURE,
      title: '', // data.picture.name_text,
    });
  }

  protected currentRouterLinkPrefix(category: Item | null, currentItem: Item, pathCatnames: string[]): null | string[] {
    if (!category) {
      return null;
    }

    if (currentItem.itemTypeId === ItemType.ITEM_TYPE_CATEGORY) {
      return ['/category', currentItem.catname];
    }

    return ['/category', category.catname].concat(pathCatnames);
  }

  protected pictureSelected(item: null | Picture) {
    if (item) {
      this.#pageEnv.set({
        layout: {isGalleryPage: true},
        pageId: PageId.PICTURE,
        title: item.nameText,
      });
    }
  }

  protected readonly errorMessage = errorMessage;
}
