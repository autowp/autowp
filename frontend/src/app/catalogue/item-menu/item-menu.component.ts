import type {APIItemChildsCounts} from '@services/item';
import type {ItemHeader} from '@utils/item-header/item-header.component';

import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {ItemHeaderComponent} from '@utils/item-header/item-header.component';

@Component({
  selector: 'app-catalogue-item-menu',
  imports: [RouterLink, ItemHeaderComponent],
  templateUrl: './item-menu.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class CatalogueItemMenuComponent {
  readonly itemRouterLink = input.required<string[]>();
  readonly header = input.required<ItemHeader>();
  readonly childsCounts = input<APIItemChildsCounts | undefined>(undefined);
  readonly picturesCount = input.required<number>();
  readonly active = input.required<string>();
}
