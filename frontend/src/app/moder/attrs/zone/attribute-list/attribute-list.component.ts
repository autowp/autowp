import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {getAttrsTranslation} from '@utils/translations';

import type {AttrAttributeTreeItem} from '../../../../api/attrs/attrs.service';

@Component({
  selector: 'app-moder-attrs-zone-attribute-list',
  standalone: true,
  templateUrl: './attribute-list.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerAttrsZoneAttributeListComponent {
  readonly attributes = input.required<AttrAttributeTreeItem[]>();
  readonly map = input.required<Record<string, boolean>>();

  protected getAttrsTranslation(id: string): string {
    return getAttrsTranslation(id);
  }
}
