require("./navbar.less");
var UsersOnline = require('users-online');

module.exports = {
    init: function() {
        UsersOnline.bind('.navbar .online a');
    }
};