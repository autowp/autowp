import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { APIUser } from './user';

export interface APIVotingVariant {
  id: number;
  votes: number;
  name: string;
  text: string;
  is_max: boolean;
  is_min: boolean;
  percent: number;
}

export interface APIVoting {
  id: number;
  name: string;
  multivariant: boolean;
  variants: APIVotingVariant[];
  can_vote: boolean;
  text: string;
}

export interface APIVotingVariantVotesGetOptions {
  fields: string;
}

export interface APIVotingVariantVote {
  id: number;
  user: APIUser;
}

export interface APIVotingVariantVotesGetResponse {
  items: APIVotingVariantVote[];
}

@Injectable()
export class VotingService {
  constructor(private http: HttpClient) {}

  public getVoting(id: number): Observable<APIVoting> {
    return this.http.get<APIVoting>('/api/voting/' + id);
  }

  public getVariantVotes(
    votingId: number,
    variantId: number,
    options: APIVotingVariantVotesGetOptions
  ): Observable<APIVotingVariantVotesGetResponse> {
    const params: { [param: string]: string } = {};

    if (options.fields) {
      params.fields = options.fields;
    }

    return this.http.get<APIVotingVariantVotesGetResponse>(
      '/api/voting/' + votingId + '/variant/' + variantId + '/vote',
      {
        params: params
      }
    );
  }
}
