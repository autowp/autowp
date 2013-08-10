CHECK=\033[32m✔\033[39m
HR=\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#

#
# BUILD CSS
#

build:
	@echo "\n${HR}"
	@echo "Building autowp..."
	@echo "${HR}\n"
	
	@lessc --yui-compress ./public_source/less/styles.less > ./public_html/css/styles.css
	
	@echo "Build css ...             ${CHECK} Done"
	
	@cat ./vendor/twitter/bootstrap3/bootstrap/js/bootstrap.js ./vendor/carhartl/jquery-cookie/jquery.cookie.js ./public_source/js/scripts.js ./public_source/js/moder.js > ./public_source/js/scripts.tmp.js
	@uglifyjs ./public_source/js/scripts.tmp.js > ./public_html/js/scripts.js
	@rm ./public_source/js/scripts.tmp.js
	
	@echo "Build js ...              ${CHECK} Done"