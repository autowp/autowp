import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute, Router} from '@angular/router';
import {APIItem, ItemType, Picture} from '@grpc/spec.pb';
import {PageEnvService} from '@services/page-env.service';
import {EMPTY, Observable, of} from 'rxjs';
import {distinctUntilChanged, map, switchMap} from 'rxjs/operators';

import {GalleryComponent} from '../../../gallery/gallery.component';
import {CategoriesService, CategoryPipeResult} from '../../service';

@Component({
  selector: 'app-category-gallery',
  imports: [GalleryComponent, AsyncPipe],
  templateUrl: './gallery.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CategoryGalleryComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #router = inject(Router);
  readonly #categoriesService = inject(CategoriesService);

  protected readonly identity$ = this.#route.paramMap.pipe(
    map((route) => route.get('identity')),
    distinctUntilChanged(),
    switchMap((identity) => {
      if (!identity) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(identity);
    }),
  );

  protected readonly data$: Observable<CategoryPipeResult> = this.#categoriesService
    .categoryPipe$(this.#route.parent!)
    .pipe(
      switchMap((data) => {
        if (!data.current) {
          this.#router.navigate(['/error-404'], {
            skipLocationChange: true,
          });
          return EMPTY;
        }
        return of(data);
      }),
    );

  ngOnInit(): void {
    setTimeout(() => {
      this.#pageEnv.set({
        layout: {isGalleryPage: true},
        pageId: 187,
        title: '', // data.picture.name_text,
      });
    }, 0);
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
      setTimeout(() => {
        this.#pageEnv.set({
          layout: {isGalleryPage: true},
          pageId: 187,
          title: item.nameText,
        });
      }, 0);
    }
  }
}
