import type {APIItemChildsCounts} from '@services/item';
import type {ItemHeader} from '@utils/item-header/item-heading';

import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {ItemHeading} from '@utils/item-header/item-heading';

@Component({
  selector: 'app-catalogue-item-menu',
  imports: [RouterLink, ItemHeading],
  templateUrl: './catalogue-item-menu.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueItemMenu {
  readonly itemRouterLink = input.required<string[]>();
  readonly header = input.required<ItemHeader>();
  readonly childsCounts = input<APIItemChildsCounts | undefined>(undefined);
  readonly picturesCount = input.required<number>();
  readonly active = input.required<string>();
}
