CHECK=\033[32m✔\033[39m
HR=\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#\#

#
# BUILD CSS
#

build:
	@echo "\n${HR}"
	@echo "Building autowp..."
	@echo "${HR}\n"
	
	@lessc --yui-compress ./public_source/less/v2.less > ./public_html/css/v2.css
	@lessc --yui-compress ./public_source/less/shared.less > ./public_html/css/shared.css
	
	@echo "Build css ...             ${CHECK} Done"
	
	@uglifyjs ./public_source/js/v2.js > ./public_html/js/v2.js
	
	@echo "Build js ...              ${CHECK} Done"