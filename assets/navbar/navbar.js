require("./navbar.scss");
var UsersOnline = require('users-online');

module.exports = {
    init: function() {
        UsersOnline.bind('.navbar a.online');
    }
};
