$(function() {
	var width = parseInt(screen.width);
	if (width > 0) {
		$.post('/capabilities/', {screen_width: width});
	}
});