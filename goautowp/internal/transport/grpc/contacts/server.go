package contacts

import (
	"context"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/contacts"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/transport/grpc/userextractor"
	"github.com/autowp/goautowp/internal/users"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

type Server struct {
	pb.UnimplementedContactsServer

	auth               *authn.Service
	contactsRepository *contacts.Repository
	userRepository     *users.Repository
	userExtractor      *userextractor.Extractor
}

func NewServer(
	auth *authn.Service,
	contactsRepository *contacts.Repository,
	userRepository *users.Repository,
	userExtractor *userextractor.Extractor,
) *Server {
	return &Server{
		auth:               auth,
		contactsRepository: contactsRepository,
		userRepository:     userRepository,
		userExtractor:      userExtractor,
	}
}

func (s *Server) CreateContact(
	ctx context.Context,
	in *pb.CreateContactRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	contact := in.GetContact()

	if contact.GetContactUserId() == userCtx.UserID {
		return nil, status.Errorf(codes.InvalidArgument, "invalid argument")
	}

	deleted := false

	user, err := s.userRepository.User(
		ctx,
		&query.UserListOptions{ID: contact.GetContactUserId(), Deleted: &deleted},
		users.UserFields{},
		users.OrderByNone,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if user == nil {
		return nil, status.Error(codes.NotFound, "not found")
	}

	err = s.contactsRepository.Create(ctx, userCtx.UserID, contact.GetContactUserId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) DeleteContact(
	ctx context.Context,
	in *pb.DeleteContactRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	err = s.contactsRepository.Delete(ctx, userCtx.UserID, in.GetUserId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) GetContact(
	ctx context.Context,
	in *pb.GetContactRequest,
) (*pb.Contact, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetUserId() == userCtx.UserID {
		return nil, status.Error(codes.InvalidArgument, "invalid argument")
	}

	exists, err := s.contactsRepository.Exists(ctx, userCtx.UserID, in.GetUserId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !exists {
		return nil, status.Error(codes.NotFound, "not found")
	}

	return &pb.Contact{
		ContactUserId: in.GetUserId(),
	}, nil
}

func (s *Server) GetContacts(
	ctx context.Context,
	_ *emptypb.Empty,
) (*pb.ContactItems, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	userRows, _, err := s.userRepository.Users(ctx, &query.UserListOptions{
		InContacts: userCtx.UserID,
	}, users.UserFields{}, users.OrderByDeletedName)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	extractedUsers, err := s.userExtractor.ExtractRows(ctx, userRows, nil, userCtx.UserID, userCtx.Roles)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	items := make([]*pb.Contact, len(extractedUsers))
	for idx, user := range extractedUsers {
		items[idx] = &pb.Contact{
			ContactUserId: user.GetId(),
			User:          user,
		}
	}

	return &pb.ContactItems{
		Items: items,
	}, nil
}
