/* tslint:disable */
/* eslint-disable */
// @ts-nocheck
//
// THIS IS A GENERATED FILE
// DO NOT MODIFY IT! YOUR CHANGES WILL BE LOST
import { Inject, Injectable, Optional } from '@angular/core';
import {
  GrpcCallType,
  GrpcClient,
  GrpcClientFactory,
  GrpcEvent,
  GrpcMetadata
} from '@ngx-grpc/common';
import {
  GRPC_CLIENT_FACTORY,
  GrpcHandler,
  takeMessages,
  throwStatusErrors
} from '@ngx-grpc/core';
import { Observable } from 'rxjs';
import * as thisProto from './spec.pb';
import * as googleProtobuf000 from '@ngx-grpc/well-known-types';
import * as googleProtobuf001 from '@ngx-grpc/well-known-types';
import * as googleApi002 from './google/api/http.pb';
import * as googleProtobuf003 from '@ngx-grpc/well-known-types';
import * as googleProtobuf004 from '@ngx-grpc/well-known-types';
import * as googleProtobuf005 from '@ngx-grpc/well-known-types';
import * as googleProtobuf006 from '@ngx-grpc/well-known-types';
import * as googleProtobuf007 from '@ngx-grpc/well-known-types';
import * as googleProtobuf008 from '@ngx-grpc/well-known-types';
import * as googleType009 from './google/type/latlng.pb';
import * as googleType010 from './google/type/date.pb';
import * as googleRpc011 from './google/rpc/status.pb';
import * as googleRpc012 from './google/rpc/error-details.pb';
import * as googleApi013 from './google/api/annotations.pb';
import * as googleApi014 from './google/api/field-behavior.pb';
import {
  GRPC_AUTOWP_CLIENT_SETTINGS,
  GRPC_FORUMS_CLIENT_SETTINGS,
  GRPC_ARTICLES_CLIENT_SETTINGS,
  GRPC_TRAFFIC_CLIENT_SETTINGS,
  GRPC_CONTACTS_CLIENT_SETTINGS,
  GRPC_USERS_CLIENT_SETTINGS,
  GRPC_RATING_CLIENT_SETTINGS,
  GRPC_ITEMS_CLIENT_SETTINGS,
  GRPC_MOSTS_CLIENT_SETTINGS,
  GRPC_COMMENTS_CLIENT_SETTINGS,
  GRPC_LOG_CLIENT_SETTINGS,
  GRPC_MAP_CLIENT_SETTINGS,
  GRPC_PICTURES_CLIENT_SETTINGS,
  GRPC_MESSAGING_CLIENT_SETTINGS,
  GRPC_STATISTICS_CLIENT_SETTINGS,
  GRPC_DONATIONS_CLIENT_SETTINGS,
  GRPC_TEXT_CLIENT_SETTINGS,
  GRPC_ATTRS_CLIENT_SETTINGS,
  GRPC_VOTINGS_CLIENT_SETTINGS
} from './spec.pbconf';
/**
 * Service client implementation for goautowp.Autowp
 */
