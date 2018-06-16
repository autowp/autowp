import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../notify';
import { Subscription, combineLatest } from 'rxjs';
import { ActivatedRoute, Router, Params } from '@angular/router';
import {
  VotingService,
  APIVoting,
  APIVotingVariant
} from '../services/voting';
import { AuthService } from '../services/auth.service';
import { PageEnvService } from '../services/page-env.service';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { VotingVotesComponent } from './votes/votes.component';
import { ACLService } from '../services/acl.service';

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
  public isModer = false; // TODO: fetch value
  private aclSub: Subscription;

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private router: Router,
    private votingService: VotingService,
    public auth: AuthService,
    private pageEnv: PageEnvService,
    private modalService: NgbModal,
    private acl: ACLService
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
    this.aclSub = this.acl
      .inheritsRole('moder')
      .subscribe(inherits => (this.isModer = inherits));

    this.routeSub = combineLatest(
      this.route.params,
      this.route.queryParams,
      (params: Params, query: Params) => ({
        params,
        query
      })
    ).subscribe(data => {
      this.id = data.params.id;
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
    this.aclSub.unsubscribe();
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

    return false;
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
    const modalRef = this.modalService.open(VotingVotesComponent, {
      size: 'lg',
      centered: true
    });

    modalRef.componentInstance.votingID = this.id;
    modalRef.componentInstance.variantID = variant.id;

    return false;
  }
}
