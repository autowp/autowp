package frontend

import (
	"net/url"
	"strconv"
)

const (
	picturePrefix = "picture"
	usersPrefix   = "users"

	BrandMixed     = "mixed"
	BrandLogotypes = "logotypes"
	BrandOther     = "other"

	decimal = 10
)

func PicturePath(identity string) string {
	return "/" + picturePrefix + "/" + url.QueryEscape(identity)
}

func PictureURL(url *url.URL, identity string) string {
	url.Path = PicturePath(identity)

	return url.String()
}

func PictureRoute(identity string) []string {
	return []string{"/" + picturePrefix, identity}
}

func PictureModerURL(url *url.URL, pictureID int64) string {
	url.Path = "/moder/pictures/" + strconv.FormatInt(pictureID, decimal)

	return url.String()
}

func userIdentity(userID int64, identity *string) string {
	if identity == nil || len(*identity) == 0 {
		return "user" + strconv.FormatInt(userID, decimal)
	}

	return *identity
}

func UserPath(userID int64, identity *string) string {
	return "/" + usersPrefix + "/" + url.QueryEscape(userIdentity(userID, identity))
}

func UserURL(url *url.URL, userID int64, identity *string) string {
	url.Path = UserPath(userID, identity)

	return url.String()
}

func UserRoute(userID int64, identity *string) []string {
	return []string{"/" + usersPrefix, userIdentity(userID, identity)}
}

func ItemModerURL(url *url.URL, id int64) string {
	url.Path = "/moder/items/item/" + strconv.FormatInt(id, decimal)

	return url.String()
}

func ArticleRoute(catname string) []string {
	return []string{"/articles", catname}
}

func ForumsMessageRoute(id int64) []string {
	return []string{"/forums", "message", strconv.FormatInt(id, decimal)}
}

func VotingRoute(id int64) []string {
	return []string{"/voting", strconv.FormatInt(id, decimal)}
}

func MuseumRoute(id int64) []string {
	return []string{"/museums", strconv.FormatInt(id, decimal)}
}

func FactoryRoute(id int64) []string {
	return []string{"/factories", strconv.FormatInt(id, decimal)}
}

func CategoryRoute(catname string) []string {
	return []string{"/category", catname}
}

func CategoryPictureRoute(catname string, identity string) []string {
	return append(CategoryRoute(catname), "pictures", identity)
}

func TwinsGroupRoute(id int64) []string {
	return []string{"/twins", "group", strconv.FormatInt(id, decimal)}
}

func TwinsGroupPictureRoute(id int64, identity string) []string {
	return append(TwinsGroupRoute(id), "pictures", identity)
}

func BrandRoute(catname string) []string {
	return []string{"/", catname}
}

func BrandConceptsRoute(catname string) []string {
	return append(BrandRoute(catname), "concepts")
}

func BrandEnginesRoute(catname string) []string {
	return append(BrandRoute(catname), "engines")
}

func BrandItemRoute(catname string, itemCatname string) []string {
	return append(BrandRoute(catname), itemCatname)
}

func BrandGroupRoute(catname string, groupCatname string) []string {
	return append(BrandRoute(catname), groupCatname)
}

func BrandGroupPictureRoute(catname string, groupCatname string, identity string) []string {
	return append(BrandGroupRoute(catname, groupCatname), identity)
}

func BrandItemPathRoute(catname string, itemCatname string, path []string) []string {
	return append(BrandItemRoute(catname, itemCatname), path...)
}

func BrandItemPathSpecificationsRoute(catname string, itemCatname string, path []string) []string {
	return append(BrandItemPathRoute(catname, itemCatname, path), "specifications")
}

func BrandItemPathPicturesPictureRoute(
	catname string,
	itemCatname string,
	path []string,
	identity string,
) []string {
	return append(BrandItemPathRoute(catname, itemCatname, path), "pictures", identity)
}

func PersonRoute(id int64) []string {
	return []string{"/persons", strconv.FormatInt(id, decimal)}
}

func PersonPictureRoute(id int64, identity string) []string {
	return append(PersonRoute(id), identity)
}