@Injectable({ providedIn: 'any' })
export class AutowpClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Autowp/CreateFeedback
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    createFeedback: (
      requestData: thisProto.CreateFeedbackRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Autowp/CreateFeedback',
        requestData,
        requestMetadata,
        requestClass: thisProto.CreateFeedbackRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Autowp/GetIP
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.IP>>
     */
    getIP: (
      requestData: thisProto.GetIPRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.IP>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Autowp/GetIP',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetIPRequest,
        responseClass: thisProto.IP
      });
    },
    /**
     * Unary call: /goautowp.Autowp/GetReCaptchaConfig
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.ReCaptchaConfig>>
     */
    getReCaptchaConfig: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.ReCaptchaConfig>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Autowp/GetReCaptchaConfig',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.ReCaptchaConfig
      });
    },
    /**
     * Unary call: /goautowp.Autowp/GetTimezones
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.Timezones>>
     */
    getTimezones: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.Timezones>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Autowp/GetTimezones',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.Timezones
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_AUTOWP_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Autowp', settings);
  }

  /**
   * Unary call @/goautowp.Autowp/CreateFeedback
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  createFeedback(
    requestData: thisProto.CreateFeedbackRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .createFeedback(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Autowp/GetIP
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.IP>
   */
  getIP(
    requestData: thisProto.GetIPRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.IP> {
    return this.$raw
      .getIP(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Autowp/GetReCaptchaConfig
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.ReCaptchaConfig>
   */
  getReCaptchaConfig(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.ReCaptchaConfig> {
    return this.$raw
      .getReCaptchaConfig(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Autowp/GetTimezones
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.Timezones>
   */
  getTimezones(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.Timezones> {
    return this.$raw
      .getTimezones(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Forums
 */
@Injectable({ providedIn: 'any' })
export class ForumsClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Forums/GetUserSummary
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIForumsUserSummary>>
     */
    getUserSummary: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIForumsUserSummary>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Forums/GetUserSummary',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.APIForumsUserSummary
      });
    },
    /**
     * Unary call: /goautowp.Forums/CreateTopic
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APICreateTopicResponse>>
     */
    createTopic: (
      requestData: thisProto.APICreateTopicRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APICreateTopicResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Forums/CreateTopic',
        requestData,
        requestMetadata,
        requestClass: thisProto.APICreateTopicRequest,
        responseClass: thisProto.APICreateTopicResponse
      });
    },
    /**
     * Unary call: /goautowp.Forums/CloseTopic
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    closeTopic: (
      requestData: thisProto.APISetTopicStatusRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Forums/CloseTopic',
        requestData,
        requestMetadata,
        requestClass: thisProto.APISetTopicStatusRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Forums/OpenTopic
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    openTopic: (
      requestData: thisProto.APISetTopicStatusRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Forums/OpenTopic',
        requestData,
        requestMetadata,
        requestClass: thisProto.APISetTopicStatusRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Forums/DeleteTopic
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deleteTopic: (
      requestData: thisProto.APISetTopicStatusRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Forums/DeleteTopic',
        requestData,
        requestMetadata,
        requestClass: thisProto.APISetTopicStatusRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Forums/MoveTopic
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    moveTopic: (
      requestData: thisProto.APIMoveTopicRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Forums/MoveTopic',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIMoveTopicRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Forums/GetTheme
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIForumsTheme>>
     */
    getTheme: (
      requestData: thisProto.APIGetForumsThemeRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIForumsTheme>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Forums/GetTheme',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIGetForumsThemeRequest,
        responseClass: thisProto.APIForumsTheme
      });
    },
    /**
     * Unary call: /goautowp.Forums/GetThemes
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIForumsThemes>>
     */
    getThemes: (
      requestData: thisProto.APIGetForumsThemesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIForumsThemes>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Forums/GetThemes',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIGetForumsThemesRequest,
        responseClass: thisProto.APIForumsThemes
      });
    },
    /**
     * Unary call: /goautowp.Forums/GetTopic
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIForumsTopic>>
     */
    getTopic: (
      requestData: thisProto.APIGetForumsTopicRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIForumsTopic>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Forums/GetTopic',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIGetForumsTopicRequest,
        responseClass: thisProto.APIForumsTopic
      });
    },
    /**
     * Unary call: /goautowp.Forums/GetLastTopic
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIForumsTopic>>
     */
    getLastTopic: (
      requestData: thisProto.APIGetForumsThemeRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIForumsTopic>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Forums/GetLastTopic',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIGetForumsThemeRequest,
        responseClass: thisProto.APIForumsTopic
      });
    },
    /**
     * Unary call: /goautowp.Forums/GetLastMessage
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APICommentMessage>>
     */
    getLastMessage: (
      requestData: thisProto.APIGetForumsTopicRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APICommentMessage>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Forums/GetLastMessage',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIGetForumsTopicRequest,
        responseClass: thisProto.APICommentMessage
      });
    },
    /**
     * Unary call: /goautowp.Forums/GetTopics
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIForumsTopics>>
     */
    getTopics: (
      requestData: thisProto.APIGetForumsTopicsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIForumsTopics>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Forums/GetTopics',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIGetForumsTopicsRequest,
        responseClass: thisProto.APIForumsTopics
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_FORUMS_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Forums', settings);
  }

  /**
   * Unary call @/goautowp.Forums/GetUserSummary
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIForumsUserSummary>
   */
  getUserSummary(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIForumsUserSummary> {
    return this.$raw
      .getUserSummary(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Forums/CreateTopic
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APICreateTopicResponse>
   */
  createTopic(
    requestData: thisProto.APICreateTopicRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APICreateTopicResponse> {
    return this.$raw
      .createTopic(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Forums/CloseTopic
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  closeTopic(
    requestData: thisProto.APISetTopicStatusRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .closeTopic(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Forums/OpenTopic
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  openTopic(
    requestData: thisProto.APISetTopicStatusRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .openTopic(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Forums/DeleteTopic
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deleteTopic(
    requestData: thisProto.APISetTopicStatusRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deleteTopic(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Forums/MoveTopic
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  moveTopic(
    requestData: thisProto.APIMoveTopicRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .moveTopic(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Forums/GetTheme
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIForumsTheme>
   */
  getTheme(
    requestData: thisProto.APIGetForumsThemeRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIForumsTheme> {
    return this.$raw
      .getTheme(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Forums/GetThemes
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIForumsThemes>
   */
  getThemes(
    requestData: thisProto.APIGetForumsThemesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIForumsThemes> {
    return this.$raw
      .getThemes(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Forums/GetTopic
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIForumsTopic>
   */
  getTopic(
    requestData: thisProto.APIGetForumsTopicRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIForumsTopic> {
    return this.$raw
      .getTopic(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Forums/GetLastTopic
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIForumsTopic>
   */
  getLastTopic(
    requestData: thisProto.APIGetForumsThemeRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIForumsTopic> {
    return this.$raw
      .getLastTopic(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Forums/GetLastMessage
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APICommentMessage>
   */
  getLastMessage(
    requestData: thisProto.APIGetForumsTopicRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APICommentMessage> {
    return this.$raw
      .getLastMessage(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Forums/GetTopics
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIForumsTopics>
   */
  getTopics(
    requestData: thisProto.APIGetForumsTopicsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIForumsTopics> {
    return this.$raw
      .getTopics(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Articles
 */
@Injectable({ providedIn: 'any' })
export class ArticlesClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Articles/GetList
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.ArticlesResponse>>
     */
    getList: (
      requestData: thisProto.ArticlesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.ArticlesResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Articles/GetList',
        requestData,
        requestMetadata,
        requestClass: thisProto.ArticlesRequest,
        responseClass: thisProto.ArticlesResponse
      });
    },
    /**
     * Unary call: /goautowp.Articles/GetItemByCatname
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.Article>>
     */
    getItemByCatname: (
      requestData: thisProto.ArticleByCatnameRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.Article>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Articles/GetItemByCatname',
        requestData,
        requestMetadata,
        requestClass: thisProto.ArticleByCatnameRequest,
        responseClass: thisProto.Article
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_ARTICLES_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Articles', settings);
  }

  /**
   * Unary call @/goautowp.Articles/GetList
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.ArticlesResponse>
   */
  getList(
    requestData: thisProto.ArticlesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.ArticlesResponse> {
    return this.$raw
      .getList(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Articles/GetItemByCatname
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.Article>
   */
  getItemByCatname(
    requestData: thisProto.ArticleByCatnameRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.Article> {
    return this.$raw
      .getItemByCatname(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Traffic
 */
@Injectable({ providedIn: 'any' })
export class TrafficClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Traffic/AddToBlacklist
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    addToBlacklist: (
      requestData: thisProto.AddToTrafficBlacklistRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Traffic/AddToBlacklist',
        requestData,
        requestMetadata,
        requestClass: thisProto.AddToTrafficBlacklistRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Traffic/AddToWhitelist
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    addToWhitelist: (
      requestData: thisProto.AddToTrafficWhitelistRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Traffic/AddToWhitelist',
        requestData,
        requestMetadata,
        requestClass: thisProto.AddToTrafficWhitelistRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Traffic/DeleteFromBlacklist
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deleteFromBlacklist: (
      requestData: thisProto.DeleteFromTrafficBlacklistRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Traffic/DeleteFromBlacklist',
        requestData,
        requestMetadata,
        requestClass: thisProto.DeleteFromTrafficBlacklistRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Traffic/DeleteFromWhitelist
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deleteFromWhitelist: (
      requestData: thisProto.DeleteFromTrafficWhitelistRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Traffic/DeleteFromWhitelist',
        requestData,
        requestMetadata,
        requestClass: thisProto.DeleteFromTrafficWhitelistRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Traffic/GetTop
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APITrafficTopResponse>>
     */
    getTop: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APITrafficTopResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Traffic/GetTop',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.APITrafficTopResponse
      });
    },
    /**
     * Unary call: /goautowp.Traffic/GetWhitelist
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APITrafficWhitelistItems>>
     */
    getWhitelist: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APITrafficWhitelistItems>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Traffic/GetWhitelist',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.APITrafficWhitelistItems
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_TRAFFIC_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Traffic', settings);
  }

  /**
   * Unary call @/goautowp.Traffic/AddToBlacklist
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  addToBlacklist(
    requestData: thisProto.AddToTrafficBlacklistRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .addToBlacklist(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Traffic/AddToWhitelist
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  addToWhitelist(
    requestData: thisProto.AddToTrafficWhitelistRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .addToWhitelist(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Traffic/DeleteFromBlacklist
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deleteFromBlacklist(
    requestData: thisProto.DeleteFromTrafficBlacklistRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deleteFromBlacklist(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Traffic/DeleteFromWhitelist
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deleteFromWhitelist(
    requestData: thisProto.DeleteFromTrafficWhitelistRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deleteFromWhitelist(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Traffic/GetTop
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APITrafficTopResponse>
   */
  getTop(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APITrafficTopResponse> {
    return this.$raw
      .getTop(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Traffic/GetWhitelist
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APITrafficWhitelistItems>
   */
  getWhitelist(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APITrafficWhitelistItems> {
    return this.$raw
      .getWhitelist(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Contacts
 */
@Injectable({ providedIn: 'any' })
export class ContactsClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Contacts/CreateContact
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    createContact: (
      requestData: thisProto.CreateContactRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Contacts/CreateContact',
        requestData,
        requestMetadata,
        requestClass: thisProto.CreateContactRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Contacts/DeleteContact
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deleteContact: (
      requestData: thisProto.DeleteContactRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Contacts/DeleteContact',
        requestData,
        requestMetadata,
        requestClass: thisProto.DeleteContactRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Contacts/GetContact
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.Contact>>
     */
    getContact: (
      requestData: thisProto.GetContactRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.Contact>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Contacts/GetContact',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetContactRequest,
        responseClass: thisProto.Contact
      });
    },
    /**
     * Unary call: /goautowp.Contacts/GetContacts
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.ContactItems>>
     */
    getContacts: (
      requestData: thisProto.GetContactsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.ContactItems>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Contacts/GetContacts',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetContactsRequest,
        responseClass: thisProto.ContactItems
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_CONTACTS_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Contacts', settings);
  }

  /**
   * Unary call @/goautowp.Contacts/CreateContact
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  createContact(
    requestData: thisProto.CreateContactRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .createContact(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Contacts/DeleteContact
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deleteContact(
    requestData: thisProto.DeleteContactRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deleteContact(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Contacts/GetContact
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.Contact>
   */
  getContact(
    requestData: thisProto.GetContactRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.Contact> {
    return this.$raw
      .getContact(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Contacts/GetContacts
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.ContactItems>
   */
  getContacts(
    requestData: thisProto.GetContactsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.ContactItems> {
    return this.$raw
      .getContacts(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Users
 */
@Injectable({ providedIn: 'any' })
export class UsersClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Users/DeleteUser
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deleteUser: (
      requestData: thisProto.APIDeleteUserRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Users/DeleteUser',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIDeleteUserRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Users/GetUser
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIUser>>
     */
    getUser: (
      requestData: thisProto.APIGetUserRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIUser>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Users/GetUser',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIGetUserRequest,
        responseClass: thisProto.APIUser
      });
    },
    /**
     * Unary call: /goautowp.Users/Me
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIUser>>
     */
    me: (
      requestData: thisProto.APIMeRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIUser>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Users/Me',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIMeRequest,
        responseClass: thisProto.APIUser
      });
    },
    /**
     * Unary call: /goautowp.Users/UpdateUser
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    updateUser: (
      requestData: thisProto.UpdateUserRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Users/UpdateUser',
        requestData,
        requestMetadata,
        requestClass: thisProto.UpdateUserRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Users/GetUserPreferences
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIUserPreferencesResponse>>
     */
    getUserPreferences: (
      requestData: thisProto.APIUserPreferencesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIUserPreferencesResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Users/GetUserPreferences',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIUserPreferencesRequest,
        responseClass: thisProto.APIUserPreferencesResponse
      });
    },
    /**
     * Unary call: /goautowp.Users/DisableUserCommentsNotifications
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    disableUserCommentsNotifications: (
      requestData: thisProto.APIUserPreferencesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Users/DisableUserCommentsNotifications',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIUserPreferencesRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Users/EnableUserCommentsNotifications
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    enableUserCommentsNotifications: (
      requestData: thisProto.APIUserPreferencesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Users/EnableUserCommentsNotifications',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIUserPreferencesRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Users/GetUsers
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIUsersResponse>>
     */
    getUsers: (
      requestData: thisProto.APIUsersRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIUsersResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Users/GetUsers',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIUsersRequest,
        responseClass: thisProto.APIUsersResponse
      });
    },
    /**
     * Unary call: /goautowp.Users/GetAccounts
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIAccountsResponse>>
     */
    getAccounts: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIAccountsResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Users/GetAccounts',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.APIAccountsResponse
      });
    },
    /**
     * Unary call: /goautowp.Users/DeleteUserAccount
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deleteUserAccount: (
      requestData: thisProto.DeleteUserAccountRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Users/DeleteUserAccount',
        requestData,
        requestMetadata,
        requestClass: thisProto.DeleteUserAccountRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Users/DeleteUserPhoto
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deleteUserPhoto: (
      requestData: thisProto.DeleteUserPhotoRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Users/DeleteUserPhoto',
        requestData,
        requestMetadata,
        requestClass: thisProto.DeleteUserPhotoRequest,
        responseClass: googleProtobuf004.Empty
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_USERS_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Users', settings);
  }

  /**
   * Unary call @/goautowp.Users/DeleteUser
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deleteUser(
    requestData: thisProto.APIDeleteUserRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deleteUser(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Users/GetUser
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIUser>
   */
  getUser(
    requestData: thisProto.APIGetUserRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIUser> {
    return this.$raw
      .getUser(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Users/Me
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIUser>
   */
  me(
    requestData: thisProto.APIMeRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIUser> {
    return this.$raw
      .me(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Users/UpdateUser
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  updateUser(
    requestData: thisProto.UpdateUserRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .updateUser(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Users/GetUserPreferences
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIUserPreferencesResponse>
   */
  getUserPreferences(
    requestData: thisProto.APIUserPreferencesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIUserPreferencesResponse> {
    return this.$raw
      .getUserPreferences(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Users/DisableUserCommentsNotifications
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  disableUserCommentsNotifications(
    requestData: thisProto.APIUserPreferencesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .disableUserCommentsNotifications(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Users/EnableUserCommentsNotifications
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  enableUserCommentsNotifications(
    requestData: thisProto.APIUserPreferencesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .enableUserCommentsNotifications(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Users/GetUsers
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIUsersResponse>
   */
  getUsers(
    requestData: thisProto.APIUsersRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIUsersResponse> {
    return this.$raw
      .getUsers(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Users/GetAccounts
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIAccountsResponse>
   */
  getAccounts(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIAccountsResponse> {
    return this.$raw
      .getAccounts(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Users/DeleteUserAccount
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deleteUserAccount(
    requestData: thisProto.DeleteUserAccountRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deleteUserAccount(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Users/DeleteUserPhoto
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deleteUserPhoto(
    requestData: thisProto.DeleteUserPhotoRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deleteUserPhoto(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Rating
 */
@Injectable({ providedIn: 'any' })
export class RatingClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Rating/GetUserPicturesRating
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIUsersRatingResponse>>
     */
    getUserPicturesRating: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIUsersRatingResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Rating/GetUserPicturesRating',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.APIUsersRatingResponse
      });
    },
    /**
     * Unary call: /goautowp.Rating/GetUserPicturesRatingBrands
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.UserRatingBrandsResponse>>
     */
    getUserPicturesRatingBrands: (
      requestData: thisProto.UserRatingDetailsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.UserRatingBrandsResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Rating/GetUserPicturesRatingBrands',
        requestData,
        requestMetadata,
        requestClass: thisProto.UserRatingDetailsRequest,
        responseClass: thisProto.UserRatingBrandsResponse
      });
    },
    /**
     * Unary call: /goautowp.Rating/GetUserCommentsRating
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIUsersRatingResponse>>
     */
    getUserCommentsRating: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIUsersRatingResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Rating/GetUserCommentsRating',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.APIUsersRatingResponse
      });
    },
    /**
     * Unary call: /goautowp.Rating/GetUserCommentsRatingFans
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.GetUserRatingFansResponse>>
     */
    getUserCommentsRatingFans: (
      requestData: thisProto.UserRatingDetailsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.GetUserRatingFansResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Rating/GetUserCommentsRatingFans',
        requestData,
        requestMetadata,
        requestClass: thisProto.UserRatingDetailsRequest,
        responseClass: thisProto.GetUserRatingFansResponse
      });
    },
    /**
     * Unary call: /goautowp.Rating/GetUserPictureLikesRating
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIUsersRatingResponse>>
     */
    getUserPictureLikesRating: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIUsersRatingResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Rating/GetUserPictureLikesRating',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.APIUsersRatingResponse
      });
    },
    /**
     * Unary call: /goautowp.Rating/GetUserPictureLikesRatingFans
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.GetUserRatingFansResponse>>
     */
    getUserPictureLikesRatingFans: (
      requestData: thisProto.UserRatingDetailsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.GetUserRatingFansResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Rating/GetUserPictureLikesRatingFans',
        requestData,
        requestMetadata,
        requestClass: thisProto.UserRatingDetailsRequest,
        responseClass: thisProto.GetUserRatingFansResponse
      });
    },
    /**
     * Unary call: /goautowp.Rating/GetUserSpecsRating
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIUsersRatingResponse>>
     */
    getUserSpecsRating: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIUsersRatingResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Rating/GetUserSpecsRating',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.APIUsersRatingResponse
      });
    },
    /**
     * Unary call: /goautowp.Rating/GetUserSpecsRatingBrands
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.UserRatingBrandsResponse>>
     */
    getUserSpecsRatingBrands: (
      requestData: thisProto.UserRatingDetailsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.UserRatingBrandsResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Rating/GetUserSpecsRatingBrands',
        requestData,
        requestMetadata,
        requestClass: thisProto.UserRatingDetailsRequest,
        responseClass: thisProto.UserRatingBrandsResponse
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_RATING_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Rating', settings);
  }

  /**
   * Unary call @/goautowp.Rating/GetUserPicturesRating
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIUsersRatingResponse>
   */
  getUserPicturesRating(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIUsersRatingResponse> {
    return this.$raw
      .getUserPicturesRating(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Rating/GetUserPicturesRatingBrands
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.UserRatingBrandsResponse>
   */
  getUserPicturesRatingBrands(
    requestData: thisProto.UserRatingDetailsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.UserRatingBrandsResponse> {
    return this.$raw
      .getUserPicturesRatingBrands(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Rating/GetUserCommentsRating
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIUsersRatingResponse>
   */
  getUserCommentsRating(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIUsersRatingResponse> {
    return this.$raw
      .getUserCommentsRating(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Rating/GetUserCommentsRatingFans
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.GetUserRatingFansResponse>
   */
  getUserCommentsRatingFans(
    requestData: thisProto.UserRatingDetailsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.GetUserRatingFansResponse> {
    return this.$raw
      .getUserCommentsRatingFans(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Rating/GetUserPictureLikesRating
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIUsersRatingResponse>
   */
  getUserPictureLikesRating(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIUsersRatingResponse> {
    return this.$raw
      .getUserPictureLikesRating(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Rating/GetUserPictureLikesRatingFans
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.GetUserRatingFansResponse>
   */
  getUserPictureLikesRatingFans(
    requestData: thisProto.UserRatingDetailsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.GetUserRatingFansResponse> {
    return this.$raw
      .getUserPictureLikesRatingFans(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Rating/GetUserSpecsRating
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIUsersRatingResponse>
   */
  getUserSpecsRating(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIUsersRatingResponse> {
    return this.$raw
      .getUserSpecsRating(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Rating/GetUserSpecsRatingBrands
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.UserRatingBrandsResponse>
   */
  getUserSpecsRatingBrands(
    requestData: thisProto.UserRatingDetailsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.UserRatingBrandsResponse> {
    return this.$raw
      .getUserSpecsRatingBrands(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Items
 */
@Injectable({ providedIn: 'any' })
export class ItemsClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Items/GetItemOfDay
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.ItemOfDay>>
     */
    getItemOfDay: (
      requestData: thisProto.ItemOfDayRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.ItemOfDay>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetItemOfDay',
        requestData,
        requestMetadata,
        requestClass: thisProto.ItemOfDayRequest,
        responseClass: thisProto.ItemOfDay
      });
    },
    /**
     * Unary call: /goautowp.Items/GetBrands
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIBrandsList>>
     */
    getBrands: (
      requestData: thisProto.GetBrandsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIBrandsList>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetBrands',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetBrandsRequest,
        responseClass: thisProto.APIBrandsList
      });
    },
    /**
     * Unary call: /goautowp.Items/GetBrandSections
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIBrandSections>>
     */
    getBrandSections: (
      requestData: thisProto.GetBrandSectionsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIBrandSections>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetBrandSections',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetBrandSectionsRequest,
        responseClass: thisProto.APIBrandSections
      });
    },
    /**
     * Unary call: /goautowp.Items/GetTopBrandsList
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APITopBrandsList>>
     */
    getTopBrandsList: (
      requestData: thisProto.GetTopBrandsListRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APITopBrandsList>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetTopBrandsList',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetTopBrandsListRequest,
        responseClass: thisProto.APITopBrandsList
      });
    },
    /**
     * Unary call: /goautowp.Items/GetTopPersonsList
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APITopPersonsList>>
     */
    getTopPersonsList: (
      requestData: thisProto.GetTopPersonsListRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APITopPersonsList>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetTopPersonsList',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetTopPersonsListRequest,
        responseClass: thisProto.APITopPersonsList
      });
    },
    /**
     * Unary call: /goautowp.Items/GetTopFactoriesList
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APITopFactoriesList>>
     */
    getTopFactoriesList: (
      requestData: thisProto.GetTopFactoriesListRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APITopFactoriesList>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetTopFactoriesList',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetTopFactoriesListRequest,
        responseClass: thisProto.APITopFactoriesList
      });
    },
    /**
     * Unary call: /goautowp.Items/GetTopCategoriesList
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APITopCategoriesList>>
     */
    getTopCategoriesList: (
      requestData: thisProto.GetTopCategoriesListRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APITopCategoriesList>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetTopCategoriesList',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetTopCategoriesListRequest,
        responseClass: thisProto.APITopCategoriesList
      });
    },
    /**
     * Unary call: /goautowp.Items/GetTwinsBrandsList
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APITwinsBrandsList>>
     */
    getTwinsBrandsList: (
      requestData: thisProto.GetTwinsBrandsListRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APITwinsBrandsList>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetTwinsBrandsList',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetTwinsBrandsListRequest,
        responseClass: thisProto.APITwinsBrandsList
      });
    },
    /**
     * Unary call: /goautowp.Items/GetTopTwinsBrandsList
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APITopTwinsBrandsList>>
     */
    getTopTwinsBrandsList: (
      requestData: thisProto.GetTopTwinsBrandsListRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APITopTwinsBrandsList>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetTopTwinsBrandsList',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetTopTwinsBrandsListRequest,
        responseClass: thisProto.APITopTwinsBrandsList
      });
    },
    /**
     * Unary call: /goautowp.Items/GetTopSpecsContributions
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.TopSpecsContributions>>
     */
    getTopSpecsContributions: (
      requestData: thisProto.TopSpecsContributionsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.TopSpecsContributions>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetTopSpecsContributions',
        requestData,
        requestMetadata,
        requestClass: thisProto.TopSpecsContributionsRequest,
        responseClass: thisProto.TopSpecsContributions
      });
    },
    /**
     * Unary call: /goautowp.Items/CreateItem
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.ItemID>>
     */
    createItem: (
      requestData: thisProto.APIItem,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.ItemID>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/CreateItem',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIItem,
        responseClass: thisProto.ItemID
      });
    },
    /**
     * Unary call: /goautowp.Items/UpdateItem
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    updateItem: (
      requestData: thisProto.UpdateItemRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/UpdateItem',
        requestData,
        requestMetadata,
        requestClass: thisProto.UpdateItemRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Items/Item
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIItem>>
     */
    item: (
      requestData: thisProto.ItemRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIItem>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/Item',
        requestData,
        requestMetadata,
        requestClass: thisProto.ItemRequest,
        responseClass: thisProto.APIItem
      });
    },
    /**
     * Unary call: /goautowp.Items/List
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIItemList>>
     */
    list: (
      requestData: thisProto.ItemsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIItemList>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/List',
        requestData,
        requestMetadata,
        requestClass: thisProto.ItemsRequest,
        responseClass: thisProto.APIItemList
      });
    },
    /**
     * Unary call: /goautowp.Items/GetTree
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APITreeItem>>
     */
    getTree: (
      requestData: thisProto.GetTreeRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APITreeItem>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetTree',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetTreeRequest,
        responseClass: thisProto.APITreeItem
      });
    },
    /**
     * Unary call: /goautowp.Items/GetContentLanguages
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIContentLanguages>>
     */
    getContentLanguages: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIContentLanguages>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetContentLanguages',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.APIContentLanguages
      });
    },
    /**
     * Unary call: /goautowp.Items/GetItemLink
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIItemLink>>
     */
    getItemLink: (
      requestData: thisProto.ItemLinksRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIItemLink>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetItemLink',
        requestData,
        requestMetadata,
        requestClass: thisProto.ItemLinksRequest,
        responseClass: thisProto.APIItemLink
      });
    },
    /**
     * Unary call: /goautowp.Items/GetItemLinks
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.ItemLinks>>
     */
    getItemLinks: (
      requestData: thisProto.ItemLinksRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.ItemLinks>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetItemLinks',
        requestData,
        requestMetadata,
        requestClass: thisProto.ItemLinksRequest,
        responseClass: thisProto.ItemLinks
      });
    },
    /**
     * Unary call: /goautowp.Items/DeleteItemLink
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deleteItemLink: (
      requestData: thisProto.APIItemLinkRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/DeleteItemLink',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIItemLinkRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Items/CreateItemLink
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APICreateItemLinkResponse>>
     */
    createItemLink: (
      requestData: thisProto.APIItemLink,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APICreateItemLinkResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/CreateItemLink',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIItemLink,
        responseClass: thisProto.APICreateItemLinkResponse
      });
    },
    /**
     * Unary call: /goautowp.Items/UpdateItemLink
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    updateItemLink: (
      requestData: thisProto.APIItemLink,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/UpdateItemLink',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIItemLink,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Items/GetItemVehicleTypes
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIGetItemVehicleTypesResponse>>
     */
    getItemVehicleTypes: (
      requestData: thisProto.APIGetItemVehicleTypesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIGetItemVehicleTypesResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetItemVehicleTypes',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIGetItemVehicleTypesRequest,
        responseClass: thisProto.APIGetItemVehicleTypesResponse
      });
    },
    /**
     * Unary call: /goautowp.Items/GetItemVehicleType
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIItemVehicleType>>
     */
    getItemVehicleType: (
      requestData: thisProto.APIItemVehicleTypeRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIItemVehicleType>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetItemVehicleType',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIItemVehicleTypeRequest,
        responseClass: thisProto.APIItemVehicleType
      });
    },
    /**
     * Unary call: /goautowp.Items/CreateItemVehicleType
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    createItemVehicleType: (
      requestData: thisProto.APIItemVehicleType,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/CreateItemVehicleType',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIItemVehicleType,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Items/DeleteItemVehicleType
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deleteItemVehicleType: (
      requestData: thisProto.APIItemVehicleTypeRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/DeleteItemVehicleType',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIItemVehicleTypeRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Items/GetItemLanguages
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.ItemLanguages>>
     */
    getItemLanguages: (
      requestData: thisProto.APIGetItemLanguagesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.ItemLanguages>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetItemLanguages',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIGetItemLanguagesRequest,
        responseClass: thisProto.ItemLanguages
      });
    },
    /**
     * Unary call: /goautowp.Items/UpdateItemLanguage
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    updateItemLanguage: (
      requestData: thisProto.ItemLanguage,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/UpdateItemLanguage',
        requestData,
        requestMetadata,
        requestClass: thisProto.ItemLanguage,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Items/GetItemParentLanguages
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.ItemParentLanguages>>
     */
    getItemParentLanguages: (
      requestData: thisProto.APIGetItemParentLanguagesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.ItemParentLanguages>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetItemParentLanguages',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIGetItemParentLanguagesRequest,
        responseClass: thisProto.ItemParentLanguages
      });
    },
    /**
     * Unary call: /goautowp.Items/SetItemParentLanguage
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    setItemParentLanguage: (
      requestData: thisProto.ItemParentLanguage,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/SetItemParentLanguage',
        requestData,
        requestMetadata,
        requestClass: thisProto.ItemParentLanguage,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Items/GetStats
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.StatsResponse>>
     */
    getStats: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.StatsResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetStats',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.StatsResponse
      });
    },
    /**
     * Unary call: /goautowp.Items/GetBrandNewItems
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.NewItemsResponse>>
     */
    getBrandNewItems: (
      requestData: thisProto.NewItemsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.NewItemsResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetBrandNewItems',
        requestData,
        requestMetadata,
        requestClass: thisProto.NewItemsRequest,
        responseClass: thisProto.NewItemsResponse
      });
    },
    /**
     * Unary call: /goautowp.Items/GetNewItems
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.NewItemsResponse>>
     */
    getNewItems: (
      requestData: thisProto.NewItemsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.NewItemsResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetNewItems',
        requestData,
        requestMetadata,
        requestClass: thisProto.NewItemsRequest,
        responseClass: thisProto.NewItemsResponse
      });
    },
    /**
     * Unary call: /goautowp.Items/GetItemParent
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.ItemParent>>
     */
    getItemParent: (
      requestData: thisProto.ItemParentsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.ItemParent>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetItemParent',
        requestData,
        requestMetadata,
        requestClass: thisProto.ItemParentsRequest,
        responseClass: thisProto.ItemParent
      });
    },
    /**
     * Unary call: /goautowp.Items/GetItemParents
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.ItemParents>>
     */
    getItemParents: (
      requestData: thisProto.ItemParentsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.ItemParents>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetItemParents',
        requestData,
        requestMetadata,
        requestClass: thisProto.ItemParentsRequest,
        responseClass: thisProto.ItemParents
      });
    },
    /**
     * Unary call: /goautowp.Items/CreateItemParent
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    createItemParent: (
      requestData: thisProto.ItemParent,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/CreateItemParent',
        requestData,
        requestMetadata,
        requestClass: thisProto.ItemParent,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Items/UpdateItemParent
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    updateItemParent: (
      requestData: thisProto.ItemParent,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/UpdateItemParent',
        requestData,
        requestMetadata,
        requestClass: thisProto.ItemParent,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Items/DeleteItemParent
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deleteItemParent: (
      requestData: thisProto.DeleteItemParentRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/DeleteItemParent',
        requestData,
        requestMetadata,
        requestClass: thisProto.DeleteItemParentRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Items/MoveItemParent
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    moveItemParent: (
      requestData: thisProto.MoveItemParentRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/MoveItemParent',
        requestData,
        requestMetadata,
        requestClass: thisProto.MoveItemParentRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Items/RefreshInheritance
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    refreshInheritance: (
      requestData: thisProto.RefreshInheritanceRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/RefreshInheritance',
        requestData,
        requestMetadata,
        requestClass: thisProto.RefreshInheritanceRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Items/SetUserItemSubscription
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    setUserItemSubscription: (
      requestData: thisProto.SetUserItemSubscriptionRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/SetUserItemSubscription',
        requestData,
        requestMetadata,
        requestClass: thisProto.SetUserItemSubscriptionRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Items/GetPath
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.PathResponse>>
     */
    getPath: (
      requestData: thisProto.PathRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.PathResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetPath',
        requestData,
        requestMetadata,
        requestClass: thisProto.PathRequest,
        responseClass: thisProto.PathResponse
      });
    },
    /**
     * Unary call: /goautowp.Items/GetAlpha
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.AlphaResponse>>
     */
    getAlpha: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.AlphaResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetAlpha',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.AlphaResponse
      });
    },
    /**
     * Unary call: /goautowp.Items/GetBrandVehicleTypes
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.BrandVehicleTypeItems>>
     */
    getBrandVehicleTypes: (
      requestData: thisProto.GetBrandVehicleTypesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.BrandVehicleTypeItems>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetBrandVehicleTypes',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetBrandVehicleTypesRequest,
        responseClass: thisProto.BrandVehicleTypeItems
      });
    },
    /**
     * Unary call: /goautowp.Items/GetVehicleTypes
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.VehicleTypeItems>>
     */
    getVehicleTypes: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.VehicleTypeItems>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetVehicleTypes',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.VehicleTypeItems
      });
    },
    /**
     * Unary call: /goautowp.Items/GetSpecs
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.SpecsItems>>
     */
    getSpecs: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.SpecsItems>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetSpecs',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.SpecsItems
      });
    },
    /**
     * Unary call: /goautowp.Items/GetBrandIcons
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.BrandIcons>>
     */
    getBrandIcons: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.BrandIcons>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Items/GetBrandIcons',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.BrandIcons
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_ITEMS_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Items', settings);
  }

  /**
   * Unary call @/goautowp.Items/GetItemOfDay
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.ItemOfDay>
   */
  getItemOfDay(
    requestData: thisProto.ItemOfDayRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.ItemOfDay> {
    return this.$raw
      .getItemOfDay(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetBrands
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIBrandsList>
   */
  getBrands(
    requestData: thisProto.GetBrandsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIBrandsList> {
    return this.$raw
      .getBrands(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetBrandSections
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIBrandSections>
   */
  getBrandSections(
    requestData: thisProto.GetBrandSectionsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIBrandSections> {
    return this.$raw
      .getBrandSections(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetTopBrandsList
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APITopBrandsList>
   */
  getTopBrandsList(
    requestData: thisProto.GetTopBrandsListRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APITopBrandsList> {
    return this.$raw
      .getTopBrandsList(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetTopPersonsList
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APITopPersonsList>
   */
  getTopPersonsList(
    requestData: thisProto.GetTopPersonsListRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APITopPersonsList> {
    return this.$raw
      .getTopPersonsList(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetTopFactoriesList
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APITopFactoriesList>
   */
  getTopFactoriesList(
    requestData: thisProto.GetTopFactoriesListRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APITopFactoriesList> {
    return this.$raw
      .getTopFactoriesList(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetTopCategoriesList
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APITopCategoriesList>
   */
  getTopCategoriesList(
    requestData: thisProto.GetTopCategoriesListRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APITopCategoriesList> {
    return this.$raw
      .getTopCategoriesList(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetTwinsBrandsList
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APITwinsBrandsList>
   */
  getTwinsBrandsList(
    requestData: thisProto.GetTwinsBrandsListRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APITwinsBrandsList> {
    return this.$raw
      .getTwinsBrandsList(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetTopTwinsBrandsList
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APITopTwinsBrandsList>
   */
  getTopTwinsBrandsList(
    requestData: thisProto.GetTopTwinsBrandsListRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APITopTwinsBrandsList> {
    return this.$raw
      .getTopTwinsBrandsList(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetTopSpecsContributions
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.TopSpecsContributions>
   */
  getTopSpecsContributions(
    requestData: thisProto.TopSpecsContributionsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.TopSpecsContributions> {
    return this.$raw
      .getTopSpecsContributions(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/CreateItem
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.ItemID>
   */
  createItem(
    requestData: thisProto.APIItem,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.ItemID> {
    return this.$raw
      .createItem(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/UpdateItem
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  updateItem(
    requestData: thisProto.UpdateItemRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .updateItem(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/Item
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIItem>
   */
  item(
    requestData: thisProto.ItemRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIItem> {
    return this.$raw
      .item(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/List
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIItemList>
   */
  list(
    requestData: thisProto.ItemsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIItemList> {
    return this.$raw
      .list(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetTree
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APITreeItem>
   */
  getTree(
    requestData: thisProto.GetTreeRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APITreeItem> {
    return this.$raw
      .getTree(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetContentLanguages
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIContentLanguages>
   */
  getContentLanguages(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIContentLanguages> {
    return this.$raw
      .getContentLanguages(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetItemLink
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIItemLink>
   */
  getItemLink(
    requestData: thisProto.ItemLinksRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIItemLink> {
    return this.$raw
      .getItemLink(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetItemLinks
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.ItemLinks>
   */
  getItemLinks(
    requestData: thisProto.ItemLinksRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.ItemLinks> {
    return this.$raw
      .getItemLinks(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/DeleteItemLink
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deleteItemLink(
    requestData: thisProto.APIItemLinkRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deleteItemLink(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/CreateItemLink
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APICreateItemLinkResponse>
   */
  createItemLink(
    requestData: thisProto.APIItemLink,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APICreateItemLinkResponse> {
    return this.$raw
      .createItemLink(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/UpdateItemLink
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  updateItemLink(
    requestData: thisProto.APIItemLink,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .updateItemLink(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetItemVehicleTypes
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIGetItemVehicleTypesResponse>
   */
  getItemVehicleTypes(
    requestData: thisProto.APIGetItemVehicleTypesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIGetItemVehicleTypesResponse> {
    return this.$raw
      .getItemVehicleTypes(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetItemVehicleType
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIItemVehicleType>
   */
  getItemVehicleType(
    requestData: thisProto.APIItemVehicleTypeRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIItemVehicleType> {
    return this.$raw
      .getItemVehicleType(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/CreateItemVehicleType
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  createItemVehicleType(
    requestData: thisProto.APIItemVehicleType,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .createItemVehicleType(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/DeleteItemVehicleType
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deleteItemVehicleType(
    requestData: thisProto.APIItemVehicleTypeRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deleteItemVehicleType(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetItemLanguages
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.ItemLanguages>
   */
  getItemLanguages(
    requestData: thisProto.APIGetItemLanguagesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.ItemLanguages> {
    return this.$raw
      .getItemLanguages(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/UpdateItemLanguage
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  updateItemLanguage(
    requestData: thisProto.ItemLanguage,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .updateItemLanguage(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetItemParentLanguages
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.ItemParentLanguages>
   */
  getItemParentLanguages(
    requestData: thisProto.APIGetItemParentLanguagesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.ItemParentLanguages> {
    return this.$raw
      .getItemParentLanguages(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/SetItemParentLanguage
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  setItemParentLanguage(
    requestData: thisProto.ItemParentLanguage,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .setItemParentLanguage(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetStats
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.StatsResponse>
   */
  getStats(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.StatsResponse> {
    return this.$raw
      .getStats(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetBrandNewItems
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.NewItemsResponse>
   */
  getBrandNewItems(
    requestData: thisProto.NewItemsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.NewItemsResponse> {
    return this.$raw
      .getBrandNewItems(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetNewItems
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.NewItemsResponse>
   */
  getNewItems(
    requestData: thisProto.NewItemsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.NewItemsResponse> {
    return this.$raw
      .getNewItems(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetItemParent
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.ItemParent>
   */
  getItemParent(
    requestData: thisProto.ItemParentsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.ItemParent> {
    return this.$raw
      .getItemParent(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetItemParents
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.ItemParents>
   */
  getItemParents(
    requestData: thisProto.ItemParentsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.ItemParents> {
    return this.$raw
      .getItemParents(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/CreateItemParent
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  createItemParent(
    requestData: thisProto.ItemParent,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .createItemParent(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/UpdateItemParent
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  updateItemParent(
    requestData: thisProto.ItemParent,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .updateItemParent(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/DeleteItemParent
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deleteItemParent(
    requestData: thisProto.DeleteItemParentRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deleteItemParent(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/MoveItemParent
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  moveItemParent(
    requestData: thisProto.MoveItemParentRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .moveItemParent(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/RefreshInheritance
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  refreshInheritance(
    requestData: thisProto.RefreshInheritanceRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .refreshInheritance(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/SetUserItemSubscription
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  setUserItemSubscription(
    requestData: thisProto.SetUserItemSubscriptionRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .setUserItemSubscription(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetPath
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.PathResponse>
   */
  getPath(
    requestData: thisProto.PathRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.PathResponse> {
    return this.$raw
      .getPath(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetAlpha
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.AlphaResponse>
   */
  getAlpha(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.AlphaResponse> {
    return this.$raw
      .getAlpha(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetBrandVehicleTypes
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.BrandVehicleTypeItems>
   */
  getBrandVehicleTypes(
    requestData: thisProto.GetBrandVehicleTypesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.BrandVehicleTypeItems> {
    return this.$raw
      .getBrandVehicleTypes(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetVehicleTypes
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.VehicleTypeItems>
   */
  getVehicleTypes(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.VehicleTypeItems> {
    return this.$raw
      .getVehicleTypes(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetSpecs
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.SpecsItems>
   */
  getSpecs(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.SpecsItems> {
    return this.$raw
      .getSpecs(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Items/GetBrandIcons
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.BrandIcons>
   */
  getBrandIcons(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.BrandIcons> {
    return this.$raw
      .getBrandIcons(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Mosts
 */
@Injectable({ providedIn: 'any' })
export class MostsClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Mosts/GetItems
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.MostsItems>>
     */
    getItems: (
      requestData: thisProto.MostsItemsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.MostsItems>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Mosts/GetItems',
        requestData,
        requestMetadata,
        requestClass: thisProto.MostsItemsRequest,
        responseClass: thisProto.MostsItems
      });
    },
    /**
     * Unary call: /goautowp.Mosts/GetMenu
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.MostsMenu>>
     */
    getMenu: (
      requestData: thisProto.MostsMenuRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.MostsMenu>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Mosts/GetMenu',
        requestData,
        requestMetadata,
        requestClass: thisProto.MostsMenuRequest,
        responseClass: thisProto.MostsMenu
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_MOSTS_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Mosts', settings);
  }

  /**
   * Unary call @/goautowp.Mosts/GetItems
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.MostsItems>
   */
  getItems(
    requestData: thisProto.MostsItemsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.MostsItems> {
    return this.$raw
      .getItems(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Mosts/GetMenu
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.MostsMenu>
   */
  getMenu(
    requestData: thisProto.MostsMenuRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.MostsMenu> {
    return this.$raw
      .getMenu(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Comments
 */
@Injectable({ providedIn: 'any' })
export class CommentsClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Comments/GetCommentVotes
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.CommentVoteItems>>
     */
    getCommentVotes: (
      requestData: thisProto.GetCommentVotesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.CommentVoteItems>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Comments/GetCommentVotes',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetCommentVotesRequest,
        responseClass: thisProto.CommentVoteItems
      });
    },
    /**
     * Unary call: /goautowp.Comments/Subscribe
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    subscribe: (
      requestData: thisProto.CommentsSubscribeRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Comments/Subscribe',
        requestData,
        requestMetadata,
        requestClass: thisProto.CommentsSubscribeRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Comments/UnSubscribe
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    unSubscribe: (
      requestData: thisProto.CommentsUnSubscribeRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Comments/UnSubscribe',
        requestData,
        requestMetadata,
        requestClass: thisProto.CommentsUnSubscribeRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Comments/View
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    view: (
      requestData: thisProto.CommentsViewRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Comments/View',
        requestData,
        requestMetadata,
        requestClass: thisProto.CommentsViewRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Comments/SetDeleted
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    setDeleted: (
      requestData: thisProto.CommentsSetDeletedRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Comments/SetDeleted',
        requestData,
        requestMetadata,
        requestClass: thisProto.CommentsSetDeletedRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Comments/MoveComment
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    moveComment: (
      requestData: thisProto.CommentsMoveCommentRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Comments/MoveComment',
        requestData,
        requestMetadata,
        requestClass: thisProto.CommentsMoveCommentRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Comments/VoteComment
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.CommentsVoteCommentResponse>>
     */
    voteComment: (
      requestData: thisProto.CommentsVoteCommentRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.CommentsVoteCommentResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Comments/VoteComment',
        requestData,
        requestMetadata,
        requestClass: thisProto.CommentsVoteCommentRequest,
        responseClass: thisProto.CommentsVoteCommentResponse
      });
    },
    /**
     * Unary call: /goautowp.Comments/Add
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.AddCommentResponse>>
     */
    add: (
      requestData: thisProto.AddCommentRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.AddCommentResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Comments/Add',
        requestData,
        requestMetadata,
        requestClass: thisProto.AddCommentRequest,
        responseClass: thisProto.AddCommentResponse
      });
    },
    /**
     * Unary call: /goautowp.Comments/GetMessagePage
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APICommentsMessagePage>>
     */
    getMessagePage: (
      requestData: thisProto.GetMessagePageRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APICommentsMessagePage>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Comments/GetMessagePage',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetMessagePageRequest,
        responseClass: thisProto.APICommentsMessagePage
      });
    },
    /**
     * Unary call: /goautowp.Comments/GetMessage
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APICommentsMessage>>
     */
    getMessage: (
      requestData: thisProto.GetMessageRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APICommentsMessage>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Comments/GetMessage',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetMessageRequest,
        responseClass: thisProto.APICommentsMessage
      });
    },
    /**
     * Unary call: /goautowp.Comments/GetMessages
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APICommentsMessages>>
     */
    getMessages: (
      requestData: thisProto.GetMessagesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APICommentsMessages>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Comments/GetMessages',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetMessagesRequest,
        responseClass: thisProto.APICommentsMessages
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_COMMENTS_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Comments', settings);
  }

  /**
   * Unary call @/goautowp.Comments/GetCommentVotes
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.CommentVoteItems>
   */
  getCommentVotes(
    requestData: thisProto.GetCommentVotesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.CommentVoteItems> {
    return this.$raw
      .getCommentVotes(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Comments/Subscribe
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  subscribe(
    requestData: thisProto.CommentsSubscribeRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .subscribe(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Comments/UnSubscribe
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  unSubscribe(
    requestData: thisProto.CommentsUnSubscribeRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .unSubscribe(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Comments/View
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  view(
    requestData: thisProto.CommentsViewRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .view(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Comments/SetDeleted
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  setDeleted(
    requestData: thisProto.CommentsSetDeletedRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .setDeleted(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Comments/MoveComment
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  moveComment(
    requestData: thisProto.CommentsMoveCommentRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .moveComment(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Comments/VoteComment
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.CommentsVoteCommentResponse>
   */
  voteComment(
    requestData: thisProto.CommentsVoteCommentRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.CommentsVoteCommentResponse> {
    return this.$raw
      .voteComment(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Comments/Add
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.AddCommentResponse>
   */
  add(
    requestData: thisProto.AddCommentRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.AddCommentResponse> {
    return this.$raw
      .add(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Comments/GetMessagePage
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APICommentsMessagePage>
   */
  getMessagePage(
    requestData: thisProto.GetMessagePageRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APICommentsMessagePage> {
    return this.$raw
      .getMessagePage(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Comments/GetMessage
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APICommentsMessage>
   */
  getMessage(
    requestData: thisProto.GetMessageRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APICommentsMessage> {
    return this.$raw
      .getMessage(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Comments/GetMessages
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APICommentsMessages>
   */
  getMessages(
    requestData: thisProto.GetMessagesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APICommentsMessages> {
    return this.$raw
      .getMessages(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Log
 */
@Injectable({ providedIn: 'any' })
export class LogClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Log/GetEvents
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.LogEvents>>
     */
    getEvents: (
      requestData: thisProto.LogEventsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.LogEvents>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Log/GetEvents',
        requestData,
        requestMetadata,
        requestClass: thisProto.LogEventsRequest,
        responseClass: thisProto.LogEvents
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_LOG_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Log', settings);
  }

  /**
   * Unary call @/goautowp.Log/GetEvents
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.LogEvents>
   */
  getEvents(
    requestData: thisProto.LogEventsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.LogEvents> {
    return this.$raw
      .getEvents(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Map
 */
@Injectable({ providedIn: 'any' })
export class MapClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Map/GetPoints
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.MapPoints>>
     */
    getPoints: (
      requestData: thisProto.MapGetPointsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.MapPoints>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Map/GetPoints',
        requestData,
        requestMetadata,
        requestClass: thisProto.MapGetPointsRequest,
        responseClass: thisProto.MapPoints
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_MAP_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Map', settings);
  }

  /**
   * Unary call @/goautowp.Map/GetPoints
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.MapPoints>
   */
  getPoints(
    requestData: thisProto.MapGetPointsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.MapPoints> {
    return this.$raw
      .getPoints(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Pictures
 */
@Injectable({ providedIn: 'any' })
export class PicturesClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Pictures/View
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    view: (
      requestData: thisProto.PicturesViewRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/View',
        requestData,
        requestMetadata,
        requestClass: thisProto.PicturesViewRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/Vote
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.PicturesVoteSummary>>
     */
    vote: (
      requestData: thisProto.PicturesVoteRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.PicturesVoteSummary>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/Vote',
        requestData,
        requestMetadata,
        requestClass: thisProto.PicturesVoteRequest,
        responseClass: thisProto.PicturesVoteSummary
      });
    },
    /**
     * Unary call: /goautowp.Pictures/CreateModerVoteTemplate
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.ModerVoteTemplate>>
     */
    createModerVoteTemplate: (
      requestData: thisProto.ModerVoteTemplate,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.ModerVoteTemplate>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/CreateModerVoteTemplate',
        requestData,
        requestMetadata,
        requestClass: thisProto.ModerVoteTemplate,
        responseClass: thisProto.ModerVoteTemplate
      });
    },
    /**
     * Unary call: /goautowp.Pictures/DeleteModerVoteTemplate
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deleteModerVoteTemplate: (
      requestData: thisProto.DeleteModerVoteTemplateRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/DeleteModerVoteTemplate',
        requestData,
        requestMetadata,
        requestClass: thisProto.DeleteModerVoteTemplateRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/GetModerVoteTemplates
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.ModerVoteTemplates>>
     */
    getModerVoteTemplates: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.ModerVoteTemplates>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/GetModerVoteTemplates',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.ModerVoteTemplates
      });
    },
    /**
     * Unary call: /goautowp.Pictures/DeleteModerVote
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deleteModerVote: (
      requestData: thisProto.DeleteModerVoteRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/DeleteModerVote',
        requestData,
        requestMetadata,
        requestClass: thisProto.DeleteModerVoteRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/UpdateModerVote
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    updateModerVote: (
      requestData: thisProto.UpdateModerVoteRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/UpdateModerVote',
        requestData,
        requestMetadata,
        requestClass: thisProto.UpdateModerVoteRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/GetUserSummary
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.PicturesUserSummary>>
     */
    getUserSummary: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.PicturesUserSummary>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/GetUserSummary',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.PicturesUserSummary
      });
    },
    /**
     * Unary call: /goautowp.Pictures/Normalize
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    normalize: (
      requestData: thisProto.PictureIDRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/Normalize',
        requestData,
        requestMetadata,
        requestClass: thisProto.PictureIDRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/Flop
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    flop: (
      requestData: thisProto.PictureIDRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/Flop',
        requestData,
        requestMetadata,
        requestClass: thisProto.PictureIDRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/CorrectFileNames
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    correctFileNames: (
      requestData: thisProto.PictureIDRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/CorrectFileNames',
        requestData,
        requestMetadata,
        requestClass: thisProto.PictureIDRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/DeleteSimilar
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deleteSimilar: (
      requestData: thisProto.DeleteSimilarRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/DeleteSimilar',
        requestData,
        requestMetadata,
        requestClass: thisProto.DeleteSimilarRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/Repair
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    repair: (
      requestData: thisProto.PictureIDRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/Repair',
        requestData,
        requestMetadata,
        requestClass: thisProto.PictureIDRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/GetPicture
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.Picture>>
     */
    getPicture: (
      requestData: thisProto.PicturesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.Picture>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/GetPicture',
        requestData,
        requestMetadata,
        requestClass: thisProto.PicturesRequest,
        responseClass: thisProto.Picture
      });
    },
    /**
     * Unary call: /goautowp.Pictures/GetPictures
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.PicturesList>>
     */
    getPictures: (
      requestData: thisProto.PicturesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.PicturesList>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/GetPictures',
        requestData,
        requestMetadata,
        requestClass: thisProto.PicturesRequest,
        responseClass: thisProto.PicturesList
      });
    },
    /**
     * Unary call: /goautowp.Pictures/GetPicturesPaginator
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.Pages>>
     */
    getPicturesPaginator: (
      requestData: thisProto.PicturesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.Pages>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/GetPicturesPaginator',
        requestData,
        requestMetadata,
        requestClass: thisProto.PicturesRequest,
        responseClass: thisProto.Pages
      });
    },
    /**
     * Unary call: /goautowp.Pictures/GetPictureItem
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.PictureItem>>
     */
    getPictureItem: (
      requestData: thisProto.PictureItemsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.PictureItem>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/GetPictureItem',
        requestData,
        requestMetadata,
        requestClass: thisProto.PictureItemsRequest,
        responseClass: thisProto.PictureItem
      });
    },
    /**
     * Unary call: /goautowp.Pictures/GetPictureItems
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.PictureItems>>
     */
    getPictureItems: (
      requestData: thisProto.PictureItemsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.PictureItems>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/GetPictureItems',
        requestData,
        requestMetadata,
        requestClass: thisProto.PictureItemsRequest,
        responseClass: thisProto.PictureItems
      });
    },
    /**
     * Unary call: /goautowp.Pictures/SetPictureItemArea
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    setPictureItemArea: (
      requestData: thisProto.SetPictureItemAreaRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/SetPictureItemArea',
        requestData,
        requestMetadata,
        requestClass: thisProto.SetPictureItemAreaRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/SetPictureItemPerspective
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    setPictureItemPerspective: (
      requestData: thisProto.SetPictureItemPerspectiveRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/SetPictureItemPerspective',
        requestData,
        requestMetadata,
        requestClass: thisProto.SetPictureItemPerspectiveRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/SetPictureItemItemID
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    setPictureItemItemID: (
      requestData: thisProto.SetPictureItemItemIDRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/SetPictureItemItemID',
        requestData,
        requestMetadata,
        requestClass: thisProto.SetPictureItemItemIDRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/DeletePictureItem
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deletePictureItem: (
      requestData: thisProto.DeletePictureItemRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/DeletePictureItem',
        requestData,
        requestMetadata,
        requestClass: thisProto.DeletePictureItemRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/CreatePictureItem
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    createPictureItem: (
      requestData: thisProto.CreatePictureItemRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/CreatePictureItem',
        requestData,
        requestMetadata,
        requestClass: thisProto.CreatePictureItemRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/SetPictureCrop
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    setPictureCrop: (
      requestData: thisProto.SetPictureCropRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/SetPictureCrop',
        requestData,
        requestMetadata,
        requestClass: thisProto.SetPictureCropRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/ClearReplacePicture
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    clearReplacePicture: (
      requestData: thisProto.PictureIDRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/ClearReplacePicture',
        requestData,
        requestMetadata,
        requestClass: thisProto.PictureIDRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/AcceptReplacePicture
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    acceptReplacePicture: (
      requestData: thisProto.PictureIDRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/AcceptReplacePicture',
        requestData,
        requestMetadata,
        requestClass: thisProto.PictureIDRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/SetPicturePoint
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    setPicturePoint: (
      requestData: thisProto.SetPicturePointRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/SetPicturePoint',
        requestData,
        requestMetadata,
        requestClass: thisProto.SetPicturePointRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/UpdatePicture
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    updatePicture: (
      requestData: thisProto.UpdatePictureRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/UpdatePicture',
        requestData,
        requestMetadata,
        requestClass: thisProto.UpdatePictureRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/SetPictureCopyrights
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    setPictureCopyrights: (
      requestData: thisProto.SetPictureCopyrightsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/SetPictureCopyrights',
        requestData,
        requestMetadata,
        requestClass: thisProto.SetPictureCopyrightsRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/SetPictureStatus
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    setPictureStatus: (
      requestData: thisProto.SetPictureStatusRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/SetPictureStatus',
        requestData,
        requestMetadata,
        requestClass: thisProto.SetPictureStatusRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Pictures/GetInbox
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.Inbox>>
     */
    getInbox: (
      requestData: thisProto.InboxRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.Inbox>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/GetInbox',
        requestData,
        requestMetadata,
        requestClass: thisProto.InboxRequest,
        responseClass: thisProto.Inbox
      });
    },
    /**
     * Unary call: /goautowp.Pictures/GetNewbox
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.Newbox>>
     */
    getNewbox: (
      requestData: thisProto.NewboxRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.Newbox>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/GetNewbox',
        requestData,
        requestMetadata,
        requestClass: thisProto.NewboxRequest,
        responseClass: thisProto.Newbox
      });
    },
    /**
     * Unary call: /goautowp.Pictures/GetCanonicalRoute
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.CanonicalRoute>>
     */
    getCanonicalRoute: (
      requestData: thisProto.CanonicalRouteRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.CanonicalRoute>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/GetCanonicalRoute',
        requestData,
        requestMetadata,
        requestClass: thisProto.CanonicalRouteRequest,
        responseClass: thisProto.CanonicalRoute
      });
    },
    /**
     * Unary call: /goautowp.Pictures/GetGallery
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.GalleryResponse>>
     */
    getGallery: (
      requestData: thisProto.GalleryRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.GalleryResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/GetGallery',
        requestData,
        requestMetadata,
        requestClass: thisProto.GalleryRequest,
        responseClass: thisProto.GalleryResponse
      });
    },
    /**
     * Unary call: /goautowp.Pictures/GetPerspectives
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.PerspectivesItems>>
     */
    getPerspectives: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.PerspectivesItems>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/GetPerspectives',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.PerspectivesItems
      });
    },
    /**
     * Unary call: /goautowp.Pictures/GetPerspectivePages
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.PerspectivePagesItems>>
     */
    getPerspectivePages: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.PerspectivePagesItems>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Pictures/GetPerspectivePages',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.PerspectivePagesItems
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_PICTURES_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Pictures', settings);
  }

  /**
   * Unary call @/goautowp.Pictures/View
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  view(
    requestData: thisProto.PicturesViewRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .view(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/Vote
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.PicturesVoteSummary>
   */
  vote(
    requestData: thisProto.PicturesVoteRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.PicturesVoteSummary> {
    return this.$raw
      .vote(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/CreateModerVoteTemplate
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.ModerVoteTemplate>
   */
  createModerVoteTemplate(
    requestData: thisProto.ModerVoteTemplate,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.ModerVoteTemplate> {
    return this.$raw
      .createModerVoteTemplate(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/DeleteModerVoteTemplate
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deleteModerVoteTemplate(
    requestData: thisProto.DeleteModerVoteTemplateRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deleteModerVoteTemplate(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/GetModerVoteTemplates
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.ModerVoteTemplates>
   */
  getModerVoteTemplates(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.ModerVoteTemplates> {
    return this.$raw
      .getModerVoteTemplates(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/DeleteModerVote
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deleteModerVote(
    requestData: thisProto.DeleteModerVoteRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deleteModerVote(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/UpdateModerVote
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  updateModerVote(
    requestData: thisProto.UpdateModerVoteRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .updateModerVote(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/GetUserSummary
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.PicturesUserSummary>
   */
  getUserSummary(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.PicturesUserSummary> {
    return this.$raw
      .getUserSummary(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/Normalize
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  normalize(
    requestData: thisProto.PictureIDRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .normalize(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/Flop
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  flop(
    requestData: thisProto.PictureIDRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .flop(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/CorrectFileNames
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  correctFileNames(
    requestData: thisProto.PictureIDRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .correctFileNames(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/DeleteSimilar
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deleteSimilar(
    requestData: thisProto.DeleteSimilarRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deleteSimilar(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/Repair
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  repair(
    requestData: thisProto.PictureIDRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .repair(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/GetPicture
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.Picture>
   */
  getPicture(
    requestData: thisProto.PicturesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.Picture> {
    return this.$raw
      .getPicture(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/GetPictures
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.PicturesList>
   */
  getPictures(
    requestData: thisProto.PicturesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.PicturesList> {
    return this.$raw
      .getPictures(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/GetPicturesPaginator
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.Pages>
   */
  getPicturesPaginator(
    requestData: thisProto.PicturesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.Pages> {
    return this.$raw
      .getPicturesPaginator(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/GetPictureItem
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.PictureItem>
   */
  getPictureItem(
    requestData: thisProto.PictureItemsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.PictureItem> {
    return this.$raw
      .getPictureItem(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/GetPictureItems
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.PictureItems>
   */
  getPictureItems(
    requestData: thisProto.PictureItemsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.PictureItems> {
    return this.$raw
      .getPictureItems(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/SetPictureItemArea
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  setPictureItemArea(
    requestData: thisProto.SetPictureItemAreaRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .setPictureItemArea(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/SetPictureItemPerspective
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  setPictureItemPerspective(
    requestData: thisProto.SetPictureItemPerspectiveRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .setPictureItemPerspective(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/SetPictureItemItemID
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  setPictureItemItemID(
    requestData: thisProto.SetPictureItemItemIDRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .setPictureItemItemID(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/DeletePictureItem
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deletePictureItem(
    requestData: thisProto.DeletePictureItemRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deletePictureItem(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/CreatePictureItem
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  createPictureItem(
    requestData: thisProto.CreatePictureItemRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .createPictureItem(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/SetPictureCrop
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  setPictureCrop(
    requestData: thisProto.SetPictureCropRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .setPictureCrop(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/ClearReplacePicture
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  clearReplacePicture(
    requestData: thisProto.PictureIDRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .clearReplacePicture(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/AcceptReplacePicture
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  acceptReplacePicture(
    requestData: thisProto.PictureIDRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .acceptReplacePicture(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/SetPicturePoint
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  setPicturePoint(
    requestData: thisProto.SetPicturePointRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .setPicturePoint(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/UpdatePicture
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  updatePicture(
    requestData: thisProto.UpdatePictureRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .updatePicture(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/SetPictureCopyrights
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  setPictureCopyrights(
    requestData: thisProto.SetPictureCopyrightsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .setPictureCopyrights(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/SetPictureStatus
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  setPictureStatus(
    requestData: thisProto.SetPictureStatusRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .setPictureStatus(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/GetInbox
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.Inbox>
   */
  getInbox(
    requestData: thisProto.InboxRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.Inbox> {
    return this.$raw
      .getInbox(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/GetNewbox
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.Newbox>
   */
  getNewbox(
    requestData: thisProto.NewboxRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.Newbox> {
    return this.$raw
      .getNewbox(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/GetCanonicalRoute
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.CanonicalRoute>
   */
  getCanonicalRoute(
    requestData: thisProto.CanonicalRouteRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.CanonicalRoute> {
    return this.$raw
      .getCanonicalRoute(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/GetGallery
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.GalleryResponse>
   */
  getGallery(
    requestData: thisProto.GalleryRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.GalleryResponse> {
    return this.$raw
      .getGallery(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/GetPerspectives
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.PerspectivesItems>
   */
  getPerspectives(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.PerspectivesItems> {
    return this.$raw
      .getPerspectives(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Pictures/GetPerspectivePages
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.PerspectivePagesItems>
   */
  getPerspectivePages(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.PerspectivePagesItems> {
    return this.$raw
      .getPerspectivePages(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Messaging
 */
@Injectable({ providedIn: 'any' })
export class MessagingClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Messaging/GetMessagesNewCount
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIMessageNewCount>>
     */
    getMessagesNewCount: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIMessageNewCount>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Messaging/GetMessagesNewCount',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.APIMessageNewCount
      });
    },
    /**
     * Unary call: /goautowp.Messaging/GetMessagesSummary
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIMessageSummary>>
     */
    getMessagesSummary: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIMessageSummary>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Messaging/GetMessagesSummary',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.APIMessageSummary
      });
    },
    /**
     * Unary call: /goautowp.Messaging/DeleteMessage
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deleteMessage: (
      requestData: thisProto.MessagingDeleteMessage,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Messaging/DeleteMessage',
        requestData,
        requestMetadata,
        requestClass: thisProto.MessagingDeleteMessage,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Messaging/ClearFolder
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    clearFolder: (
      requestData: thisProto.MessagingClearFolder,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Messaging/ClearFolder',
        requestData,
        requestMetadata,
        requestClass: thisProto.MessagingClearFolder,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Messaging/CreateMessage
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    createMessage: (
      requestData: thisProto.MessagingCreateMessage,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Messaging/CreateMessage',
        requestData,
        requestMetadata,
        requestClass: thisProto.MessagingCreateMessage,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Messaging/GetMessages
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.MessagingGetMessagesResponse>>
     */
    getMessages: (
      requestData: thisProto.MessagingGetMessagesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.MessagingGetMessagesResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Messaging/GetMessages',
        requestData,
        requestMetadata,
        requestClass: thisProto.MessagingGetMessagesRequest,
        responseClass: thisProto.MessagingGetMessagesResponse
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_MESSAGING_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Messaging', settings);
  }

  /**
   * Unary call @/goautowp.Messaging/GetMessagesNewCount
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIMessageNewCount>
   */
  getMessagesNewCount(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIMessageNewCount> {
    return this.$raw
      .getMessagesNewCount(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Messaging/GetMessagesSummary
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIMessageSummary>
   */
  getMessagesSummary(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIMessageSummary> {
    return this.$raw
      .getMessagesSummary(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Messaging/DeleteMessage
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deleteMessage(
    requestData: thisProto.MessagingDeleteMessage,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deleteMessage(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Messaging/ClearFolder
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  clearFolder(
    requestData: thisProto.MessagingClearFolder,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .clearFolder(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Messaging/CreateMessage
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  createMessage(
    requestData: thisProto.MessagingCreateMessage,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .createMessage(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Messaging/GetMessages
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.MessagingGetMessagesResponse>
   */
  getMessages(
    requestData: thisProto.MessagingGetMessagesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.MessagingGetMessagesResponse> {
    return this.$raw
      .getMessages(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Statistics
 */
@Injectable({ providedIn: 'any' })
export class StatisticsClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Statistics/GetPulse
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.PulseResponse>>
     */
    getPulse: (
      requestData: thisProto.PulseRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.PulseResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Statistics/GetPulse',
        requestData,
        requestMetadata,
        requestClass: thisProto.PulseRequest,
        responseClass: thisProto.PulseResponse
      });
    },
    /**
     * Unary call: /goautowp.Statistics/GetAboutData
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.AboutDataResponse>>
     */
    getAboutData: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.AboutDataResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Statistics/GetAboutData',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.AboutDataResponse
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_STATISTICS_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Statistics', settings);
  }

  /**
   * Unary call @/goautowp.Statistics/GetPulse
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.PulseResponse>
   */
  getPulse(
    requestData: thisProto.PulseRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.PulseResponse> {
    return this.$raw
      .getPulse(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Statistics/GetAboutData
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.AboutDataResponse>
   */
  getAboutData(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.AboutDataResponse> {
    return this.$raw
      .getAboutData(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Donations
 */
@Injectable({ providedIn: 'any' })
export class DonationsClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Donations/GetVODData
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.VODDataResponse>>
     */
    getVODData: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.VODDataResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Donations/GetVODData',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.VODDataResponse
      });
    },
    /**
     * Unary call: /goautowp.Donations/GetTransactions
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.DonationsTransactionsResponse>>
     */
    getTransactions: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.DonationsTransactionsResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Donations/GetTransactions',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.DonationsTransactionsResponse
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_DONATIONS_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Donations', settings);
  }

  /**
   * Unary call @/goautowp.Donations/GetVODData
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.VODDataResponse>
   */
  getVODData(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.VODDataResponse> {
    return this.$raw
      .getVODData(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Donations/GetTransactions
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.DonationsTransactionsResponse>
   */
  getTransactions(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.DonationsTransactionsResponse> {
    return this.$raw
      .getTransactions(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Text
 */
@Injectable({ providedIn: 'any' })
export class TextClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Text/GetText
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.APIGetTextResponse>>
     */
    getText: (
      requestData: thisProto.APIGetTextRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.APIGetTextResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Text/GetText',
        requestData,
        requestMetadata,
        requestClass: thisProto.APIGetTextRequest,
        responseClass: thisProto.APIGetTextResponse
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_TEXT_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Text', settings);
  }

  /**
   * Unary call @/goautowp.Text/GetText
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.APIGetTextResponse>
   */
  getText(
    requestData: thisProto.APIGetTextRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.APIGetTextResponse> {
    return this.$raw
      .getText(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Attrs
 */
@Injectable({ providedIn: 'any' })
export class AttrsClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Attrs/GetAttribute
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.AttrAttribute>>
     */
    getAttribute: (
      requestData: thisProto.AttrAttributeID,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.AttrAttribute>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/GetAttribute',
        requestData,
        requestMetadata,
        requestClass: thisProto.AttrAttributeID,
        responseClass: thisProto.AttrAttribute
      });
    },
    /**
     * Unary call: /goautowp.Attrs/GetAttributes
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.AttrAttributesResponse>>
     */
    getAttributes: (
      requestData: thisProto.AttrAttributesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.AttrAttributesResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/GetAttributes',
        requestData,
        requestMetadata,
        requestClass: thisProto.AttrAttributesRequest,
        responseClass: thisProto.AttrAttributesResponse
      });
    },
    /**
     * Unary call: /goautowp.Attrs/GetAttributeTypes
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.AttrAttributeTypesResponse>>
     */
    getAttributeTypes: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.AttrAttributeTypesResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/GetAttributeTypes',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.AttrAttributeTypesResponse
      });
    },
    /**
     * Unary call: /goautowp.Attrs/GetListOptions
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.AttrListOptionsResponse>>
     */
    getListOptions: (
      requestData: thisProto.AttrListOptionsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.AttrListOptionsResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/GetListOptions',
        requestData,
        requestMetadata,
        requestClass: thisProto.AttrListOptionsRequest,
        responseClass: thisProto.AttrListOptionsResponse
      });
    },
    /**
     * Unary call: /goautowp.Attrs/GetUnits
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.AttrUnitsResponse>>
     */
    getUnits: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.AttrUnitsResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/GetUnits',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.AttrUnitsResponse
      });
    },
    /**
     * Unary call: /goautowp.Attrs/GetZoneAttributes
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.AttrZoneAttributesResponse>>
     */
    getZoneAttributes: (
      requestData: thisProto.AttrZoneAttributesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.AttrZoneAttributesResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/GetZoneAttributes',
        requestData,
        requestMetadata,
        requestClass: thisProto.AttrZoneAttributesRequest,
        responseClass: thisProto.AttrZoneAttributesResponse
      });
    },
    /**
     * Unary call: /goautowp.Attrs/GetZones
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.AttrZonesResponse>>
     */
    getZones: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.AttrZonesResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/GetZones',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.AttrZonesResponse
      });
    },
    /**
     * Unary call: /goautowp.Attrs/GetValues
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.AttrValuesResponse>>
     */
    getValues: (
      requestData: thisProto.AttrValuesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.AttrValuesResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/GetValues',
        requestData,
        requestMetadata,
        requestClass: thisProto.AttrValuesRequest,
        responseClass: thisProto.AttrValuesResponse
      });
    },
    /**
     * Unary call: /goautowp.Attrs/GetUserValues
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.AttrUserValuesResponse>>
     */
    getUserValues: (
      requestData: thisProto.AttrUserValuesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.AttrUserValuesResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/GetUserValues',
        requestData,
        requestMetadata,
        requestClass: thisProto.AttrUserValuesRequest,
        responseClass: thisProto.AttrUserValuesResponse
      });
    },
    /**
     * Unary call: /goautowp.Attrs/SetUserValues
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    setUserValues: (
      requestData: thisProto.AttrSetUserValuesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/SetUserValues',
        requestData,
        requestMetadata,
        requestClass: thisProto.AttrSetUserValuesRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Attrs/DeleteUserValues
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    deleteUserValues: (
      requestData: thisProto.DeleteAttrUserValuesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/DeleteUserValues',
        requestData,
        requestMetadata,
        requestClass: thisProto.DeleteAttrUserValuesRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Attrs/MoveUserValues
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    moveUserValues: (
      requestData: thisProto.MoveAttrUserValuesRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/MoveUserValues',
        requestData,
        requestMetadata,
        requestClass: thisProto.MoveAttrUserValuesRequest,
        responseClass: googleProtobuf004.Empty
      });
    },
    /**
     * Unary call: /goautowp.Attrs/GetConflicts
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.AttrConflictsResponse>>
     */
    getConflicts: (
      requestData: thisProto.AttrConflictsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.AttrConflictsResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/GetConflicts',
        requestData,
        requestMetadata,
        requestClass: thisProto.AttrConflictsRequest,
        responseClass: thisProto.AttrConflictsResponse
      });
    },
    /**
     * Unary call: /goautowp.Attrs/GetSpecifications
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.GetSpecificationsResponse>>
     */
    getSpecifications: (
      requestData: thisProto.GetSpecificationsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.GetSpecificationsResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/GetSpecifications',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetSpecificationsRequest,
        responseClass: thisProto.GetSpecificationsResponse
      });
    },
    /**
     * Unary call: /goautowp.Attrs/GetChildSpecifications
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.GetSpecificationsResponse>>
     */
    getChildSpecifications: (
      requestData: thisProto.GetSpecificationsRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.GetSpecificationsResponse>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/GetChildSpecifications',
        requestData,
        requestMetadata,
        requestClass: thisProto.GetSpecificationsRequest,
        responseClass: thisProto.GetSpecificationsResponse
      });
    },
    /**
     * Unary call: /goautowp.Attrs/GetChartParameters
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.ChartParameters>>
     */
    getChartParameters: (
      requestData: googleProtobuf004.Empty,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.ChartParameters>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/GetChartParameters',
        requestData,
        requestMetadata,
        requestClass: googleProtobuf004.Empty,
        responseClass: thisProto.ChartParameters
      });
    },
    /**
     * Unary call: /goautowp.Attrs/GetChartData
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.ChartData>>
     */
    getChartData: (
      requestData: thisProto.ChartDataRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.ChartData>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Attrs/GetChartData',
        requestData,
        requestMetadata,
        requestClass: thisProto.ChartDataRequest,
        responseClass: thisProto.ChartData
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_ATTRS_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Attrs', settings);
  }

  /**
   * Unary call @/goautowp.Attrs/GetAttribute
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.AttrAttribute>
   */
  getAttribute(
    requestData: thisProto.AttrAttributeID,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.AttrAttribute> {
    return this.$raw
      .getAttribute(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/GetAttributes
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.AttrAttributesResponse>
   */
  getAttributes(
    requestData: thisProto.AttrAttributesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.AttrAttributesResponse> {
    return this.$raw
      .getAttributes(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/GetAttributeTypes
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.AttrAttributeTypesResponse>
   */
  getAttributeTypes(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.AttrAttributeTypesResponse> {
    return this.$raw
      .getAttributeTypes(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/GetListOptions
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.AttrListOptionsResponse>
   */
  getListOptions(
    requestData: thisProto.AttrListOptionsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.AttrListOptionsResponse> {
    return this.$raw
      .getListOptions(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/GetUnits
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.AttrUnitsResponse>
   */
  getUnits(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.AttrUnitsResponse> {
    return this.$raw
      .getUnits(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/GetZoneAttributes
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.AttrZoneAttributesResponse>
   */
  getZoneAttributes(
    requestData: thisProto.AttrZoneAttributesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.AttrZoneAttributesResponse> {
    return this.$raw
      .getZoneAttributes(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/GetZones
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.AttrZonesResponse>
   */
  getZones(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.AttrZonesResponse> {
    return this.$raw
      .getZones(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/GetValues
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.AttrValuesResponse>
   */
  getValues(
    requestData: thisProto.AttrValuesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.AttrValuesResponse> {
    return this.$raw
      .getValues(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/GetUserValues
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.AttrUserValuesResponse>
   */
  getUserValues(
    requestData: thisProto.AttrUserValuesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.AttrUserValuesResponse> {
    return this.$raw
      .getUserValues(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/SetUserValues
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  setUserValues(
    requestData: thisProto.AttrSetUserValuesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .setUserValues(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/DeleteUserValues
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  deleteUserValues(
    requestData: thisProto.DeleteAttrUserValuesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .deleteUserValues(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/MoveUserValues
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  moveUserValues(
    requestData: thisProto.MoveAttrUserValuesRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .moveUserValues(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/GetConflicts
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.AttrConflictsResponse>
   */
  getConflicts(
    requestData: thisProto.AttrConflictsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.AttrConflictsResponse> {
    return this.$raw
      .getConflicts(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/GetSpecifications
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.GetSpecificationsResponse>
   */
  getSpecifications(
    requestData: thisProto.GetSpecificationsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.GetSpecificationsResponse> {
    return this.$raw
      .getSpecifications(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/GetChildSpecifications
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.GetSpecificationsResponse>
   */
  getChildSpecifications(
    requestData: thisProto.GetSpecificationsRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.GetSpecificationsResponse> {
    return this.$raw
      .getChildSpecifications(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/GetChartParameters
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.ChartParameters>
   */
  getChartParameters(
    requestData: googleProtobuf004.Empty,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.ChartParameters> {
    return this.$raw
      .getChartParameters(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Attrs/GetChartData
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.ChartData>
   */
  getChartData(
    requestData: thisProto.ChartDataRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.ChartData> {
    return this.$raw
      .getChartData(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
/**
 * Service client implementation for goautowp.Votings
 */
@Injectable({ providedIn: 'any' })
export class VotingsClient {
  private client: GrpcClient<any>;

  /**
   * Raw RPC implementation for each service client method.
   * The raw methods provide more control on the incoming data and events. E.g. they can be useful to read status `OK` metadata.
   * Attention: these methods do not throw errors when non-zero status codes are received.
   */
  $raw = {
    /**
     * Unary call: /goautowp.Votings/GetVoting
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.Voting>>
     */
    getVoting: (
      requestData: thisProto.VotingRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.Voting>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Votings/GetVoting',
        requestData,
        requestMetadata,
        requestClass: thisProto.VotingRequest,
        responseClass: thisProto.Voting
      });
    },
    /**
     * Unary call: /goautowp.Votings/GetVotingVariantVotes
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<thisProto.VotingVariantVotes>>
     */
    getVotingVariantVotes: (
      requestData: thisProto.VotingRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<thisProto.VotingVariantVotes>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Votings/GetVotingVariantVotes',
        requestData,
        requestMetadata,
        requestClass: thisProto.VotingRequest,
        responseClass: thisProto.VotingVariantVotes
      });
    },
    /**
     * Unary call: /goautowp.Votings/Vote
     *
     * @param requestMessage Request message
     * @param requestMetadata Request metadata
     * @returns Observable<GrpcEvent<googleProtobuf004.Empty>>
     */
    vote: (
      requestData: thisProto.VoteRequest,
      requestMetadata = new GrpcMetadata()
    ): Observable<GrpcEvent<googleProtobuf004.Empty>> => {
      return this.handler.handle({
        type: GrpcCallType.unary,
        client: this.client,
        path: '/goautowp.Votings/Vote',
        requestData,
        requestMetadata,
        requestClass: thisProto.VoteRequest,
        responseClass: googleProtobuf004.Empty
      });
    }
  };

  constructor(
    @Optional() @Inject(GRPC_VOTINGS_CLIENT_SETTINGS) settings: any,
    @Inject(GRPC_CLIENT_FACTORY) clientFactory: GrpcClientFactory<any>,
    private handler: GrpcHandler
  ) {
    this.client = clientFactory.createClient('goautowp.Votings', settings);
  }

  /**
   * Unary call @/goautowp.Votings/GetVoting
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.Voting>
   */
  getVoting(
    requestData: thisProto.VotingRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.Voting> {
    return this.$raw
      .getVoting(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Votings/GetVotingVariantVotes
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<thisProto.VotingVariantVotes>
   */
  getVotingVariantVotes(
    requestData: thisProto.VotingRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<thisProto.VotingVariantVotes> {
    return this.$raw
      .getVotingVariantVotes(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }

  /**
   * Unary call @/goautowp.Votings/Vote
   *
   * @param requestMessage Request message
   * @param requestMetadata Request metadata
   * @returns Observable<googleProtobuf004.Empty>
   */
  vote(
    requestData: thisProto.VoteRequest,
    requestMetadata = new GrpcMetadata()
  ): Observable<googleProtobuf004.Empty> {
    return this.$raw
      .vote(requestData, requestMetadata)
      .pipe(throwStatusErrors(), takeMessages());
  }
}
