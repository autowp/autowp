import { Component, Injectable, Input, OnInit } from '@angular/core';

@Component({
  selector: 'app-past-time-indicator',
  templateUrl: './past-time-indicator.component.html',
  styleUrls: ['./styles.scss']
})
@Injectable()
export class PastTimeIndicatorComponent implements OnInit {
  @Input() date: string;
  past: boolean;

  ngOnInit(): void {
    this.past =
      new Date(this.date).getTime() < new Date().getTime() - 86400 * 1000;
  }
}

