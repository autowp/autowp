import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {NgbToast} from '@ng-bootstrap/ng-bootstrap';

import {ToastsService} from '../toasts.service';

@Component({
  selector: 'app-toasts',
  imports: [NgbToast],
  templateUrl: './container.component.html',
  styleUrl: './container.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ContainerComponent {
  readonly toastService = inject(ToastsService);

  protected typeToClass(type: string): null | string {
    switch (type) {
      case 'danger':
        return 'bg-danger text-light';
      case 'success':
        return 'bg-success text-light';
    }

    return null;
  }
}
