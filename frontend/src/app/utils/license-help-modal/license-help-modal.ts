import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {LicenseBadge, pictureLicenseOptions} from '@utils/license-badge/license-badge';
import {getPictureLicenseDescriptionTranslation, getPictureLicenseTranslation} from '@utils/translations';

@Component({
  selector: 'app-license-help-modal',
  imports: [LicenseBadge],
  templateUrl: './license-help-modal.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LicenseHelpModal {
  protected readonly activeModal = inject(NgbActiveModal);

  protected readonly licenses = pictureLicenseOptions.map((value) => ({
    description: getPictureLicenseDescriptionTranslation(value.toString()),
    label: getPictureLicenseTranslation(value.toString()),
    value,
  }));
}
