package donations

import (
	"context"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/donations"
	"github.com/autowp/goautowp/internal/itemofday"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/timestamppb"
)

type Server struct {
	pb.UnimplementedDonationsServer

	itemOfDay         *itemofday.Repository
	donationsVodPrice int32
	repository        *donations.Repository
}

func NewServer(
	itemOfDay *itemofday.Repository,
	donationsVodPrice int32,
	repository *donations.Repository,
) *Server {
	return &Server{
		itemOfDay:         itemOfDay,
		donationsVodPrice: donationsVodPrice,
		repository:        repository,
	}
}

func (s *Server) GetVODData(
	ctx context.Context,
	_ *emptypb.Empty,
) (*pb.VODDataResponse, error) {
	dates := make([]*pb.VODDataDate, 0)

	nextDates, err := s.itemOfDay.NextDates(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	for _, nextDate := range nextDates {
		dates = append(dates, &pb.VODDataDate{
			Date: timestamppb.New(nextDate.Date),
			Free: nextDate.Free,
		})
	}

	return &pb.VODDataResponse{
		Dates: dates,
		Sum:   s.donationsVodPrice,
	}, nil
}

func (s *Server) GetTransactions(
	ctx context.Context, _ *emptypb.Empty,
) (*pb.DonationsTransactionsResponse, error) {
	rows, err := s.repository.Transactions(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*pb.DonationsTransaction, 0, len(rows))

	for _, row := range rows {
		userID := row.UserID.Int64
		if !row.UserID.Valid {
			userID = 0
		}

		res = append(res, &pb.DonationsTransaction{
			Sum:         row.Sum,
			Currency:    row.Currency,
			CreateTime:  timestamppb.New(row.Date),
			Contributor: row.Contributor,
			Purpose:     row.Purpose,
			UserId:      userID,
		})
	}

	return &pb.DonationsTransactionsResponse{Items: res}, nil
}
