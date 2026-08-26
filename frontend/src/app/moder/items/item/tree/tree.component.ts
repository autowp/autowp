import type {TreeItem} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {ItemParentType} from '@grpc/spec.pb';

@Component({
  selector: 'app-moder-items-item-tree',
  imports: [RouterLink],
  templateUrl: './tree.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class ModerItemsItemTreeComponent {
  readonly item = input.required<TreeItem>();

  protected readonly ItemParentType = ItemParentType;
}
