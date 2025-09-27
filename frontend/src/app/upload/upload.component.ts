import {ChangeDetectionStrategy, Component} from '@angular/core';
import {RouterLink, RouterOutlet} from '@angular/router';

@Component({
  selector: 'app-upload',
  imports: [RouterLink, RouterOutlet],
  templateUrl: './upload.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UploadComponent {}
