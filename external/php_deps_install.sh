#!/bin/bash

if [ -f /etc/redhat-release ]; then
	sudo yum update
	sudo yum -y install libxml2-devel pcre-devel curl-devel openssl-devel ncurses-devel
else
	sudo apt-get update
	sudo apt-get -y install libxml2-devel pcre-devel curl-devel openssl-devel ncurses-devel
fi