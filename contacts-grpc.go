package goautowp

import (
	"context"

	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/users"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

type ContactsGRPCServer struct {
	UnimplementedContactsServer

	auth               *Auth
	contactsRepository *ContactsRepository
	userRepository     *users.Repository
	userExtractor      *UserExtractor
}

func NewContactsGRPCServer(
	auth *Auth,
	contactsRepository *ContactsRepository,
	userRepository *users.Repository,
	userExtractor *UserExtractor,
) *ContactsGRPCServer {
	return &ContactsGRPCServer{
		auth:               auth,
		contactsRepository: contactsRepository,
		userRepository:     userRepository,
		userExtractor:      userExtractor,
	}
}

func (s *ContactsGRPCServer) CreateContact(
	ctx context.Context,
	in *CreateContactRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	if in.GetUserId() == userCtx.UserID {
		return nil, status.Errorf(codes.InvalidArgument, "InvalidArgument")
	}

	deleted := false

	user, err := s.userRepository.User(
		ctx,
		&query.UserListOptions{ID: in.GetUserId(), Deleted: &deleted},
		users.UserFields{},
		users.OrderByNone,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if user == nil {
		return nil, status.Error(codes.NotFound, "NotFound")
	}

	err = s.contactsRepository.create(ctx, userCtx.UserID, in.GetUserId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *ContactsGRPCServer) DeleteContact(
	ctx context.Context,
	in *DeleteContactRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Error(codes.PermissionDenied, "PermissionDenied")
	}

	err = s.contactsRepository.delete(ctx, userCtx.UserID, in.GetUserId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *ContactsGRPCServer) GetContact(
	ctx context.Context,
	in *GetContactRequest,
) (*Contact, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Error(codes.PermissionDenied, "PermissionDenied")
	}

	if in.GetUserId() == userCtx.UserID {
		return nil, status.Error(codes.InvalidArgument, "InvalidArgument")
	}

	exists, err := s.contactsRepository.isExists(ctx, userCtx.UserID, in.GetUserId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !exists {
		return nil, status.Error(codes.NotFound, "NotFound")
	}

	return &Contact{
		ContactUserId: in.GetUserId(),
	}, nil
}

func (s *ContactsGRPCServer) GetContacts(
	ctx context.Context,
	_ *GetContactsRequest,
) (*ContactItems, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Error(codes.PermissionDenied, "PermissionDenied")
	}

	userRows, _, err := s.userRepository.Users(ctx, &query.UserListOptions{
		InContacts: userCtx.UserID,
	}, users.UserFields{}, users.OrderByDeletedName)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	items := make([]*Contact, len(userRows))

	for idx := range userRows {
		user, err := s.userExtractor.Extract(
			ctx,
			&userRows[idx],
			nil,
			userCtx.UserID,
			userCtx.Roles,
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		items[idx] = &Contact{
			ContactUserId: user.GetId(),
			User:          user,
		}
	}

	return &ContactItems{
		Items: items,
	}, nil
}
