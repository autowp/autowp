import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import Notify from '../../notify';
import { MessageStateParams, ForumService } from '../../services/forum';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';

@Component({
  selector: 'app-forums-message',
  templateUrl: './message.component.html'
})
@Injectable()
export class MessageComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;

  constructor(
    private router: Router,
    private forumService: ForumService,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.routeSub = this.route.params.subscribe(params => {
      this.forumService.getMessageStateParams(params.message_id).then(
        message => {
          this.router.navigate(['/forums/topic', message.topic_id], {
            queryParams: {
              page: message.page
            }
          });
        },
        response => {
          Notify.response(response);
        }
      );
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }
}
