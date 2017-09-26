package main

import "fmt"
import "net/http"
import "os"
import "database/sql"
import "github.com/gin-gonic/gin"
import _ "github.com/go-sql-driver/mysql"
import sq "github.com/Masterminds/squirrel"

type perspective struct {
	Id   int    `json:"id"`
	Name string `json:"name"`
}

type perspectiveResult struct {
	Items []perspective `json:"items"`
}

type spec struct {
	Id        int    `json:"id"`
	Name      string `json:"name"`
	ShortName string `json:"short_name"`
	Childs    []spec `json:"childs"`
}

type specResult struct {
	Items []spec `json:"items"`
}

func getSpecs(db *sql.DB, parentId int) []spec {
	sqSelect := sq.Select("id, name, short_name").From("spec").OrderBy("name")

	if parentId != 0 {
		sqSelect = sqSelect.Where(sq.Eq{"parent_id": parentId})
	} else {
		sqSelect = sqSelect.Where(sq.Eq{"parent_id": nil})
	}

	rows, err := sqSelect.RunWith(db).Query()
	if err != nil {
		panic(err.Error())
	}

	specs := []spec{}
	for rows.Next() {
		var r spec
		err = rows.Scan(&r.Id, &r.Name, &r.ShortName)
		if err != nil {
			panic(err)
		}
		r.Childs = getSpecs(db, r.Id)
		specs = append(specs, r)
	}

	return specs
}

func getPerspectives(db *sql.DB) []perspective {
	sqSelect := sq.Select("id, name").From("perspectives").OrderBy("position")

	rows, err := sqSelect.RunWith(db).Query()
	if err != nil {
		panic(err.Error())
	}

	perspectives := []perspective{}
	for rows.Next() {
		var r perspective
		err = rows.Scan(&r.Id, &r.Name)
		if err != nil {
			panic(err)
		}
		perspectives = append(perspectives, r)
	}

	return perspectives
}

func main() {

	var dsn string = fmt.Sprintf("%s:%s@tcp(%s)/%s", os.Getenv("AUTOWP_DB_USERNAME"), os.Getenv("AUTOWP_DB_PASSWORD"), os.Getenv("AUTOWP_DB_HOST"), os.Getenv("AUTOWP_DB_DBNAME"))

	db, err := sql.Open("mysql", dsn)
	if err != nil {
		panic(err.Error())
	}
	defer db.Close()

	r := gin.Default()

	apiGroup := r.Group("/go-api")
	{
		var perspectives []perspective = getPerspectives(db)

		apiGroup.GET("/perspective", func(c *gin.Context) {
			c.JSON(200, perspectiveResult{perspectives})
		})

		var specs []spec = getSpecs(db, 0)

		apiGroup.GET("/spec", func(c *gin.Context) {

			c.JSON(200, specResult{specs})
		})
	}

	http.ListenAndServe(os.Getenv("AUTOWP_GO_LISTEN"), r)
	r.Run()
}
