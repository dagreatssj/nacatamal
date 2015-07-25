Nacatamal
=========

What is Nacatamal?
------------------

Nacatamal is a deployment tool that I created to help me launch my code to production/development servers. I decided to 
create my own after trying Capistrano and other related tools. I found that there was too many options than what
I needed. Thus, I created this simple tool to help me out and I thought I would share it.

Requirements
------------

To use Nacatamal you have to have php version 5.3 and up installed, and you can only use git as the VCS.

Installation
------------

In the root directory, simply run the following:
```
$ make install
```
This will download composer and install the required dependencies.

Getting Started
----------------

In order to use Nacatamal, create a config.yml and fill values like the distributed config.yml.

*   To package up your source code:

        php nacatamal package --project=myproject

*   To deploy the newest packaged code base to a server:

        php nacatamal deploy --project=myproject --build=latest --server=prod

What is left
-------------

- Jenkins
- Logging
