import type {Design} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {RouterLink} from '@angular/router';

export interface ItemHeader {
  design?: Design | null;
  nameHTML: string;
  produced?: {
    count: number;
    exactly: boolean;
  };
}

@Component({
  selector: 'app-item-header',
  imports: [RouterLink],
  templateUrl: './item-heading.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ItemHeading {
  readonly item = input.required<ItemHeader>();
}
