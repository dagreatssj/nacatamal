#!/bin/bash

if [ -f /etc/redhat-release ]; then
	sudo yum update
	sudo yum install -y libxml2-devel pcre-devel curl-devel openssl-devel ncurses-devel
else
	sudo apt-get update -y
	sudo apt-get install -y libxml2-dev libpcre3-dev libcurl4-openssl-dev libssl-dev libncurses-dev
fi