package attrs

import (
	"context"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/attrs"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/timestamppb"
)

type Server struct {
	pb.UnimplementedAttrsServer

	repository *attrs.Repository
	auth       *authn.Service
}

func NewServer(repository *attrs.Repository, auth *authn.Service) *Server {
	return &Server{
		repository: repository,
		auth:       auth,
	}
}

func (s *Server) GetAttribute(
	ctx context.Context,
	in *pb.GetAttributeRequest,
) (*pb.AttrAttribute, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	row, err := s.repository.Attribute(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if row == nil {
		return nil, status.Error(codes.NotFound, "not found")
	}

	return convertAttribute(row), nil
}

func (s *Server) ListAttributes(
	ctx context.Context, in *pb.ListAttributesRequest,
) (*pb.ListAttributesResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	rows, err := s.repository.Attributes(
		ctx,
		&query.AttrsListOptions{ZoneID: in.GetZoneId(), ParentID: in.GetParentId()},
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*pb.AttrAttribute, len(rows))

	for idx, row := range rows {
		res[idx] = convertAttribute(row)
	}

	return &pb.ListAttributesResponse{
		Items: res,
	}, nil
}

func (s *Server) GetAttributeTypes(
	ctx context.Context, _ *emptypb.Empty,
) (*pb.AttrAttributeTypesResponse, error) {
	rows, err := s.repository.AttributeTypes(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*pb.AttrAttributeType, len(rows))
	for idx, row := range rows {
		res[idx] = &pb.AttrAttributeType{
			Id:   convertTypeID(row.ID),
			Name: row.Name,
		}
	}

	return &pb.AttrAttributeTypesResponse{
		Items: res,
	}, nil
}

func (s *Server) GetListOptions(
	ctx context.Context, in *pb.AttrListOptionsRequest,
) (*pb.AttrListOptionsResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	rows, err := s.repository.ListOptions(ctx, in.GetAttributeId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	options := make([]*pb.AttrListOption, len(rows))

	for idx, row := range rows {
		var parentID int64
		if row.ParentID.Valid {
			parentID = row.ParentID.Int64
		}

		options[idx] = &pb.AttrListOption{
			Id:          row.ID,
			Name:        row.Name,
			AttributeId: row.AttributeID,
			ParentId:    parentID,
		}
	}

	return &pb.AttrListOptionsResponse{
		Items: options,
	}, nil
}

func (s *Server) GetUnits(
	ctx context.Context,
	_ *emptypb.Empty,
) (*pb.AttrUnitsResponse, error) {
	rows, err := s.repository.Units(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	units := make([]*pb.AttrUnit, len(rows))
	for idx, row := range rows {
		units[idx] = &pb.AttrUnit{
			Id:   row.ID,
			Name: row.Name,
			Abbr: row.Abbr,
		}
	}

	return &pb.AttrUnitsResponse{
		Items: units,
	}, nil
}

func (s *Server) GetZoneAttributes(
	ctx context.Context, in *pb.AttrZoneAttributesRequest,
) (*pb.AttrZoneAttributesResponse, error) {
	rows, err := s.repository.ZoneAttributes(ctx, in.GetZoneId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*pb.AttrZoneAttribute, len(rows))
	for idx, row := range rows {
		res[idx] = &pb.AttrZoneAttribute{
			ZoneId:      row.ZoneID,
			AttributeId: row.AttributeID,
		}
	}

	return &pb.AttrZoneAttributesResponse{
		Items: res,
	}, nil
}

func (s *Server) GetZones(
	ctx context.Context,
	_ *emptypb.Empty,
) (*pb.AttrZonesResponse, error) {
	rows, err := s.repository.Zones(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*pb.AttrZone, len(rows))
	for idx, row := range rows {
		res[idx] = &pb.AttrZone{
			Id:   row.ID,
			Name: row.Name,
		}
	}

	return &pb.AttrZonesResponse{
		Items: res,
	}, nil
}

func (s *Server) GetValues(
	ctx context.Context,
	in *pb.AttrValuesRequest,
) (*pb.AttrValuesResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(
			codes.PermissionDenied,
			"PermissionDenied: specifications.edit is required",
		)
	}

	rows, err := s.repository.Values(ctx, query.AttrsValueListOptions{
		ZoneID: in.GetZoneId(),
		ItemID: in.GetItemId(),
	}, attrs.ValuesOrderByNone)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	values, err := s.repository.ActualValuesText(ctx, rows, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*pb.AttrValue, len(rows))
	for index, row := range rows {
		value := extractAttrValue(values[index].Value)
		res[index] = &pb.AttrValue{
			AttributeId: row.AttributeID, ItemId: row.ItemID, Value: &value, ValueText: values[index].Text,
		}
	}

	return &pb.AttrValuesResponse{
		Items: res,
	}, nil
}

func (s *Server) GetUserValues(
	ctx context.Context, in *pb.AttrUserValuesRequest,
) (*pb.AttrUserValuesResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(
			codes.PermissionDenied,
			"PermissionDenied: specifications.edit is required",
		)
	}

	if in.GetItemId() == 0 {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied: item_id cannot be nil")
	}

	rows, err := s.repository.UserValueRows(ctx, query.AttrsUserValueListOptions{
		ZoneID:        in.GetZoneId(),
		ItemID:        in.GetItemId(),
		UserID:        in.GetUserId(),
		ExcludeUserID: in.GetExcludeUserId(),
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	values, err := s.repository.UserValues(ctx, rows, in.GetLanguage(), in.GetFields().GetValueText())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*pb.AttrUserValue, len(rows))
	for index, row := range rows {
		extracted := extractAttrValue(values[index].Value)

		var updateDate *timestamppb.Timestamp
		if row.UpdateDate.Valid {
			updateDate = timestamppb.New(row.UpdateDate.Time)
		}

		res[index] = &pb.AttrUserValue{
			AttributeId: row.AttributeID, ItemId: row.ItemID, UserId: row.UserID,
			Value: &extracted, ValueText: values[index].Text, UpdateTime: updateDate,
		}
	}

	return &pb.AttrUserValuesResponse{
		Items: res,
	}, nil
}

func (s *Server) GetConflicts(
	ctx context.Context,
	in *pb.AttrConflictsRequest,
) (*pb.AttrConflictsResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	const conflictsPerPage = 30

	data, pages, err := s.repository.ValuesPaginated(ctx, query.AttrsValueListOptions{
		Conflict: in.GetFilter() == pb.AttrConflictsRequest_ALL,
		UserValues: &query.AttrsUserValueListOptions{
			UserID:         userCtx.UserID,
			WeightLtZero:   in.GetFilter() == pb.AttrConflictsRequest_MINUS_WEIGHT,
			ConflictLtZero: in.GetFilter() == pb.AttrConflictsRequest_I_DISAGREE,
			ConflictGtZero: in.GetFilter() == pb.AttrConflictsRequest_DO_NOT_AGREE_WITH_ME,
		},
	}, attrs.ValuesOrderByUpdateDate, in.GetPage(), conflictsPerPage)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	userRows, err := s.repository.UserValueRowsForValues(ctx, data)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	values, err := s.repository.UserValues(ctx, userRows, in.GetLanguage(), true)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	grouped := make(map[[2]int64][]*pb.AttrConflictValue)

	for index, row := range userRows {
		key := [2]int64{row.AttributeID, row.ItemID}
		grouped[key] = append(
			grouped[key],
			&pb.AttrConflictValue{
				Value:        values[index].Text,
				ValueIsEmpty: values[index].Value.IsEmpty,
				UserId:       row.UserID,
			},
		)
	}

	res := make([]*pb.AttrConflict, 0, len(data))
	for _, row := range data {
		selected := grouped[[2]int64{row.AttributeID, row.ItemID}]

		conflictValues := make([]*pb.AttrConflictValue, 0, len(selected))
		if row.AttributeID != 0 && row.ItemID != 0 {
			conflictValues = append(conflictValues, selected...)
		} else {
			for index, userRow := range userRows {
				if (row.AttributeID == 0 || row.AttributeID == userRow.AttributeID) &&
					(row.ItemID == 0 || row.ItemID == userRow.ItemID) {
					conflictValues = append(
						conflictValues,
						&pb.AttrConflictValue{
							Value:        values[index].Text,
							ValueIsEmpty: values[index].Value.IsEmpty,
							UserId:       userRow.UserID,
						},
					)
				}
			}
		}

		res = append(res, &pb.AttrConflict{ItemId: row.ItemID, AttributeId: row.AttributeID, Values: conflictValues})
	}

	return &pb.AttrConflictsResponse{
		Items: res,
		Paginator: &pb.Pages{
			PageCount:      pages.PageCount,
			Current:        pages.Current,
			TotalItemCount: pages.TotalItemCount,
		},
	}, nil
}

func (s *Server) DeleteUserValues(
	ctx context.Context, in *pb.DeleteAttrUserValuesRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleAdmin) {
		return nil, status.Errorf(
			codes.PermissionDenied,
			"PermissionDenied: specifications.admin is required",
		)
	}

	err = s.repository.DeleteUserValue(ctx, in.GetAttributeId(), in.GetItemId(), in.GetUserId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) SetUserValues(
	ctx context.Context,
	in *pb.AttrSetUserValuesRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(
			codes.PermissionDenied,
			"PermissionDenied: specifications.edit is required",
		)
	}

	for _, item := range in.GetItems() {
		protoValue := item.GetValue()

		_, err = s.repository.SetUserValue(
			ctx,
			userCtx.UserID,
			item.GetAttributeId(),
			item.GetItemId(),
			attrs.Value{
				Valid:       protoValue.GetValid(),
				FloatValue:  protoValue.GetFloatValue(),
				IntValue:    protoValue.GetIntValue(),
				BoolValue:   protoValue.GetBoolValue(),
				ListValue:   protoValue.GetListValue(),
				StringValue: protoValue.GetStringValue(),
				IsEmpty:     protoValue.GetIsEmpty(),
			},
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) MoveUserValues(
	ctx context.Context,
	in *pb.MoveAttrUserValuesRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleAdmin) {
		return nil, status.Errorf(
			codes.PermissionDenied,
			"PermissionDenied: specifications.admin is required",
		)
	}

	srcItemID := in.GetSrcItemId()
	dstItemID := in.GetDestItemId()

	if srcItemID > 0 && dstItemID > 0 {
		err = s.repository.MoveUserValues(ctx, srcItemID, dstItemID)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) GetSpecifications(
	ctx context.Context, in *pb.GetSpecificationsRequest,
) (*pb.GetSpecificationsResponse, error) {
	table, err := s.repository.Specifications(ctx, []int64{in.GetItemId()}, 0, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	html, err := table.Render()
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.GetSpecificationsResponse{
		Html: html,
	}, nil
}

func (s *Server) GetChildSpecifications(
	ctx context.Context, in *pb.GetSpecificationsRequest,
) (*pb.GetSpecificationsResponse, error) {
	table, err := s.repository.ChildSpecifications(ctx, in.GetItemId(), in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	html, err := table.Render()
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.GetSpecificationsResponse{
		Html: html,
	}, nil
}

func (s *Server) GetChartParameters(
	ctx context.Context,
	_ *emptypb.Empty,
) (*pb.ChartParameters, error) {
	rows, err := s.repository.Attributes(ctx, &query.AttrsListOptions{
		IDs: attrs.ChartParameters,
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	params := make([]*pb.ChartParameter, 0, len(rows))
	for _, row := range rows {
		params = append(params, &pb.ChartParameter{
			Name: row.Name,
			Id:   row.ID,
		})
	}

	return &pb.ChartParameters{
		Parameters: params,
	}, nil
}

func (s *Server) GetChartData(
	ctx context.Context,
	in *pb.ChartDataRequest,
) (*pb.ChartData, error) {
	datasets, err := s.repository.ChartData(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*pb.ChartDataDataset, 0)

	for _, dataset := range datasets {
		pairs := make(map[int32]*pb.AttrValueValue, len(dataset.Pairs))

		for year, value := range dataset.Pairs {
			extractedValue := extractAttrValue(value)
			pairs[int32(year)] = &extractedValue //nolint: gosec
		}

		result = append(result, &pb.ChartDataDataset{
			Name:   dataset.Title,
			Values: pairs,
		})
	}

	return &pb.ChartData{
		Datasets: result,
	}, nil
}
