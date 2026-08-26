import type {Pages} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, computed, input} from '@angular/core';
import {RouterLink} from '@angular/router';

const PAGE_RANGE = 10;

interface PageWindow {
  first: number;
  firstPageInRange: number;
  last: number;
  lastPageInRange: number;
  next: number;
  pages: number[];
  previous: number;
}

function normalizePageNumber(pageNumber: number, pageCount: number): number {
  if (pageNumber < 1) {
    return 1;
  }

  if (pageCount > 0 && pageNumber > pageCount) {
    return pageCount;
  }

  return pageNumber;
}

/**
 * Mirrors goautowp/util/paginator.go's GetPages() - pageCount and current are the only inputs to
 * first/last/previous/next and the page-number window around current, so the server sends just
 * those two and this reconstructs the rest. PAGE_RANGE=10 is the only value the server ever uses;
 * keep the two in sync if that ever changes.
 */
function computeWindow(pageCount: number, current: number): PageWindow {
  let pageRange = PAGE_RANGE;
  if (pageRange > pageCount) {
    pageRange = pageCount;
  }

  let delta = Math.ceil(pageRange / 2);

  let lowerBound = pageCount - pageRange + 1;
  let upperBound = pageCount;

  if (current - delta <= pageCount - pageRange) {
    if (current - delta < 0) {
      delta = current;
    }

    const offset = current - delta;
    lowerBound = offset + 1;
    upperBound = offset + pageRange;
  }

  const firstPageInRange = normalizePageNumber(lowerBound, pageCount);
  const lastPageInRange = normalizePageNumber(upperBound, pageCount);

  const pages: number[] = [];
  for (let page = firstPageInRange; page <= lastPageInRange; page++) {
    pages.push(page);
  }

  return {
    first: 1,
    firstPageInRange,
    last: pageCount,
    lastPageInRange,
    next: current + 1 <= pageCount ? current + 1 : 0,
    pages,
    previous: current - 1 > 0 ? current - 1 : 0,
  };
}

@Component({
  selector: 'app-paginator',
  imports: [RouterLink],
  templateUrl: './paginator.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class PaginatorComponent {
  readonly data = input.required<Pages>();

  protected readonly window = computed(() => computeWindow(this.data().pageCount, this.data().current));

  protected padd(page: number): string {
    const size = Math.max(2, this.data().pageCount.toString().length);
    return page.toString().padStart(size, '0');
  }
}
