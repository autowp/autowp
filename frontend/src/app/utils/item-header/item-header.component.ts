import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {Design} from '@grpc/spec.pb';

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
  templateUrl: './item-header.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ItemHeaderComponent {
  readonly item = input.required<ItemHeader>();
}
