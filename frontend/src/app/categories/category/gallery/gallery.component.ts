import {ChangeDetectionStrategy, Component, effect, inject, OnInit} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router} from '@angular/router';
import {APIItem, ItemType, Picture} from '@grpc/spec.pb';
import {PageEnvService} from '@services/page-env.service';
import {GalleryComponent} from 'app/gallery/gallery.component';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {of} from 'rxjs';
import {map, switchMap} from 'rxjs/operators';

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
  readonly #router = inject(Router);
  readonly #categoriesService = inject(CategoriesService);

  protected readonly identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
  });

  protected readonly dataResource = rxResource({
    stream: () => {
      if (!this.identity()) {
        return notFoundError();
      }
      return this.#categoriesService
        .categoryPipe$(this.#route.parent!)
        .pipe(switchMap((data) => (data.current ? of(data) : notFoundError())));
    },
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.dataResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
      }
    });
  }

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isGalleryPage: true},
      pageId: 187,
      title: '', // data.picture.name_text,
    });
  }

  protected currentRouterLinkPrefix(
    category: APIItem | null,
    currentItem: APIItem,
    pathCatnames: string[],
  ): null | string[] {
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
        pageId: 187,
        title: item.nameText,
      });
    }
  }
}
