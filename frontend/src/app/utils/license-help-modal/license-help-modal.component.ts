import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {LicenseBadgeComponent, pictureLicenseOptions} from '@utils/license-badge/license-badge.component';
import {getPictureLicenseDescriptionTranslation, getPictureLicenseTranslation} from '@utils/translations';

@Component({
  selector: 'app-license-help-modal',
  imports: [LicenseBadgeComponent],
  templateUrl: './license-help-modal.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LicenseHelpModalComponent {
  protected readonly activeModal = inject(NgbActiveModal);

  protected readonly licenses = pictureLicenseOptions.map((value) => ({
    description: getPictureLicenseDescriptionTranslation(value.toString()),
    label: getPictureLicenseTranslation(value.toString()),
    value,
  }));
}
