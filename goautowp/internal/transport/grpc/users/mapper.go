package users

import (
	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
)

func convertUserFields(fields *pb.UserFields, currentUserRoles []string) users.UserFields {
	lastIP := fields.GetLastIp() && util.Contains(currentUserRoles, users.RoleModer)
	login := fields.GetLogin() && util.Contains(currentUserRoles, users.RoleModer)

	return users.UserFields{
		Email:         fields.GetEmail(),
		Timezone:      fields.GetTimezone(),
		Language:      fields.GetLanguage(),
		VotesPerDay:   fields.GetVotesPerDay(),
		VotesLeft:     fields.GetVotesLeft(),
		RegDate:       fields.GetRegDate(),
		PicturesAdded: fields.GetPicturesAdded(),
		LastIP:        lastIP,
		LastOnline:    fields.GetLastOnline(),
		Login:         login,
		Contacts:      fields.GetContacts(),
	}
}
