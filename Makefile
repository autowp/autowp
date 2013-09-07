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
	