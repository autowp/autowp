import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import * as $ from 'jquery';
import { HttpClient } from '@angular/common/http';
import Notify from '../notify';
import { Subscription } from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';
import {
  VotingService,
  APIVotingVariantVote,
  APIVoting,
  APIVotingVariant
} from '../services/voting';
import { AuthService } from '../services/auth.service';
import { PageEnvService } from '../services/page-env.service';

@Component({
  selector: 'app-voting',
  templateUrl: './voting.component.html'
})
@Injectable()
export class VotingComponent implements OnInit, OnDestroy {
  private id: number;
  private routeSub: Subscription;
  public voting: APIVoting;
  public filter = false;
  public selected: {};
  public votes: APIVotingVariantVote[];
  public isModer = false; // TODO: fetch value

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private router: Router,
    private votingService: VotingService,
    public auth: AuthService,
    private pageEnv: PageEnvService
  ) {}

  public load(callback?: () => void) {
    this.votingService.getVoting(this.id).subscribe(
      response => {
        this.voting = response;

        if (callback) {
          callback();
        }
      },
      response => {
        this.router.navigate(['/error-404']);
      }
    );
  }

  ngOnInit(): void {
    this.routeSub = this.route.params.subscribe(params => {
      this.id = params.id;
      this.load(() => {
        this.pageEnv.set({
          layout: {
            needRight: true
          },
          name: 'page/157/name',
          pageId: 157,
          args: {
            VOTING_NAME: this.voting.name,
            VOTING_ID: this.voting.id + ''
          }
        });
      });
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
    /*$(this.$element)
      .find('.modal')
      .modal('hide');*/
  }

  public vote() {
    const ids: number[] = [];

    if (!this.voting.multivariant) {
      if (this.selected) {
        ids.push(this.selected as number);
      }
    } else {
      for (const key in this.selected) {
        if (this.selected.hasOwnProperty(key)) {
          const value = this.selected[key];
          if (value) {
            ids.push(parseInt(key, 10));
          }
        }
      }
    }

    this.http
      .patch<void>('/api/voting/' + this.id, {
        vote: ids
      })
      .subscribe(
        response => {
          this.load();
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public isVariantSelected(): boolean {
    if (!this.voting.multivariant) {
      return this.selected > 0;
    }

    let count = 0;
    for (const key in this.selected) {
      if (this.selected.hasOwnProperty(key)) {
        const value = this.selected[key];
        if (value) {
          count++;
        }
      }
    }
    return count > 0;
  }

  public showWhoVoted(variant: APIVotingVariant) {
    this.votes = [];

    this.votingService
      .getVariantVotes(this.id, variant.id, {
        fields: 'user'
      })
      .subscribe(
        response => {
          this.votes = response.items;
          /*$(this.$element)
            .find('.modal')
            .modal('show');*/
        },
        response => {
          Notify.response(response);
        }
      );
  }
}
