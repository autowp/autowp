import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {getAttrsTranslation} from '@utils/translations';

import type {AttrAttributeTreeItem} from '../../../api/attrs/attrs.service';

@Component({
  selector: 'app-moder-attrs-attribute-list',
  imports: [RouterLink],
  templateUrl: './attribute-list.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class ModerAttrsAttributeListComponent {
  readonly attributes = input.required<AttrAttributeTreeItem[]>();

  protected getAttrsTranslation(id: string): string {
    return getAttrsTranslation(id);
  }
}
