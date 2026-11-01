package users

import (
	"context"
	"errors"
	"fmt"
	"strings"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/audit"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/contacts"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/transport/grpc/userextractor"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

type Server struct {
	pb.UnimplementedUsersServer

	auth               *authn.Service
	contactsRepository *contacts.Repository
	userRepository     *users.Repository
	events             *audit.Recorder
	languages          map[string]config.LanguageConfig
	captcha            bool
	userExtractor      *userextractor.Extractor
}

func NewServer(
	auth *authn.Service,
	contactsRepository *contacts.Repository,
	userRepository *users.Repository,
	events *audit.Recorder,
	languages map[string]config.LanguageConfig,
	captcha bool,
	userExtractor *userextractor.Extractor,
) *Server {
	return &Server{
		auth:               auth,
		contactsRepository: contactsRepository,
		userRepository:     userRepository,
		events:             events,
		languages:          languages,
		captcha:            captcha,
		userExtractor:      userExtractor,
	}
}

func (s *Server) Me(ctx context.Context, in *pb.MeRequest) (*pb.User, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	return s.GetUser(ctx, &pb.GetUserRequest{
		UserId: userCtx.UserID,
		Fields: in.GetFields(),
	})
}

func (s *Server) GetUser(ctx context.Context, in *pb.GetUserRequest) (*pb.User, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	dbUser, err := s.userRepository.User(ctx, &query.UserListOptions{
		ID:       in.GetUserId(),
		Identity: in.GetIdentity(),
	}, convertUserFields(in.GetFields(), userCtx.Roles), users.OrderByNone)
	if err != nil {
		if errors.Is(err, users.ErrUserNotFound) {
			return nil, status.Error(codes.NotFound, "User not found")
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	apiUser, err := s.userExtractor.Extract(
		ctx,
		dbUser,
		in.GetFields(),
		userCtx.UserID,
		userCtx.Roles,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return apiUser, err
}

func (s *Server) DeleteUser(
	ctx context.Context,
	in *pb.DeleteUserRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RoleAdmin) {
		if userCtx.UserID != in.GetUserId() {
			return nil, status.Errorf(codes.Internal, "Forbidden")
		}

		match, err := s.userRepository.PasswordMatch(ctx, in.GetUserId(), in.GetPassword())
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		if !match {
			return nil, grpcerrors.FieldViolations([]*errdetails.BadRequest_FieldViolation{{
				Field:       "oldPassword",
				Description: "Password is incorrect",
			}})
		}
	}

	ctx = context.WithoutCancel(ctx)

	success, err := s.userRepository.DeleteUser(ctx, in.GetUserId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if success {
		err = s.contactsRepository.DeleteUserEverywhere(ctx, in.GetUserId())
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		err = s.events.Add(ctx, audit.Event{
			UserID:  userCtx.UserID,
			Message: fmt.Sprintf("Удаление пользователя №%d", in.GetUserId()),
			Users:   []int64{in.GetUserId()},
		})
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) DisableUserCommentsNotifications(
	ctx context.Context,
	in *pb.UserPreferencesRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	if in.GetUserId() == userCtx.UserID {
		return nil, status.Errorf(codes.InvalidArgument, "invalid argument")
	}

	err = s.userRepository.SetDisableUserCommentsNotifications(
		ctx,
		userCtx.UserID,
		in.GetUserId(),
		true,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) EnableUserCommentsNotifications(
	ctx context.Context,
	in *pb.UserPreferencesRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	if in.GetUserId() == userCtx.UserID {
		return nil, status.Errorf(codes.InvalidArgument, "invalid argument")
	}

	err = s.userRepository.SetDisableUserCommentsNotifications(
		ctx,
		userCtx.UserID,
		in.GetUserId(),
		false,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) GetUserPreferences(
	ctx context.Context,
	in *pb.UserPreferencesRequest,
) (*pb.UserPreferencesResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	if in.GetUserId() == userCtx.UserID {
		return nil, status.Errorf(codes.InvalidArgument, "invalid argument")
	}

	prefs, err := s.userRepository.UserPreferences(ctx, userCtx.UserID, in.GetUserId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	blockedByTarget, err := s.userRepository.IsBlacklisted(ctx, in.GetUserId(), userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.UserPreferencesResponse{
		DisableCommentsNotifications: prefs.DisableCommentsNotifications,
		Blacklist:                    prefs.Blacklist,
		BlockedByTarget:              blockedByTarget,
	}, nil
}

func (s *Server) AddUserToBlacklist(
	ctx context.Context,
	in *pb.UserPreferencesRequest,
) (*emptypb.Empty, error) {
	return s.setUserBlacklist(ctx, in, true)
}

func (s *Server) RemoveUserFromBlacklist(
	ctx context.Context,
	in *pb.UserPreferencesRequest,
) (*emptypb.Empty, error) {
	return s.setUserBlacklist(ctx, in, false)
}

func (s *Server) GetBlacklistedUsers(
	ctx context.Context,
	_ *emptypb.Empty,
) (*pb.UsersResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	rows, _, err := s.userRepository.Users(ctx, &query.UserListOptions{
		BlacklistedBy: userCtx.UserID,
	}, users.UserFields{}, users.OrderByDeletedName)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result, err := s.userExtractor.ExtractRows(ctx, rows, nil, userCtx.UserID, userCtx.Roles)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.UsersResponse{Items: result}, nil
}

func (s *Server) GetUsers(
	ctx context.Context,
	in *pb.UsersRequest,
) (*pb.UsersResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	rows, pages, err := s.userRepository.Users(ctx, &query.UserListOptions{
		IsOnline: in.GetIsOnline(),
		Limit:    in.GetLimit(),
		Page:     in.GetPage(),
		Search:   in.GetSearch(),
		IDs:      in.GetId(),
	}, convertUserFields(in.GetFields(), userCtx.Roles), users.OrderByNone)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result, err := s.userExtractor.ExtractRows(ctx, rows, in.GetFields(), userCtx.UserID, userCtx.Roles)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	var paginator *pb.Pages
	if pages != nil {
		paginator = &pb.Pages{
			PageCount:      pages.PageCount,
			Current:        pages.Current,
			TotalItemCount: pages.TotalItemCount,
		}
	}

	return &pb.UsersResponse{
		Items:     result,
		Paginator: paginator,
	}, nil
}

func (s *Server) GetAccounts(
	ctx context.Context,
	_ *emptypb.Empty,
) (*pb.AccountsResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	rows, err := s.userRepository.UserAccounts(ctx, userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	accounts := make([]*pb.AccountsAccount, 0, len(rows))

	for _, row := range rows {
		if row.ServiceID != "keycloak" {
			canRemove, err := s.canRemoveAccount(ctx, userCtx.UserID, row.ID)
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}

			accounts = append(accounts, &pb.AccountsAccount{
				Icon: "fa fa-" + strings.ReplaceAll(
					row.ServiceID,
					"googleplus",
					"google-plus",
				),
				Id:        row.ID,
				Link:      row.Link,
				Name:      row.Name,
				CanRemove: canRemove,
			})
		}
	}

	return &pb.AccountsResponse{
		Items: accounts,
	}, nil
}

func (s *Server) DeleteUserAccount(
	ctx context.Context,
	in *pb.DeleteUserAccountRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	canRemove, err := s.canRemoveAccount(ctx, userCtx.UserID, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !canRemove {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	err = s.userRepository.RemoveUserAccount(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) DeleteUserPhoto(
	ctx context.Context,
	in *pb.DeleteUserPhotoRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleUsersModer) {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	if in.GetId() == 0 {
		return nil, status.Errorf(codes.InvalidArgument, "id is zero")
	}

	success, err := s.userRepository.DeletePhoto(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if success {
		err = s.events.Add(ctx, audit.Event{
			UserID:  userCtx.UserID,
			Message: fmt.Sprintf("Удаление фотографии пользователя №%d", in.GetId()),
			Users:   []int64{in.GetId()},
		})
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) RecordConsent(
	ctx context.Context,
	in *pb.RecordConsentRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	err = s.userRepository.RecordConsent(ctx, userCtx.UserID, in.GetAnalytics(), in.GetPolicyVersion())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) AcceptTerms(
	ctx context.Context,
	_ *pb.AcceptTermsRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	if err = s.userRepository.AcceptTerms(ctx, userCtx.UserID); err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) UpdateUser(
	ctx context.Context,
	in *pb.UpdateUserRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	values := in.GetUser()

	if values.GetId() == 0 {
		return nil, status.Errorf(codes.InvalidArgument, "id is zero")
	}

	if userCtx.UserID != values.GetId() {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	maskPaths := in.GetUpdateMask().GetPaths()

	if maskPaths == nil {
		maskPaths = []string{}
	}

	InvalidParams, err := validateUser(values, s.languages, maskPaths)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, grpcerrors.FieldViolations(InvalidParams)
	}

	set := schema.UsersRow{
		ID: userCtx.UserID,
	}

	if util.Contains(maskPaths, "language") {
		set.Language = values.GetLanguage()
	}

	if util.Contains(maskPaths, "timezone") {
		set.Timezone = values.GetTimezone()
	}

	if util.Contains(maskPaths, "contacts_public") {
		set.ContactsPublic = values.GetContactsPublic()
	}

	err = s.userRepository.UpdateUser(ctx, set, maskPaths)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if util.Contains(maskPaths, "contacts") {
		contacts := make([]schema.UserContactRow, 0, len(values.GetContacts()))

		for _, contact := range values.GetContacts() {
			if contact.GetUsername() == "" {
				continue
			}

			//nolint:gosec // platform is a proto enum with a small range
			platformID := schema.UserContactPlatform(contact.GetPlatform())

			contacts = append(contacts, schema.UserContactRow{
				UserID:   userCtx.UserID,
				Platform: platformID,
				Username: contact.GetUsername(),
			})
		}

		if err = s.userRepository.SetUserContacts(ctx, userCtx.UserID, contacts); err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) setUserBlacklist(
	ctx context.Context,
	in *pb.UserPreferencesRequest,
	blacklisted bool,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	if in.GetUserId() == userCtx.UserID {
		return nil, status.Errorf(codes.InvalidArgument, "invalid argument")
	}

	err = s.userRepository.SetUserBlacklist(ctx, userCtx.UserID, in.GetUserId(), blacklisted)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) canRemoveAccount(
	ctx context.Context,
	userID int64,
	id int64,
) (bool, error) {
	user, err := s.userRepository.User(
		ctx,
		&query.UserListOptions{ID: userID},
		users.UserFields{},
		users.OrderByNone,
	)
	if err != nil {
		return false, err
	}

	if user.EMail != nil && len(*user.EMail) > 0 {
		return true, nil
	}

	return s.userRepository.HaveAccountsForOtherServices(ctx, userID, id)
}
