import {ChangeDetectionStrategy, Component, computed, input} from '@angular/core';
import {PictureLicense} from '@grpc/spec.pb';
import {getPictureLicenseTranslation} from '@utils/translations';

const ccDeedUrls: Partial<Record<PictureLicense, string>> = {
  [PictureLicense.PICTURE_LICENSE_CC0]: 'https://creativecommons.org/publicdomain/zero/1.0/',
  [PictureLicense.PICTURE_LICENSE_CC_BY]: 'https://creativecommons.org/licenses/by/4.0/',
  [PictureLicense.PICTURE_LICENSE_CC_BY_SA]: 'https://creativecommons.org/licenses/by-sa/4.0/',
  [PictureLicense.PICTURE_LICENSE_CC_BY_NC]: 'https://creativecommons.org/licenses/by-nc/4.0/',
  [PictureLicense.PICTURE_LICENSE_CC_BY_NC_SA]: 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
  [PictureLicense.PICTURE_LICENSE_CC_BY_ND]: 'https://creativecommons.org/licenses/by-nd/4.0/',
  [PictureLicense.PICTURE_LICENSE_CC_BY_NC_ND]: 'https://creativecommons.org/licenses/by-nc-nd/4.0/',
  [PictureLicense.PICTURE_LICENSE_PUBLIC_DOMAIN]: 'https://creativecommons.org/publicdomain/mark/1.0/',
};

const successBadgeLicenses = new Set<PictureLicense>([
  PictureLicense.PICTURE_LICENSE_CC0,
  PictureLicense.PICTURE_LICENSE_CC_BY,
  PictureLicense.PICTURE_LICENSE_CC_BY_NC,
  PictureLicense.PICTURE_LICENSE_CC_BY_NC_ND,
  PictureLicense.PICTURE_LICENSE_CC_BY_NC_SA,
  PictureLicense.PICTURE_LICENSE_CC_BY_ND,
  PictureLicense.PICTURE_LICENSE_CC_BY_SA,
  PictureLicense.PICTURE_LICENSE_PUBLIC_DOMAIN,
]);

@Component({
  selector: 'app-license-badge',
  templateUrl: './license-badge.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LicenseBadgeComponent {
  readonly license = input<PictureLicense>(PictureLicense.PICTURE_LICENSE_UNKNOWN);
  readonly sourceUrl = input<string>('');

  protected readonly label = computed(() => getPictureLicenseTranslation(this.license().toString()));
  protected readonly badgeClass = computed(() =>
    successBadgeLicenses.has(this.license()) ? 'badge text-bg-success' : 'badge text-bg-secondary',
  );
  protected readonly deedUrl = computed(() => ccDeedUrls[this.license()] ?? null);
}
