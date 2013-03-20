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
	
	@cat ./vendor/autowp/bootstrap/bootstrap/js/bootstrap.js ./public_source/js/v2.js > ./public_source/js/scripts.js
	@uglifyjs ./public_source/js/scripts.js > ./public_html/js/scripts.js
	@rm ./public_source/js/scripts.js
	
	@echo "Build js ...              ${CHECK} Done"